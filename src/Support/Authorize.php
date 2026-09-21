<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Single authorisation chokepoint shared by the page, the picker, the policy
 * and the HTTP controller, so no entry point can drift out of sync.
 *
 * Two questions are asked, in this order:
 *
 *  1. The host application's Gate. This is where a Laravel application keeps
 *     its authorisation: a super-admin bypass is a Gate::before callback, a
 *     custom rule is Gate::define, and spatie/laravel-permission publishes its
 *     own Gate::before that answers permission names. Asking the Gate is what
 *     makes this package obey the application it is installed in.
 *
 *  2. hasPermissionTo() on the user model, for applications that expose
 *     permissions without registering them on the Gate (spatie's
 *     `register_permission_check_method` switched off, or a hand-rolled user
 *     model). spatie/laravel-permission is the common case but is not a hard
 *     dependency.
 *
 * When permissions are switched off every check passes — authentication is
 * still required by the surrounding panel/route middleware.
 */
final class Authorize
{
    /**
     * Permission names already reported as unanswerable, so a broken install
     * writes one line per name per process instead of one per click.
     *
     * @var array<string, true>
     */
    private static array $reported = [];

    public static function check(string $ability, ?Authenticatable $user = null): bool
    {
        if (! MediaLibraryConfig::permissionsEnabled()) {
            return true;
        }

        $user ??= Auth::user();

        if ($user === null) {
            return false;
        }

        $permission = MediaLibraryConfig::permission($ability);

        if (self::gateAllows($permission, $user)) {
            return true;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return (bool) $user->hasPermissionTo($permission);
        } catch (Throwable $e) {
            // The permission row does not exist, or the guard does not match.
            // Deny — but say so, because a 403 with an empty log is how a
            // missing seeder survives for weeks.
            self::report($permission, $user, $e);

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

    /**
     * Ask the host application's Gate, treating a failure as "no opinion".
     *
     * A Gate::before callback type-hinted for the application's own user model
     * raises a TypeError when some other authenticatable arrives. That is the
     * host's bug, not grounds for this package to deny every request, so the
     * direct permission check below still gets its turn.
     */
    private static function gateAllows(string $permission, Authenticatable $user): bool
    {
        try {
            return Gate::forUser($user)->allows($permission);
        } catch (Throwable $e) {
            self::report($permission, $user, $e);

            return false;
        }
    }

    private static function report(string $permission, Authenticatable $user, Throwable $e): void
    {
        if (isset(self::$reported[$permission])) {
            return;
        }

        self::$reported[$permission] = true;

        Log::warning('filament-media-library: permission check could not be answered, denying.', [
            'permission' => $permission,
            'user_id' => $user->getAuthIdentifier(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'hint' => 'Seed the permission rows: php artisan db:seed --class="Batustun\FilamentMediaLibrary\Database\Seeders\MediaPermissionSeeder", or switch permissions off with MEDIA_LIBRARY_PERMISSIONS=false.',
        ]);
    }

    /** Test seam: forget which permissions have already been reported. */
    public static function forgetReported(): void
    {
        self::$reported = [];
    }
}
