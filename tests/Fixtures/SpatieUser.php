<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/** A user backed by the real spatie/laravel-permission traits and tables. */
class SpatieUser extends Authenticatable
{
    use HasRoles;

    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'users';

    protected string $guard_name = 'web';
}
