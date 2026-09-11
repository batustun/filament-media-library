<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

it('exposes a stable plugin id', function () {
    expect(FilamentMediaLibraryPlugin::make()->getId())->toBe('filament-media-library');
});

it('never writes plugin settings into the global config repository', function () {
    $before = config('filament-media-library.default_disk');

    FilamentMediaLibraryPlugin::make()
        ->defaultDisk('cdn')
        ->navigationGroup('Content')
        ->navigationSort(3);

    // Panel-scoped state must stay on the instance, otherwise a second panel
    // registering the plugin would clobber the first one's disk.
    expect(config('filament-media-library.default_disk'))->toBe($before)
        ->and(config('filament-media-library.navigation.group'))->toBeNull();
});

it('keeps two plugin instances independent', function () {
    $a = FilamentMediaLibraryPlugin::make()->defaultDisk('public');
    $b = FilamentMediaLibraryPlugin::make()->defaultDisk('cdn');

    expect($a->getDefaultDisk())->toBe('public')
        ->and($b->getDefaultDisk())->toBe('cdn');
});

it('falls back to the config file outside a panel', function () {
    config()->set('filament-media-library.default_disk', 'cdn');

    expect(MediaLibraryConfig::defaultDisk())->toBe('cdn');
});

it('trusts an explicit disk allow-list verbatim', function () {
    // Disks can be registered at runtime (Storage::fake, Storage::extend,
    // per-tenant disks), so the allow-list must not be intersected with
    // config('filesystems.disks') — that would silently drop them.
    config()->set('filament-media-library.disks', ['public', 'cdn', 'registered-at-runtime']);

    expect(MediaLibraryConfig::disks())
        ->toEqualCanonicalizing(['public', 'cdn', 'registered-at-runtime'])
        ->and(MediaLibraryConfig::isDiskAllowed('registered-at-runtime'))->toBeTrue()
        ->and(MediaLibraryConfig::isDiskAllowed('never-mentioned'))->toBeFalse();
});

it('reads the config file, not the default panel, outside a panel request', function () {
    // Documented precedence: a plugin's settings apply to the panel it is
    // registered on. A console command or a JSON route has no current panel,
    // so the config file is the only source.
    config()->set('filament-media-library.permissions.enabled', true);

    expect(MediaLibraryConfig::plugin())->toBeNull()
        ->and(MediaLibraryConfig::permissionsEnabled())->toBeTrue();

    config()->set('filament-media-library.permissions.enabled', false);

    expect(MediaLibraryConfig::permissionsEnabled())->toBeFalse();
});

it('returns every configured disk when the allow-list is empty', function () {
    config()->set('filament-media-library.disks', []);

    expect(MediaLibraryConfig::disks())->toContain('public', 'private', 'cdn');
});

it('clamps the page size to one of the offered options', function () {
    config()->set('filament-media-library.page_sizes', [24, 48]);
    config()->set('filament-media-library.default_page_size', 999);

    expect(MediaLibraryConfig::defaultPageSize())->toBe(24);
});
