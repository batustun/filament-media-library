<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * The package's own fixtures answer every permission question politely, which
 * is exactly what a real application does not do. This case boots the genuine
 * spatie/laravel-permission — its service provider, its Gate::before callback
 * and its tables — so authorisation is tested against the thing that is
 * actually installed in the applications this package ships to.
 */
abstract class SpatiePermissionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
        $this->createPermissionTables();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            PermissionServiceProvider::class,
        ]);
    }

    /** Testbench ships no users table, and spatie's pivots point at one. */
    private function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
    }

    /**
     * spatie publishes its migration as a stub rather than loading it, so a
     * package test has to build the tables itself.
     */
    private function createPermissionTables(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $pivotRole = $columns['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columns['permission_pivot_key'] ?? 'permission_id';
        $morphKey = $columns['model_morph_key'] ?? 'model_id';

        Schema::create($tables['permissions'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($pivotPermission, $morphKey): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->primary([$pivotPermission, $morphKey, 'model_type']);
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($pivotRole, $morphKey): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->primary([$pivotRole, $morphKey, 'model_type']);
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);
            $table->primary([$pivotPermission, $pivotRole]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
