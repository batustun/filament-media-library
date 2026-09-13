<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Http\Request;

/**
 * Resolves the media a request names.
 *
 * The package's routes run on plain web middleware, outside any panel, so
 * Filament knows of no tenant there and the tenant scope deliberately hides
 * everything. A signed URL is the exception: it was minted inside the panel,
 * where the tenant was known and the query that produced it was already
 * scoped, so the signature is what vouches for this one id.
 */
final class SignedMedia
{
    public static function findOrFail(Request $request, string $id): Media
    {
        $query = Media::query();

        if ($request->hasValidSignature()) {
            $query->withoutGlobalScope(Media::TENANT_SCOPE);
        }

        return $query->findOrFail($id);
    }

    /**
     * The tenant a signed URL was minted for, so a file uploaded through a
     * route outside the panel is stamped with the right one.
     */
    public static function tenantKey(Request $request): ?string
    {
        if (! $request->hasValidSignature()) {
            return null;
        }

        $key = $request->query('tenant');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
