<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RuntimeException;

/**
 * A user model that behaves like spatie/laravel-permission does in a real
 * application: asking about a permission that was never seeded THROWS
 * (PermissionDoesNotExist) instead of politely returning false.
 *
 * PermissionedUser cannot catch the bug this fixture exists for — it answers
 * every question, so a host application whose media.* rows were never created
 * looks identical to one that denied on purpose.
 */
class StrictPermissionedUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'users';

    /** Permission names that exist in the database at all. */
    public array $knownPermissions = [];

    /** @var array<int, string> */
    public array $grantedPermissions = [];

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (! in_array((string) $permission, $this->knownPermissions, true)) {
            throw new RuntimeException("There is no permission named `{$permission}`.");
        }

        return in_array((string) $permission, $this->grantedPermissions, true);
    }
}
