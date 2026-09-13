<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Components\LibraryPickerAction;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PickerHost;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\RepeaterHost;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

it('builds the modal for the field Filament mounted, not the one it captured', function () {
    // configureUsing hands the closure the instance it saw. Inside a repeater
    // that is a blueprint belonging to no item, and on a page of many fields it
    // is not necessarily the one being opened — Filament says which by mounting
    // the action with the schema component. Depending on the captured field
    // rendered an empty modal, or none at all.
    $captured = FileUpload::make('image')->disk('public');
    $mounted = field(Media::sole()->path);

    $action = LibraryPickerAction::for($captured, returns: 'path')->schemaComponent($mounted);

    $content = $action->getModalContent();

    expect($content)->not->toBeNull();
    expect($content->getData()['statePath'])->toBe($mounted->getStatePath());
    expect($content->getData()['selectedIds'])->toBe([Media::sole()->id]);
});

it('opens for a field inside a repeater, on that item', function () {
    // End to end through Filament's own mounting, on the shape of form the
    // failing page used.
    $host = Livewire::test(RepeaterHost::class);

    preg_match('/form\\.rows\\.[a-f0-9-]{36}\\.image/', html_entity_decode($host->html(), ENT_QUOTES), $matches);

    expect($matches)->not->toBeEmpty('the picker was not rendered on the repeater item');

    $host->call('mountAction', LibraryPickerAction::NAME, [], ['schemaComponent' => $matches[0]]);

    $instance = $host->instance();

    if (! $instance instanceof RepeaterHost) {
        throw new RuntimeException('Expected the repeater host.');
    }

    $content = $instance->getMountedAction()?->getModalContent();

    expect($content)->not->toBeNull();
    expect($content->getData()['statePath'])->toBe(str_replace('form.', 'data.', $matches[0]));
});
