<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates downscaled variants of an image so a listing can serve a 320px
 * thumbnail instead of the 4000px original, and so an <img> can carry a
 * srcset.
 *
 * Written against ext-gd rather than an image library, so the package stays
 * dependency-free. When GD is missing, or the format is one GD cannot decode
 * (HEIC, AVIF, TIFF), conversions are skipped and the original is used — never
 * an error, just no variants.
 */
class ImageConversionService
{
    /** Formats GD can reliably read and write. */
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function isSupported(?string $mimeType): bool
    {
        return extension_loaded('gd')
            && in_array(strtolower((string) $mimeType), self::SUPPORTED, true);
    }

    public function shouldRun(Media $media): bool
    {
        return MediaLibraryConfig::conversionsEnabled()
            && $this->isSupported($media->mime_type);
    }

    /**
     * Build every configured variant that is genuinely smaller than the
     * original, and record them in the item's meta.
     *
     * @return array<string, array{path: string, width: int, height: int}>
     */
    public function generate(Media $media): array
    {
        if (! $this->shouldRun($media)) {
            return [];
        }

        $disk = Storage::disk($media->disk);

        try {
            $contents = $disk->get($media->path);
        } catch (Throwable $e) {
            Log::warning('filament-media-library: conversion source unreadable', [
                'media' => $media->getKey(), 'error' => $e->getMessage(),
            ]);

            return [];
        }

        if ($contents === null || $contents === '') {
            return [];
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return [];
        }

        $originalWidth = imagesx($source);
        $conversions = [];

        try {
            foreach (MediaLibraryConfig::conversionSizes() as $name => $width) {
                // Never upscale: a 200px logo has no 1536px variant.
                if ($width >= $originalWidth) {
                    continue;
                }

                $variant = $this->resize($source, $width);

                if ($variant === null) {
                    continue;
                }

                [$resource, $height] = $variant;

                try {
                    $encoded = $this->encode($resource, (string) $media->mime_type);

                    if ($encoded === null) {
                        continue;
                    }

                    $path = $this->pathFor($media, (string) $name);

                    $disk->put($path, $encoded, $this->writeOptions());

                    $conversions[$name] = ['path' => $path, 'width' => $width, 'height' => $height];
                } finally {
                    imagedestroy($resource);
                }
            }
        } finally {
            imagedestroy($source);
        }

        $media->forceFill([
            'meta' => [...($media->meta ?? []), 'conversions' => $conversions],
        ])->save();

        return $conversions;
    }

    /** Remove an item's variants from the disk. */
    public function purge(Media $media): void
    {
        $conversions = $media->conversions();

        if ($conversions === []) {
            return;
        }

        try {
            Storage::disk($media->disk)->delete(array_column($conversions, 'path'));
        } catch (Throwable $e) {
            Log::warning('filament-media-library: conversion cleanup failed', [
                'media' => $media->getKey(), 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  \GdImage  $source
     * @return array{0: \GdImage, 1: int}|null
     */
    private function resize($source, int $width): ?array
    {
        $scaled = @imagescale($source, $width);

        if ($scaled === false) {
            return null;
        }

        return [$scaled, imagesy($scaled)];
    }

    /** @param \GdImage $image */
    private function encode($image, string $mimeType): ?string
    {
        $quality = MediaLibraryConfig::conversionQuality();

        ob_start();

        $ok = match (strtolower($mimeType)) {
            'image/jpeg' => imagejpeg($image, null, $quality),
            'image/webp' => imagewebp($image, null, $quality),
            // PNG takes a 0-9 compression level, not a 0-100 quality.
            'image/png' => imagealphablending($image, false)
                && imagesavealpha($image, true)
                && imagepng($image, null, 6),
            'image/gif' => imagegif($image),
            default => false,
        };

        $encoded = ob_get_clean();

        return $ok && is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    private function pathFor(Media $media, string $name): string
    {
        $directory = trim((string) $media->directory, '/');
        $base = pathinfo((string) $media->name, PATHINFO_FILENAME);
        $extension = pathinfo((string) $media->name, PATHINFO_EXTENSION);

        $file = $base.'-'.$name.($extension !== '' ? '.'.$extension : '');

        return ltrim(($directory === '' ? '' : $directory.'/').'conversions/'.$file, '/');
    }

    /** @return array<string, mixed> */
    private function writeOptions(): array
    {
        $visibility = MediaLibraryConfig::visibility();

        return $visibility === null ? [] : ['visibility' => $visibility];
    }
}
