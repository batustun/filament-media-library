<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A user model that has no hasPermissionTo() at all. */
class PermissionlessUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'users';
}
