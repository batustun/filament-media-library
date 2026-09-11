<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Providers\Contracts;

use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

/**
 * A source of media that is NOT a filesystem.
 *
 * Video platforms (Bunny Stream, Mux, Cloudflare Stream) accept an upload,
 * transcode it asynchronously and then serve an adaptive manifest — there is
 * no path to list and no single file to fetch, so they cannot be modelled as a
 * Flysystem disk. A provider fills that gap: the library stores an
 * `external_id` instead of a `path`, and asks the provider for URLs and state.
 */
interface MediaProvider
{
    /** Stable key used in config, in the `provider` column and in the UI. */
    public function key(): string;

    /** Human label for the source picker. */
    public function label(): string;

    public function isConfigured(): bool;

    /**
     * Hand the file to the platform and return what the library should store.
     *
     * @param  array<string, mixed>  $options
     * @return array{external_id: string, meta: array<string, mixed>, duration?: int|null, width?: int|null, height?: int|null}
     */
    public function upload(UploadedFile|File $file, array $options = []): array;

    /**
     * Re-read the platform's state — transcoding finishes long after upload.
     *
     * @return array{meta: array<string, mixed>, duration?: int|null, width?: int|null, height?: int|null}
     */
    public function refresh(Media $media): array;

    public function delete(Media $media): bool;

    /** Adaptive manifest or embed URL used for playback. */
    public function playbackUrl(Media $media): ?string;

    /** Still image representing the asset. */
    public function posterUrl(Media $media): ?string;

    /** False while the platform is still transcoding. */
    public function isReady(Media $media): bool;
}
