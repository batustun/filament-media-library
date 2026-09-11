<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Scopes media to the current Filament tenant.
 *
 * A global scope rather than a condition in the browser's query builder, so a
 * direct Media::find() from application code is scoped too — a library that is
 * only scoped on "the other" query path is not scoped at all.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('filament-media-library-tenant', function (Builder $query): void {
            $tenantKey = self::currentTenantKey();

            if ($tenantKey === null) {
                return;
            }

            $query->where(
                $query->getModel()->getTable().'.'.MediaLibraryConfig::tenantColumn(),
                $tenantKey,
            );
        });

        static::creating(function (self $media): void {
            $column = MediaLibraryConfig::tenantColumn();

            if ($media->getAttribute($column) === null) {
                $media->setAttribute($column, self::currentTenantKey());
            }
        });
    }

    /**
     * The current Filament tenant's key, or null when tenancy is off or there
     * is no tenanted panel in play (console, queue, a plain HTTP route).
     */
    public static function currentTenantKey(): int|string|null
    {
        if (! MediaLibraryConfig::tenancyEnabled()) {
            return null;
        }

        try {
            return Filament::getTenant()?->getKey();
        } catch (Throwable) {
            return null;
        }
    }
}
