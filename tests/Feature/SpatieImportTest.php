<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    // A minimal stand-in for spatie/laravel-medialibrary's table, so the
    // importer is exercised against the real column layout it reads.
    Schema::create('media', function (Blueprint $table): void {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->unsignedBigInteger('size');
        $table->json('custom_properties')->nullable();
        $table->timestamps();
    });
});

afterEach(fn () => Schema::dropIfExists('media'));

function spatieRow(array $overrides = []): int
{
    return DB::table('media')->insertGetId(array_merge([
        'model_type' => 'App\\Models\\Post',
        'model_id' => 7,
        'collection_name' => 'cover',
        'name' => 'Hero image',
        'file_name' => 'hero.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 204800,
        'custom_properties' => json_encode(['alt' => 'A hero']),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('imports spatie media, rebuilding the id-based path', function () {
    $id = spatieRow();

    $this->artisan('media-library:import-spatie')->assertSuccessful();

    $media = Media::sole();

    expect($media->path)->toBe("{$id}/hero.jpg")
        ->and($media->directory)->toBe((string) $id)
        ->and($media->disk)->toBe('public')
        ->and($media->title)->toBe('Hero image')
        ->and($media->kind)->toBe('image')
        ->and($media->size)->toBe(204800)
        ->and($media->meta['spatie_media_id'])->toBe($id)
        ->and($media->meta['spatie_collection'])->toBe('cover')
        ->and($media->meta['custom']['alt'])->toBe('A hero');
});

it('is safe to run twice', function () {
    spatieRow();

    $this->artisan('media-library:import-spatie')->assertSuccessful();
    $this->artisan('media-library:import-spatie')->assertSuccessful();

    expect(Media::count())->toBe(1);
});

it('writes nothing on a dry run', function () {
    spatieRow();

    $this->artisan('media-library:import-spatie', ['--dry-run' => true])->assertSuccessful();

    expect(Media::count())->toBe(0);
});

it('can be limited to one collection or disk', function () {
    spatieRow(['collection_name' => 'cover']);
    spatieRow(['collection_name' => 'gallery', 'file_name' => 'b.jpg']);
    spatieRow(['disk' => 's3', 'file_name' => 'c.jpg']);

    $this->artisan('media-library:import-spatie', ['--collection' => 'gallery'])->assertSuccessful();

    expect(Media::count())->toBe(1)
        ->and(Media::sole()->name)->toBe('b.jpg');
});

it('leaves the spatie records untouched', function () {
    spatieRow();

    $this->artisan('media-library:import-spatie')->assertSuccessful();

    expect(DB::table('media')->count())->toBe(1);
});

it('fails clearly when there is no spatie table', function () {
    Schema::drop('media');

    $this->artisan('media-library:import-spatie')->assertFailed();
});
