<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The picker's upload flow, driven the way the browser drives it.
 *
 * Nothing here calls an action by hand: choosing a file is the whole gesture,
 * so the tests set the property and expect the file to be in the library.
 */
function picker(array $mount = []): Testable
{
    return Livewire::test(MediaPicker::class, array_merge([
        'multiple' => false,
        'disk' => 'public',
        'directory' => '',
        'kinds' => [],
        'targetStatePath' => 'data.image',
    ], $mount));
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
});

it('stores a file the moment it is chosen', function () {
    // Regression: uploads used to wait behind a second button whose only
    // visible change was its own tint, so choosing a file looked like nothing
    // had happened and the file never arrived.
    picker()->set('uploads', [UploadedFile::fake()->image('hero.jpg', 400, 300)]);

    expect(Media::count())->toBe(1)
        ->and(Media::sole()->disk)->toBe('public');
});

it('shows what it just stored in the grid', function () {
    $picker = picker()->set('uploads', [UploadedFile::fake()->image('hero.jpg', 400, 300)]);

    expect($picker->html())->toContain(Media::sole()->name);
});

it('leaves the upload selected so the editor can see what arrived', function () {
    // Uploading must not confirm the selection: confirming closes the modal, so
    // the file just added would flash past without ever appearing in the grid.
    $picker = picker()->set('uploads', [UploadedFile::fake()->image('hero.jpg')]);

    expect($picker->get('selected'))->toBe([Media::sole()->id]);
});

it('uploads into the directory the field was configured with', function () {
    $picker = picker(['uploadDirectory' => 'advertisements'])
        ->set('uploads', [UploadedFile::fake()->image('banner.jpg')]);

    expect(Media::sole()->directory)->toBe('advertisements')
        // Browsing stays where it was, so the grid still shows everything.
        ->and($picker->get('directory'))->toBe('');

    Storage::disk('public')->assertExists(Media::sole()->path);
});

it('keeps every uploaded file selected when the field takes many', function () {
    // Different dimensions, so these are genuinely different bytes — two
    // identical fakes would be collapsed by duplicate detection, correctly.
    $picker = picker(['multiple' => true])->set('uploads', [
        UploadedFile::fake()->image('a.jpg', 100, 100),
        UploadedFile::fake()->image('b.jpg', 200, 150),
    ]);

    expect($picker->get('selected'))->toHaveCount(2)
        ->and(Media::count())->toBe(2);
});

it('reuses an existing record rather than storing the same bytes twice', function () {
    $picker = picker();

    $picker->set('uploads', [UploadedFile::fake()->createWithContent('a.txt', 'same bytes')]);
    $first = Media::sole()->id;

    $picker->set('uploads', [UploadedFile::fake()->createWithContent('b.txt', 'same bytes')]);

    expect(Media::count())->toBe(1)
        // Still selected, so a reused file is not a dead end.
        ->and($picker->get('selected'))->toBe([$first]);
});

it('clears the choice when the chosen file is clicked again', function () {
    // A single-value field could be changed but never emptied by the same
    // gesture: re-selecting simply wrote the same id back.
    $picker = picker()->set('uploads', [UploadedFile::fake()->image('hero.jpg')]);

    $id = Media::sole()->id;

    $picker->call('toggleSelectFor', $id);
    expect($picker->get('selected'))->toBe([]);

    $picker->call('toggleSelectFor', $id);
    expect($picker->get('selected'))->toBe([$id]);
});

it('adopts files that went up through the chunked endpoint', function () {
    // Those bypass Livewire entirely, so the component is told explicitly.
    $picker = picker();

    $media = Media::create([
        'disk' => 'public', 'path' => 'big/clip.mp4', 'name' => 'clip.mp4',
        'kind' => 'video', 'size' => 40_000_000,
    ]);

    $picker->call('adoptUploads', [$media->id]);

    expect($picker->get('selected'))->toBe([$media->id]);
});
