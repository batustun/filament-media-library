<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Database\Seeders;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Optional: only useful when spatie/laravel-permission is installed.
 *
 *     php artisan db:seed --class="Batustun\FilamentMediaLibrary\Database\Seeders\MediaPermissionSeeder"
 */
class MediaPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(Permission::class)) {
            $this->command?->warn('spatie/laravel-permission is not installed — nothing to seed.');

            return;
        }

        $guard = MediaLibraryConfig::permissionGuard();

        $permissions = array_map(
            static fn (string $ability): string => MediaLibraryConfig::permission($ability),
            ['view', 'upload', 'delete', 'manage'],
        );

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, $guard);
        }

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            $role?->givePermissionTo($permissions);
        }

        $this->command?->info('Media library permissions seeded: '.implode(', ', $permissions));
    }
}
