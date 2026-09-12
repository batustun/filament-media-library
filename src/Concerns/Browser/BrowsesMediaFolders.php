<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

/**
 * Folder navigation.
 *
 * Folders are derived from the `directory` column rather than stored in a table
 * of their own, so the tree can never drift from what is actually on the disk.
 * The derivation is cached, and every mutation clears it.
 */
trait BrowsesMediaFolders
{
    #[Url(as: 'dir', except: '')]
    public string $directory = '';

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
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    protected function remember(string $key, Closure $callback): mixed
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
        foreach (['folders', 'root-folders', 'usage', 'tags'] as $key) {
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

        return $this->withPendingFolder(collect($tree));
    }

    /**
     * Show the folder the user is standing in even when nothing has landed in
     * it yet.
     *
     * Folders are derived from the directories of indexed files, so a brand new
     * one is invisible until its first upload. Creating a folder and seeing the
     * sidebar unchanged reads as "it did not work" — the opposite of what
     * actually happened.
     *
     * @param  Collection<int, array{name: string, path: string, depth: int}>  $folders
     * @return Collection<int, array{name: string, path: string, depth: int}>
     */
    protected function withPendingFolder(Collection $folders): Collection
    {
        $current = trim($this->directory, '/');

        if ($current === '' || $folders->contains('path', $current)) {
            return $folders;
        }

        // Ancestors too, so a nested new folder stays reachable.
        $accumulated = '';

        foreach (explode('/', $current) as $depth => $segment) {
            $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;

            if (! $folders->contains('path', $accumulated)) {
                $folders->push(['name' => $segment, 'path' => $accumulated, 'depth' => $depth]);
            }
        }

        return $folders->sortBy('path')->values();
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

    public function diskUsageBytes(): int
    {
        return (int) $this->remember(
            'usage',
            fn (): int => (int) Media::query()->onDisk($this->disk)->sum('size'),
        );
    }
}
