<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Infolists\MediaEntry;
use Batustun\FilamentMediaLibrary\Filament\Tables\MediaColumn;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.conversions.sizes', ['thumb' => 100]);
});

it('resolves media from a uuid, a path or a url alike', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
        'covers',
    )->fresh();

    expect(MediaResolver::resolve($media->id)?->id)->toBe($media->id)
        ->and(MediaResolver::resolve($media->path, 'public')?->id)->toBe($media->id)
        ->and(MediaResolver::resolve($media->publicUrl(), 'public')?->id)->toBe($media->id);
});

it('returns null for state that points at nothing', function () {
    expect(MediaResolver::resolve(''))->toBeNull()
        ->and(MediaResolver::resolve(null))->toBeNull()
        ->and(MediaResolver::resolve('no/such/file.png', 'public'))->toBeNull();
});

it('serves the small variant for a table thumbnail', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->image('wide.jpg', 800, 400),
        'public',
    )->fresh();

    expect(MediaResolver::thumbnailUrl($media->id))->toContain('-thumb.');
});

it('passes an unknown absolute url straight through', function () {
    expect(MediaResolver::thumbnailUrl('https://elsewhere.test/a.png'))
        ->toBe('https://elsewhere.test/a.png');
});

it('exposes a table column and an infolist entry', function () {
    expect(MediaColumn::make('cover'))->toBeInstanceOf(MediaColumn::class)
        ->and(MediaColumn::make('cover')->mediaDisk('public')->getMediaDisk())->toBe('public')
        ->and(MediaEntry::make('cover'))->toBeInstanceOf(MediaEntry::class)
        ->and(MediaEntry::make('cover')->mediaDisk('public')->getMediaDisk())->toBe('public');
});
