<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Exercises the picker's upload flow on the component itself.
 *
 * Livewire rendering is covered by the consuming application's suite; Testbench
 * cannot render a component here (its validation support reads a shared error
 * bag that only real middleware provides).
 */
function picker(array $mount = []): MediaPicker
{
    $picker = new MediaPicker;

    $picker->mount(...array_merge([
        'targetStatePath' => 'data.image',
        'disk' => 'public',
    ], $mount));

    return $picker;
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
});

it('indexes a file uploaded from inside the picker', function () {
    $picker = picker();
    $picker->uploads = [UploadedFile::fake()->image('hero.jpg', 400, 300)];

    $picker->uploadAndApply();

    expect(Media::count())->toBe(1)
        ->and(Media::sole()->disk)->toBe('public');
});

it('leaves the upload selected so the editor can see what arrived', function () {
    // Regression: uploading used to confirm the selection immediately, which
    // closes the modal — the file just added flashed past without ever
    // appearing in the grid, and read as "the upload did nothing".
    $picker = picker();
    $picker->uploads = [UploadedFile::fake()->image('hero.jpg')];

    $picker->uploadAndApply();

    expect($picker->selected)->toBe([Media::sole()->id]);
});

it('uploads into the directory the field was configured with', function () {
    $picker = picker(['uploadDirectory' => 'advertisements']);
    $picker->uploads = [UploadedFile::fake()->image('banner.jpg')];

    $picker->uploadAndApply();

    expect(Media::sole()->directory)->toBe('advertisements')
        // Browsing returns to where it was, so the grid still shows everything.
        ->and($picker->directory)->toBe('');

    Storage::disk('public')->assertExists(Media::sole()->path);
});

it('keeps every uploaded file selected when the field takes many', function () {
    $picker = picker(['multiple' => true]);
    // Different dimensions, so these are genuinely different bytes — two
    // identical fakes would be collapsed by duplicate detection, correctly.
    $picker->uploads = [
        UploadedFile::fake()->image('a.jpg', 100, 100),
        UploadedFile::fake()->image('b.jpg', 200, 150),
    ];

    $picker->uploadAndApply();

    expect($picker->selected)->toHaveCount(2)
        ->and(Media::count())->toBe(2);
});

it('reuses an existing record rather than storing the same bytes twice', function () {
    $picker = picker();
    $picker->uploads = [UploadedFile::fake()->createWithContent('a.txt', 'same bytes')];
    $picker->uploadAndApply();

    $picker->uploads = [UploadedFile::fake()->createWithContent('b.txt', 'same bytes')];
    $picker->uploadAndApply();

    expect(Media::count())->toBe(1)
        ->and($picker->selected)->toBe([Media::sole()->id]);
});
