<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Reads what a file can tell us about itself.
 *
 * Everything here works on the local temporary copy and never on the storage
 * disk, and everything is streamed or memory-mapped by the underlying
 * extension — so none of it grows with the size of the upload.
 */
final class FileInspector
{
    /**
     * SHA-256 of a file, read as a stream so a multi-gigabyte upload costs a
     * few kilobytes of memory rather than its own size.
     */
    public static function hashFor(UploadedFile|File $file): ?string
    {
        if (! MediaLibraryConfig::hashUploads()) {
            return null;
        }

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || ! is_readable($realPath)) {
            return null;
        }

        return hash_file('sha256', $realPath) ?: null;
    }

    /**
     * Pixel dimensions, when the format is one GD can measure.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function readImageDimensions(UploadedFile|File $file, ?string $mimeType): array
    {
        if (! MediaLibraryConfig::readImageDimensions()) {
            return [null, null];
        }

        if (! str_starts_with((string) $mimeType, 'image/')) {
            return [null, null];
        }

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || ! is_readable($realPath)) {
            return [null, null];
        }

        try {
            $size = @getimagesize($realPath);

            if (is_array($size)) {
                return [(int) $size[0], (int) $size[1]];
            }
        } catch (Throwable) {
            // SVG and some formats are not supported by getimagesize().
        }

        return [null, null];
    }

    /**
     * EXIF for JPEG and TIFF, when the extension is available. Camera and
     * capture date are what editors actually look for; the rest is noise.
     *
     * @return array<string, mixed>
     */
    public static function readExif(UploadedFile|File $file, ?string $mimeType): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }

        if (! in_array(strtolower((string) $mimeType), ['image/jpeg', 'image/tiff'], true)) {
            return [];
        }

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || ! is_readable($realPath)) {
            return [];
        }

        $exif = @exif_read_data($realPath);

        if (! is_array($exif)) {
            return [];
        }

        return array_filter([
            'camera' => trim(($exif['Make'] ?? '').' '.($exif['Model'] ?? '')) ?: null,
            'taken_at' => $exif['DateTimeOriginal'] ?? null,
            'orientation' => isset($exif['Orientation']) ? (int) $exif['Orientation'] : null,
            'iso' => isset($exif['ISOSpeedRatings']) ? (int) $exif['ISOSpeedRatings'] : null,
            'exposure' => $exif['ExposureTime'] ?? null,
            'aperture' => $exif['FNumber'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
