<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

/** What is selected, what is being previewed, and how the grid is displayed. */
trait SelectsMedia
{
    protected const VIEW_MODE_SESSION_KEY = 'filament-media-library.view-mode';

    protected const EXTENSIONS_SESSION_KEY = 'filament-media-library.show-extensions';

    public string $viewMode = 'grid';

    public bool $showExtensions = true;

    /** @var array<int, string> */
    public array $selected = [];

    public ?string $detailId = null;

    public function setView(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';

        if (MediaLibraryConfig::remembersViewMode()) {
            session()->put(self::VIEW_MODE_SESSION_KEY, $this->viewMode);
        }
    }

    public function toggleExtensions(): void
    {
        $this->showExtensions = ! $this->showExtensions;

        session()->put(self::EXTENSIONS_SESSION_KEY, $this->showExtensions);
    }

    public function toggleSelect(string $id): void
    {
        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));

            return;
        }

        $this->selected[] = $id;
    }

    /**
     * Select a contiguous run of items, used by Shift+click.
     *
     * @param  array<int, string>  $ids
     */
    public function selectRange(array $ids): void
    {
        $ids = array_values(array_filter($ids, 'is_string'));

        $this->selected = array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function showDetail(string $id): void
    {
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function detailRecord(): ?Media
    {
        if ($this->detailId === null) {
            return null;
        }

        return Media::query()->onDisk($this->disk)->whereKey($this->detailId)->first();
    }
}
