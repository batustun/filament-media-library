<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Services\ImageOptimizer;

/**
 * Downscaled renditions of an image.
 *
 * Two strategies, in order: a resizing CDN if one is configured — ask it for
 * the exact width — otherwise the variants generated at upload time.
 */
trait HasConversions
{
    /**
     * A time-limited URL for private disks. Returns null when the adapter
     * does not support signing.
     */
    /**
     * Generated variants, keyed by name, smallest first.
     *
     * Deliberately typed loosely: this is decoded JSON written by an earlier
     * release of the package, so the shape is checked at the point of use
     * rather than assumed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function conversions(): array
    {
        $conversions = $this->meta['conversions'] ?? [];

        return is_array($conversions) ? $conversions : [];
    }

    public function conversionUrl(string $name): ?string
    {
        $conversion = $this->conversions()[$name] ?? null;

        if (! is_array($conversion) || ! isset($conversion['path'])) {
            return null;
        }

        return $this->resolveUrlFor((string) $conversion['path']);
    }

    /**
     * The cheapest image to show in a grid: the smallest generated variant,
     * falling back to the original when there is none.
     */
    public function thumbnailUrl(): string
    {
        if ($poster = $this->mediaProvider()?->posterUrl($this)) {
            return $poster;
        }

        // A resizing CDN makes pre-generated variants redundant: ask it for the
        // exact width rather than picking the closest file we happen to have.
        if ($optimized = $this->optimizedUrl(width: 320)) {
            return $optimized;
        }

        foreach ($this->conversions() as $conversion) {
            if (isset($conversion['path'])) {
                return $this->resolveUrlFor((string) $conversion['path']);
            }
        }

        return $this->publicUrl();
    }

    /**
     * A srcset covering every variant plus the original, so the browser picks
     * the right one for the viewport instead of always downloading full size.
     */
    public function srcset(): ?string
    {
        $entries = [];

        foreach ($this->conversions() as $conversion) {
            if (! isset($conversion['path'], $conversion['width'])) {
                continue;
            }

            $entries[] = $this->resolveUrlFor((string) $conversion['path']).' '.((int) $conversion['width']).'w';
        }

        if ($entries === []) {
            return null;
        }

        if ($this->width) {
            $entries[] = $this->publicUrl().' '.((int) $this->width).'w';
        }

        return implode(', ', $entries);
    }

    /**
     * Whether the in-browser editor can open this item.
     *
     * The canvas re-encodes what it draws, so formats a browser cannot decode
     * (HEIC, HEIF, TIFF) or that would be destroyed by rasterising (SVG) are
     * excluded rather than silently mangled.
     */
    public function isEditableImage(): bool
    {
        return in_array(
            strtolower((string) $this->mime_type),
            ['image/jpeg', 'image/png', 'image/webp'],
            true,
        );
    }

    /**
     * The URL rewritten for the configured resizing CDN, or null when none is
     * configured or this item is not an image on a disk.
     */
    public function optimizedUrl(?int $width = null, ?int $height = null, ?int $quality = null, ?string $format = null): ?string
    {
        $optimizer = app(ImageOptimizer::class);

        if (! $optimizer->isEnabled() || $this->kind !== MediaKind::Image->value || $this->isProviderBacked()) {
            return null;
        }

        return $optimizer->transform($this->publicUrl(), $width, $height, $quality, $format);
    }
}
