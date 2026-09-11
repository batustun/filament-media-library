<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

/**
 * Everything that changes something.
 *
 * Each helper authorises itself rather than trusting its caller: a Livewire
 * action is a public HTTP endpoint, so "the page already checked" is not a
 * defence.
 */
trait MutatesMedia
{
    /** @var array<int, mixed>|null */
    public ?array $uploads = [];

    /** @var array<string, mixed> State for the host application's file-info components. */
    public array $fileInfoData = [];

    /**
     * Extra form components the host application registered for the file-info
     * panel, as a real Filament schema so its fields validate and behave
     * exactly like they would anywhere else in the panel.
     */
    public function fileInfoForm(Schema $schema): Schema
    {
        return $schema
            ->components(MediaLibraryConfig::fileInfoComponents())
            ->statePath('fileInfoData');
    }

    public function hasFileInfoForm(): bool
    {
        return $this instanceof HasSchemas && MediaLibraryConfig::fileInfoComponents() !== [];
    }

    /** Load the host application's values for the item now being previewed. */
    protected function hydrateFileInfoForm(?Media $media): void
    {
        // Checked inline rather than through hasFileInfoForm() so the narrowing
        // is visible to both the reader and static analysis: getSchema() only
        // exists on a component that implements HasSchemas.
        if (! $this instanceof HasSchemas || MediaLibraryConfig::fileInfoComponents() === []) {
            return;
        }

        $hydrate = MediaLibraryConfig::plugin()?->getHydrateFileInfoCallback();

        $this->fileInfoData = ($media !== null && $hydrate !== null)
            ? (array) $hydrate($media)
            : [];

        $this->getSchema('fileInfoForm')?->fill($this->fileInfoData);
    }

    /** @return array<string, mixed> */
    protected function fileInfoState(): array
    {
        if (! $this instanceof HasSchemas || MediaLibraryConfig::fileInfoComponents() === []) {
            return [];
        }

        return $this->getSchema('fileInfoForm')?->getState() ?? [];
    }

    protected function authorizeMediaAction(string $ability): void
    {
        Authorize::ensure($ability);
    }

    public function canMedia(string $ability): bool
    {
        return Authorize::check($ability);
    }

    /**
     * Actions the host application registered for the current selection.
     *
     * Cloned and cached per component: an Action carries a reference to the
     * Livewire component it is mounted on, so handing the very same instance to
     * both the page and the picker would make one of them drive the other.
     *
     * @return array<int, Action>
     */
    public function customBulkActions(): array
    {
        return $this->cacheCustomActions(MediaLibraryConfig::bulkActions());
    }

    /** @return array<int, Action> */
    public function customItemActions(): array
    {
        return $this->cacheCustomActions(MediaLibraryConfig::itemActions());
    }

    /**
     * @param  array<int, Action>  $actions
     * @return array<int, Action>
     */
    protected function cacheCustomActions(array $actions): array
    {
        if (! $this instanceof HasActions) {
            return [];
        }

        return array_map(fn (Action $action): Action => $this->cacheAction(clone $action), $actions);
    }

    /**
     * Store every pending upload.
     *
     * When an identical file (same SHA-256, same disk) is already indexed, the
     * existing record is reused instead of writing a second copy: one asset
     * keeps one canonical URL, so replacing it later updates every page that
     * references it.
     *
     * @return array{stored: int, reused: int, ids: array<int, string>}
     */
    public function performUpload(MediaService $service): array
    {
        $this->authorizeMediaAction('upload');

        $reuseDuplicates = MediaLibraryConfig::reusesDuplicates();

        $stored = 0;
        $reused = 0;
        $ids = [];

        foreach ((array) $this->uploads as $upload) {
            if (! $upload) {
                continue;
            }

            $duplicate = $reuseDuplicates
                ? $service->findDuplicate($this->disk, $service->hashFor($upload))
                : null;

            if ($duplicate !== null) {
                $reused++;
                $ids[] = (string) $duplicate->getKey();

                continue;
            }

            $media = ($provider = $this->currentProvider())
                ? $service->storeToProvider($provider, $upload)
                : $service->store($upload, $this->disk, $this->directory ?: null);

            $stored++;
            $ids[] = (string) $media->getKey();
        }

        $this->uploads = [];
        $this->forgetFolderCaches();

        return ['stored' => $stored, 'reused' => $reused, 'ids' => $ids];
    }

    public function performDelete(MediaService $service, string $id): bool
    {
        $this->authorizeMediaAction('delete');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return false;
        }

        $deleted = $service->delete($media);

        $this->forgetFolderCaches();

        if ($this->detailId === $id) {
            $this->detailId = null;
        }

        $this->selected = array_values(array_diff($this->selected, [$id]));

        return $deleted;
    }

    public function performBulkDelete(MediaService $service): int
    {
        $this->authorizeMediaAction('delete');

        $count = 0;

        foreach ($this->selected as $id) {
            if ($this->performDelete($service, $id)) {
                $count++;
            }
        }

        return $count;
    }

    public function performRename(MediaService $service, string $id, string $newName): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        return $service->rename($media, $newName);
    }

    /**
     * Move the current selection into a directory.
     *
     * @return int number of items actually moved
     */
    public function performMoveSelection(MediaService $service, ?string $directory): int
    {
        $this->authorizeMediaAction('manage');

        if ($this->selected === []) {
            return 0;
        }

        $items = Media::query()
            ->onDisk($this->disk)
            ->whereIn('id', $this->selected)
            ->get();

        $moved = $service->moveMany($items, $directory);

        $this->forgetFolderCaches();
        $this->selected = [];

        return $moved;
    }

    /** Move a single item, used by the drag-and-drop onto a folder. */
    public function performMoveOne(MediaService $service, string $id, ?string $directory): bool
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return false;
        }

        $moved = $service->moveMany([$media], $directory) > 0;

        $this->forgetFolderCaches();

        return $moved;
    }

    public function performRenameFolder(MediaService $service, string $from, string $to): int
    {
        $this->authorizeMediaAction('manage');

        $renamed = $service->renameDirectory($this->disk, $from, $to);

        if ($renamed > 0 && ($this->directory === $from || str_starts_with($this->directory, $from.'/'))) {
            $this->directory = $service->normalizeDirectory($to).substr($this->directory, strlen($from));
        }

        $this->forgetFolderCaches();

        return $renamed;
    }

    /** Swap the bytes behind an item while keeping its id, path and URL. */
    public function performReplace(MediaService $service, string $id, mixed $upload): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media || ! $upload) {
            return null;
        }

        $replaced = $service->replace($media, $upload);

        $this->forgetFolderCaches();

        return $replaced;
    }

    public function performDuplicate(MediaService $service, string $id): ?Media
    {
        $this->authorizeMediaAction('upload');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        $copy = $service->duplicate($media);

        $this->forgetFolderCaches();

        return $copy;
    }

    /** @param array<int, string> $names */
    public function performSyncTags(string $id, array $names): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        $media->syncTagNames($names);
        $this->forgetFolderCaches();

        return $media;
    }

    /** @param array<string, mixed> $payload */
    public function performUpdateMeta(string $id, array $payload): ?Media
    {
        $this->authorizeMediaAction('manage');

        $media = Media::query()->onDisk($this->disk)->whereKey($id)->first();

        if (! $media) {
            return null;
        }

        $media->fill(array_intersect_key($payload, array_flip(['title', 'alt', 'description'])));

        if (isset($payload['custom']) && is_array($payload['custom'])) {
            $media->setCustomMeta($payload['custom']);
        }

        $media->save();

        // Anything the host application stores elsewhere gets its turn here,
        // with the state of its own components merged in.
        if ($save = MediaLibraryConfig::plugin()?->getSaveFileInfoCallback()) {
            $save($media, [...$payload, ...$this->fileInfoState()]);
        }

        return $media;
    }
}
