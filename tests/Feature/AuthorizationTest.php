<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionedUser;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionlessUser;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config()->set('filament-media-library.permissions.enabled', true);
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
