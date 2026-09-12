<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('creates its tables under a prefixed name', function () {
    // `media` alone would collide head-on with spatie/laravel-medialibrary.
    expect(Schema::hasTable('media_library_items'))->toBeTrue()
        ->and(Schema::hasTable('media_library_attachables'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeFalse();
});

it('does not create a folders table, because folders are derived', function () {
    // Directories live in the `directory` column, so the tree cannot drift
    // from what is actually on the disk. A folders table would be an empty
    // second source of truth.
    expect(Schema::hasTable('media_library_folders'))->toBeFalse()
        ->and(Schema::hasColumn('media_library_items', 'folder_id'))->toBeFalse();
});

it('registers both console commands', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('media-library:sync', 'media-library:doctor');
});

it('publishes its config under its own key', function () {
    expect(config('filament-media-library.default_disk'))->not->toBeNull()
        ->and(MediaLibraryConfig::table('media'))->toBe('media_library_items');
});

it('registers the picker as a livewire component', function () {
    // Livewire 3 resolves through the component registry, Livewire 4 through
    // the finder. The package supports both, so the test asks whichever exists.
    // A string, not ::class: on Livewire 4 this class is gone, and static
    // analysis would rightly object to a reference it cannot resolve.
    $registry = 'Livewire\\Mechanisms\\ComponentRegistry';

    $resolved = class_exists($registry)
        ? app($registry)->getClass('filament-media-library-picker')
        : app('livewire.finder')->resolveClassComponentClassName('filament-media-library-picker');

    expect($resolved)->toBe(MediaPicker::class);
});

it('exposes the publish tags the README documents', function () {
    $groups = array_keys(ServiceProvider::$publishGroups);

    expect($groups)->toContain(
        'filament-media-library-config',
        'filament-media-library-views',
        'filament-media-library-translations',
    );
});

it('translates every media kind', function () {
    app()->setLocale('en');
    expect(MediaKind::Image->getLabel())->toBe('Image');

    app()->setLocale('tr');
    expect(MediaKind::Image->getLabel())->toBe('Görsel');
});

it('never emits a dynamic tailwind class for kind colours', function () {
    foreach (MediaKind::cases() as $kind) {
        expect($kind->hexColor())->toMatch('/^#[0-9a-f]{6}$/');
    }
});
