<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

/**
 * Decides how an item should be shown in the detail panel.
 *
 * The kind alone is not enough. HEIC and TIFF are images that no mainstream
 * browser can decode, and an Office document is only previewable by handing its
 * URL to a third party — so the decision lives here rather than as a switch
 * scattered through a Blade file.
 */
trait HasPreview
{
    /** Image formats every mainstream browser can actually decode. */
    private const RENDERABLE_IMAGES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/svg+xml', 'image/bmp', 'image/x-icon', 'image/vnd.microsoft.icon',
    ];

    /** Office formats, which need an external viewer or nothing at all. */
    private const OFFICE_TYPES = [MediaKind::Doc, MediaKind::Sheet, MediaKind::Slides];

    /**
     * @return 'image'|'video'|'audio'|'pdf'|'text'|'archive'|'office'|'none'
     */
    public function previewStrategy(): string
    {
        if ($this->isProviderBacked()) {
            return 'video';
        }

        $kind = $this->kind_enum;

        return match (true) {
            $kind === MediaKind::Image => $this->isRenderableImage() ? 'image' : 'none',
            $kind === MediaKind::Video => 'video',
            $kind === MediaKind::Audio => 'audio',
            $kind === MediaKind::Pdf => 'pdf',
            $kind === MediaKind::Code => MediaLibraryConfig::previewsText() ? 'text' : 'none',
            $kind === MediaKind::Archive => MediaLibraryConfig::previewsArchives() ? 'archive' : 'none',
            // A CSV is a spreadsheet the browser can read as text.
            $kind === MediaKind::Sheet && $this->hasExtension('csv') => MediaLibraryConfig::previewsText() ? 'text' : 'none',
            in_array($kind, self::OFFICE_TYPES, true) => $this->officeViewerUrl() !== null ? 'office' : 'none',
            default => 'none',
        };
    }

    /**
     * Whether a browser can decode this image.
     *
     * HEIC, HEIF and TIFF are stored and indexed happily, but rendering one in
     * an <img> gives a broken-image icon, so they fall back to the type icon.
     */
    public function isRenderableImage(): bool
    {
        return in_array(strtolower((string) $this->mime_type), self::RENDERABLE_IMAGES, true);
    }

    /**
     * A third-party viewer URL for an Office document, or null when that is
     * switched off.
     *
     * Both viewers fetch the file themselves, so this only works for a URL the
     * public internet can reach — and it tells that company the URL.
     */
    public function officeViewerUrl(): ?string
    {
        $viewer = MediaLibraryConfig::officeViewer();

        if ($viewer === null || $this->isProviderBacked()) {
            return null;
        }

        $url = $this->publicUrl();

        if (! str_starts_with($url, 'http')) {
            return null;
        }

        return match ($viewer) {
            'microsoft' => 'https://view.officeapps.live.com/op/embed.aspx?src='.urlencode($url),
            'google' => 'https://docs.google.com/viewer?embedded=1&url='.urlencode($url),
            default => null,
        };
    }

    private function hasExtension(string $extension): bool
    {
        return strtolower((string) pathinfo((string) $this->name, PATHINFO_EXTENSION)) === $extension;
    }
}
