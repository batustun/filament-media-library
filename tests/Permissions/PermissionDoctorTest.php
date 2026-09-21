<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Database\Seeders\MediaPermissionSeeder;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\SpatieUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Permissions, against the real spatie/laravel-permission
|--------------------------------------------------------------------------
|
| Two failures live here, both of which shipped and both of which look
| identical from the browser: a bare 403 on POST /livewire/update.
|
|  1. The host application's super admin is a Gate::before callback. A check
|     that calls hasPermissionTo() directly never consults it, so the one
|     account that is allowed everything is allowed nothing here.
|  2. Switching permissions on without seeding the rows makes every lookup
|     throw, which used to be swallowed into a silent denial.
|
| Fixtures that answer politely cannot catch either, so this file boots the
| real package.
|
*/

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', true);
    Authorize::forgetReported();
});

function superAdmin(): SpatieUser
{
    $role = Role::findOrCreate('super_admin', 'web');
    $user = SpatieUser::create(['id' => 1, 'name' => 'Root', 'email' => 'root@example.test', 'password' => 'x']);
    $user->assignRole($role);

    // How every Laravel application spells "this account may do anything".
    Gate::before(fn (SpatieUser $user): ?bool => $user->hasRole('super_admin') ? true : null);

    return $user;
}

it('lets a super admin in even though no media permission row exists', function () {
    $user = superAdmin();

    expect(Authorize::check('view', $user))->toBeTrue()
        ->and(Authorize::check('manage', $user))->toBeTrue();
});

it('opens the picker for that super admin rather than answering 403', function () {
    $this->actingAs(superAdmin());

    Livewire::test(MediaPicker::class, ['targetStatePath' => 'data.cover'])->assertOk();
});

it('still refuses an ordinary user who holds no media permission', function () {
    $user = SpatieUser::create(['id' => 2, 'name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'x']);

    expect(Authorize::check('view', $user))->toBeFalse();

    $this->actingAs($user);
    Livewire::test(MediaPicker::class, ['targetStatePath' => 'data.cover'])->assertForbidden();
});

it('lets an ordinary user in once the permission is seeded and granted', function () {
    (new MediaPermissionSeeder)->run();

    $user = SpatieUser::create(['id' => 3, 'name' => 'Editor', 'email' => 'editor2@example.test', 'password' => 'x']);
    $user->givePermissionTo('media.view');

    expect(Authorize::check('view', $user))->toBeTrue()
        ->and(Authorize::check('delete', $user))->toBeFalse();
});

it('makes the doctor say the library is unopenable when the rows are missing', function () {
    $this->artisan('media-library:doctor', ['--disk' => 'public'])
        ->expectsOutputToContain('media.view')
        ->expectsOutputToContain('db:seed')
        ->assertSuccessful();
});

it('makes the doctor confirm the rows once they are seeded', function () {
    (new MediaPermissionSeeder)->run();

    $this->artisan('media-library:doctor', ['--disk' => 'public'])
        ->expectsOutputToContain('all four rows exist')
        ->assertSuccessful();
});

it('makes the doctor say so when permission checks are switched off entirely', function () {
    config()->set('filament-media-library.permissions.enabled', false);

    $this->artisan('media-library:doctor', ['--disk' => 'public'])
        ->expectsOutputToContain('Permission checks are OFF')
        ->assertSuccessful();
});
