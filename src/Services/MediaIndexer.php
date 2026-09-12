<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Exceptions\CannotListDisk;
use Batustun\FilamentMediaLibrary\Models\Media;
use Closure;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaIndexer
{
    public function __construct(private readonly MediaService $service) {}

    /**
     * Walk a disk (optionally one sub-directory) and index anything missing.
     *
     * @return array{indexed: int, skipped: int, failed: int}
     */
    public function sync(string $disk, ?string $directory = null, int $chunkSize = 500, ?Closure $progress = null): array
    {
        try {
            $files = Storage::disk($disk)->allFiles($directory ?? '');
        } catch (Throwable $e) {
            // A disk can refuse to enumerate: wrong credentials, an adapter
            // with no deep listing, a bucket too large to walk. That is worth
            // reporting, not worth a stack trace.
            throw new CannotListDisk($disk, $e);
        }

        $indexed = 0;
        $skipped = 0;
        $failed = 0;

        foreach (array_chunk($files, max(50, $chunkSize)) as $batch) {
            $existing = array_flip(
                Media::query()
                    ->where('disk', $disk)
                    ->whereIn('path', $batch)
                    ->pluck('path')
                    ->all(),
            );

            foreach ($batch as $path) {
                if (isset($existing[$path])) {
                    $skipped++;
                    $progress?->__invoke($path, 'skipped');

                    continue;
                }

                try {
                    $this->service->indexFromDisk($disk, $path);
                    $indexed++;
                    $progress?->__invoke($path, 'indexed');
                } catch (Throwable $e) {
                    $failed++;
                    $progress?->__invoke($path, 'failed', $e);
                }
            }
        }

        return ['indexed' => $indexed, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Media rows whose backing object no longer exists on the disk.
     *
     * @return array<int, Media>
     */
    public function findOrphans(string $disk): array
    {
        $orphans = [];

        Media::query()->where('disk', $disk)->chunkById(500, function ($chunk) use (&$orphans): void {
            foreach ($chunk as $media) {
                if (! $media->existsOnDisk()) {
                    $orphans[] = $media;
                }
            }
        });

        return $orphans;
    }
}
