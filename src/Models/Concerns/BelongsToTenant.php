<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Scopes media to the current Filament tenant.
 *
 * A global scope rather than a condition in the browser's query builder, so a
 * direct Media::find() from application code is scoped too — a library that is
 * only scoped on "the other" query path is not scoped at all.
 *
 * It fails closed. Where no tenant can be determined the scope hides every
 * tenant's media rather than showing all of it: the package registers plain
 * HTTP routes that run outside the panel, where Filament knows of no tenant,
 * and those used to answer any file to anyone who knew its id.
 */
trait BelongsToTenant
{
    public const TENANT_SCOPE = 'filament-media-library-tenant';

    /**
     * Stands in for the panel while a signed request is being served, which is
     * the one case where a route outside the panel may be trusted with a tenant.
     */
    protected static int|string|null $tenantOverride = null;

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(self::TENANT_SCOPE, function (Builder $query): void {
            if (! MediaLibraryConfig::tenancyEnabled()) {
                return;
            }

            $column = $query->getModel()->getTable().'.'.MediaLibraryConfig::tenantColumn();
            $tenantKey = self::currentTenantKey();

            if ($tenantKey !== null) {
                $query->where(function (Builder $query) use ($column, $tenantKey): void {
                    $query->where($column, $tenantKey);

                    if (MediaLibraryConfig::tenancyShares()) {
                        $query->orWhereNull($column);
                    }
                });

                return;
            }

            if (self::mayReadEveryTenant()) {
                return;
            }

            $query->whereNull($column);
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

        if (static::$tenantOverride !== null) {
            return static::$tenantOverride;
        }

        try {
            return Filament::getTenant()?->getKey();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Run something as a given tenant.
     *
     * Used by the routes the package mints signed URLs for: the signature was
     * produced inside the panel, where the tenant was known and the query that
     * produced it was already scoped.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function actingForTenant(int|string|null $tenantKey, Closure $callback): mixed
    {
        $previous = static::$tenantOverride;
        static::$tenantOverride = $tenantKey;

        try {
            return $callback();
        } finally {
            static::$tenantOverride = $previous;
        }
    }

    /**
     * Whether the caller is entitled to see across tenants.
     *
     * A panel that does not use tenancy — the usual admin panel — is one
     * legitimate case. The other is having no request to serve at all: sync,
     * doctor, a queue worker, a scheduled command, none of which belong to a
     * tenant. The SAPI is not the test for that, because the test suite runs
     * HTTP requests from the console too; a resolved route is.
     *
     * Everything else is a request that cannot say which tenant is asking.
     */
    protected static function mayReadEveryTenant(): bool
    {
        try {
            if (Filament::getCurrentPanel()?->hasTenancy() === false) {
                return true;
            }
        } catch (Throwable) {
            // A panel that will not resolve is no reason to widen access.
        }

        return app('request')->route() === null;
    }
}
