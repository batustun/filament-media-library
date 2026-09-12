<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Illuminate\Support\Str;

/**
 * Turns user input and original filenames into safe, predictable storage paths.
 *
 * Pure functions with no I/O and no state, which is why they live here rather
 * than on the service: naming and path hygiene are decisions about strings, and
 * keeping them separate makes both sides easier to read and to test.
 */
final class MediaPath
{
    /**
     * Strip traversal segments, normalise separators and expand date tokens.
     *
     * `uploads/{Y}/{m}` becomes `uploads/2026/09`, which is how WordPress has
     * organised uploads for two decades and what keeps a directory listing
     * usable once a library passes a few thousand files.
     */
    public static function normalizeDirectory(?string $directory): string
    {
        $directory = self::expandDateTokens(str_replace('\\', '/', (string) $directory));

        $segments = array_filter(
            explode('/', $directory),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );

        return implode('/', $segments);
    }

    /** Supported tokens: {Y} {y} {m} {d} {H} — all zero-padded but {y}. */
    public static function expandDateTokens(string $directory): string
    {
        if (! str_contains($directory, '{')) {
            return $directory;
        }

        $now = now();

        return strtr($directory, [
            '{Y}' => $now->format('Y'),
            '{y}' => $now->format('y'),
            '{m}' => $now->format('m'),
            '{d}' => $now->format('d'),
            '{H}' => $now->format('H'),
        ]);
    }

    /**
     * A slug, a timestamp and six random characters.
     *
     * The timestamp keeps a directory listing chronological; the random suffix
     * is what stops two people uploading "scan.pdf" in the same second from
     * colliding on the unique(disk, path) index.
     */
    public static function buildFilename(string $original, string $extension): string
    {
        $base = Str::slug((string) pathinfo($original, PATHINFO_FILENAME)) ?: 'file';

        return $base.'-'.now()->format('YmdHis').'-'.Str::random(6)
            .($extension !== '' ? '.'.$extension : '');
    }

    /** A sensible extension when the client sent a file without one. */
    public static function guessExtensionFromMime(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            default => '',
        };
    }
}
