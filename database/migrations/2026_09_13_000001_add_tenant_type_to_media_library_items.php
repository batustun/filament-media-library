<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which kind of tenant an item belongs to, not just which key.
 *
 * An application can have more than one tenanted panel — restaurants in one,
 * sellers in another — and their primary keys are separate sequences, so both
 * have a tenant 1. Keyed on the id alone, those two tenants were the same
 * tenant and saw each other's media.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MediaLibraryConfig::table('media');
        $column = MediaLibraryConfig::tenantTypeColumn();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->string($column, 191)->nullable()->after(MediaLibraryConfig::tenantColumn());
            $blueprint->index([MediaLibraryConfig::tenantColumn(), $column], 'fml_tenant_index');
        });
    }

    public function down(): void
    {
        $table = MediaLibraryConfig::table('media');
        $column = MediaLibraryConfig::tenantTypeColumn();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropIndex('fml_tenant_index');
            $blueprint->dropColumn($column);
        });
    }
};
