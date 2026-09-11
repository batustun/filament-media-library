<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Livewire;

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
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
class MediaPicker extends Component
{
    use InteractsWithMediaBrowser;

    public bool $multiple = false;

    public string $targetStatePath = '';

    /**
     * Where new uploads land while the user is still browsing "All files", so
     * the field's configured directory is honoured even at the library root.
     */
    public string $uploadDirectory = '';

    /** @var array<int, string> */
    public array $kinds = [];

    /** @param array<int, string> $kinds */
    public function mount(
        bool $multiple = false,
        ?string $disk = null,
        ?string $directory = null,
        array $kinds = [],
        string $targetStatePath = '',
        string $uploadDirectory = '',
    ): void {
        Authorize::ensure('view');

        $this->multiple = $multiple;
        $this->targetStatePath = $targetStatePath;
        $this->kinds = array_values(array_filter($kinds, 'is_string'));

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
    public function createFolder(string $name): void
    {
        $this->authorizeMediaAction('upload');

        $name = app(MediaService::class)->normalizeDirectory($name);

        if ($name === '') {
            return;
        }

        $this->directory = ($this->directory !== '' ? $this->directory.'/' : '').$name;
        $this->resetPage();
    }

    public function refresh(): void
    {
        $this->forgetFolderCaches();
        $this->resetPage();
    }

    public function renameMedia(string $id, string $newName): void
    {
        if (trim($newName) === '') {
            return;
        }

        $this->performRename(app(MediaService::class), $id, $newName);
    }

    public function deleteMedia(string $id): void
    {
        $this->performDelete(app(MediaService::class), $id);
    }

    /**
     * Delete a folder and everything nested inside it, from both the database
     * and the storage disk.
     */
    public function deleteFolder(string $path): void
    {
        $this->authorizeMediaAction('delete');

        $path = app(MediaService::class)->normalizeDirectory($path);

        if ($path === '') {
            return;
        }

        $service = app(MediaService::class);

        Media::query()
            ->onDisk($this->disk)
            ->inDirectoryTree($path)
            ->chunkById(200, function ($chunk) use ($service): void {
                foreach ($chunk as $media) {
                    $service->delete($media);
                }
            });

        if ($this->directory === $path || str_starts_with($this->directory, $path.'/')) {
            $this->directory = '';
        }

        $this->forgetFolderCaches();
        $this->resetPage();
    }

    public function toggleSelectFor(string $id): void
    {
        if ($this->multiple) {
            $this->toggleSelect($id);

            return;
        }

        $this->selected = [$id];
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

    public function uploadAndApply(): void
    {
        $service = app(MediaService::class);

        $originalDirectory = $this->directory;

        if ($this->directory === '' && $this->uploadDirectory !== '') {
            $this->directory = $this->uploadDirectory;
        }

        try {
            $result = $this->performUpload($service);
        } finally {
            $this->directory = $originalDirectory;
        }

        if ($result['ids'] === []) {
            return;
        }

        // performUpload reports exactly which records the upload resolved to,
        // including any it reused rather than duplicated, so the field is
        // always filled with the right ones — no "latest row" guesswork.
        $this->selected = $this->multiple
            ? $result['ids']
            : [$result['ids'][0]];

        $this->confirmSelection();
    }

    public function moveSelectionTo(?string $directory): void
    {
        $this->performMoveSelection(app(MediaService::class), $directory);
    }

    public function moveItemTo(string $id, ?string $directory): void
    {
        $this->performMoveOne(app(MediaService::class), $id, $directory);
    }

    public function renameFolder(string $from, string $to): void
    {
        $this->performRenameFolder(app(MediaService::class), $from, $to);
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
