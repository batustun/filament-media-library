<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Components\LibraryPickerAction;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PickerHost;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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

function pickerActionOf(mixed $component): Action
{
    if (! $component instanceof FileUpload) {
        throw new RuntimeException('Expected a FileUpload, got '.get_debug_type($component));
    }

    $action = collect($component->getHintActions())
        ->first(fn (Action $action): bool => $action->getName() === LibraryPickerAction::NAME);

    if (! $action instanceof Action) {
        throw new RuntimeException('The field carries no library picker.');
    }

    return $action;
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

it('does not take the page down when the field is detached from its schema', function () {
    // Filament renders an action's modal wherever the action is rendered,
    // including a re-render at the end of a Livewire request. The field the
    // closure holds is the instance configureUsing saw — for a repeater's child
    // schema, a blueprint that may never be attached. Reading its state then
    // raised "Typed property Component::$container must not be accessed before
    // initialization" and returned a 500 for the whole page.
    expect(pickerActionOf(FileUpload::make('image')->disk('public'))->getModalContent())->toBeNull();
});

it('still opens for a field inside a repeater', function () {
    // The blueprint does get a container once the repeater builds its child
    // schema, so the picker has to keep working there — the guard above must
    // not quietly disable it.
    $host = new PickerHost;
    $host->data = ['rows' => [['image' => Media::sole()->path]]];

    $repeater = Repeater::make('rows')->schema([
        FileUpload::make('image')->disk('public')->directory('categories'),
    ]);
    $repeater->container(Schema::make($host)->components([$repeater])->statePath('data'));

    // Reading the child schema's components is what attaches them, and those
    // are the instances Filament renders.
    $field = collect($repeater->getChildSchema()?->getComponents() ?? [])
        ->first(fn (mixed $component): bool => $component instanceof FileUpload);

    expect(pickerActionOf($field)->getModalContent())->not->toBeNull();
});
