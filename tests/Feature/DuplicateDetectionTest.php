<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('recognises a byte-identical file already on the disk', function () {
    $service = app(MediaService::class);

    $first = $service->store(
        UploadedFile::fake()->createWithContent('one.txt', 'identical bytes'),
        'public',
    );

    $again = UploadedFile::fake()->createWithContent('two.txt', 'identical bytes');

    expect($service->findDuplicate('public', $service->hashFor($again))?->id)->toBe($first->id);
});

it('does not treat different content as a duplicate', function () {
    $service = app(MediaService::class);

    $service->store(UploadedFile::fake()->createWithContent('a.txt', 'one'), 'public');

    $other = UploadedFile::fake()->createWithContent('b.txt', 'two');

    expect($service->findDuplicate('public', $service->hashFor($other)))->toBeNull();
});

it('scopes duplicate detection to a single disk', function () {
    Storage::fake('cdn');

    $service = app(MediaService::class);
    $service->store(UploadedFile::fake()->createWithContent('a.txt', 'shared'), 'public');

    $other = UploadedFile::fake()->createWithContent('a.txt', 'shared');

    expect($service->findDuplicate('cdn', $service->hashFor($other)))->toBeNull();
});

it('finds nothing when hashing is switched off', function () {
    config()->set('filament-media-library.hash_uploads', false);

    $service = app(MediaService::class);
    $service->store(UploadedFile::fake()->createWithContent('a.txt', 'x'), 'public');

    $other = UploadedFile::fake()->createWithContent('b.txt', 'x');

    expect($service->hashFor($other))->toBeNull()
        ->and($service->findDuplicate('public', null))->toBeNull();
});

it('returns the oldest match, so one asset keeps one canonical url', function () {
    config()->set('filament-media-library.duplicates', 'allow');

    $service = app(MediaService::class);

    $first = $service->store(UploadedFile::fake()->createWithContent('a.txt', 'same'), 'public');
    $second = $service->store(UploadedFile::fake()->createWithContent('b.txt', 'same'), 'public');

    // Make the ordering unambiguous rather than depending on sub-second
    // timestamps happening to differ.
    $first->forceFill(['created_at' => now()->subHour()])->save();
    $second->forceFill(['created_at' => now()])->save();

    expect(Media::count())->toBe(2)
        ->and($service->findDuplicate('public', $first->hash)?->id)->toBe($first->id);
});
