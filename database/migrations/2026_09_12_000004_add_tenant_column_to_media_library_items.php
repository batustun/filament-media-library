<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes items to a Filament tenant.
 *
 * Always added, never conditionally: a nullable column costs nothing on a
 * single-tenant install, whereas making the schema depend on a config flag
 * means turning tenancy on later requires a migration the application does not
 * have.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MediaLibraryConfig::table('media');
        $column = MediaLibraryConfig::tenantColumn();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->string($column, 191)->nullable()->after('disk');
            $blueprint->index([$column, 'disk']);
        });
    }

    public function down(): void
    {
        $table = MediaLibraryConfig::table('media');
        $column = MediaLibraryConfig::tenantColumn();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropIndex([$column, 'disk']);
            $blueprint->dropColumn($column);
        });
    }
};
