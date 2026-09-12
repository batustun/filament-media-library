<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Components\LibraryPickerAction;
use Batustun\FilamentMediaLibrary\Filament\Components\MediaInput;
use Batustun\FilamentMediaLibrary\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Names of the hint actions a field ended up with. */
function hintActionNames(FileUpload $field): array
{
    return array_map(
        fn (Action $action): string => $action->getName(),
        $field->getHintActions(),
    );
}

it('adds the library picker to a plain FileUpload nobody changed', function () {
    // The whole point: an existing form gains the picker without being edited.
    expect(hintActionNames(FileUpload::make('cover')))
        ->toContain(LibraryPickerAction::NAME);
});

it('gives MediaInput exactly one picker, not two', function () {
    $names = hintActionNames(MediaInput::make('cover'));

    expect(array_count_values($names)[LibraryPickerAction::NAME] ?? 0)->toBe(1);
});

it('keeps a hint action the application defined itself', function () {
    $field = FileUpload::make('cover')->hintAction(Action::make('myOwnAction'));

    expect(hintActionNames($field))
        ->toContain('myOwnAction')
        ->toContain(LibraryPickerAction::NAME);
});

it('attaches nothing when the option is switched off', function () {
    config()->set('filament-media-library.auto_attach.file_upload', false);

    expect(hintActionNames(FileUpload::make('cover')))
        ->not->toContain(LibraryPickerAction::NAME);
});

it('does not index uploads from ordinary fields by default', function () {
    // Off by default: routing uploads through the library changes the
    // generated filename, and an application may depend on the current one.
    expect(config('filament-media-library.auto_attach.index_uploads'))->toBeFalse();
});

it('indexes uploads from ordinary fields once that is switched on', function () {
    Storage::fake('public');

    config()->set('filament-media-library.auto_attach.index_uploads', true);

    $field = FileUpload::make('cover')->disk('public')->directory('covers');

    // Reach the writer the way Filament does when it persists an upload.
    $writer = (fn () => $this->saveUploadedFileUsing)->call($field);

    expect($writer)->not->toBeNull();

    $path = $field->evaluate($writer, ['file' => UploadedFile::fake()->image('hero.jpg')]);

    expect(Media::count())->toBe(1)
        ->and(Media::sole()->path)->toBe($path)
        ->and($path)->toStartWith('covers/');
});
