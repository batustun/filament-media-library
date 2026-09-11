<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Infolists;

use Batustun\FilamentMediaLibrary\Support\MediaResolver;
use Filament\Infolists\Components\ImageEntry;

/**
 * Shows a library item as an image in an infolist.
 *
 * Like MediaColumn, it accepts a UUID, a path or a URL and resolves the best
 * available rendition through the library.
 *
 *     MediaEntry::make('cover_image')->height(200)
 */
class MediaEntry extends ImageEntry
{
    protected ?string $mediaDisk = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getStateUsing(function (MediaEntry $entry): mixed {
            $state = $entry->getRecord()?->getAttribute($entry->getName());
            $media = MediaResolver::resolve($state, $entry->getMediaDisk());

            // The detail view is large, so prefer a mid-size variant over the
            // grid thumbnail, and fall back to the original.
            return $media?->conversionUrl('medium')
                ?? $media?->publicUrl()
                ?? MediaResolver::thumbnailUrl($state, $entry->getMediaDisk());
        });
    }

    public function mediaDisk(string $disk): static
    {
        $this->mediaDisk = $disk;

        return $this;
    }

    public function getMediaDisk(): ?string
    {
        return $this->mediaDisk;
    }
}
