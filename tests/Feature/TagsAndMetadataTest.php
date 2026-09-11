<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Models\MediaTag;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function item(array $overrides = []): Media
{
    return Media::create(array_merge([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => 'a.png',
        'kind' => 'image',
        'size' => 1024,
    ], $overrides));
}

it('creates tags on demand and matches them by slug', function () {
    $media = item();

    $media->syncTagNames(['Product Shots', 'Hero']);

    expect($media->tagNames())->toEqualCanonicalizing(['Hero', 'Product Shots'])
        ->and(MediaTag::count())->toBe(2);

    // "product shots" and "Product-Shots" are the same tag, not three near-duplicates.
    item()->syncTagNames(['product shots']);

    expect(MediaTag::count())->toBe(2);
});

it('replaces tags rather than appending on each sync', function () {
    $media = item();

    $media->syncTagNames(['one', 'two']);
    $media->syncTagNames(['two', 'three']);

    expect($media->tagNames())->toEqualCanonicalizing(['three', 'two']);
});

it('ignores blank tag names', function () {
    $media = item();

    $media->syncTagNames(['  ', '', 'real']);

    expect($media->tagNames())->toBe(['real']);
});

it('filters media by tag', function () {
    $tagged = item(['name' => 'tagged.png']);
    item(['name' => 'untagged.png']);

    $tagged->syncTagNames(['Campaign']);

    expect(Media::query()->taggedWith('campaign')->pluck('name')->all())->toBe(['tagged.png']);
});

it('drops the pivot row when a tag is deleted', function () {
    $media = item();
    $media->syncTagNames(['temporary']);

    MediaTag::query()->delete();

    expect($media->fresh()->tagNames())->toBe([]);
});

it('only stores custom metadata that has been declared', function () {
    config()->set('filament-media-library.metadata_fields', [
        ['key' => 'photographer', 'type' => 'text'],
    ]);

    $media = item();

    $media->setCustomMeta(['photographer' => 'Ara Güler', 'secret' => 'nope']);
    $media->save();

    expect($media->fresh()->customMetaValue('photographer'))->toBe('Ara Güler')
        ->and($media->fresh()->customMetaValue('secret'))->toBeNull();
});

it('keeps custom metadata alongside conversions in meta', function () {
    config()->set('filament-media-library.metadata_fields', [['key' => 'licence', 'type' => 'text']]);
    config()->set('filament-media-library.conversions.sizes', ['thumb' => 100]);

    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
    )->fresh();

    $media->setCustomMeta(['licence' => 'CC-BY']);
    $media->save();

    $fresh = $media->fresh();

    expect($fresh->customMetaValue('licence'))->toBe('CC-BY')
        ->and($fresh->conversions())->toHaveKey('thumb');
});

it('normalises declared metadata fields and falls back to a humanised label', function () {
    config()->set('filament-media-library.metadata_fields', [
        ['key' => 'shot_by'],
        ['key' => 'licence', 'label' => 'Licence', 'type' => 'select', 'options' => ['rf' => 'Royalty free']],
        ['type' => 'text'],           // no key — ignored
    ]);

    $fields = MediaLibraryConfig::metadataFields();

    expect($fields)->toHaveCount(2)
        ->and($fields[0]['label'])->toBe('Shot by')
        ->and($fields[0]['type'])->toBe('text')
        ->and($fields[1]['options'])->toBe(['rf' => 'Royalty free']);
});

it('duplicates a file into its own record and its own bytes', function () {
    $service = app(MediaService::class);

    $original = $service->store(
        UploadedFile::fake()->createWithContent('brief.txt', 'original bytes'),
        'public',
        'docs',
    );
    $original->syncTagNames(['brief']);

    $copy = $service->duplicate($original);

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->path)->not->toBe($original->path)
        ->and($copy->directory)->toBe('docs')
        ->and($copy->name)->toContain('-copy-')
        ->and($copy->tagNames())->toBe(['brief']);

    Storage::disk('public')->assertExists($copy->path);
    Storage::disk('public')->assertExists($original->path);

    // Editing the copy must not touch the original.
    $service->replace($copy, UploadedFile::fake()->createWithContent('new.txt', 'changed'));

    expect(Storage::disk('public')->get($original->path))->toBe('original bytes');
});

it('refuses to duplicate a provider-backed item', function () {
    $media = item(['provider' => 'bunny-stream', 'external_id' => 'abc', 'kind' => 'video']);

    expect(fn () => app(MediaService::class)->duplicate($media))->toThrow(RuntimeException::class);
});
