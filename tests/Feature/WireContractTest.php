<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * One set of JavaScript drives two components, so every method it calls has to
 * exist on whichever component is rendering.
 *
 * Dragging a file onto a folder called `$wire.moveItemTo(...)`, which only the
 * picker defined; on the library page it reached nothing at all, and creating,
 * renaming or deleting a folder there was dead for the same reason. Nothing
 * anywhere said so — a missing Livewire method fails in the browser.
 */

/** Livewire's own surface, which no component declares. */
const LIVEWIRE_BUILT_INS = [
    'set', 'get', 'call', 'refresh', 'dispatch', 'dispatchSelf', 'dispatchTo',
    'upload', 'uploadMultiple', 'removeUpload', 'watch', 'entangle', 'commit',
    'mountAction', 'unmountAction', 'mountFormComponentAction', 'replaceMountedAction',
    'previousPage', 'nextPage', 'gotoPage', 'setPage', 'resetPage',
];

/** @return array<int, string> the component methods some markup or script asks for */
function wireCallsIn(string $source): array
{
    // $wire.method(...) anywhere, including inside Alpine attributes.
    preg_match_all('/\$wire\.(?:\$?)([a-zA-Z_]\w*)\s*\(/', $source, $direct);

    // wire:click="method(...)", and no-argument actions, which have no parens.
    preg_match_all(
        '/wire:(?:click|submit|change|keydown[\w.]*)(?:\.\w+)*="\s*([a-zA-Z_]\w*)\s*[("]/',
        $source,
        $attributes,
    );

    return array_values(array_diff(
        array_unique([...$direct[1], ...$attributes[1]]),
        LIVEWIRE_BUILT_INS,
    ));
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);

    Storage::disk('public')->put('advertisements/banner.png', 'x');

    Media::create([
        'disk' => 'public',
        'path' => 'advertisements/banner.png',
        'directory' => 'advertisements',
        'name' => 'banner.png',
        'kind' => 'image',
        'size' => 10,
    ]);
});

it('backs every method the markup it renders calls', function (string $component, Closure $render) {
    $shared = wireCallsIn(File::get(__DIR__.'/../../resources/dist/filament-media-library.js'));
    $calls = array_unique([...$shared, ...wireCallsIn($render())]);

    $missing = array_values(array_filter(
        $calls,
        fn (string $method): bool => ! method_exists($component, $method),
    ));

    expect($missing)->toBe([], class_basename($component).' is missing: '.implode(', ', $missing));
})->with([
    'picker' => [
        MediaPicker::class,
        fn (): string => Livewire::test(MediaPicker::class, [
            'multiple' => true,
            'disk' => 'public',
            'directory' => '',
            'kinds' => [],
            'targetStatePath' => 'data.image',
        ])->set('viewMode', 'list')->call('toggleSelect', Media::sole()->id)->html(),
    ],
    'library page' => [
        MediaLibrary::class,
        fn (): string => Livewire::test(MediaLibrary::class)
            ->call('showDetail', Media::sole()->id)
            ->call('toggleSelect', Media::sole()->id)
            ->html(),
    ],
]);

it('finds the calls it is meant to be checking', function () {
    // A regex that quietly matches nothing would pass the test above forever.
    $calls = wireCallsIn(File::get(__DIR__.'/../../resources/dist/filament-media-library.js'));

    expect($calls)
        ->toContain('moveItemTo')
        ->toContain('moveSelectionTo')
        ->toContain('createFolder')
        ->toContain('deleteFolder')
        ->toContain('adoptUploads');
});
