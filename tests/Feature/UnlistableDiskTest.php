<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Exceptions\CannotListDisk;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Illuminate\Filesystem\FilesystemAdapter as Filesystem;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToListContents;

/**
 * A disk can refuse to enumerate: placeholder credentials, an adapter with no
 * deep listing, a bucket too large to walk. That must read as a clear message,
 * not as a Flysystem stack trace.
 */
beforeEach(function () {
    Storage::fake('public');

    // A real disk whose adapter refuses to enumerate, which is what a wrong
    // API key or an adapter without deep listing actually looks like.
    config()->set('filesystems.disks.broken', ['driver' => 'unlistable']);

    Storage::extend('unlistable', function (): Filesystem {
        $adapter = new class extends InMemoryFilesystemAdapter
        {
            public function listContents(string $path, bool $deep): iterable
            {
                throw UnableToListContents::atLocation(
                    $path,
                    $deep,
                    new RuntimeException('Client error: 401 Unauthorized'),
                );
            }
        };

        return new Filesystem(new FlysystemFilesystem($adapter), $adapter);
    });
});

it('reports a disk that cannot be listed instead of unrolling a stack trace', function () {
    expect(fn () => app(MediaIndexer::class)->sync('broken'))
        ->toThrow(CannotListDisk::class);
});

it('carries the disk name and a readable reason', function () {
    try {
        app(MediaIndexer::class)->sync('broken');
    } catch (CannotListDisk $e) {
        expect($e->disk)->toBe('broken')
            ->and($e->getMessage())->toContain('[broken]')
            // Flysystem's own wrapping and the HTTP body are stripped away.
            ->and($e->reason())->toContain('401 Unauthorized')
            ->and($e->reason())->not->toContain('Unable to list contents');

        return;
    }

    $this->fail('Expected CannotListDisk.');
});

it('fails the sync command cleanly rather than crashing', function () {
    $this->artisan('media-library:sync', ['--disk' => 'broken'])
        ->assertFailed();
});

it('leaves a listable disk untouched', function () {
    Storage::disk('public')->put('a.txt', 'x');

    $stats = app(MediaIndexer::class)->sync('public');

    expect($stats['indexed'])->toBe(1)
        ->and(Media::count())->toBe(1);
});
