<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MediaKind: string implements HasColor, HasIcon, HasLabel
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Video = 'video';
    case Audio = 'audio';
    case Doc = 'doc';
    case Sheet = 'sheet';
    case Slides = 'slides';
    case Archive = 'archive';
    case Code = 'code';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('filament-media-library::filament-media-library.kinds.'.$this->value);
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Image => 'heroicon-o-photo',
            self::Pdf => 'heroicon-o-document-text',
            self::Video => 'heroicon-o-film',
            self::Audio => 'heroicon-o-musical-note',
            self::Doc => 'heroicon-o-document',
            self::Sheet => 'heroicon-o-table-cells',
            self::Slides => 'heroicon-o-presentation-chart-bar',
            self::Archive => 'heroicon-o-archive-box',
            self::Code => 'heroicon-o-code-bracket',
            self::Other => 'heroicon-o-document',
        };
    }

    public function icon(): string
    {
        return $this->getIcon();
    }

    /**
     * Filament semantic colour name. Used for badges, which resolve it through
     * Filament's own colour system rather than a Tailwind class name.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Image => 'success',
            self::Pdf => 'danger',
            self::Video => 'warning',
            self::Audio => 'info',
            self::Doc, self::Sheet, self::Slides => 'primary',
            self::Archive, self::Code, self::Other => 'gray',
        };
    }

    public function color(): string
    {
        return $this->getColor();
    }

    /**
     * A literal hex colour for the icon tint.
     *
     * Deliberately NOT a `text-{colour}-500` Tailwind class: those are built
     * by the host application's Tailwind pass, which never scans this
     * package's views, so a dynamic class name would silently render
     * colourless on every site that installs the plugin.
     */
    public function hexColor(): string
    {
        return match ($this) {
            self::Image => '#16a34a',
            self::Pdf => '#dc2626',
            self::Video => '#d97706',
            self::Audio => '#0891b2',
            self::Doc, self::Sheet, self::Slides => '#2563eb',
            self::Archive, self::Code, self::Other => '#6b7280',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
