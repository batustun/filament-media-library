<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Components\LibraryPickerAction;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PickerHost;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

/** The FileUpload on the host form, with whatever the field is holding. */
function field(mixed $state = null): FileUpload
{
    $upload = FileUpload::make('image')->disk('public')->directory('advertisements');

    $host = new PickerHost;
    $host->data = $state === null ? [] : ['image' => $state];

    // A container is what gives the field a state path to read from.
    $upload->container(Schema::make($host)->components([$upload])->statePath('data'));

    return $upload;
}

/** @return array<string, mixed> */
function modalData(FileUpload $component, string $returns = 'path'): array
{
    $content = LibraryPickerAction::for($component, returns: $returns)->getModalContent();

    expect($content)->toBeInstanceOf(View::class);

    return $content->getData();
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);

    Media::create([
        'disk' => 'public',
        'path' => 'advertisements/banner.png',
        'directory' => 'advertisements',
        'name' => 'banner.png',
        'kind' => 'image',
        'size' => 10,
    ]);
});

it('opens in the folder the chosen file lives in, with it selected', function () {
    // Opening at the library root every time made the picker forget where the
    // last choice was made, which is the one place the next one usually is.
    $media = Media::sole();

    $data = modalData(field(['uuid-1' => $media->path]));

    expect($data['directory'])->toBe('advertisements')
        ->and($data['selectedIds'])->toBe([$media->id]);
});

it('recognises the value whichever shape the field stores', function (string $returns, string $state) {
    $data = modalData(field($state), $returns);

    expect($data['selectedIds'])->toBe([Media::sole()->id]);
})->with([
    'path' => ['path', 'advertisements/banner.png'],
    'id' => fn () => ['id', Media::sole()->id],
    'url' => fn () => ['url', Media::sole()->publicUrl()],
]);

it('opens on the whole library when the field is empty', function () {
    $data = modalData(field());

    expect($data['directory'])->toBe('')
        ->and($data['selectedIds'])->toBe([]);
});

it('ignores a value the library has never seen', function () {
    // A field filled before the library existed, or pointing somewhere else.
    $data = modalData(field('https://example.test/elsewhere.png'), 'url');

    expect($data['directory'])->toBe('')
        ->and($data['selectedIds'])->toBe([]);
});

it('still steers new uploads to the directory the field was configured with', function () {
    expect(modalData(field())['uploadDirectory'])->toBe('advertisements');
});
