<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\ImageOptimizer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('cdn');

    config()->set('filament-media-library.url_resolvers', [
        'cdn' => fn (string $path): string => 'https://cdn.example.test/'.ltrim($path, '/'),
    ]);
});

function optimizable(): Media
{
    return Media::create([
        'disk' => 'cdn',
        'path' => 'photos/hero.jpg',
        'name' => 'hero.jpg',
        'kind' => 'image',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
        'width' => 4000,
    ]);
}

it('leaves urls alone when no optimizer is configured', function () {
    expect(app(ImageOptimizer::class)->isEnabled())->toBeFalse()
        ->and(optimizable()->optimizedUrl(width: 800))->toBeNull();
});

it('appends bunny optimizer parameters', function () {
    config()->set('filament-media-library.optimizer.driver', 'bunny');
    config()->set('filament-media-library.optimizer.quality', 75);

    expect(optimizable()->optimizedUrl(width: 800))
        ->toBe('https://cdn.example.test/photos/hero.jpg?width=800&quality=75');
});

it('builds a cloudflare images path segment', function () {
    config()->set('filament-media-library.optimizer.driver', 'cloudflare');
    config()->set('filament-media-library.optimizer.quality', 80);
    config()->set('filament-media-library.optimizer.format', 'webp');

    expect(optimizable()->optimizedUrl(width: 640))
        ->toBe('https://cdn.example.test/cdn-cgi/image/width=640,quality=80,format=webp/photos/hero.jpg');
});

it('rewrites through a glide route', function () {
    config()->set('filament-media-library.optimizer.driver', 'glide');
    config()->set('filament-media-library.optimizer.quality', 70);
    config()->set('filament-media-library.optimizer.glide_path', '/images');

    expect(optimizable()->optimizedUrl(width: 400, height: 300))
        ->toBe('/images/photos/hero.jpg?w=400&h=300&q=70');
});

it('uses the optimizer for thumbnails instead of a generated variant', function () {
    config()->set('filament-media-library.optimizer.driver', 'bunny');

    expect(optimizable()->thumbnailUrl())->toContain('width=320');
});

it('never optimizes a non-image or a provider-backed item', function () {
    config()->set('filament-media-library.optimizer.driver', 'bunny');

    $pdf = Media::create([
        'disk' => 'cdn', 'path' => 'a.pdf', 'name' => 'a.pdf', 'kind' => 'pdf', 'size' => 1,
    ]);

    $video = Media::create([
        'disk' => 'bunny-stream', 'provider' => 'bunny-stream', 'external_id' => 'x',
        'path' => 'x', 'name' => 'v.mp4', 'kind' => 'video', 'size' => 1,
    ]);

    expect($pdf->optimizedUrl(width: 100))->toBeNull()
        ->and($video->optimizedUrl(width: 100))->toBeNull();
});

it('ignores an unknown driver rather than mangling the url', function () {
    config()->set('filament-media-library.optimizer.driver', 'something-else');

    expect(optimizable()->optimizedUrl(width: 800))->toBe('https://cdn.example.test/photos/hero.jpg');
});
