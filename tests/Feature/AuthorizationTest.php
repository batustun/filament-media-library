<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionedUser;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionlessUser;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\StrictPermissionedUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config()->set('filament-media-library.permissions.enabled', true);

    // The "already reported" set is process-wide by design (one log line per
    // broken permission, not one per click), so it must not leak between tests.
    Authorize::forgetReported();
});

it('allows everything when permissions are disabled', function () {
    config()->set('filament-media-library.permissions.enabled', false);

    expect(Authorize::check('delete'))->toBeTrue();
});

it('denies a guest when permissions are enabled', function () {
    expect(Authorize::check('view'))->toBeFalse();
});

it('grants only the abilities the user actually holds', function () {
    $user = new PermissionedUser(['id' => 1]);
    $user->grantedPermissions = ['media.view', 'media.upload'];

    Auth::setUser($user);

    expect(Authorize::check('view'))->toBeTrue()
        ->and(Authorize::check('upload'))->toBeTrue()
        ->and(Authorize::check('delete'))->toBeFalse()
        ->and(Authorize::check('manage'))->toBeFalse();
});

it('denies a user model that cannot answer permission questions', function () {
    Auth::setUser(new PermissionlessUser(['id' => 1]));

    expect(Authorize::check('view'))->toBeFalse();
});

it('aborts with 403 rather than silently continuing', function () {
    Auth::setUser(new PermissionlessUser(['id' => 1]));

    expect(fn () => Authorize::ensure('delete'))->toThrow(HttpException::class);
});

it('honours renamed permission names from config', function () {
    config()->set('filament-media-library.permissions.delete', 'assets.destroy');

    $user = new PermissionedUser(['id' => 1]);
    $user->grantedPermissions = ['assets.destroy'];

    Auth::setUser($user);

    expect(Authorize::check('delete'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The host application's Gate
|--------------------------------------------------------------------------
|
| Every real Laravel application authorises through the Gate: a super-admin
| bypass is a Gate::before callback, spatie/laravel-permission publishes its
| own Gate::before, and custom abilities are Gate::define. A check that calls
| hasPermissionTo() directly sees none of it and denies people the rest of the
| application lets in.
|
*/

it('honours a super admin bypass registered as Gate::before', function () {
    Gate::before(fn (): bool => true);

    $user = new StrictPermissionedUser(['id' => 1]);
    // Production shape: the media.* rows were never seeded, so asking about
    // them throws rather than returning false.
    $user->knownPermissions = [];

    Auth::setUser($user);

    expect(Authorize::check('view'))->toBeTrue()
        ->and(Authorize::check('delete'))->toBeTrue();

    Authorize::ensure('view');
});

it('honours an ability the host application defined on the Gate', function () {
    Gate::define('media.view', fn (): bool => true);

    $user = new PermissionlessUser(['id' => 1]);
    Auth::setUser($user);

    expect(Authorize::check('view'))->toBeTrue()
        ->and(Authorize::check('delete'))->toBeFalse();
});

it('still denies when the Gate declines and the permission is not held', function () {
    Gate::before(fn () => null);

    $user = new PermissionedUser(['id' => 1]);
    $user->grantedPermissions = ['media.view'];

    Auth::setUser($user);

    expect(Authorize::check('view'))->toBeTrue()
        ->and(Authorize::check('delete'))->toBeFalse();
});

it('reports a permission lookup that blew up instead of swallowing it', function () {
    $log = Log::spy();

    $user = new StrictPermissionedUser(['id' => 1]);
    $user->knownPermissions = [];

    Auth::setUser($user);

    expect(Authorize::check('view'))->toBeFalse();

    // A 403 with nothing in the log is how a missing seeder survives for weeks:
    // the browser shows a bare console error and the server says nothing.
    $log->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'filament-media-library')
            && ($context['permission'] ?? null) === 'media.view')
        ->atLeast()->once();
});
