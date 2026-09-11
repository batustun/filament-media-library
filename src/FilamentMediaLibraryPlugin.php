<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary;

use Batustun\FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Panel plugin entry point.
 *
 * Every setter stores its value on THIS instance — the plugin never writes to
 * the global config repository. Filament keeps one plugin instance per panel,
 * so two panels can each run the media library against a different disk,
 * navigation group or permission model without interfering.
 *
 *     ->plugin(
 *         FilamentMediaLibraryPlugin::make()
 *             ->defaultDisk('s3')
 *             ->navigationGroup('Content')
 *     )
 */
class FilamentMediaLibraryPlugin implements Plugin
{
    public const ID = 'filament-media-library';

    protected ?string $defaultDisk = null;

    /** @var array<int, string>|null */
    protected ?array $disks = null;

    protected ?string $visibility = null;

    /** @var array<string, callable>|null */
    protected ?array $urlResolvers = null;

    protected ?string $navigationGroup = null;

    protected ?string $navigationIcon = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationLabel = null;

    protected ?bool $hasNavigation = null;

    protected ?string $slug = null;

    protected ?bool $hasPermissions = null;

    protected bool $registersPage = true;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin instance registered on the current panel.
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        if (! $this->registersPage) {
            return;
        }

        $panel->pages([
            MediaLibrary::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    // ---------------------------------------------------------------------
    // Storage
    // ---------------------------------------------------------------------

    public function defaultDisk(string $disk): static
    {
        $this->defaultDisk = $disk;

        return $this;
    }

    public function getDefaultDisk(): ?string
    {
        return $this->defaultDisk;
    }

    /**
     * Restrict which disks this panel may browse. Empty = every configured disk.
     *
     * @param  array<int, string>  $disks
     */
    public function disks(array $disks): static
    {
        $this->disks = array_values(array_filter($disks, 'is_string'));

        return $this;
    }

    /** @return array<int, string>|null */
    public function getDisks(): ?array
    {
        return $this->disks;
    }

    /**
     * Visibility passed to Storage::put(). Pass null (the default) to send no
     * ACL, which is what S3 buckets with ACLs disabled and adapters such as
     * BunnyCDN or FTP require.
     */
    public function visibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    /**
     * Per-disk closures that turn a stored path into a public URL. Resolved on
     * every read, so changing a CDN hostname here fixes existing records with
     * no backfill.
     *
     * @param  array<string, callable>  $resolvers
     */
    public function urlResolvers(array $resolvers): static
    {
        $this->urlResolvers = array_merge($this->urlResolvers ?? [], $resolvers);

        return $this;
    }

    /** @return array<string, callable>|null */
    public function getUrlResolvers(): ?array
    {
        return $this->urlResolvers;
    }

    // ---------------------------------------------------------------------
    // Navigation
    // ---------------------------------------------------------------------

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigation(bool $condition = true): static
    {
        $this->hasNavigation = $condition;

        return $this;
    }

    public function hasNavigation(): ?bool
    {
        return $this->hasNavigation;
    }

    public function slug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * Register the plugin without adding the full-page media library — useful
     * when a panel should only get the picker and the form field.
     */
    public function registerPage(bool $condition = true): static
    {
        $this->registersPage = $condition;

        return $this;
    }

    public function registersPage(): bool
    {
        return $this->registersPage;
    }

    // ---------------------------------------------------------------------
    // Authorisation
    // ---------------------------------------------------------------------

    public function permissions(bool $condition = true): static
    {
        $this->hasPermissions = $condition;

        return $this;
    }

    public function hasPermissions(): ?bool
    {
        return $this->hasPermissions;
    }
}
