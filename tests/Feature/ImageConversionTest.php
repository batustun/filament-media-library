<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Jobs\GenerateImageConversions;
use Batustun\FilamentMediaLibrary\Services\ImageConversionService;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.conversions.sizes', ['thumb' => 100, 'medium' => 300]);
});

it('generates only the variants that are smaller than the original', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
        'photos',
    );

    $conversions = $media->fresh()->conversions();

    expect($conversions)->toHaveKeys(['thumb', 'medium'])
        ->and($conversions['thumb']['width'])->toBe(100)
        // The height follows the aspect ratio.
        ->and($conversions['thumb']['height'])->toBe(50);

    Storage::disk('public')->assertExists($conversions['thumb']['path']);
    Storage::disk('public')->assertExists($conversions['medium']['path']);
});

it('never upscales a small image', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('icon.png', 64, 64),
        'public',
    );

    expect($media->fresh()->conversions())->toBe([]);
});

it('keeps variants beside the original, under a conversions folder', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
        'photos/2026',
    );

    expect($media->fresh()->conversions()['thumb']['path'])
        ->toStartWith('photos/2026/conversions/');
});

it('serves the smallest variant as the thumbnail', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
    )->fresh();

    expect($media->thumbnailUrl())->toContain('-thumb.')
        ->and($media->thumbnailUrl())->not->toBe($media->publicUrl());
});

it('falls back to the original when there are no variants', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('icon.png', 64, 64),
        'public',
    )->fresh();

    expect($media->thumbnailUrl())->toBe($media->publicUrl())
        ->and($media->srcset())->toBeNull();
});

it('builds a srcset covering the variants and the original', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
    )->fresh();

    $srcset = $media->srcset();

    expect($srcset)->toContain('100w')
        ->and($srcset)->toContain('300w')
        ->and($srcset)->toContain('800w');
});

it('resolves variant urls through the same cdn resolver as the original', function () {
    config()->set('filament-media-library.url_resolvers', [
        'public' => fn (string $path): string => 'https://cdn.example.test/'.ltrim($path, '/'),
    ]);

    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
    )->fresh();

    expect($media->thumbnailUrl())->toStartWith('https://cdn.example.test/')
        ->and($media->conversionUrl('medium'))->toStartWith('https://cdn.example.test/');
});

it('removes the variants when the item is deleted', function () {
    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('wide.jpg', 800, 400), 'public')->fresh();

    $thumb = $media->conversions()['thumb']['path'];
    Storage::disk('public')->assertExists($thumb);

    $service->delete($media);

    Storage::disk('public')->assertMissing($thumb);
});

it('rebuilds the variants when the file is replaced', function () {
    $service = app(MediaService::class);
    $media = $service->store(UploadedFile::fake()->image('wide.jpg', 800, 400), 'public')->fresh();

    $before = $media->conversions()['thumb']['path'];

    $service->replace($media, UploadedFile::fake()->image('tall.jpg', 600, 900));

    $after = $media->fresh()->conversions();

    expect($after['thumb']['height'])->toBe(150)   // 100 wide, 3:2 portrait
        ->and(Storage::disk('public')->exists($before))->toBeTrue(); // same path, new bytes
});

it('queues generation instead of blocking the request when a queue is set', function () {
    Queue::fake();
    config()->set('filament-media-library.conversions.queue', 'media');

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('wide.jpg', 800, 400), 'public');

    Queue::assertPushed(GenerateImageConversions::class, fn ($job) => $job->media->is($media));

    expect($media->fresh()->conversions())->toBe([]);
});

it('skips formats gd cannot decode instead of failing', function () {
    $service = app(ImageConversionService::class);

    expect($service->isSupported('image/heic'))->toBeFalse()
        ->and($service->isSupported('image/svg+xml'))->toBeFalse()
        ->and($service->isSupported('application/pdf'))->toBeFalse()
        ->and($service->isSupported('image/jpeg'))->toBeTrue();

    // An SVG upload still succeeds; it simply gets no variants.
    $svg = app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'),
        'public',
    );

    expect($svg->fresh()->conversions())->toBe([]);
});

it('can be switched off entirely', function () {
    config()->set('filament-media-library.conversions.enabled', false);

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('wide.jpg', 800, 400), 'public');

    expect($media->fresh()->conversions())->toBe([]);
});
