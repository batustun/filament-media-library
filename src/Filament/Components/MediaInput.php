<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Components;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A FileUpload that is backed by the media library.
 *
 * It keeps Filament's native upload UI (drag & drop, preview, progress,
 * remove) and adds two things:
 *
 *   1. Uploads are routed through MediaService, so every file is indexed.
 *   2. A hint action opens the library picker, letting editors reuse assets
 *      instead of re-uploading them.
 *
 * What gets written into the form state is controlled by ->returns():
 *   'url'  (default) — the public URL
 *   'path'           — the disk-relative path
 *   'id'             — the media UUID
 */
class MediaInput extends FileUpload
{
    /** @var array<int, string> */
    protected array $acceptedKinds = [];

    protected string $returns = 'url';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk(fn (): string => MediaLibraryConfig::defaultDisk());
        $this->directory(fn (): string => MediaLibraryConfig::defaultDirectory());
        $this->reorderable(false);

        // Only send an ACL when the disk is known to support one. S3 buckets
        // with ACLs disabled and BunnyCDN/FTP adapters reject it outright.
        if ($visibility = MediaLibraryConfig::visibility()) {
            $this->visibility($visibility);
        }

        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
            $media = app(MediaService::class)->store(
                $file,
                $this->getDiskName(),
                $this->getDirectory() ?: null,
            );

            return $this->resolveStateFromMedia($media);
        });

        $this->getUploadedFileUsing(
            fn (string $file, ?string $storedFileName): ?array => $this->resolveUploadedFile($file),
        );

        // Clearing the field must not destroy the library record; physical
        // deletion is an explicit action on the library page.
        $this->deleteUploadedFileUsing(fn (): null => null);

        $this->hintAction(fn (): Action => LibraryPickerAction::for($this, $this->acceptedKinds, $this->returns));
    }

    public function returns(string $type): static
    {
        $this->returns = in_array($type, ['url', 'id', 'path'], true) ? $type : 'url';

        return $this;
    }

    public function getReturns(): string
    {
        return $this->returns;
    }

    /**
     * Constrain the picker and the accepted upload types to broad media kinds.
     *
     * @param  array<int, string>  $kinds
     */
    public function acceptedKinds(array $kinds): static
    {
        $this->acceptedKinds = array_values(array_filter($kinds, 'is_string'));

        $mimeMap = [
            'image' => ['image/*'],
            'pdf' => ['application/pdf'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'video' => ['video/*'],
            'audio' => ['audio/*'],
        ];

        $mimes = [];

        foreach ($this->acceptedKinds as $kind) {
            $mimes = array_merge($mimes, $mimeMap[$kind] ?? []);
        }

        if ($mimes !== []) {
            $this->acceptedFileTypes(array_values(array_unique($mimes)));
        }

        return $this;
    }

    /** @param array<int, string> $mimes */
    public function acceptedMimeTypes(array $mimes): static
    {
        $this->acceptedFileTypes($mimes);

        return $this;
    }

    /** @return array<int, string> */
    public function getAcceptedKinds(): array
    {
        return $this->acceptedKinds;
    }

    protected function resolveStateFromMedia(Media $media): string
    {
        return match ($this->returns) {
            'id' => (string) $media->id,
            'path' => (string) $media->path,
            default => $media->publicUrl(),
        };
    }

    /** @return array{name: string, size: int, type: string, url: string}|null */
    protected function resolveUploadedFile(string $file): ?array
    {
        if ($file === '') {
            return null;
        }

        $disk = $this->getDiskName();

        $media = match ($this->returns) {
            'id' => Media::query()->whereKey($file)->first(),
            'path' => Media::query()->onDisk($disk)->where('path', $file)->first(),
            // Resolved through the URL→path fallback, so the field still finds
            // its record when `persist_url` is disabled and the `url` column
            // is empty.
            default => app(MediaService::class)->findByUrl($file, $disk),
        };

        if ($media === null) {
            // The state points at something the library does not know about —
            // a legacy URL, or a path uploaded before the library existed.
            return [
                'name' => basename($file),
                'size' => 0,
                'type' => '',
                'url' => str_starts_with($file, 'http')
                    ? $file
                    : app(MediaService::class)->resolveUrl($this->getDiskName(), $file),
            ];
        }

        return [
            'name' => $media->name,
            'size' => (int) $media->size,
            'type' => (string) $media->mime_type,
            'url' => $media->publicUrl(),
        ];
    }
}
