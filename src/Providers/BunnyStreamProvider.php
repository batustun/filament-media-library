<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Providers;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bunny Stream — video upload, transcoding and adaptive playback.
 *
 * Note this is Bunny *Stream*, not Bunny *Storage*. Storage is object storage
 * and is used through an ordinary Flysystem disk; Stream is a transcoding
 * platform whose assets have a GUID rather than a path, several renditions
 * rather than one file, and a status that only becomes "playable" some time
 * after the upload returns. That is why it lives behind MediaProvider.
 *
 * Upload is two calls, which is what Bunny's API requires:
 *   1. POST   /library/{id}/videos          → creates the record, returns a guid
 *   2. PUT    /library/{id}/videos/{guid}   → streams the bytes into it
 */
class BunnyStreamProvider implements MediaProvider
{
    public const KEY = 'bunny-stream';

    private const API = 'https://video.bunnycdn.com';

    private const EMBED = 'https://iframe.mediadelivery.net/embed';

    /** Bunny's numeric encode states. */
    private const STATUS_FINISHED = 4;

    private const STATUS_FAILED = [5, 6];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Bunny Stream';
    }

    public function isConfigured(): bool
    {
        return $this->libraryId() !== '' && $this->apiKey() !== '';
    }

    // -----------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $options
     * @return array{external_id: string, meta: array<string, mixed>, duration: int|null, width: int|null, height: int|null}
     */
    public function upload(UploadedFile|File $file, array $options = []): array
    {
        $title = (string) ($options['title'] ?? (
            $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file->getPathname())
        ));

        $created = $this->request()
            ->post($this->url('videos'), array_filter([
                'title' => $title,
                'collectionId' => $options['collection'] ?? null,
            ], static fn (mixed $value): bool => $value !== null));

        if (! $created->successful()) {
            throw new RuntimeException("Bunny Stream refused to create the video: HTTP {$created->status()}.");
        }

        $guid = (string) ($created->json('guid') ?? '');

        if ($guid === '') {
            throw new RuntimeException('Bunny Stream did not return a video guid.');
        }

        $handle = fopen((string) $file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the video for upload.');
        }

        try {
            // Wrapped as a PSR-7 stream rather than passed as a raw resource:
            // the HTTP client only accepts string|StreamInterface, and a string
            // would pull a multi-gigabyte master into memory.
            $uploaded = $this->request()
                ->withBody(Utils::streamFor($handle), 'application/octet-stream')
                ->put($this->url("videos/{$guid}"));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $uploaded->successful()) {
            // Do not leave an empty video record behind on the platform.
            $this->request()->delete($this->url("videos/{$guid}"));

            throw new RuntimeException("Bunny Stream rejected the upload: HTTP {$uploaded->status()}.");
        }

        return [
            'external_id' => $guid,
            'meta' => $this->metaFrom($created->json() ?? []) + ['title' => $title],
            'duration' => null,
            'width' => null,
            'height' => null,
        ];
    }

    /**
     * @return array{meta: array<string, mixed>, duration: int|null, width: int|null, height: int|null}
     */
    public function refresh(Media $media): array
    {
        $guid = (string) $media->external_id;

        if ($guid === '') {
            return ['meta' => [], 'duration' => null, 'width' => null, 'height' => null];
        }

        $response = $this->request()->get($this->url("videos/{$guid}"));

        if (! $response->successful()) {
            return ['meta' => [], 'duration' => null, 'width' => null, 'height' => null];
        }

        $payload = $response->json() ?? [];

        return [
            'meta' => $this->metaFrom($payload),
            // Bunny reports length in whole seconds.
            'duration' => isset($payload['length']) ? (int) $payload['length'] : null,
            'width' => isset($payload['width']) ? (int) $payload['width'] : null,
            'height' => isset($payload['height']) ? (int) $payload['height'] : null,
        ];
    }

    public function delete(Media $media): bool
    {
        $guid = (string) $media->external_id;

        if ($guid === '') {
            return true;
        }

        $response = $this->request()->delete($this->url("videos/{$guid}"));

        // A video that is already gone is a success, not a failure.
        return $response->successful() || $response->status() === 404;
    }

    // -----------------------------------------------------------------
    // URLs
    // -----------------------------------------------------------------

    /**
     * The HLS manifest when a pull zone is configured, otherwise the hosted
     * embed player — which always works, even without a pull zone.
     */
    public function playbackUrl(Media $media): ?string
    {
        $guid = (string) $media->external_id;

        if ($guid === '') {
            return null;
        }

        $pullZone = $this->pullZone();

        return $pullZone === ''
            ? $this->embedUrl($media)
            : "{$pullZone}/{$guid}/playlist.m3u8";
    }

    public function embedUrl(Media $media): ?string
    {
        $guid = (string) $media->external_id;

        return $guid === '' ? null : self::EMBED."/{$this->libraryId()}/{$guid}";
    }

    public function posterUrl(Media $media): ?string
    {
        $guid = (string) $media->external_id;
        $pullZone = $this->pullZone();

        if ($guid === '' || $pullZone === '') {
            return null;
        }

        $thumbnail = (string) ($media->meta['thumbnail_file_name'] ?? 'thumbnail.jpg');

        return "{$pullZone}/{$guid}/{$thumbnail}";
    }

    public function isReady(Media $media): bool
    {
        return (int) ($media->meta['status'] ?? -1) === self::STATUS_FINISHED;
    }

    public function hasFailed(Media $media): bool
    {
        return in_array((int) ($media->meta['status'] ?? -1), self::STATUS_FAILED, true);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Normalise Bunny's PascalCase payload into the snake_case subset the
     * library actually uses, so the rest of the package never has to know the
     * vendor's field names.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metaFrom(array $payload): array
    {
        return array_filter([
            'status' => isset($payload['status']) ? (int) $payload['status'] : null,
            'encode_progress' => isset($payload['encodeProgress']) ? (int) $payload['encodeProgress'] : null,
            'thumbnail_file_name' => $payload['thumbnailFileName'] ?? null,
            'available_resolutions' => $payload['availableResolutions'] ?? null,
            'storage_size' => isset($payload['storageSize']) ? (int) $payload['storageSize'] : null,
            'library_id' => $this->libraryId(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'AccessKey' => $this->apiKey(),
            'accept' => 'application/json',
        ])->timeout($this->setting('timeout', 120));
    }

    private function url(string $path): string
    {
        return self::API."/library/{$this->libraryId()}/{$path}";
    }

    private function libraryId(): string
    {
        return (string) $this->setting('library_id', '');
    }

    private function apiKey(): string
    {
        return (string) $this->setting('api_key', '');
    }

    private function pullZone(): string
    {
        $zone = trim((string) $this->setting('pull_zone', ''));

        if ($zone === '') {
            return '';
        }

        // Accept "vz-x.b-cdn.net", "//vz-x.b-cdn.net" and
        // "https://vz-x.b-cdn.net/" alike — operators copy the hostname out of
        // the Bunny dashboard in all three shapes.
        if (! str_contains($zone, '://')) {
            $zone = 'https://'.ltrim($zone, '/');
        }

        return rtrim($zone, '/');
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return MediaLibraryConfig::provider(self::KEY)[$key] ?? $default;
    }
}
