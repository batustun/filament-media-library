<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an upload on any flysystem disk and indexes it', function (string $disk) {
    Storage::fake($disk);

    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('Photo Of A Cat.jpg', 120, 80),
        $disk,
        'gallery/2026',
    );

    expect($media->disk)->toBe($disk)
        ->and($media->directory)->toBe('gallery/2026')
        ->and($media->kind)->toBe('image')
        ->and($media->width)->toBe(120)
        ->and($media->height)->toBe(80)
        ->and($media->name)->toStartWith('photo-of-a-cat-')
        ->and($media->hash)->toHaveLength(64);

    Storage::disk($disk)->assertExists($media->path);
})->with(['public', 'private', 'cdn']);

it('sends no ACL unless visibility is explicitly configured', function () {
    // S3 buckets with ACLs disabled reject any ACL, so the default must be silent.
    expect(config('filament-media-library.visibility'))->toBeNull();

    Storage::fake('public');

    $media = app(MediaService::class)->store(UploadedFile::fake()->create('doc.pdf', 10), 'public');

    Storage::disk('public')->assertExists($media->path);
});

it('strips directory traversal from user supplied directories', function () {
    $service = app(MediaService::class);

    expect($service->normalizeDirectory('../../etc/passwd'))->toBe('etc/passwd')
        ->and($service->normalizeDirectory('/leading/and/trailing/'))->toBe('leading/and/trailing')
        ->and($service->normalizeDirectory('a/../../../b'))->toBe('a/b')
        ->and($service->normalizeDirectory('..'))->toBe('')
        ->and($service->normalizeDirectory(null))->toBe('');
});

it('does not load the whole file into memory when uploading', function () {
    Storage::fake('public');

    $before = memory_get_peak_usage(true);

    // 12 MB — comfortably larger than any tolerable memory delta.
    app(MediaService::class)->store(UploadedFile::fake()->create('big.bin', 12 * 1024), 'public');

    $delta = memory_get_peak_usage(true) - $before;

    expect($delta)->toBeLessThan(8 * 1024 * 1024);
});

it('indexes an existing disk file idempotently', function () {
    Storage::fake('public');
    Storage::disk('public')->put('legacy/logo.png', 'binary');

    $service = app(MediaService::class);

    $first = $service->indexFromDisk('public', 'legacy/logo.png');
    $second = $service->indexFromDisk('public', 'legacy/logo.png');

    expect($first->id)->toBe($second->id)
        ->and($first->directory)->toBe('legacy')
        ->and(Media::count())->toBe(1);
});

it('deletes the row and the object on the disk together', function () {
    Storage::fake('public');

    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('x.png'), 'public');
    $path = $media->path;

    $service->delete($media);

    Storage::disk('public')->assertMissing($path);
    expect(Media::count())->toBe(0);
});

it('keeps the url and path stable when renaming, so existing references survive', function () {
    Storage::fake('public');

    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('original.png'), 'public');

    $path = $media->path;
    $url = $media->publicUrl();

    $service->rename($media, 'A Friendlier Name.png');

    expect($media->fresh()->name)->toBe('A Friendlier Name.png')
        ->and($media->fresh()->path)->toBe($path)
        ->and($media->fresh()->publicUrl())->toBe($url);

    Storage::disk('public')->assertExists($path);
});
