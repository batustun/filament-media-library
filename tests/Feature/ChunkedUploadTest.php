<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionlessUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
});

/** Post one slice of a file. */
function sendChunk(string $uuid, int $index, int $total, string $name, string $bytes): TestResponse
{
    return test()->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson('/media-library/chunk', [
            'uuid' => $uuid,
            'index' => $index,
            'total' => $total,
            'name' => $name,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $bytes),
            'disk' => 'public',
            'directory' => 'big',
        ]);
}

it('reassembles a file from its parts, in order', function () {
    $uuid = (string) Str::uuid();
    $parts = ['alpha-', 'beta-', 'gamma'];

    foreach ($parts as $index => $bytes) {
        $response = sendChunk($uuid, $index, count($parts), 'movie.mp4', $bytes);

        if ($index + 1 < count($parts)) {
            $response->assertOk()->assertJson(['ok' => true, 'received' => $index + 1]);
        }
    }

    $media = Media::sole();

    expect($media->directory)->toBe('big')
        ->and(Storage::disk('public')->get($media->path))->toBe('alpha-beta-gamma');
});

it('cleans up the chunk directory once the file is assembled', function () {
    $uuid = (string) Str::uuid();

    sendChunk($uuid, 0, 1, 'one.bin', 'only part')->assertCreated();

    expect(is_dir(storage_path('app/filament-media-library-chunks/'.$uuid)))->toBeFalse();
});

it('rejects a blocked extension on the very first chunk', function () {
    $uuid = (string) Str::uuid();

    sendChunk($uuid, 0, 3, 'shell.php', 'nope')->assertStatus(422);

    expect(Media::count())->toBe(0);
});

it('refuses an unauthenticated request', function () {
    $this->postJson('/media-library/chunk', [])->assertStatus(401);
});

it('validates the upload envelope', function () {
    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson('/media-library/chunk', [
            'uuid' => 'not-a-uuid',
            'index' => -1,
            'total' => 0,
            'name' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['uuid', 'index', 'total', 'name', 'chunk']);
});

it('rejects a disk outside the allow-list', function () {
    config()->set('filament-media-library.disks', ['public']);

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->postJson('/media-library/chunk', [
            'uuid' => (string) Str::uuid(),
            'index' => 0,
            'total' => 1,
            'name' => 'a.bin',
            'chunk' => UploadedFile::fake()->createWithContent('chunk', 'x'),
            'disk' => 'somewhere-else',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['disk']);
});

it('can be switched off entirely', function () {
    config()->set('filament-media-library.chunked_uploads.enabled', false);

    sendChunk((string) Str::uuid(), 0, 1, 'a.bin', 'x')->assertNotFound();
});

it('removes abandoned chunk directories', function () {
    $stale = storage_path('app/filament-media-library-chunks/'.Str::uuid());
    $fresh = storage_path('app/filament-media-library-chunks/'.Str::uuid());

    File::ensureDirectoryExists($stale, 0755, true);
    File::ensureDirectoryExists($fresh, 0755, true);
    touch($stale, now()->subDays(3)->getTimestamp());

    $this->artisan('media-library:clean-chunks', ['--hours' => 24])->assertSuccessful();

    expect(is_dir($stale))->toBeFalse()
        ->and(is_dir($fresh))->toBeTrue();

    File::deleteDirectory(storage_path('app/filament-media-library-chunks'));
});
