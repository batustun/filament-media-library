<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

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
        $available = $this->availableDisks();

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
        $this->cacheRepository()->forget($this->cacheKey('folders'));
        $this->cacheRepository()->forget($this->cacheKey('root-folders'));
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
                ->where('disk', $disk)
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

        return Media::query()->where('disk', $this->disk)->whereKey($this->detailId)->first();
    }

    // -----------------------------------------------------------------
    // Query
    // -----------------------------------------------------------------

    public function items(): LengthAwarePaginator
    {
        $query = Media::query()->where('disk', $this->disk);

        if ($this->directory !== '') {
            $query->inDirectory($this->directory);
        }

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->kindFilter !== '') {
            $query->ofKind($this->kindFilter);
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

    public function diskUsageBytes(): int
    {
        return (int) Media::query()->where('disk', $this->disk)->sum('size');
    }

    // -----------------------------------------------------------------
    // Mutations (each authorises itself)
    // -----------------------------------------------------------------

    public function performUpload(MediaService $service): int
    {
        $this->authorizeMediaAction('upload');

        $count = 0;

        foreach ((array) $this->uploads as $upload) {
            if (! $upload) {
                continue;
            }

            $service->store($upload, $this->disk, $this->directory ?: null);
            $count++;
        }

        $this->uploads = [];
        $this->forgetFolderCaches();

        return $count;
    }

    public function performDelete(MediaService $service, string $id): bool
    {
        $this->authorizeMediaAction('delete');

        $media = Media::query()->where('disk', $this->disk)->whereKey($id)->first();

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

        $media = Media::query()->where('disk', $this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        return $service->rename($media, $newName);
    }

    /** @param array<string, mixed> $payload */
    public function performUpdateMeta(string $id, array $payload): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->where('disk', $this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        $media->fill(array_intersect_key($payload, array_flip(['title', 'alt', 'description'])));
        $media->save();

        return $media;
    }
}
