<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\UploadGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('public'));

it('rejects a file larger than the configured limit', function () {
    // Regression: the limit used to hold on the JSON endpoint only, so the
    // in-panel uploader let anything through.
    config()->set('filament-media-library.max_upload_size_kb', 64);

    expect(fn () => app(MediaService::class)->store(
        UploadedFile::fake()->create('big.bin', 512),
        'public',
    ))->toThrow(ValidationException::class);
});

it('rejects a mime type outside the accepted list', function () {
    config()->set('filament-media-library.accepted_mime_types', ['image/png']);

    expect(fn () => app(MediaService::class)->store(
        UploadedFile::fake()->create('payload.pdf', 4, 'application/pdf'),
        'public',
    ))->toThrow(ValidationException::class);
});

it('accepts a file that satisfies both rules', function () {
    config()->set('filament-media-library.accepted_mime_types', ['image/*']);
    config()->set('filament-media-library.max_upload_size_kb', 4096);

    $media = app(MediaService::class)->store(UploadedFile::fake()->image('ok.png'), 'public');

    expect($media->kind)->toBe('image');
});

it('understands wildcard mime patterns the way an accept attribute does', function () {
    expect(UploadGuard::mimeMatches('image/png', ['image/*']))->toBeTrue()
        ->and(UploadGuard::mimeMatches('image/png', ['video/*']))->toBeFalse()
        ->and(UploadGuard::mimeMatches('application/pdf', ['application/pdf']))->toBeTrue()
        ->and(UploadGuard::mimeMatches('application/pdf', ['*/*']))->toBeTrue()
        ->and(UploadGuard::mimeMatches('text/plain', []))->toBeFalse();
});

it('imposes no limits when nothing is configured', function () {
    config()->set('filament-media-library.accepted_mime_types', []);
    config()->set('filament-media-library.max_upload_size_kb', 0);

    expect(UploadGuard::violationFor(UploadedFile::fake()->create('any.bin', 2048)))->toBeNull();
});
