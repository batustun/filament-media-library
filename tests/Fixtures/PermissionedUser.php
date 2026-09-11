<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A user model mimicking spatie/laravel-permission's contract. */
class PermissionedUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'users';

    /** @var array<int, string> */
    public array $grantedPermissions = [];

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return in_array((string) $permission, $this->grantedPermissions, true);
    }
}
