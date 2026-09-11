<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Single authorisation chokepoint shared by the page, the picker, the policy
 * and the HTTP controller, so no entry point can drift out of sync.
 *
 * Any user model exposing hasPermissionTo() works (spatie/laravel-permission
 * is the common case but is not a hard dependency). When permissions are
 * switched off every check passes — authentication is still required by the
 * surrounding panel/route middleware.
 */
final class Authorize
{
    public static function check(string $ability, ?Authenticatable $user = null): bool
    {
        if (! MediaLibraryConfig::permissionsEnabled()) {
            return true;
        }

        $user ??= Auth::user();

        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return (bool) $user->hasPermissionTo(MediaLibraryConfig::permission($ability));
        } catch (Throwable) {
            // Permission row does not exist, or a guard mismatch: deny.
            return false;
        }
    }

    /**
     * @throws HttpException
     */
    public static function ensure(string $ability, ?Authenticatable $user = null): void
    {
        if (self::check($ability, $user)) {
            return;
        }

        abort(403, __('filament-media-library::filament-media-library.messages.forbidden'));
    }
}
