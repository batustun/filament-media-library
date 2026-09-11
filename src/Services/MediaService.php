<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * All disk I/O goes through here, via Flysystem only — so the same code path
 * serves "public"/"local", S3, MinIO, DigitalOcean Spaces, BunnyCDN, FTP/SFTP
 * and anything else registered in config/filesystems.php.
 */
class MediaService
{
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
            $extension = $this->guessExtensionFromMime($mimeType);
        }

        $filename ??= $this->buildFilename($originalName, $extension);

        $options = [];
        if ($visibility = MediaLibraryConfig::visibility()) {
            // Only sent when explicitly configured: S3 buckets with ACLs
            // disabled and adapters such as BunnyCDN/FTP reject ACL calls.
            $options['visibility'] = $visibility;
        }

        $stored = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            $options,
        );

        if ($stored === false) {
            throw new RuntimeException("Failed to write [{$filename}] to disk [{$disk}].");
        }

        $path = ltrim((string) $stored, '/');

        $realPath = $file->getRealPath();

        $hash = MediaLibraryConfig::hashUploads() && is_string($realPath) && is_readable($realPath)
            ? (hash_file('sha256', $realPath) ?: null)   // streamed, not loaded into memory
            : null;

        [$width, $height] = $this->readImageDimensions($file, $mimeType);

        return Media::create([
            'disk' => $disk,
            'directory' => $directory !== '' ? $directory : null,
            'path' => $path,
            'url' => MediaLibraryConfig::persistUrl() ? $this->resolveUrl($disk, $path) : null,
            'name' => $filename,
            'title' => null,
            'mime_type' => $mimeType,
            'kind' => MimeKindResolver::fromMime($mimeType, $filename)->value,
            'size' => (int) ($file->getSize() ?: 0),
            'width' => $width,
            'height' => $height,
            'duration' => null,
            'hash' => $hash,
            'meta' => [],
            'uploaded_by' => $userId ?? Auth::id(),
        ]);
    }

    public function delete(Media $media, bool $purgeDisk = true): bool
    {
        if ($purgeDisk) {
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

    public function findByUrl(string $url): ?Media
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        return Media::query()->where('url', $url)->first();
    }

    /** Strip traversal segments and normalise separators. */
    public function normalizeDirectory(?string $directory): string
    {
        $directory = str_replace('\\', '/', (string) $directory);

        $segments = array_filter(
            explode('/', $directory),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );

        return implode('/', $segments);
    }

    private function buildFilename(string $original, string $extension): string
    {
        $base = Str::slug((string) pathinfo($original, PATHINFO_FILENAME)) ?: 'file';

        return $base.'-'.now()->format('YmdHis').'-'.Str::random(6)
            .($extension !== '' ? '.'.$extension : '');
    }

    private function guessExtensionFromMime(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            default => '',
        };
    }

    /** @return array{0: int|null, 1: int|null} */
    private function readImageDimensions(UploadedFile|File $file, ?string $mimeType): array
    {
        if (! MediaLibraryConfig::readImageDimensions()) {
            return [null, null];
        }

        if (! str_starts_with((string) $mimeType, 'image/')) {
            return [null, null];
        }

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || ! is_readable($realPath)) {
            return [null, null];
        }

        try {
            $size = @getimagesize($realPath);

            if (is_array($size)) {
                return [(int) $size[0], (int) $size[1]];
            }
        } catch (Throwable) {
            // SVG and some formats are not supported by getimagesize().
        }

        return [null, null];
    }
}
