<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns;

use Batustun\FilamentMediaLibrary\Concerns\Browser\BrowsesMediaFolders;
use Batustun\FilamentMediaLibrary\Concerns\Browser\FiltersMedia;
use Batustun\FilamentMediaLibrary\Concerns\Browser\InteractsWithMediaSources;
use Batustun\FilamentMediaLibrary\Concerns\Browser\MutatesMedia;
use Batustun\FilamentMediaLibrary\Concerns\Browser\SelectsMedia;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * The media browser, shared by the full-page library and the modal picker.
 *
 * Composed from five concerns so each one stays readable on its own:
 *
 *   InteractsWithMediaSources  which disk or provider is being browsed
 *   BrowsesMediaFolders        the folder tree, derived from the directory column
 *   FiltersMedia               search, filters, sorters and the listing query
 *   SelectsMedia               selection, preview and display mode
 *   MutatesMedia               everything that changes something, each authorised
 */
trait InteractsWithMediaBrowser
{
    use BrowsesMediaFolders;
    use FiltersMedia;
    use InteractsWithMediaSources;
    use MutatesMedia;
    use SelectsMedia;
    use WithFileUploads;
    use WithPagination;

    public function bootInteractsWithMediaBrowser(): void
    {
        $this->disk = $this->sanitizeDisk($this->disk);

        if (! in_array($this->perPage, MediaLibraryConfig::pageSizes(), true)) {
            $this->perPage = MediaLibraryConfig::defaultPageSize();
        }

        // Grid or list is a personal preference, not a property of the data,
        // so it survives navigating away and coming back.
        if (MediaLibraryConfig::remembersViewMode()) {
            $remembered = session(self::VIEW_MODE_SESSION_KEY);

            if (in_array($remembered, ['grid', 'list'], true)) {
                $this->viewMode = $remembered;
            }
        }

        $this->showExtensions = (bool) session(
            self::EXTENSIONS_SESSION_KEY,
            MediaLibraryConfig::showsExtensions(),
        );
    }
}
