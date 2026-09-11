<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * One place that decides whether a file may be accepted.
 *
 * Both entry points run through it — the JSON endpoint and the in-panel
 * Livewire uploader — because `accepted_mime_types` and `max_upload_size_kb`
 * that only hold on one of the two are worse than no limits at all: they read
 * as enforced while silently letting everything through the other door.
 */
final class UploadGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertAcceptable(UploadedFile|File $file, string $attribute = 'file'): void
    {
        if ($message = self::violationFor($file)) {
            throw ValidationException::withMessages([$attribute => $message]);
        }
    }

    /** The first rule the file breaks, or null when it is acceptable. */
    public static function violationFor(UploadedFile|File $file): ?string
    {
        $extension = self::extensionOf($file);

        if ($extension !== '' && in_array($extension, MediaLibraryConfig::blockedExtensions(), true)) {
            return __('filament-media-library::filament-media-library.validation.blocked_type', [
                'extension' => $extension,
            ]);
        }

        $maxKb = MediaLibraryConfig::maxUploadSizeKb();
        $sizeKb = (int) ceil(((int) $file->getSize()) / 1024);

        if ($maxKb > 0 && $sizeKb > $maxKb) {
            return __('filament-media-library::filament-media-library.validation.too_large', [
                'max' => MimeKindResolver::formatBytes($maxKb * 1024),
                'size' => MimeKindResolver::formatBytes((int) $file->getSize()),
            ]);
        }

        $accepted = MediaLibraryConfig::acceptedMimeTypes();

        if ($accepted !== [] && ! self::mimeMatches((string) $file->getMimeType(), $accepted)) {
            return __('filament-media-library::filament-media-library.validation.wrong_type', [
                'types' => implode(', ', $accepted),
            ]);
        }

        return null;
    }

    /**
     * The real extension, taken from the ORIGINAL client filename.
     *
     * Livewire's temporary uploads are stored with a generated name, so
     * reading the extension off the temp path would see ".tmp" and wave every
     * blocked type straight through.
     */
    public static function extensionOf(UploadedFile|File $file): string
    {
        $name = $file instanceof UploadedFile
            ? $file->getClientOriginalName()
            : basename($file->getPathname());

        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    /**
     * Supports both exact types ("application/pdf") and the wildcard form
     * ("image/*") that HTML accept attributes use.
     *
     * @param  array<int, string>  $accepted
     */
    public static function mimeMatches(string $mimeType, array $accepted): bool
    {
        $mimeType = strtolower(trim($mimeType));

        foreach ($accepted as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === '' || $pattern === '*' || $pattern === '*/*') {
                return true;
            }

            if (str_ends_with($pattern, '/*')) {
                if (str_starts_with($mimeType, substr($pattern, 0, -1))) {
                    return true;
                }

                continue;
            }

            if ($mimeType === $pattern) {
                return true;
            }
        }

        return false;
    }
}
