<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Enums\MediaKind;
use Batustun\FilamentMediaLibrary\Jobs\GenerateImageConversions;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Support\FileInspector;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MediaPath;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Batustun\FilamentMediaLibrary\Support\UploadGuard;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * All disk I/O goes through here, via Flysystem only — so the same code path
 * serves "public"/"local", S3, MinIO, DigitalOcean Spaces, BunnyCDN, FTP/SFTP
 * and anything else registered in config/filesystems.php.
 */
class MediaService
{
    public function __construct(private readonly MediaWriter $writer) {}

    public function defaultDisk(): string
    {
        return MediaLibraryConfig::defaultDisk();
    }

    /**
     * Public URL for a path on a disk. Never throws: disks with no public URL
     * simply fall back to the path.
     */
    public function resolveUrl(string $disk, string $path): string
    {
        if ($resolver = MediaLibraryConfig::urlResolver($disk)) {
            try {
                $resolved = $resolver($path);

                if (is_string($resolved) && $resolved !== '') {
                    return $resolved;
                }
            } catch (Throwable) {
                // fall through
            }
        }

        try {
            $url = Storage::disk($disk)->url($path);

            if ($url !== '') {
                return $url;
            }
        } catch (Throwable) {
            // Disk has no public URL configured.
        }

        return $path;
    }

    /**
     * Stream an uploaded file onto a disk and index it.
     *
     * The file is handed to Flysystem as a stream (putFileAs) rather than read
     * into a PHP string, so a 512 MB upload costs a constant few kilobytes of
     * memory instead of 512 MB — which is what makes large S3/BunnyCDN
     * uploads viable at all.
     */
    public function store(
        UploadedFile|File $file,
        ?string $disk = null,
        ?string $directory = null,
        ?string $filename = null,
        int|string|null $userId = null,
    ): Media {
        $disk ??= $this->defaultDisk();
        $directory = $this->normalizeDirectory($directory ?? MediaLibraryConfig::defaultDirectory());

        $originalName = $file instanceof UploadedFile
            ? $file->getClientOriginalName()
            : basename($file->getPathname());

        $mimeType = $file->getMimeType() ?: null;

        $extension = $file instanceof UploadedFile
            ? strtolower($file->getClientOriginalExtension())
            : strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = MediaPath::guessExtensionFromMime($mimeType);
        }

        $filename ??= MediaPath::buildFilename($originalName, $extension);

        UploadGuard::assertAcceptable($file);

        $path = $this->writer->write($disk, $directory, $filename, $file, $mimeType);

        [$width, $height] = FileInspector::readImageDimensions($file, $mimeType);

        $media = Media::create([
            'disk' => $disk,
            'directory' => $directory !== '' ? $directory : null,
            'path' => $path,
            'url' => MediaLibraryConfig::persistUrl() ? $this->resolveUrl($disk, $path) : null,
            'name' => $filename,
            'title' => null,
            'mime_type' => $mimeType,
            'kind' => MimeKindResolver::fromMime($mimeType, $filename)->value,
            'size' => (int) (Storage::disk($disk)->size($path) ?: $file->getSize() ?: 0),
            'width' => $width,
            'height' => $height,
            'duration' => null,
            'hash' => $this->hashFor($file),
            'meta' => $this->readExif($file, $mimeType),
            'uploaded_by' => $userId ?? Auth::id(),
        ]);

        $this->dispatchConversions($media);

        return $media;
    }

    /**
     * Hand a file to a media provider (a video platform) instead of a disk.
     *
     * The row carries `provider` + `external_id` rather than a path, and every
     * URL is asked of the platform at read time.
     *
     * @param  array<string, mixed>  $options
     */
    public function storeToProvider(
        MediaProvider $provider,
        UploadedFile|File $file,
        int|string|null $userId = null,
        array $options = [],
    ): Media {
        UploadGuard::assertAcceptable($file);

        $name = $file instanceof UploadedFile
            ? $file->getClientOriginalName()
            : basename($file->getPathname());

        $result = $provider->upload($file, $options + ['title' => $name]);

        return Media::create([
            'disk' => $provider->key(),
            'provider' => $provider->key(),
            'external_id' => $result['external_id'],
            'directory' => null,
            'path' => $result['external_id'],
            'url' => null,
            'name' => $name,
            'mime_type' => $file->getMimeType() ?: null,
            'kind' => MediaKind::Video->value,
            'size' => (int) ($file->getSize() ?: 0),
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
            'duration' => $result['duration'] ?? null,
            'hash' => $this->hashFor($file),
            'meta' => $result['meta'],
            'uploaded_by' => $userId ?? Auth::id(),
        ]);
    }

    /** Re-read a provider-backed item's state, e.g. after transcoding. */
    public function refreshFromProvider(Media $media): Media
    {
        $provider = $media->mediaProvider();

        if ($provider === null) {
            return $media;
        }

        $result = $provider->refresh($media);

        $media->forceFill(array_filter([
            'meta' => [...($media->meta ?? []), ...$result['meta']],
            'duration' => $result['duration'] ?? $media->duration,
            'width' => $result['width'] ?? $media->width,
            'height' => $result['height'] ?? $media->height,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return $media;
    }

    public function delete(Media $media, bool $purgeDisk = true): bool
    {
        if ($provider = $media->mediaProvider()) {
            if ($purgeDisk) {
                $provider->delete($media);
            }

            return (bool) $media->delete();
        }

        if ($purgeDisk) {
            // Variants live beside the original and would otherwise be orphaned.
            app(ImageConversionService::class)->purge($media);

            try {
                Storage::disk($media->disk)->delete($media->path);
            } catch (Throwable $e) {
                Log::warning('filament-media-library: disk delete failed', [
                    'disk' => $media->disk,
                    'path' => $media->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return (bool) $media->delete();
    }

    /**
     * Rename the display name only.
     *
     * The on-disk object and its public URL are deliberately left untouched so
     * that everything already referencing the URL (blog posts, rich-editor
     * content, logos) keeps resolving. Use move() to relocate the object.
     */
    public function rename(Media $media, string $newName): Media
    {
        $newName = trim($newName);

        if ($newName === '' || $newName === $media->name) {
            return $media;
        }

        $media->forceFill(['name' => $newName])->save();

        return $media;
    }

    /** Physically relocate the object on its disk. */
    public function move(Media $media, ?string $newDirectory): Media
    {
        $newDirectory = $this->normalizeDirectory($newDirectory);
        $newPath = ltrim(($newDirectory === '' ? '' : $newDirectory.'/').$media->name, '/');

        if ($newPath === $media->path) {
            return $media;
        }

        Storage::disk($media->disk)->move($media->path, $newPath);

        $media->forceFill([
            'directory' => $newDirectory !== '' ? $newDirectory : null,
            'path' => $newPath,
            'url' => MediaLibraryConfig::persistUrl() ? $this->resolveUrl($media->disk, $newPath) : null,
        ])->save();

        return $media;
    }

    /** Index a file that already exists on a disk. Idempotent. */
    public function indexFromDisk(string $disk, string $path, int|string|null $userId = null): Media
    {
        $path = ltrim($path, '/');
        $name = basename($path);
        $directory = trim((string) dirname($path), '.\\/');

        $existing = Media::query()->where('disk', $disk)->where('path', $path)->first();

        if ($existing) {
            return $existing;
        }

        $size = 0;
        $mime = null;

        try {
            $size = (int) Storage::disk($disk)->size($path);
        } catch (Throwable) {
            // Adapter cannot report size.
        }

        try {
            // Several adapters (notably FTP-backed ones) do not implement this;
            // kind resolution then falls back to the file extension.
            $mime = Storage::disk($disk)->mimeType($path) ?: null;
        } catch (Throwable) {
            $mime = null;
        }

        return Media::create([
            'disk' => $disk,
            'directory' => $directory !== '' ? $directory : null,
            'path' => $path,
            'url' => MediaLibraryConfig::persistUrl() ? $this->resolveUrl($disk, $path) : null,
            'name' => $name,
            'mime_type' => $mime,
            'kind' => MimeKindResolver::fromMime($mime, $name)->value,
            'size' => $size,
            'meta' => ['indexed' => true],
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * SHA-256 of a file, read as a stream so a multi-gigabyte upload costs a
     * few kilobytes of memory rather than its own size.
     */
    public function hashFor(UploadedFile|File $file): ?string
    {
        return FileInspector::hashFor($file);
    }

    /**
     * An already-indexed, byte-identical file on the same disk.
     *
     * Reusing it instead of uploading a second copy keeps storage costs down
     * and — more importantly — keeps one canonical URL for one asset, so
     * replacing it later updates every page that references it.
     */
    public function findDuplicate(string $disk, ?string $hash): ?Media
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        return Media::query()
            ->onDisk($disk)
            ->withHash($hash)
            ->oldest('created_at')
            ->first();
    }

    /**
     * Overwrite the bytes behind an existing item, keeping its id, path and
     * public URL. Everything already pointing at this asset picks up the new
     * file with no edits.
     */
    public function replace(Media $media, UploadedFile|File $file): Media
    {
        UploadGuard::assertAcceptable($file);

        $mimeType = $file->getMimeType() ?: $media->mime_type;

        $this->writer->overwrite($media->disk, $media->path, $file, $mimeType, (string) $media->name);
        [$width, $height] = FileInspector::readImageDimensions($file, $mimeType);

        $media->forceFill([
            'mime_type' => $mimeType,
            'kind' => MimeKindResolver::fromMime($mimeType, $media->name)->value,
            'size' => (int) (Storage::disk($media->disk)->size($media->path) ?: $file->getSize() ?: 0),
            'width' => $width,
            'height' => $height,
            'hash' => $this->hashFor($file),
        ])->save();

        // The bytes changed, so the old variants no longer represent the file.
        app(ImageConversionService::class)->purge($media);
        $this->dispatchConversions($media);

        return $media;
    }

    /**
     * Copy an item to a new file and a new record.
     *
     * The copy gets its own name and path, so editing or deleting it cannot
     * touch the original — which is exactly what separates "duplicate" from
     * "replace".
     */
    public function duplicate(Media $media): Media
    {
        if ($media->isProviderBacked()) {
            throw new RuntimeException('Provider-backed items cannot be duplicated.');
        }

        $extension = (string) pathinfo((string) $media->name, PATHINFO_EXTENSION);
        $base = (string) pathinfo((string) $media->name, PATHINFO_FILENAME);

        $filename = MediaPath::buildFilename($base.'-copy', $extension);
        $path = $this->writer->join((string) ($media->directory ?? ''), $filename);

        $this->writer->copy($media->disk, $media->path, $path);

        $copy = $media->replicate(['id', 'created_at', 'updated_at']);

        $copy->forceFill([
            'path' => $path,
            'name' => $filename,
            'url' => MediaLibraryConfig::persistUrl() ? $this->resolveUrl($media->disk, $path) : null,
            // Conversions belong to the original file, not to this new one.
            'meta' => array_diff_key((array) $media->meta, array_flip(['conversions'])),
            'uploaded_by' => Auth::id() ?? $media->uploaded_by,
        ])->save();

        $copy->syncTagNames($media->tagNames());

        $this->dispatchConversions($copy);

        return $copy;
    }

    /**
     * Move items into a directory, on the disk and in the index.
     *
     * @param  iterable<Media>  $items
     * @return int number of items actually moved
     */
    public function moveMany(iterable $items, ?string $directory): int
    {
        $moved = 0;

        foreach ($items as $media) {
            $before = $media->path;

            $this->move($media, $directory);

            if ($media->path !== $before) {
                $moved++;
            }
        }

        return $moved;
    }

    /**
     * Rename a directory by relocating everything beneath it, preserving the
     * nesting under it.
     *
     * There is no folder table to update: directories exist purely as the
     * `directory` column, so the index cannot drift from the disk.
     */
    public function renameDirectory(string $disk, string $from, string $to): int
    {
        $from = $this->normalizeDirectory($from);
        $to = $this->normalizeDirectory($to);

        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }

        if (str_starts_with($to.'/', $from.'/')) {
            throw new RuntimeException('A directory cannot be moved inside itself.');
        }

        $moved = 0;

        Media::query()
            ->onDisk($disk)
            ->inDirectoryTree($from)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($from, $to, &$moved): void {
                foreach ($chunk as $media) {
                    $suffix = substr((string) $media->directory, strlen($from));

                    $moved += $this->moveMany([$media], $to.$suffix);
                }
            });

        return $moved;
    }

    /**
     * Turn a public URL back into the disk-relative path it was built from.
     *
     * The base is derived by resolving an empty path through exactly the same
     * resolver chain that produced the URL, so this stays correct for a CDN
     * resolver and for Storage::url() alike — and keeps working when the `url`
     * column is not persisted at all.
     */
    public function pathFromUrl(string $disk, string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $base = rtrim($this->resolveUrl($disk, ''), '/');

        if ($base !== '' && str_starts_with($url, $base.'/')) {
            return ltrim(substr($url, strlen($base) + 1), '/');
        }

        // Not an absolute URL at all: the state already holds a path.
        return str_contains($url, '://') ? null : ltrim($url, '/');
    }

    /**
     * Find an indexed item from a public URL, whether or not the `url` column
     * was persisted.
     */
    public function findByUrl(string $url, ?string $disk = null): ?Media
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($media = Media::query()->where('url', $url)->first()) {
            return $media;
        }

        $disk ??= $this->defaultDisk();
        $path = $this->pathFromUrl($disk, $url);

        return $path === null
            ? null
            : Media::query()->onDisk($disk)->where('path', $path)->first();
    }

    /**
     * Strip traversal segments, normalise separators and expand date tokens.
     *
     * `uploads/{Y}/{m}` becomes `uploads/2026/09`, which is how WordPress has
     * organised uploads for two decades and what keeps a directory listing
     * usable once a library passes a few thousand files.
     */
    public function normalizeDirectory(?string $directory): string
    {
        return MediaPath::normalizeDirectory($directory);
    }

    /**
     * Queue variant generation when a queue is configured, otherwise build
     * them inline so a fresh install needs no worker to get thumbnails.
     */
    private function dispatchConversions(Media $media): void
    {
        $service = app(ImageConversionService::class);

        if (! $service->shouldRun($media)) {
            return;
        }

        $queue = MediaLibraryConfig::conversionQueue();

        if ($queue === null) {
            $service->generate($media);

            return;
        }

        GenerateImageConversions::dispatch($media)->onQueue($queue);
    }

    /**
     * EXIF for JPEG/TIFF, when the extension is available. Camera and capture
     * date are what editors actually look for; the rest is noise.
     *
     * @return array<string, mixed>
     */
    public function readExif(UploadedFile|File $file, ?string $mimeType): array
    {
        return FileInspector::readExif($file, $mimeType);
    }
}
