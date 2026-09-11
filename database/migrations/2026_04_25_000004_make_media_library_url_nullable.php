<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public URLs are resolved at read time, so the `url` column is now only a
 * fallback cache and may legitimately be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MediaLibraryConfig::table('media');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'url')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: rows legitimately created with a NULL
        // url could not be restored to NOT NULL without inventing data.
    }
};
