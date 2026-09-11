<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionlessUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
    config()->set('filament-media-library.conversions.sizes', ['thumb' => 50]);
});

function editable(): Media
{
    return app(MediaService::class)->store(
        UploadedFile::fake()->image('photo.jpg', 600, 400),
        'public',
        'photos',
    );
}

it('replaces the image in place, keeping the url and rebuilding variants', function () {
    $media = editable()->fresh();

    $path = $media->path;
    $url = $media->publicUrl();
    $originalBytes = Storage::disk('public')->get($path);

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->post("/media-library/{$media->id}/image", [
            // What the canvas sends back: a complete, re-encoded image.
            'image' => UploadedFile::fake()->image('edited.jpg', 300, 200),
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $fresh = $media->fresh();

    expect($fresh->path)->toBe($path)
        ->and($fresh->publicUrl())->toBe($url)
        ->and($fresh->width)->toBe(300)
        ->and($fresh->height)->toBe(200)
        ->and(Storage::disk('public')->get($path))->not->toBe($originalBytes)
        ->and(Media::count())->toBe(1);
});

it('refuses anything that is not a browser-encodable image', function () {
    $media = editable();

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson("/media-library/{$media->id}/image", [
            'image' => UploadedFile::fake()->create('payload.pdf', 4, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

it('refuses to edit a provider-backed item', function () {
    $media = Media::create([
        'disk' => 'bunny-stream', 'provider' => 'bunny-stream', 'external_id' => 'x',
        'path' => 'x', 'name' => 'v.mp4', 'kind' => 'video', 'size' => 1,
    ]);

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson("/media-library/{$media->id}/image", [
            'image' => UploadedFile::fake()->image('a.jpg', 10, 10),
        ])
        ->assertStatus(422);
});

it('requires authentication', function () {
    $media = editable();

    $this->postJson("/media-library/{$media->id}/image", [])->assertStatus(401);
});

it('requires the manage permission when permissions are on', function () {
    config()->set('filament-media-library.permissions.enabled', true);

    $media = editable();

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson("/media-library/{$media->id}/image", [
            'image' => UploadedFile::fake()->image('a.jpg', 10, 10),
        ])
        ->assertForbidden();
});

it('only offers the editor for formats a canvas can re-encode', function () {
    $jpeg = Media::create(['disk' => 'public', 'path' => 'a.jpg', 'name' => 'a.jpg', 'kind' => 'image', 'mime_type' => 'image/jpeg', 'size' => 1]);
    $svg = Media::create(['disk' => 'public', 'path' => 'a.svg', 'name' => 'a.svg', 'kind' => 'image', 'mime_type' => 'image/svg+xml', 'size' => 1]);
    $heic = Media::create(['disk' => 'public', 'path' => 'a.heic', 'name' => 'a.heic', 'kind' => 'image', 'mime_type' => 'image/heic', 'size' => 1]);

    expect($jpeg->isEditableImage())->toBeTrue()
        ->and($svg->isEditableImage())->toBeFalse()
        ->and($heic->isEditableImage())->toBeFalse();
});
