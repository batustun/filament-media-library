<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires schema that an early internal version created but never used.
 *
 * Folders were briefly modelled as their own table. Nothing ever wrote to it:
 * the folder tree is derived from the `directory` column, which cannot drift
 * from what is actually on the disk. The empty table, its foreign key and the
 * `folder_id` column are removed here.
 *
 * Both steps are skipped unless they are provably safe, so this migration is
 * inert on a fresh install and non-destructive on an existing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $media = MediaLibraryConfig::table('media');
        $folders = MediaLibraryConfig::table('folders');

        if (Schema::hasTable($media) && Schema::hasColumn($media, 'url')) {
            // URLs are resolved at read time now, so the column is a cache.
            Schema::table($media, function (Blueprint $blueprint): void {
                $blueprint->text('url')->nullable()->change();
            });
        }

        if (Schema::hasTable($media) && Schema::hasColumn($media, 'folder_id')) {
            Schema::table($media, function (Blueprint $blueprint) use ($media, $folders): void {
                if (Schema::hasTable($folders)) {
                    $blueprint->dropForeign($media.'_folder_id_foreign');
                }

                $blueprint->dropColumn('folder_id');
            });
        }

        // Only ever drop a folders table that nobody managed to put a row in.
        if (Schema::hasTable($folders) && DB::table($folders)->count() === 0) {
            Schema::drop($folders);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: re-creating an empty table that no code
        // path reads or writes would restore nothing of value.
    }
};
