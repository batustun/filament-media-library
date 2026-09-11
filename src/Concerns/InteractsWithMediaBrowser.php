<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * Shared browsing state for the full-page library and the modal picker.
 *
 * Every mutating helper authorises itself, so a new host component cannot
 * accidentally expose an unguarded Livewire action.
 */
trait InteractsWithMediaBrowser
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'disk', except: '')]
    public string $disk = '';

    #[Url(as: 'dir', except: '')]
    public string $directory = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'kind', except: '')]
    public string $kindFilter = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    public string $viewMode = 'grid';

    public int $perPage = 48;

    /** @var array<int, string> */
    public array $selected = [];

    public ?string $detailId = null;

    /** @var array<int, mixed>|null */
    public ?array $uploads = [];

    public function bootInteractsWithMediaBrowser(): void
    {
        $this->disk = $this->sanitizeDisk($this->disk);

        if (! in_array($this->perPage, MediaLibraryConfig::pageSizes(), true)) {
            $this->perPage = MediaLibraryConfig::defaultPageSize();
        }
    }

    // -----------------------------------------------------------------
    // Authorisation
    // -----------------------------------------------------------------

    protected function authorizeMediaAction(string $ability): void
    {
        Authorize::ensure($ability);
    }

    public function canMedia(string $ability): bool
    {
        return Authorize::check($ability);
    }

    // -----------------------------------------------------------------
    // Disks
    // -----------------------------------------------------------------

    /**
     * Never trust the disk: it arrives from a query string and from mount()
     * arguments, so it is always forced back into the allow-list.
     */
    protected function sanitizeDisk(?string $disk): string
    {
        $disk = trim((string) $disk);
        $available = $this->availableSources();

        if ($disk !== '' && in_array($disk, $available, true)) {
            return $disk;
        }

        $default = MediaLibraryConfig::defaultDisk();

        if ($available === [] || in_array($default, $available, true)) {
            return $default;
        }

        return $available[0];
    }

    /** @return array<int, string> */
    public function availableDisks(): array
    {
        return MediaLibraryConfig::disks();
    }

    /**
     * Everything an editor can browse or upload to: filesystem disks plus any
     * configured provider (a video platform), which behaves like a disk in the
     * UI but stores an external id rather than a path.
     *
     * @return array<int, string>
     */
    public function availableSources(): array
    {
        return [...$this->availableDisks(), ...app(MediaProviderRegistry::class)->keys()];
    }

    /** @return array<string, string> Source key => human label. */
    public function sourceOptions(): array
    {
        $options = [];

        foreach ($this->availableDisks() as $disk) {
            $options[$disk] = $disk;
        }

        foreach (app(MediaProviderRegistry::class)->all() as $key => $provider) {
            $options[$key] = $provider->label();
        }

        return $options;
    }

    public function currentProvider(): ?MediaProvider
    {
        return app(MediaProviderRegistry::class)->get($this->disk);
    }

    // -----------------------------------------------------------------
    // Livewire lifecycle
    // -----------------------------------------------------------------

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingKindFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDirectory(): void
    {
        $this->resetPage();
    }

    public function updatedDisk(): void
    {
        $this->disk = $this->sanitizeDisk($this->disk);
        $this->directory = '';
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->kindFilter !== ''
            || $this->dateFrom !== ''
            || $this->dateTo !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->kindFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    /**
     * Livewire lets the browser assign any public property, so the page size
     * has to be forced back into the offered options — otherwise a crafted
     * request could ask for a single page of the entire library.
     */
    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, MediaLibraryConfig::pageSizes(), true)) {
            $this->perPage = MediaLibraryConfig::defaultPageSize();
        }

        $this->resetPage();
    }

    /** @return array<int, int> */
    public function pageSizeOptions(): array
    {
        return MediaLibraryConfig::pageSizes();
    }

    // -----------------------------------------------------------------
    // Folders
    // -----------------------------------------------------------------

    protected function cacheRepository(): CacheRepository
    {
        return Cache::store(MediaLibraryConfig::cacheStore());
    }

    protected function cacheKey(string $suffix): string
    {
        return "filament-media-library:{$suffix}:{$this->disk}";
    }

    /**
     * @template TValue
     *
     * @param  \Closure(): TValue  $callback
     * @return TValue
     */
    protected function remember(string $key, \Closure $callback): mixed
    {
        if (! MediaLibraryConfig::cacheEnabled()) {
            return $callback();
        }

        return $this->cacheRepository()->remember(
            $this->cacheKey($key),
            MediaLibraryConfig::cacheTtl(),
            $callback,
        );
    }

    protected function forgetFolderCaches(): void
    {
        foreach (['folders', 'root-folders', 'usage'] as $key) {
            $this->cacheRepository()->forget($this->cacheKey($key));
        }
    }

    /**
     * Flat, depth-annotated folder tree derived from the indexed directories.
     *
     * @return Collection<int, array{name: string, path: string, depth: int}>
     */
    public function folderTree(): Collection
    {
        $disk = $this->disk;

        $tree = $this->remember('folders', function () use ($disk): array {
            $directories = Media::query()
                ->onDisk($disk)
                ->whereNotNull('directory')
                ->where('directory', '!=', '')
                ->distinct()
                ->orderBy('directory')
                ->pluck('directory');

            $expanded = collect();

            foreach ($directories as $directory) {
                $segments = explode('/', trim((string) $directory, '/'));
                $accumulated = '';

                foreach ($segments as $depth => $segment) {
                    if ($segment === '') {
                        continue;
                    }

                    $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;

                    $expanded->push([
                        'name' => $segment,
                        'path' => $accumulated,
                        'depth' => $depth,
                    ]);
                }
            }

            return $expanded->unique('path')->sortBy('path')->values()->all();
        });

        return collect($tree);
    }

    /**
     * Top-level folders with file counts, aggregated in SQL rather than by
     * hydrating every row.
     *
     * @return array<int, array{name: string, path: string, count: int}>
     */
    public function rootFolders(): array
    {
        $disk = $this->disk;

        $folders = $this->remember('root-folders', function () use ($disk): array {
            $counts = [];

            // Aggregated in SQL through the base query builder: grouping on an
            // Eloquent query would hydrate a Media model per directory just to
            // read a count off it.
            Media::query()
                ->toBase()
                ->where('disk', $disk)
                ->whereNotNull('directory')
                ->where('directory', '!=', '')
                ->groupBy('directory')
                ->select('directory')
                ->selectRaw('count(*) as directory_count')
                ->get()
                ->each(function (object $row) use (&$counts): void {
                    $segments = explode('/', trim((string) $row->directory, '/'), 2);
                    $root = $segments[0];

                    if ($root === '') {
                        return;
                    }

                    $counts[$root] = ($counts[$root] ?? 0) + (int) $row->directory_count;
                });

            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);

            return array_map(
                static fn (string $name, int $count): array => [
                    'name' => $name,
                    'path' => $name,
                    'count' => $count,
                ],
                array_keys($counts),
                array_values($counts),
            );
        });

        return $folders;
    }

    public function selectFolder(string $path): void
    {
        $this->directory = app(MediaService::class)->normalizeDirectory($path);
        $this->resetPage();
    }

    public function clearFolder(): void
    {
        $this->directory = '';
        $this->resetPage();
    }

    // -----------------------------------------------------------------
    // View state
    // -----------------------------------------------------------------

    public function setView(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    public function toggleSelect(string $id): void
    {
        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));

            return;
        }

        $this->selected[] = $id;
    }

    /**
     * Select a contiguous run of items, used by Shift+click.
     *
     * @param  array<int, string>  $ids
     */
    public function selectRange(array $ids): void
    {
        $ids = array_values(array_filter($ids, 'is_string'));

        $this->selected = array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function showDetail(string $id): void
    {
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function detailRecord(): ?Media
    {
        if ($this->detailId === null) {
            return null;
        }

        return Media::query()->onDisk($this->disk)->whereKey($this->detailId)->first();
    }

    // -----------------------------------------------------------------
    // Query
    // -----------------------------------------------------------------

    public function items(): LengthAwarePaginator
    {
        $query = Media::query()->onDisk($this->disk);

        if ($this->directory !== '') {
            $query->inDirectory($this->directory);
        }

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->kindFilter !== '') {
            $query->ofKind($this->kindFilter);
        }

        // Dates arrive from a query string, so anything unparseable is ignored
        // rather than throwing the whole listing away.
        if ($from = $this->parseDate($this->dateFrom)) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($this->dateTo)) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $query = match ($this->sort) {
            'oldest' => $query->orderBy('created_at'),
            'name' => $query->orderBy('name'),
            'size_desc' => $query->orderByDesc('size'),
            'size_asc' => $query->orderBy('size'),
            default => $query->orderByDesc('created_at'),
        };

        // Deterministic tiebreaker so pagination cannot repeat or skip rows
        // when many records share a timestamp.
        return $query->orderBy('id')->paginate($this->perPage);
    }

    /**
     * Cached alongside the folder tree: a SUM over the whole table on every
     * render is a full scan of a library that only changes on upload/delete.
     */
    protected function parseDate(string $value): ?CarbonInterface
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function diskUsageBytes(): int
    {
        return (int) $this->remember(
            'usage',
            fn (): int => (int) Media::query()->onDisk($this->disk)->sum('size'),
        );
    }

    // -----------------------------------------------------------------
    // Mutations (each authorises itself)
    // -----------------------------------------------------------------

    /**
     * Store every pending upload.
     *
     * When an identical file (same SHA-256, same disk) is already indexed, the
     * existing record is reused instead of writing a second copy: one asset
     * keeps one canonical URL, so replacing it later updates every page that
     * references it.
     *
     * @return array{stored: int, reused: int, ids: array<int, string>}
     */
    public function performUpload(MediaService $service): array
    {
        $this->authorizeMediaAction('upload');

        $reuseDuplicates = MediaLibraryConfig::reusesDuplicates();

        $stored = 0;
        $reused = 0;
        $ids = [];

        foreach ((array) $this->uploads as $upload) {
            if (! $upload) {
                continue;
            }

            $duplicate = $reuseDuplicates
                ? $service->findDuplicate($this->disk, $service->hashFor($upload))
                : null;

            if ($duplicate !== null) {
                $reused++;
                $ids[] = (string) $duplicate->getKey();

                continue;
            }

            $media = ($provider = $this->currentProvider())
                ? $service->storeToProvider($provider, $upload)
                : $service->store($upload, $this->disk, $this->directory ?: null);

            $stored++;
            $ids[] = (string) $media->getKey();
        }

        $this->uploads = [];
        $this->forgetFolderCaches();

        return ['stored' => $stored, 'reused' => $reused, 'ids' => $ids];
    }

    public function performDelete(MediaService $service, string $id): bool
    {
        $this->authorizeMediaAction('delete');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return false;
        }

        $deleted = $service->delete($media);

        $this->forgetFolderCaches();

        if ($this->detailId === $id) {
            $this->detailId = null;
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));

        return $deleted;
    }

    public function performBulkDelete(MediaService $service): int
    {
        $this->authorizeMediaAction('delete');

        $count = 0;

        foreach ($this->selected as $id) {
            if ($this->performDelete($service, $id)) {
                $count++;
            }
        }

        return $count;
    }

    public function performRename(MediaService $service, string $id, string $newName): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        return $service->rename($media, $newName);
    }

    /**
     * Move the current selection into a directory.
     *
     * @return int number of items actually moved
     */
    public function performMoveSelection(MediaService $service, ?string $directory): int
    {
        $this->authorizeMediaAction('manage');

        if ($this->selected === []) {
            return 0;
        }

        $items = Media::query()
            ->onDisk($this->disk)
            ->whereIn('id', $this->selected)
            ->get();

        $moved = $service->moveMany($items, $directory);

        $this->forgetFolderCaches();
        $this->selected = [];

        return $moved;
    }

    /** Move a single item, used by the drag-and-drop onto a folder. */
    public function performMoveOne(MediaService $service, string $id, ?string $directory): bool
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return false;
        }

        $moved = $service->moveMany([$media], $directory) > 0;

        $this->forgetFolderCaches();

        return $moved;
    }

    public function performRenameFolder(MediaService $service, string $from, string $to): int
    {
        $this->authorizeMediaAction('manage');

        $renamed = $service->renameDirectory($this->disk, $from, $to);

        if ($renamed > 0 && ($this->directory === $from || str_starts_with($this->directory, $from.'/'))) {
            $this->directory = $service->normalizeDirectory($to).substr($this->directory, strlen($from));
        }

        $this->forgetFolderCaches();

        return $renamed;
    }

    /** Swap the bytes behind an item while keeping its id, path and URL. */
    public function performReplace(MediaService $service, string $id, mixed $upload): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media || ! $upload) {
            return null;
        }

        $replaced = $service->replace($media, $upload);

        $this->forgetFolderCaches();

        return $replaced;
    }

    /** @param array<string, mixed> $payload */
    public function performUpdateMeta(string $id, array $payload): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        $media->fill(array_intersect_key($payload, array_flip(['title', 'alt', 'description'])));
        $media->save();

        return $media;
    }
}
