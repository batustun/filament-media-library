<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Providers\BunnyStreamProvider;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

const GUID = '8f2c1e4a-0000-4000-8000-abcdef123456';

beforeEach(function () {
    config()->set('filament-media-library.providers.bunny-stream', [
        'enabled' => true,
        'driver' => BunnyStreamProvider::class,
        'library_id' => '123456',
        'api_key' => 'test-access-key',
        'pull_zone' => 'vz-example.b-cdn.net',
        'webhook_secret' => 'sh4red-s3cret',
        'timeout' => 10,
    ]);

    app(MediaProviderRegistry::class)->flush();
});

function bunnyVideo(array $overrides = []): array
{
    return array_merge([
        'guid' => GUID,
        'title' => 'clip.mp4',
        'status' => 4,
        'encodeProgress' => 100,
        'length' => 92,
        'width' => 1920,
        'height' => 1080,
        'thumbnailFileName' => 'thumbnail.jpg',
        'availableResolutions' => '360p,720p,1080p',
        'storageSize' => 1048576,
    ], $overrides);
}

it('is only offered once it is actually configured', function () {
    expect(app(MediaProviderRegistry::class)->has('bunny-stream'))->toBeTrue();

    config()->set('filament-media-library.providers.bunny-stream.api_key', null);
    app(MediaProviderRegistry::class)->flush();

    expect(app(MediaProviderRegistry::class)->has('bunny-stream'))->toBeFalse();
});

it('creates the video then streams the bytes into it', function () {
    Http::fake([
        'video.bunnycdn.com/library/123456/videos' => Http::response(bunnyVideo(['status' => 0]), 200),
        'video.bunnycdn.com/library/123456/videos/*' => Http::response(['success' => true], 200),
    ]);

    $media = app(MediaService::class)->storeToProvider(
        app(MediaProviderRegistry::class)->get('bunny-stream'),
        UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
    );

    expect($media->provider)->toBe('bunny-stream')
        ->and($media->external_id)->toBe(GUID)
        ->and($media->kind)->toBe('video')
        ->and($media->path)->toBe(GUID);

    // Bunny requires exactly this two-step handshake.
    Http::assertSentInOrder([
        fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://video.bunnycdn.com/library/123456/videos'
            && $request->header('AccessKey') === ['test-access-key']
            && $request['title'] === 'clip.mp4',
        fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://video.bunnycdn.com/library/123456/videos/'.GUID,
    ]);
});

it('does not leave an orphaned video behind when the upload fails', function () {
    Http::fake([
        'video.bunnycdn.com/library/123456/videos' => Http::response(bunnyVideo(['status' => 0]), 200),
        'video.bunnycdn.com/library/123456/videos/*' => Http::sequence()
            ->push(['error' => 'too large'], 413)
            ->push(['success' => true], 200),
    ]);

    expect(fn () => app(MediaService::class)->storeToProvider(
        app(MediaProviderRegistry::class)->get('bunny-stream'),
        UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
    ))->toThrow(RuntimeException::class);

    expect(Media::count())->toBe(0);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://video.bunnycdn.com/library/123456/videos/'.GUID);
});

it('reports a video as not ready until transcoding finishes', function () {
    $media = providerMedia(['meta' => ['status' => 3]]);

    expect($media->isReady())->toBeFalse();

    $media->forceFill(['meta' => ['status' => 4]])->save();

    expect($media->fresh()->isReady())->toBeTrue();
});

it('serves the hls manifest and poster from the pull zone', function () {
    $media = providerMedia(['meta' => ['status' => 4, 'thumbnail_file_name' => 'thumbnail.jpg']]);

    expect($media->publicUrl())->toBe('https://vz-example.b-cdn.net/'.GUID.'/playlist.m3u8')
        ->and($media->posterUrl())->toBe('https://vz-example.b-cdn.net/'.GUID.'/thumbnail.jpg')
        ->and($media->thumbnailUrl())->toBe('https://vz-example.b-cdn.net/'.GUID.'/thumbnail.jpg');
});

it('falls back to the hosted embed player when no pull zone is configured', function () {
    config()->set('filament-media-library.providers.bunny-stream.pull_zone', null);
    app(MediaProviderRegistry::class)->flush();

    $media = providerMedia();

    expect($media->publicUrl())->toBe('https://iframe.mediadelivery.net/embed/123456/'.GUID)
        ->and($media->posterUrl())->toBeNull();
});

it('accepts a pull zone written with or without a scheme', function (string $zone) {
    config()->set('filament-media-library.providers.bunny-stream.pull_zone', $zone);
    app(MediaProviderRegistry::class)->flush();

    expect(providerMedia()->publicUrl())->toBe('https://vz-example.b-cdn.net/'.GUID.'/playlist.m3u8');
})->with(['vz-example.b-cdn.net', 'https://vz-example.b-cdn.net/', '//vz-example.b-cdn.net']);

it('reads length and resolution back from the platform', function () {
    Http::fake(['video.bunnycdn.com/library/123456/videos/*' => Http::response(bunnyVideo(), 200)]);

    $media = app(MediaService::class)->refreshFromProvider(providerMedia(['meta' => ['status' => 3]]));

    expect($media->duration)->toBe(92)
        ->and($media->width)->toBe(1920)
        ->and($media->height)->toBe(1080)
        ->and($media->meta['status'])->toBe(4)
        ->and($media->meta['available_resolutions'])->toBe('360p,720p,1080p');
});

it('deletes the video on the platform when the item is removed', function () {
    Http::fake(['video.bunnycdn.com/library/123456/videos/*' => Http::response([], 200)]);

    app(MediaService::class)->delete(providerMedia());

    expect(Media::count())->toBe(0);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});

it('treats an already-deleted video as deleted', function () {
    Http::fake(['video.bunnycdn.com/library/123456/videos/*' => Http::response(['error' => 'not found'], 404)]);

    expect(app(MediaProviderRegistry::class)->get('bunny-stream')->delete(providerMedia()))->toBeTrue();
});

it('updates an item when the platform posts a status webhook', function () {
    Http::fake(['video.bunnycdn.com/library/123456/videos/*' => Http::response(bunnyVideo(), 200)]);

    $media = providerMedia(['meta' => ['status' => 3]]);

    $this->postJson('/media-library/webhooks/bunny-stream?secret=sh4red-s3cret', [
        'VideoLibraryId' => 123456,
        'VideoGuid' => GUID,
        'Status' => 4,
    ])->assertOk();

    expect($media->fresh()->duration)->toBe(92)
        ->and($media->fresh()->meta['status'])->toBe(4);
});

it('refuses a webhook with a wrong or missing secret', function () {
    providerMedia();

    $this->postJson('/media-library/webhooks/bunny-stream?secret=guessing', ['VideoGuid' => GUID])
        ->assertForbidden();

    $this->postJson('/media-library/webhooks/bunny-stream', ['VideoGuid' => GUID])
        ->assertForbidden();
});

it('refuses every webhook when no secret is configured at all', function () {
    config()->set('filament-media-library.providers.bunny-stream.webhook_secret', null);

    $this->postJson('/media-library/webhooks/bunny-stream?secret=', ['VideoGuid' => GUID])
        ->assertForbidden();
});

it('acknowledges a webhook for a video it does not know', function () {
    $this->postJson('/media-library/webhooks/bunny-stream?secret=sh4red-s3cret', [
        'VideoGuid' => 'not-ours',
    ])->assertOk()->assertJson(['known' => false]);
});

it('appears in the browser source list beside the disks', function () {
    $page = new class
    {
        use InteractsWithMediaBrowser;
    };

    expect($page->availableSources())->toContain('public', 'bunny-stream')
        ->and($page->sourceOptions()['bunny-stream'])->toBe('Bunny Stream');
});

function providerMedia(array $overrides = []): Media
{
    return Media::create(array_merge([
        'disk' => 'bunny-stream',
        'provider' => 'bunny-stream',
        'external_id' => GUID,
        'path' => GUID,
        'name' => 'clip.mp4',
        'kind' => 'video',
        'mime_type' => 'video/mp4',
        'size' => 1048576,
        'meta' => ['status' => 4],
    ], $overrides));
}
