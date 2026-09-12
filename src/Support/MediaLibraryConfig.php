<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Batustun\FilamentMediaLibrary\Filters\MediaFilter;
use Batustun\FilamentMediaLibrary\Filters\MediaSorter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Spatie\Tags\Tag;
use Throwable;

/**
 * Resolves every setting through a single, panel-aware funnel.
 *
 * Precedence: the plugin instance registered on the CURRENT Filament panel
 * wins; otherwise the published/merged config file is used. That is what lets
 * two panels in the same application point the media library at two different
 * disks without one clobbering the other — the plugin never writes to the
 * global config repository.
 *
 * Outside a panel (console commands, the JSON upload routes, queued jobs) the
 * config file is the only source, which is exactly the desired behaviour.
 */
final class MediaLibraryConfig
{
    /** Input types a declared metadata field may use. */
    public const METADATA_FIELD_TYPES = ['text', 'textarea', 'number', 'url', 'date', 'boolean', 'select'];

    public static function plugin(): ?FilamentMediaLibraryPlugin
    {
        try {
            // Strictly the panel currently being served — NOT the default
            // panel. Outside a panel request (console commands, the JSON
            // routes, queued jobs) there is no panel whose settings could
            // reasonably apply, and silently letting the default panel
            // override the config file there is a debugging trap.
            $panel = Filament::getCurrentPanel();

            if ($panel === null) {
                return null;
            }

            $plugin = $panel->getPlugin(FilamentMediaLibraryPlugin::ID);

            return $plugin instanceof FilamentMediaLibraryPlugin ? $plugin : null;
        } catch (Throwable) {
            // No panel booted, or the plugin is not registered on this panel.
            return null;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return config('filament-media-library.'.$key, $default);
    }

    public static function defaultDisk(): string
    {
        $disk = self::plugin()?->getDefaultDisk()
            ?? self::get('default_disk', 'public');

        return (string) $disk;
    }

    /**
     * Disks the library may browse, intersected with what is actually
     * configured in config/filesystems.php.
     *
     * @return array<int, string>
     */
    public static function disks(): array
    {
        $allowList = self::plugin()?->getDisks() ?? (array) self::get('disks', []);

        if ($allowList !== []) {
            // An explicit allow-list is trusted as written. Intersecting it
            // with config('filesystems.disks') would silently drop every disk
            // registered at runtime — Storage::fake() in tests, Storage::extend()
            // for custom adapters, per-tenant disks — and would turn a typo
            // into a disk that quietly vanishes from the UI instead of a loud
            // "disk does not have a configured driver".
            return array_values(array_filter($allowList, 'is_string'));
        }

        // No allow-list: enumerate whatever the application has configured.
        return array_keys((array) config('filesystems.disks', []));
    }

    public static function isDiskAllowed(string $disk): bool
    {
        return in_array($disk, self::disks(), true);
    }

    /**
     * Null means "send no ACL", which is required for S3 buckets with ACLs
     * disabled and for adapters that do not implement visibility at all.
     */
    public static function visibility(): ?string
    {
        $visibility = self::plugin()?->getVisibility() ?? self::get('visibility');

        return is_string($visibility) && $visibility !== '' ? $visibility : null;
    }

    public static function urlResolver(string $disk): ?callable
    {
        $resolvers = array_merge(
            (array) self::get('url_resolvers', []),
            self::plugin()?->getUrlResolvers() ?? [],
        );

        $resolver = $resolvers[$disk] ?? null;

        return is_callable($resolver) ? $resolver : null;
    }

    public static function persistUrl(): bool
    {
        return (bool) self::get('persist_url', true);
    }

    public static function defaultDirectory(): string
    {
        return (string) self::get('default_directory', 'uploads');
    }

    public static function maxUploadSizeKb(): int
    {
        return (int) self::get('max_upload_size_kb', 524288);
    }

    /** @return array<int, int> */
    public static function pageSizes(): array
    {
        $sizes = array_values(array_filter(array_map('intval', (array) self::get('page_sizes', [24, 48, 96, 192]))));

        return $sizes === [] ? [48] : $sizes;
    }

    public static function defaultPageSize(): int
    {
        $size = (int) self::get('default_page_size', 48);
        $sizes = self::pageSizes();

        return in_array($size, $sizes, true) ? $size : $sizes[0];
    }

    /** @return array<int, string> */
    public static function acceptedMimeTypes(): array
    {
        return array_values(array_filter((array) self::get('accepted_mime_types', [])));
    }

    /**
     * Every configured provider block, keyed by provider name.
     *
     * @return array<string, array<string, mixed>>
     */
    /** @return array<int, MediaFilter> */
    public static function customFilters(): array
    {
        return self::plugin()?->getFilters() ?? [];
    }

    /** @return array<int, MediaSorter> */
    public static function customSorters(): array
    {
        return self::plugin()?->getSorters() ?? [];
    }

    /** @return array<int, Action> */
    public static function itemActions(): array
    {
        return self::plugin()?->getItemActions() ?? [];
    }

    /** @return array<int, Action> */
    public static function bulkActions(): array
    {
        return self::plugin()?->getBulkActions() ?? [];
    }

    /** @return array<int, mixed> */
    public static function fileInfoComponents(): array
    {
        return self::plugin()?->getFileInfoComponents() ?? [];
    }

    public static function tagsEnabled(): bool
    {
        return (bool) self::get('tags.enabled', true);
    }

    public static function syncsSpatieTags(): bool
    {
        return (bool) self::get('tags.sync_spatie_tags', false)
            && class_exists(Tag::class);
    }

    /**
     * Extra fields shown in the detail panel, normalised so a view can rely on
     * every key being present.
     *
     * @return array<int, array{key: string, label: string, type: string, options: array<string, string>}>
     */
    public static function metadataFields(): array
    {
        $fields = [];

        foreach ((array) self::get('metadata_fields', []) as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }

            $key = (string) $field['key'];

            if ($key === '') {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');

            $fields[] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? self::metadataLabel($key)),
                'type' => in_array($type, self::METADATA_FIELD_TYPES, true) ? $type : 'text',
                'options' => array_map('strval', (array) ($field['options'] ?? [])),
            ];
        }

        return $fields;
    }

    private static function metadataLabel(string $key): string
    {
        $translation = 'filament-media-library::filament-media-library.custom.'.$key;
        $translated = __($translation);

        return is_string($translated) && $translated !== $translation
            ? $translated
            : ucfirst(str_replace(['_', '-'], ' ', $key));
    }

    public static function optimizerDriver(): ?string
    {
        $driver = self::get('optimizer.driver');

        return is_string($driver) && $driver !== '' ? strtolower($driver) : null;
    }

    /** @return array<string, mixed> */
    public static function optimizer(): array
    {
        return (array) self::get('optimizer', []);
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) self::get('tenancy.enabled', false);
    }

    public static function tenantColumn(): string
    {
        $column = (string) self::get('tenancy.column', 'tenant_id');

        return $column !== '' ? $column : 'tenant_id';
    }

    public static function chunkedUploadsEnabled(): bool
    {
        return (bool) self::get('chunked_uploads.enabled', true);
    }

    public static function chunkSizeBytes(): int
    {
        return max(1, (int) self::get('chunked_uploads.chunk_size_mb', 8)) * 1024 * 1024;
    }

    public static function chunkThresholdBytes(): int
    {
        return max(1, (int) self::get('chunked_uploads.threshold_mb', 16)) * 1024 * 1024;
    }

    public static function showsExtensions(): bool
    {
        return (bool) self::get('ui.show_extensions', true);
    }

    public static function remembersViewMode(): bool
    {
        return (bool) self::get('ui.remember_view_mode', true);
    }

    public static function providers(): array
    {
        $providers = [];

        foreach ((array) self::get('providers', []) as $key => $settings) {
            if (is_array($settings)) {
                $providers[(string) $key] = $settings;
            }
        }

        return $providers;
    }

    /** @return array<string, mixed> */
    public static function provider(string $key): array
    {
        return self::providers()[$key] ?? [];
    }

    public static function conversionsEnabled(): bool
    {
        return (bool) self::get('conversions.enabled', true) && self::conversionSizes() !== [];
    }

    /**
     * Configured variant widths, smallest first so the first one is always the
     * cheapest thumbnail.
     *
     * @return array<string, int>
     */
    public static function conversionSizes(): array
    {
        $sizes = [];

        foreach ((array) self::get('conversions.sizes', []) as $name => $width) {
            $width = (int) $width;

            if ($width > 0) {
                $sizes[(string) $name] = $width;
            }
        }

        asort($sizes);

        return $sizes;
    }

    public static function conversionQuality(): int
    {
        return max(1, min(100, (int) self::get('conversions.quality', 82)));
    }

    public static function conversionQueue(): ?string
    {
        $queue = self::get('conversions.queue');

        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    public static function previewsText(): bool
    {
        return (bool) self::get('preview.text', true);
    }

    public static function previewsArchives(): bool
    {
        return (bool) self::get('preview.archives', true);
    }

    /** "microsoft", "google", or null when third-party previewing is off. */
    public static function officeViewer(): ?string
    {
        $viewer = strtolower((string) self::get('preview.office_viewer', ''));

        return in_array($viewer, ['microsoft', 'google'], true) ? $viewer : null;
    }

    public static function autoAttachesToFileUpload(): bool
    {
        return (bool) self::get('auto_attach.file_upload', true);
    }

    public static function autoIndexesUploads(): bool
    {
        return (bool) self::get('auto_attach.index_uploads', false);
    }

    public static function sanitizesSvg(): bool
    {
        return (bool) self::get('security.sanitize_svg', true);
    }

    /** @return array<int, string> */
    public static function blockedExtensions(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $extension): string => strtolower(ltrim((string) $extension, '.')),
            (array) self::get('security.blocked_extensions', []),
        )));
    }

    public static function permissionsEnabled(): bool
    {
        return (bool) (self::plugin()?->hasPermissions() ?? self::get('permissions.enabled', false));
    }

    public static function permission(string $key): string
    {
        return (string) self::get('permissions.'.$key, 'media.'.$key);
    }

    public static function permissionGuard(): string
    {
        return (string) self::get('permissions.guard', 'web');
    }

    public static function navigation(string $key, mixed $default = null): mixed
    {
        $fromPlugin = match ($key) {
            'group' => self::plugin()?->getNavigationGroup(),
            'icon' => self::plugin()?->getNavigationIcon(),
            'sort' => self::plugin()?->getNavigationSort(),
            'label' => self::plugin()?->getNavigationLabel(),
            'slug' => self::plugin()?->getSlug(),
            'enabled' => self::plugin()?->hasNavigation(),
            default => null,
        };

        return $fromPlugin ?? self::get('navigation.'.$key, $default);
    }

    public static function table(string $key): string
    {
        $defaults = [
            'media' => 'media_library_items',
            // Only referenced by the legacy cleanup migration.
            'folders' => 'media_library_folders',
            'morph' => 'media_library_attachables',
            'tags' => 'media_library_tags',
            'taggables' => 'media_library_taggables',
        ];

        return (string) self::get('tables.'.$key, $defaults[$key] ?? $key);
    }

    public static function morphKeyType(): string
    {
        $type = strtolower((string) self::get('morph_key_type', 'id'));

        return in_array($type, ['id', 'uuid', 'ulid'], true) ? $type : 'id';
    }

    /**
     * Falls back to the application's own auth model rather than assuming
     * App\Models\User: an app that renamed or moved its user model would
     * otherwise fatal the moment someone opened a file's detail pane.
     */
    public static function userModel(): string
    {
        foreach ([self::get('user_model'), config('auth.providers.users.model')] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return 'App\\Models\\User';
    }

    /**
     * Whether a byte-identical upload should reuse the existing record rather
     * than writing a second copy. Needs hashing to have anything to compare.
     */
    public static function reusesDuplicates(): bool
    {
        return self::hashUploads()
            && strtolower((string) self::get('duplicates', 'reuse')) === 'reuse';
    }

    public static function hashUploads(): bool
    {
        return (bool) self::get('hash_uploads', true);
    }

    public static function readImageDimensions(): bool
    {
        return (bool) self::get('read_image_dimensions', true);
    }

    public static function routesEnabled(): bool
    {
        return (bool) self::get('routes.enabled', true);
    }

    public static function cacheEnabled(): bool
    {
        return (bool) self::get('cache.enabled', true);
    }

    public static function cacheTtl(): int
    {
        return max(0, (int) self::get('cache.ttl', 120));
    }

    public static function cacheStore(): ?string
    {
        $store = self::get('cache.store');

        return is_string($store) && $store !== '' ? $store : null;
    }
}
