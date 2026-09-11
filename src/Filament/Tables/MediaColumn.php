<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Tables;

use Batustun\FilamentMediaLibrary\Support\MediaResolver;
use Filament\Tables\Columns\ImageColumn;

/**
 * Shows a library item as a thumbnail in a table.
 *
 * Accepts whatever MediaInput stored — a UUID, a path or a URL — and serves
 * the smallest generated variant, so a listing of fifty rows pulls fifty
 * 320px thumbnails rather than fifty full-size originals.
 *
 *     MediaColumn::make('cover_image')->circular()
 */
class MediaColumn extends ImageColumn
{
    protected ?string $mediaDisk = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultImageUrl(null);

        $this->getStateUsing(function (MediaColumn $column): mixed {
            $state = $column->getRecord()?->getAttribute($column->getName());

            return MediaResolver::thumbnailUrl($state, $column->getMediaDisk());
        });
    }

    /** Which disk a bare path should be resolved against. */
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
