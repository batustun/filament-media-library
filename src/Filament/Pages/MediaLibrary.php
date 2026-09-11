<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Pages;

use BackedEnum;
use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;

class MediaLibrary extends Page
{
    use InteractsWithMediaBrowser;

    protected string $view = 'filament-media-library::pages.media-library';

    public static function getNavigationLabel(): string
    {
        return (string) (MediaLibraryConfig::navigation('label')
            ?: __('filament-media-library::filament-media-library.navigation.label'));
    }

    public static function getNavigationGroup(): ?string
    {
        $group = MediaLibraryConfig::navigation('group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        $icon = MediaLibraryConfig::navigation('icon', 'heroicon-o-photo');

        return is_string($icon) && $icon !== '' ? $icon : 'heroicon-o-photo';
    }

    public static function getNavigationSort(): ?int
    {
        $sort = MediaLibraryConfig::navigation('sort');

        return $sort === null ? null : (int) $sort;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        $slug = MediaLibraryConfig::navigation('slug', 'media');

        return is_string($slug) && $slug !== '' ? $slug : 'media';
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) MediaLibraryConfig::navigation('enabled', true) && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return Authorize::check('view');
    }

    public function mount(): void
    {
        Authorize::ensure('view');

        $this->bootInteractsWithMediaBrowser();
    }

    public function uploadFiles(): void
    {
        $result = $this->performUpload(app(MediaService::class));

        if ($result['stored'] === 0 && $result['reused'] === 0) {
            return;
        }

        $notification = Notification::make()->success();

        if ($result['stored'] > 0) {
            $notification->title(trans_choice(
                'filament-media-library::filament-media-library.messages.uploaded',
                $result['stored'],
                ['count' => $result['stored']],
            ));
        } else {
            $notification->title(__('filament-media-library::filament-media-library.messages.all_reused'));
        }

        if ($result['reused'] > 0 && $result['stored'] > 0) {
            $notification->body(trans_choice(
                'filament-media-library::filament-media-library.messages.reused',
                $result['reused'],
                ['count' => $result['reused']],
            ));
        }

        $notification->send();
    }

    public function moveSelection(?string $directory): void
    {
        $moved = $this->performMoveSelection(app(MediaService::class), $directory);

        if ($moved === 0) {
            return;
        }

        Notification::make()
            ->title(trans_choice('filament-media-library::filament-media-library.messages.moved', $moved, [
                'count' => $moved,
                'folder' => $directory ?: '/',
            ]))
            ->success()
            ->send();
    }

    public function moveOne(string $id, ?string $directory): void
    {
        if (! $this->performMoveOne(app(MediaService::class), $id, $directory)) {
            return;
        }

        Notification::make()
            ->title(trans_choice('filament-media-library::filament-media-library.messages.moved', 1, [
                'count' => 1,
                'folder' => $directory ?: '/',
            ]))
            ->success()
            ->send();
    }

    public function renameFolder(string $from, string $to): void
    {
        $renamed = $this->performRenameFolder(app(MediaService::class), $from, $to);

        if ($renamed === 0) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.folder_renamed', ['folder' => $to]))
            ->success()
            ->send();
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

    public function deleteOne(string $id): void
    {
        if (! $this->performDelete(app(MediaService::class), $id)) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.deleted'))
            ->success()
            ->send();
    }

    public function bulkDelete(): void
    {
        $count = $this->performBulkDelete(app(MediaService::class));

        if ($count === 0) {
            return;
        }

        Notification::make()
            ->title(trans_choice('filament-media-library::filament-media-library.messages.bulk_deleted', $count, ['count' => $count]))
            ->success()
            ->send();
    }

    public function renameOne(string $id, string $newName): void
    {
        if ($this->performRename(app(MediaService::class), $id, $newName) === null) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.renamed'))
            ->success()
            ->send();
    }

    /** @param array<string, mixed> $payload */
    public function updateMeta(string $id, array $payload): void
    {
        if ($this->performUpdateMeta($id, $payload) === null) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.meta_updated'))
            ->success()
            ->send();
    }

    public function duplicateOne(string $id): void
    {
        if ($this->performDuplicate(app(MediaService::class), $id) === null) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.duplicated'))
            ->success()
            ->send();
    }

    /** @param array<int, string> $names */
    public function syncTags(string $id, array $names): void
    {
        if ($this->performSyncTags($id, $names) === null) {
            return;
        }

        Notification::make()
            ->title(__('filament-media-library::filament-media-library.messages.tags_updated'))
            ->success()
            ->send();
    }

    public function copyUrlOf(string $id): ?string
    {
        return Media::query()
            ->where('disk', $this->disk)
            ->whereKey($id)
            ->first()
            ?->publicUrl();
    }
}
