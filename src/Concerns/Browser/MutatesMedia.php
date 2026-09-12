<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
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
     * Files are stored the moment they are chosen.
     *
     * Staging them behind a second button changed nothing on screen but that
     * button's tint, so choosing a file was indistinguishable from the upload
     * having failed — and nobody knew there was a second step at all.
     */
    public function updatedUploads(): void
    {
        $this->storeUploads();
    }

    public function storeUploads(): void
    {
        $result = $this->performUpload(app(MediaService::class));

        $this->announceUpload($result);
        $this->afterUpload($result['ids']);
    }

    /**
     * Adopt files that went up through the chunked endpoint.
     *
     * Those bypass Livewire entirely — the property never changes, so without
     * this the picker would never learn they had arrived.
     *
     * @param  array<int, string>  $ids
     */
    public function adoptUploads(array $ids): void
    {
        $this->authorizeMediaAction('upload');

        $ids = array_values(array_filter(array_map(strval(...), $ids)));

        if ($ids === []) {
            return;
        }

        $this->forgetFolderCaches();
        $this->announceUpload(['stored' => count($ids), 'reused' => 0, 'ids' => $ids]);
        $this->afterUpload($ids);
    }

    /**
     * Say what the upload actually did.
     *
     * Silence is the worst outcome here: a byte-identical file is reused rather
     * than stored again, so no new card appears — and without a word, that is
     * indistinguishable from the upload having failed.
     *
     * @param  array{stored: int, reused: int, ids: array<int, string>}  $result
     */
    protected function announceUpload(array $result): void
    {
        $t = 'filament-media-library::filament-media-library.messages.';

        if ($result['stored'] === 0 && $result['reused'] === 0) {
            return;
        }

        $notification = Notification::make()->success();

        if ($result['stored'] > 0) {
            $notification->title(trans_choice($t.'uploaded', $result['stored'], ['count' => $result['stored']]));

            if ($result['reused'] > 0) {
                $notification->body(trans_choice($t.'reused', $result['reused'], ['count' => $result['reused']]));
            }
        } else {
            $notification
                ->title(__($t.'all_reused'))
                ->body(__($t.'reused_hint'));
        }

        $notification->send();
    }

    /**
     * What the host does with the files that just arrived. The page only has to
     * show them; the picker also selects them.
     *
     * @param  array<int, string>  $ids
     */
    protected function afterUpload(array $ids): void
    {
        $this->resetPage();
    }

    /**
     * Where new uploads land. The picker overrides this so a field configured
     * with a directory keeps filling it even while browsing the library root.
     *
     * Public because the dropzone names the destination in its hint, and a hint
     * that disagrees with where the file actually goes is worse than none.
     */
    public function uploadTargetDirectory(): ?string
    {
        return $this->directory ?: null;
    }

    /**
     * The actions the browser's front end calls.
     *
     * They live here rather than on each host because one set of JavaScript and
     * one set of views drive both: defined separately, the two drifted apart
     * under different names and half of them reached nothing on the page.
     */
    public function createFolder(string $name): void
    {
        $this->authorizeMediaAction('upload');

        $name = app(MediaService::class)->normalizeDirectory($name);

        if ($name === '') {
            return;
        }

        // Folders are derived from the files in them, so a new one becomes real
        // by being navigated into and receiving its first upload.
        $this->directory = ($this->directory !== '' ? $this->directory.'/' : '').$name;
        $this->resetPage();
    }

    public function renameFolder(string $from, string $to): void
    {
        if ($this->performRenameFolder(app(MediaService::class), $from, $to) === 0) {
            return;
        }

        $this->notify('folder_renamed', ['folder' => $to]);
    }

    /** Delete a folder and everything nested inside it, disk included. */
    public function deleteFolder(string $path): void
    {
        $this->authorizeMediaAction('delete');

        $service = app(MediaService::class);
        $path = $service->normalizeDirectory($path);

        if ($path === '') {
            return;
        }

        Media::query()
            ->onDisk($this->disk)
            ->inDirectoryTree($path)
            ->chunkById(200, function ($chunk) use ($service): void {
                foreach ($chunk as $media) {
                    $service->delete($media);
                }
            });

        // Standing in a folder that no longer exists shows an empty grid with
        // no way back, so browsing returns to the root.
        if ($this->directory === $path || str_starts_with($this->directory, $path.'/')) {
            $this->directory = '';
        }

        $this->forgetFolderCaches();
        $this->resetPage();
        $this->notify('folder_deleted', ['folder' => $path]);
    }

    public function renameMedia(string $id, string $newName): void
    {
        if (trim($newName) === '' || $this->performRename(app(MediaService::class), $id, $newName) === null) {
            return;
        }

        $this->notify('renamed');
    }

    public function deleteMedia(string $id): void
    {
        if (! $this->performDelete(app(MediaService::class), $id)) {
            return;
        }

        $this->notify('deleted');
    }

    public function bulkDelete(): void
    {
        $count = $this->performBulkDelete(app(MediaService::class));

        if ($count === 0) {
            return;
        }

        $this->notify('bulk_deleted', ['count' => $count], $count);
    }

    public function duplicateOne(string $id): void
    {
        if ($this->performDuplicate(app(MediaService::class), $id) === null) {
            return;
        }

        $this->notify('duplicated');
    }

    public function replaceFile(string $id): void
    {
        $upload = is_array($this->uploads) ? ($this->uploads[0] ?? null) : null;

        if (! $upload) {
            return;
        }

        $replaced = $this->performReplace(app(MediaService::class), $id, $upload);

        $this->uploads = [];

        if (! $replaced) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.replaced'))
            ->body(__('filament-media-library::filament-media-library.messages.replaced_hint'))
            ->success()
            ->send();
    }

    /** @param array<string, mixed> $payload */
    public function updateMeta(string $id, array $payload): void
    {
        if ($this->performUpdateMeta($id, $payload) === null) {
            return;
        }

        $this->notify('meta_updated');
    }

    /** @param array<int, string> $names */
    public function syncTags(string $id, array $names): void
    {
        if ($this->performSyncTags($id, $names) === null) {
            return;
        }

        $this->notify('tags_updated');
    }

    /** Re-read the disk, for when something changed outside the browser. */
    public function refresh(): void
    {
        $this->forgetFolderCaches();
        $this->resetPage();
    }

    /**
     * Say that something worked.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function notify(string $key, array $replace = [], ?int $count = null): void
    {
        $key = 'filament-media-library::filament-media-library.messages.'.$key;

        Notification::make()
            ->title($count === null ? __($key, $replace) : trans_choice($key, $count, $replace))
            ->success()
            ->send();
    }

    /**
     * Move what is selected, and say so.
     *
     * Named for what the browser's JavaScript calls: the page and the picker
     * used to define these separately under different names, so dragging a file
     * onto a folder reached a method that existed on only one of them.
     */
    public function moveSelectionTo(?string $directory): void
    {
        $this->announceMove($this->performMoveSelection(app(MediaService::class), $directory), $directory);
    }

    public function moveItemTo(string $id, ?string $directory): void
    {
        $moved = $this->performMoveOne(app(MediaService::class), $id, $directory) ? 1 : 0;

        $this->announceMove($moved, $directory);
    }

    protected function announceMove(int $moved, ?string $directory): void
    {
        if ($moved === 0) {
            return;
        }

        Notification::make()
            ->title(trans_choice(
                'filament-media-library::filament-media-library.messages.moved',
                $moved,
                ['count' => $moved, 'folder' => $directory ?: '/'],
            ))
            ->success()
            ->send();
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
                : $service->store($upload, $this->disk, $this->uploadTargetDirectory());

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
