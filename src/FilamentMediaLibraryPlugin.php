<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary;

use Batustun\FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use Batustun\FilamentMediaLibrary\Filters\MediaFilter;
use Batustun\FilamentMediaLibrary\Filters\MediaSorter;
use Batustun\FilamentMediaLibrary\Models\Media;
use Closure;
use Filament\Actions\Action;
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

    /** @var class-string<MediaLibrary> */
    protected string $page = MediaLibrary::class;

    /** @var array<int, MediaFilter>|null */
    protected ?array $filters = null;

    /** @var array<int, MediaSorter>|null */
    protected ?array $sorters = null;

    /** @var array<int, Action>|null */
    protected ?array $itemActions = null;

    /** @var array<int, Action>|null */
    protected ?array $bulkActions = null;

    /** @var array<int, mixed>|null */
    protected ?array $fileInfoComponents = null;

    protected ?Closure $hydrateFileInfo = null;

    protected ?Closure $saveFileInfo = null;

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
            $this->page,
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

    // ---------------------------------------------------------------------
    // Extension points
    // ---------------------------------------------------------------------

    /**
     * Swap in your own subclass of the library page, to add header widgets or
     * override behaviour.
     *
     * @param  class-string<MediaLibrary>  $page
     */
    public function mediaLibraryPage(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    /** @return class-string<MediaLibrary> */
    public function getMediaLibraryPage(): string
    {
        return $this->page;
    }

    /**
     * Filters added to the toolbar, on top of the built-in ones.
     *
     * @param  array<int, mixed>  $filters  MediaFilter instances; anything else is ignored
     */
    public function filters(array $filters): static
    {
        $this->filters = array_values(array_filter($filters, fn (mixed $f): bool => $f instanceof MediaFilter));

        return $this;
    }

    /** @return array<int, MediaFilter>|null */
    public function getFilters(): ?array
    {
        return $this->filters;
    }

    /**
     * Orderings added to the sort menu.
     *
     * @param  array<int, mixed>  $sorters  MediaSorter instances; anything else is ignored
     */
    public function sorters(array $sorters): static
    {
        $this->sorters = array_values(array_filter($sorters, fn (mixed $s): bool => $s instanceof MediaSorter));

        return $this;
    }

    /** @return array<int, MediaSorter>|null */
    public function getSorters(): ?array
    {
        return $this->sorters;
    }

    /**
     * Filament actions shown on a single item in the detail panel.
     *
     * @param  array<int, mixed>  $actions  Action instances; anything else is ignored
     */
    public function itemActions(array $actions): static
    {
        $this->itemActions = array_values(array_filter($actions, fn (mixed $a): bool => $a instanceof Action));

        return $this;
    }

    /** @return array<int, Action>|null */
    public function getItemActions(): ?array
    {
        return $this->itemActions;
    }

    /**
     * Filament actions shown when items are selected.
     *
     * @param  array<int, mixed>  $actions  Action instances; anything else is ignored
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = array_values(array_filter($actions, fn (mixed $a): bool => $a instanceof Action));

        return $this;
    }

    /** @return array<int, Action>|null */
    public function getBulkActions(): ?array
    {
        return $this->bulkActions;
    }

    /**
     * Extra form components for the file-info edit form, on top of the
     * built-in title/alt/description and any configured metadata fields.
     *
     * Pair with hydrateFileInfoUsing() and saveFileInfoUsing() when the values
     * do not live in the item's own custom metadata.
     *
     * @param  array<int, mixed>  $components
     */
    public function fileInfoComponents(array $components): static
    {
        $this->fileInfoComponents = array_values($components);

        return $this;
    }

    /** @return array<int, mixed>|null */
    public function getFileInfoComponents(): ?array
    {
        return $this->fileInfoComponents;
    }

    /** @param Closure(Media): array<string, mixed> $callback */
    public function hydrateFileInfoUsing(Closure $callback): static
    {
        $this->hydrateFileInfo = $callback;

        return $this;
    }

    public function getHydrateFileInfoCallback(): ?Closure
    {
        return $this->hydrateFileInfo;
    }

    /** @param Closure(Media, array<string, mixed>): void $callback */
    public function saveFileInfoUsing(Closure $callback): static
    {
        $this->saveFileInfo = $callback;

        return $this;
    }

    public function getSaveFileInfoCallback(): ?Closure
    {
        return $this->saveFileInfo;
    }

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
