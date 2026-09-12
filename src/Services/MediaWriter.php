<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Services;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Batustun\FilamentMediaLibrary\Support\SvgSanitizer;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Everything about getting bytes onto a disk, in one place.
 *
 * The service above decides what a media record means; this decides how its
 * file is written — which visibility to send, when to stream and when to
 * rewrite. Both policies were previously interleaved in three different
 * methods, so a change to either meant reading all of them.
 *
 * Two rules live here and nowhere else:
 *
 *   - An ACL is sent only when one is configured. S3 buckets with Object
 *     Ownership enforced, and adapters such as BunnyCDN and FTP, reject it.
 *   - An SVG is stored rewritten rather than copied. It is executable markup,
 *     so what lands on the disk must be the sanitised document.
 */
class MediaWriter
{
    /**
     * Write an upload into a directory.
     *
     * @return string the path it was stored at
     *
     * @throws ValidationException when an SVG cannot be parsed
     */
    public function write(
        string $disk,
        string $directory,
        string $filename,
        UploadedFile|File $file,
        ?string $mimeType,
    ): string {
        $path = $this->join($directory, $filename);

        if ($this->shouldSanitize($mimeType, $filename)) {
            $this->putSanitizedSvg($disk, $path, $file);

            return $path;
        }

        $stored = Storage::disk($disk)->putFileAs($directory, $file, $filename, $this->options());

        if ($stored === false) {
            throw new RuntimeException("Failed to write [{$filename}] to disk [{$disk}].");
        }

        return ltrim((string) $stored, '/');
    }

    /**
     * Replace the bytes at an existing path, leaving the path itself alone.
     *
     * @throws ValidationException when an SVG cannot be parsed
     */
    public function overwrite(
        string $disk,
        string $path,
        UploadedFile|File $file,
        ?string $mimeType,
        string $name,
    ): void {
        if ($this->shouldSanitize($mimeType, $name)) {
            $this->putSanitizedSvg($disk, $path, $file);

            return;
        }

        $stream = fopen((string) $file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the replacement file.');
        }

        try {
            $written = Storage::disk($disk)->put($path, $stream, $this->options());
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            throw new RuntimeException("Failed to replace [{$path}] on disk [{$disk}].");
        }
    }

    /** Copy an object to another path on the same disk, streamed. */
    public function copy(string $disk, string $from, string $to): void
    {
        $filesystem = Storage::disk($disk);
        $stream = $filesystem->readStream($from);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read [{$from}] for duplication.");
        }

        try {
            if ($filesystem->put($to, $stream, $this->options()) === false) {
                throw new RuntimeException("Failed to write the duplicate to [{$to}].");
            }
        } finally {
            fclose($stream);
        }
    }

    public function join(string $directory, string $filename): string
    {
        return ltrim(($directory === '' ? '' : $directory.'/').$filename, '/');
    }

    private function shouldSanitize(?string $mimeType, string $filename): bool
    {
        return MediaLibraryConfig::sanitizesSvg() && SvgSanitizer::isSvg($mimeType, $filename);
    }

    /** @throws ValidationException */
    private function putSanitizedSvg(string $disk, string $path, UploadedFile|File $file): void
    {
        $clean = SvgSanitizer::sanitize((string) file_get_contents((string) $file->getRealPath()));

        if ($clean === null) {
            throw ValidationException::withMessages([
                'file' => __('filament-media-library::filament-media-library.validation.unsafe_svg'),
            ]);
        }

        if (Storage::disk($disk)->put($path, $clean, $this->options()) === false) {
            throw new RuntimeException("Failed to write [{$path}] to disk [{$disk}].");
        }
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        $visibility = MediaLibraryConfig::visibility();

        return $visibility === null ? [] : ['visibility' => $visibility];
    }
}
