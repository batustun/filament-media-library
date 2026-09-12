<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Livewire;

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Livewire\Component;
use Throwable;

/**
 * The modal media browser mounted by MediaInput's "Choose from Library" action.
 *
 * Every mutating entry point authorises through the shared browser trait: a
 * Livewire action is a public HTTP endpoint, so "the parent page already
 * checked" is not a defence.
 */
class MediaPicker extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithMediaBrowser;
    use InteractsWithSchemas;

    public bool $multiple = false;

    public string $targetStatePath = '';

    /**
     * Where new uploads land while the user is still browsing "All files", so
     * the field's configured directory is honoured even at the library root.
     */
    public string $uploadDirectory = '';

    /** @var array<int, string> */
    public array $kinds = [];

    /**
     * @param  array<int, string>  $kinds
     * @param  array<int, string>  $selectedIds  what the field already holds
     */
    public function mount(
        bool $multiple = false,
        ?string $disk = null,
        ?string $directory = null,
        array $kinds = [],
        string $targetStatePath = '',
        string $uploadDirectory = '',
        array $selectedIds = [],
    ): void {
        Authorize::ensure('view');

        $this->multiple = $multiple;
        $this->targetStatePath = $targetStatePath;
        $this->kinds = array_values(array_filter($kinds, 'is_string'));

        // Open on what the field is already showing, so the picker starts where
        // the last choice was made rather than at the top of the library.
        $this->selected = array_values(array_filter($selectedIds, 'is_string'));

        $service = app(MediaService::class);
        $this->uploadDirectory = $service->normalizeDirectory($uploadDirectory);

        $this->disk = $this->sanitizeDisk($disk ?? $this->disk);

        if ($directory !== null && $directory !== '') {
            $this->directory = $service->normalizeDirectory($directory);
        }

        $this->bootInteractsWithMediaBrowser();

        if (count($this->kinds) === 1) {
            $this->kindFilter = $this->kinds[0];
        }
    }

    /**
     * Navigate into a new folder. Empty folders are not persisted on their
     * own — the directory becomes real once the first file lands in it.
     */
    public function toggleSelectFor(string $id): void
    {
        if ($this->multiple) {
            $this->toggleSelect($id);

            return;
        }

        // Clicking the chosen file again clears it. Without this a single-value
        // field could be changed but never emptied by the same gesture.
        $this->selected = $this->selected === [$id] ? [] : [$id];
    }

    /**
     * Ask the field's component to unmount the action this picker was opened
     * from: a nested component cannot close the modal that contains it.
     */
    public function close(): void
    {
        $this->dispatch('filament-media-library:closed', statePath: $this->targetStatePath);
    }

    public function confirmSelection(): void
    {
        if ($this->selected === []) {
            return;
        }

        $items = Media::query()
            ->onDisk($this->disk)
            ->whereIn('id', $this->selected)
            ->get()
            ->map(function (Media $media): ?array {
                try {
                    return [
                        'id' => $media->id,
                        'url' => $media->publicUrl(),
                        'path' => (string) $media->path,
                    ];
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return;
        }

        $this->dispatch(
            'filament-media-library:picked',
            items: $items,
            statePath: $this->targetStatePath,
        );
    }

    /**
     * New uploads land in the directory the field was configured with, even
     * while the browser is showing the library root — so a field pointed at
     * "advertisements" never scatters files across the library.
     */
    public function uploadTargetDirectory(): ?string
    {
        return ($this->directory ?: $this->uploadDirectory) ?: null;
    }

    /**
     * Select what just arrived, but do NOT confirm: confirming closes the
     * modal, so the file would flash past without ever being seen in the grid —
     * which reads as "the upload did nothing". They confirm when ready.
     *
     * @param  array<int, string>  $ids
     */
    protected function afterUpload(array $ids): void
    {
        if ($ids !== []) {
            $this->selected = $this->multiple ? $ids : [$ids[0]];
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return ViewFactory::make('filament-media-library::livewire.media-picker', [
            'paginator' => $this->items(),
            'folders' => $this->folderTree(),
            'rootFolders' => $this->rootFolders(),
        ]);
    }
}
