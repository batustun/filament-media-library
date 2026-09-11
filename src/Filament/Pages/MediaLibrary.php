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
        $count = $this->performUpload(app(MediaService::class));

        if ($count === 0) {
            return;
        }

        Notification::make()
            ->title(trans_choice('filament-media-library::filament-media-library.messages.uploaded', $count, ['count' => $count]))
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

    public function copyUrlOf(string $id): ?string
    {
        return Media::query()
            ->where('disk', $this->disk)
            ->whereKey($id)
            ->first()
            ?->publicUrl();
    }
}
