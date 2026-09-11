<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Batustun\FilamentMediaLibrary\Enums\MediaKind;

class MimeKindResolver
{
    public static function fromMime(?string $mime, ?string $filename = null): MediaKind
    {
        $mime = strtolower((string) $mime);
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        if ($mime !== '') {
            if (str_starts_with($mime, 'image/')) {
                return MediaKind::Image;
            }
            if ($mime === 'application/pdf') {
                return MediaKind::Pdf;
            }
            if (str_starts_with($mime, 'video/')) {
                return MediaKind::Video;
            }
            if (str_starts_with($mime, 'audio/')) {
                return MediaKind::Audio;
            }
            if (str_contains($mime, 'word') || $mime === 'application/rtf') {
                return MediaKind::Doc;
            }
            if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') || $mime === 'text/csv') {
                return MediaKind::Sheet;
            }
            if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) {
                return MediaKind::Slides;
            }
            if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, 'tar') || str_contains($mime, 'gzip') || str_contains($mime, '7z')) {
                return MediaKind::Archive;
            }
        }

        return self::fromExtension($ext);
    }

    public static function fromExtension(string $ext): MediaKind
    {
        $ext = strtolower(ltrim($ext, '.'));

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif', 'avif', 'tiff', 'ico'], true) => MediaKind::Image,
            $ext === 'pdf' => MediaKind::Pdf,
            in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg', '3gp'], true) => MediaKind::Video,
            in_array($ext, ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'oga', 'opus'], true) => MediaKind::Audio,
            in_array($ext, ['doc', 'docx', 'rtf', 'odt', 'pages'], true) => MediaKind::Doc,
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods', 'numbers'], true) => MediaKind::Sheet,
            in_array($ext, ['ppt', 'pptx', 'odp', 'key'], true) => MediaKind::Slides,
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'], true) => MediaKind::Archive,
            in_array($ext, ['json', 'xml', 'html', 'css', 'js', 'ts', 'php', 'py', 'rb', 'go', 'sh', 'yaml', 'yml', 'md', 'txt'], true) => MediaKind::Code,
            default => MediaKind::Other,
        };
    }

    public static function isImage(?string $mime, ?string $filename = null): bool
    {
        return self::fromMime($mime, $filename) === MediaKind::Image;
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $precision).' '.$units[$power];
    }
}
