<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The folder sidebar arrives flat and depth-first; which rows are branches, and
 * which must stay on screen, is decided in the markup. Both are easy to get
 * silently wrong, and both are invisible to every other test.
 */
beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);

    foreach (['advertisements', 'assistsocial/uploads/posts/photos', 'avatars'] as $directory) {
        Storage::disk('public')->put($directory.'/f.png', 'x');

        Media::create([
            'disk' => 'public',
            'path' => $directory.'/f.png',
            // MediaService fills this when storing; created by hand it must be
            // set, or the tree has nothing to build from.
            'directory' => $directory,
            'name' => 'f.png',
            'kind' => 'image',
            'size' => 10,
        ]);
    }
});

function sidebar(string $directory = ''): string
{
    $html = Livewire::test(MediaPicker::class, [
        'multiple' => false,
        'disk' => 'public',
        'directory' => $directory,
        'kinds' => [],
        'targetStatePath' => 'data.image',
    ])->html();

    // Attribute values arrive HTML-escaped; the assertions below read the
    // JavaScript as the browser will, not as the transport spells it.
    return html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
}

it('offers a disclosure only on folders that have children', function () {
    $html = sidebar();

    // "assistsocial" holds "uploads"; "avatars" holds nothing.
    expect($html)->toContain("toggleFolder('assistsocial')")
        ->and($html)->not->toContain("toggleFolder('avatars')")
        // The deepest folder is a leaf and gets the spacer, not a button.
        ->and($html)->not->toContain("toggleFolder('assistsocial\\/uploads\\/posts\\/photos')");
});

it('starts collapsed, so a deep library does not bury its top level', function () {
    // Nothing is expanded up front: a nested row is shown only once its
    // ancestors are opened, which `false` here leaves to the browser.
    expect(sidebar())->toContain("isFolderVisible('assistsocial\\/uploads', false)");
});

it('never hides the folder being browsed, nor the way back up to it', function () {
    $html = sidebar('assistsocial/uploads/posts');

    expect($html)
        ->toContain("isFolderVisible('assistsocial', true)")
        ->toContain("isFolderVisible('assistsocial\\/uploads', true)")
        ->toContain("isFolderVisible('assistsocial\\/uploads\\/posts', true)")
        // A sibling branch is unaffected.
        ->toContain("isFolderVisible('advertisements', false)");
});

it('indents each level rather than every row alike', function () {
    expect(sidebar())
        ->toContain('padding-inline-start: 0rem')
        ->toContain('padding-inline-start: 0.75rem')
        ->toContain('padding-inline-start: 2.25rem');
});
