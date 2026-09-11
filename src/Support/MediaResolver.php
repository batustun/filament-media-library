<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Support\Str;

/**
 * Turns whatever a field stored — a UUID, a disk path or a public URL — back
 * into a Media record.
 *
 * Table columns and infolist entries receive raw state without knowing which
 * of the three shapes MediaInput::returns() was configured with, so the lookup
 * has to work them out.
 */
final class MediaResolver
{
    public static function resolve(mixed $state, ?string $disk = null): ?Media
    {
        if ($state instanceof Media) {
            return $state;
        }

        if (! is_string($state) && ! is_numeric($state)) {
            return null;
        }

        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        if (Str::isUuid($state)) {
            return Media::query()->whereKey($state)->first();
        }

        $disk ??= MediaLibraryConfig::defaultDisk();

        // Try both shapes rather than guessing from the string: a disk's URL
        // can be relative ("/storage/covers/a.jpg"), which looks exactly like a
        // path and is not one.
        return Media::query()->onDisk($disk)->where('path', ltrim($state, '/'))->first()
            ?? app(MediaService::class)->findByUrl($state, $disk);
    }

    /**
     * A thumbnail URL for arbitrary state, falling back to the state itself
     * when it is already a URL the library does not know about.
     */
    public static function thumbnailUrl(mixed $state, ?string $disk = null): ?string
    {
        if ($media = self::resolve($state, $disk)) {
            return $media->thumbnailUrl();
        }

        return is_string($state) && str_contains($state, '://') ? $state : null;
    }
}
