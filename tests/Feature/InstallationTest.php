<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('creates its tables under a prefixed name', function () {
    // `media` alone would collide head-on with spatie/laravel-medialibrary.
    expect(Schema::hasTable('media_library_items'))->toBeTrue()
        ->and(Schema::hasTable('media_library_folders'))->toBeTrue()
        ->and(Schema::hasTable('media_library_attachables'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeFalse();
});

it('publishes its config under its own key', function () {
    expect(config('filament-media-library.default_disk'))->not->toBeNull()
        ->and(MediaLibraryConfig::table('media'))->toBe('media_library_items');
});

it('registers the picker as a livewire component', function () {
    expect(app('livewire.finder')->resolveClassComponentClassName('filament-media-library-picker'))
        ->toBe(MediaPicker::class);
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
