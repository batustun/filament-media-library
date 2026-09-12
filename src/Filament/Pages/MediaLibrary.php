<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Pages;

use BackedEnum;
use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
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
}
