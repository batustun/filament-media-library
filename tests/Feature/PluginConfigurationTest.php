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

it('intersects the disk allow-list with configured filesystems', function () {
    config()->set('filament-media-library.disks', ['public', 'cdn', 'disk-that-does-not-exist']);

    expect(MediaLibraryConfig::disks())->toEqualCanonicalizing(['public', 'cdn'])
        ->and(MediaLibraryConfig::isDiskAllowed('disk-that-does-not-exist'))->toBeFalse();
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
