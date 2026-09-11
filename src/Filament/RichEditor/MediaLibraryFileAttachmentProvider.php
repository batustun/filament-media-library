<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\RichEditor;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Stores RichEditor inline uploads in the media library and embeds the public
 * URL. Media IDs are persisted in the content, so the URL is resolved live —
 * moving to a new CDN never breaks already-published content.
 */
class MediaLibraryFileAttachmentProvider implements FileAttachmentProvider
{
    protected ?RichContentAttribute $attribute = null;

    protected ?string $diskOverride = null;

    protected ?string $directoryOverride = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function disk(string $disk): static
    {
        $this->diskOverride = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directoryOverride = $directory;

        return $this;
    }

    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if (! is_string($file) && ! is_numeric($file)) {
            return null;
        }

        $file = (string) $file;

        $media = Str::isUuid($file)
            ? Media::query()->whereKey($file)->first()
            : Media::query()->where('path', $file)->first();

        return $media?->publicUrl();
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        return app(MediaService::class)->store(
            $file,
            $this->diskOverride ?? MediaLibraryConfig::defaultDisk(),
            $this->directoryOverride ?? 'rich-editor',
        )->id;
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return MediaLibraryConfig::visibility();
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    /** @param array<int, mixed> $exceptIds */
    public function cleanUpFileAttachments(array $exceptIds): void
    {
        // Intentionally a no-op: removing an image from a post must not delete
        // it from the library, where it may still be referenced elsewhere.
    }
}
