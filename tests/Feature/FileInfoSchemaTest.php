<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\FileInfoHost;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
});

function hostWithComponents(array $components = [], ?Closure $hydrate = null, ?Closure $save = null): FileInfoHost
{
    $plugin = FilamentMediaLibraryPlugin::get()->fileInfoComponents($components);

    if ($hydrate) {
        $plugin->hydrateFileInfoUsing($hydrate);
    }

    if ($save) {
        $plugin->saveFileInfoUsing($save);
    }

    $host = new FileInfoHost;
    $host->disk = 'public';

    return $host;
}

function subject(): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => 'a.png',
        'kind' => 'image',
        'size' => 1,
    ]);
}

it('registers host components on the plugin', function () {
    hostWithComponents([TextInput::make('sku')]);

    expect(MediaLibraryConfig::fileInfoComponents())->toHaveCount(1);
});

it('reports no form when the host registered nothing', function () {
    $host = hostWithComponents([]);

    expect($host->hasFileInfoForm())->toBeFalse();
});

it('reports a form once components are registered', function () {
    $host = hostWithComponents([TextInput::make('sku')]);

    expect($host->hasFileInfoForm())->toBeTrue();
});

it('hydrates the form from the host callback when an item is previewed', function () {
    $media = subject();

    $host = hostWithComponents(
        [TextInput::make('sku')],
        hydrate: fn (Media $item): array => ['sku' => 'SKU-'.substr($item->name, 0, 1)],
    );

    $host->showDetail($media->id);

    // Regression: this used to do nothing at all — hydrateFileInfoUsing() was
    // registered on the plugin and then never called.
    expect($host->fileInfoData)->toBe(['sku' => 'SKU-a']);
});

it('initialises the form with empty fields when no hydrator is registered', function () {
    $media = subject();

    $host = hostWithComponents([TextInput::make('sku')]);
    $host->showDetail($media->id);

    // Filling a schema initialises its declared fields, so the key is present
    // and empty rather than the array being bare.
    expect($host->fileInfoData)->toHaveKey('sku')
        ->and($host->fileInfoData['sku'])->toBeNull();
});

it('hands the host component state to the save callback', function () {
    $media = subject();
    $received = null;

    $host = hostWithComponents(
        [TextInput::make('sku')],
        save: function (Media $item, array $data) use (&$received): void {
            $received = $data;
        },
    );

    $host->showDetail($media->id);
    $host->fileInfoData = ['sku' => 'SKU-123'];

    $host->performUpdateMeta($media->id, ['title' => 'Renamed']);

    expect($received)->toMatchArray(['title' => 'Renamed', 'sku' => 'SKU-123']);
});

it('still saves the built-in fields when no host callback is registered', function () {
    $media = subject();

    $host = hostWithComponents([]);
    $host->performUpdateMeta($media->id, ['title' => 'Just the basics']);

    expect($media->fresh()->title)->toBe('Just the basics');
});
