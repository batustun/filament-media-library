<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('public'));

it('replaces the bytes of a file while keeping its id, path and url', function () {
    $service = app(MediaService::class);

    $media = $service->store(
        UploadedFile::fake()->createWithContent('report.txt', 'first draft'),
        'public',
        'docs',
    );

    $id = $media->id;
    $path = $media->path;
    $url = $media->publicUrl();

    $service->replace($media, UploadedFile::fake()->createWithContent('final.txt', 'second draft, longer'));

    $fresh = Media::find($id);

    expect($fresh->path)->toBe($path)
        ->and($fresh->publicUrl())->toBe($url)
        ->and($fresh->size)->toBe(strlen('second draft, longer'))
        ->and(Storage::disk('public')->get($path))->toBe('second draft, longer')
        ->and(Media::count())->toBe(1);
});

it('refuses a replacement that breaks the upload rules', function () {
    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('a.png'), 'public');

    config()->set('filament-media-library.max_upload_size_kb', 1);

    expect(fn () => $service->replace($media, UploadedFile::fake()->create('huge.bin', 4096)))
        ->toThrow(ValidationException::class);
});

it('moves files on the disk as well as in the index', function () {
    $service = app(MediaService::class);

    $a = $service->store(UploadedFile::fake()->image('a.png'), 'public', 'inbox');
    $b = $service->store(UploadedFile::fake()->image('b.png'), 'public', 'inbox');

    $moved = $service->moveMany([$a, $b], 'archive/2026');

    expect($moved)->toBe(2)
        ->and($a->fresh()->directory)->toBe('archive/2026')
        ->and($b->fresh()->directory)->toBe('archive/2026');

    Storage::disk('public')->assertExists($a->fresh()->path);
    Storage::disk('public')->assertMissing('inbox/'.$a->name);
});

it('renames a directory and carries its whole subtree along', function () {
    $service = app(MediaService::class);

    $top = $service->store(UploadedFile::fake()->image('a.png'), 'public', 'gallery');
    $nested = $service->store(UploadedFile::fake()->image('b.png'), 'public', 'gallery/2026/spring');
    $other = $service->store(UploadedFile::fake()->image('c.png'), 'public', 'gallery-archive');

    $renamed = $service->renameDirectory('public', 'gallery', 'photos');

    expect($renamed)->toBe(2)
        ->and($top->fresh()->directory)->toBe('photos')
        ->and($nested->fresh()->directory)->toBe('photos/2026/spring')
        // A sibling whose name merely starts with the same characters is left alone.
        ->and($other->fresh()->directory)->toBe('gallery-archive');

    Storage::disk('public')->assertExists($nested->fresh()->path);
});

it('refuses to move a directory inside itself', function () {
    $service = app(MediaService::class);
    $service->store(UploadedFile::fake()->image('a.png'), 'public', 'gallery');

    expect(fn () => $service->renameDirectory('public', 'gallery', 'gallery/2026'))
        ->toThrow(RuntimeException::class);
});

it('resolves a public url back to its path so a field can find its record', function () {
    config()->set('filament-media-library.persist_url', false);

    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('a.png'), 'public', 'covers');

    // Regression: with persist_url off the `url` column is empty, and the
    // form field used to lose the record entirely.
    expect($media->url)->toBeNull()
        ->and($service->findByUrl($media->publicUrl(), 'public')?->id)->toBe($media->id);
});

it('resolves a url back to its path behind a cdn resolver too', function () {
    config()->set('filament-media-library.url_resolvers', [
        'public' => fn (string $path): string => 'https://cdn.example.test/'.ltrim($path, '/'),
    ]);

    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('a.png'), 'public', 'covers');

    expect($service->pathFromUrl('public', $media->publicUrl()))->toBe($media->path)
        ->and($service->findByUrl($media->publicUrl(), 'public')?->id)->toBe($media->id);
});
