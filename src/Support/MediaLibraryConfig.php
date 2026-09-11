<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Filament\Facades\Filament;
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
    public static function plugin(): ?FilamentMediaLibraryPlugin
    {
        try {
            $panel = Filament::getCurrentOrDefaultPanel();

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
        $configured = array_keys((array) config('filesystems.disks', []));

        if ($allowList === []) {
            return $configured;
        }

        return array_values(array_intersect($allowList, $configured));
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
            'folders' => 'media_library_folders',
            'morph' => 'media_library_attachables',
        ];

        return (string) self::get('tables.'.$key, $defaults[$key] ?? $key);
    }

    public static function morphKeyType(): string
    {
        $type = strtolower((string) self::get('morph_key_type', 'id'));

        return in_array($type, ['id', 'uuid', 'ulid'], true) ? $type : 'id';
    }

    public static function userModel(): string
    {
        return (string) self::get('user_model', 'App\\Models\\User');
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
