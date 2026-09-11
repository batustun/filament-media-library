<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

/**
 * Rewrites an image URL to ask a resizing CDN for the rendition you want.
 *
 * This is a pure URL transform, which is what makes it cheap: the library
 * already resolves every public URL at read time, so switching an optimiser on
 * changes what the whole application serves without re-uploading or
 * regenerating a single file.
 */
class ImageOptimizer
{
    public function isEnabled(): bool
    {
        return MediaLibraryConfig::optimizerDriver() !== null;
    }

    /**
     * @param  int|null  $width  target width in pixels
     * @param  int|null  $height  target height in pixels
     */
    public function transform(string $url, ?int $width = null, ?int $height = null, ?int $quality = null, ?string $format = null): string
    {
        $driver = MediaLibraryConfig::optimizerDriver();

        if ($driver === null || $url === '') {
            return $url;
        }

        $settings = MediaLibraryConfig::optimizer();
        $quality ??= isset($settings['quality']) ? (int) $settings['quality'] : null;
        $format ??= is_string($settings['format'] ?? null) && $settings['format'] !== '' ? (string) $settings['format'] : null;

        return match ($driver) {
            'bunny' => $this->query($url, array_filter([
                'width' => $width,
                'height' => $height,
                'quality' => $quality,
                'format' => $format,
            ], static fn (mixed $v): bool => $v !== null)),

            // Cloudflare takes its options as a path segment, not a query
            // string, and wraps the original URL after them.
            'cloudflare' => $this->cloudflare($url, array_filter([
                'width' => $width,
                'height' => $height,
                'quality' => $quality,
                'format' => $format,
            ], static fn (mixed $v): bool => $v !== null)),

            'glide' => $this->glide($url, array_filter([
                'w' => $width,
                'h' => $height,
                'q' => $quality,
                'fm' => $format,
            ], static fn (mixed $v): bool => $v !== null)),

            default => $url,
        };
    }

    /** @param array<string, int|string> $params */
    private function query(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params);
    }

    /** @param array<string, int|string> $options */
    private function cloudflare(string $url, array $options): string
    {
        if ($options === []) {
            return $url;
        }

        $parts = [];

        foreach ($options as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        $segment = '/cdn-cgi/image/'.implode(',', $parts);

        // Same-origin URLs get the segment injected at the root; absolute URLs
        // on another host are appended whole, which is what Cloudflare expects.
        $parsed = parse_url($url);

        if (isset($parsed['scheme'], $parsed['host'])) {
            $origin = $parsed['scheme'].'://'.$parsed['host'].(isset($parsed['port']) ? ':'.$parsed['port'] : '');
            $path = (string) ($parsed['path'] ?? '');
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

            return $origin.$segment.$path.$query;
        }

        return $segment.'/'.ltrim($url, '/');
    }

    /** @param array<string, int|string> $params */
    private function glide(string $url, array $params): string
    {
        $base = rtrim((string) (MediaLibraryConfig::optimizer()['glide_path'] ?? '/img'), '/');
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return $this->query($base.'/'.ltrim((string) $path, '/'), $params);
    }
}
