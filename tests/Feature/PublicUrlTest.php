<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('resolves the public url from the disk by default', function () {
    Storage::fake('cdn');

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.png'), 'cdn', 'img');

    expect($media->publicUrl())->toContain('/img/');
});

it('prefers a configured per-disk resolver over the disk url', function () {
    Storage::fake('cdn');

    config()->set('filament-media-library.url_resolvers', [
        'cdn' => fn (string $path): string => 'https://pull.example.net/'.ltrim($path, '/'),
    ]);

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.png'), 'cdn', 'img');

    expect($media->publicUrl())->toStartWith('https://pull.example.net/img/');
});

it('reflects a cdn hostname change on existing rows with no backfill', function () {
    Storage::fake('cdn');

    config()->set('filament-media-library.url_resolvers', [
        'cdn' => fn (string $path): string => 'https://old-zone.b-cdn.net/'.$path,
    ]);

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.png'), 'cdn');

    expect($media->publicUrl())->toStartWith('https://old-zone.b-cdn.net/');

    // Operator moves to a new pull zone: config change only, no migration.
    config()->set('filament-media-library.url_resolvers', [
        'cdn' => fn (string $path): string => 'https://new-zone.b-cdn.net/'.$path,
    ]);

    expect($media->fresh()->publicUrl())->toStartWith('https://new-zone.b-cdn.net/');
});

it('never throws for a disk that has no public url configured', function () {
    Storage::fake('private');

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.png'), 'private', 'secret');

    expect(fn () => $media->publicUrl())->not->toThrow(Throwable::class)
        ->and($media->publicUrl())->toBeString()
        ->and($media->publicUrl())->not->toBe('');
});

it('falls back to the stored url column when nothing else resolves', function () {
    $media = Media::create([
        'disk' => 'nonexistent-disk',
        'path' => 'some/file.png',
        'url' => 'https://archived.example.com/some/file.png',
        'name' => 'file.png',
        'kind' => 'image',
        'size' => 1,
    ]);

    expect($media->publicUrl())->toBe('https://archived.example.com/some/file.png');
});

it('can skip persisting the url column entirely', function () {
    Storage::fake('public');

    config()->set('filament-media-library.persist_url', false);

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.png'), 'public');

    expect($media->url)->toBeNull()
        ->and($media->publicUrl())->toContain('/storage/');
});
