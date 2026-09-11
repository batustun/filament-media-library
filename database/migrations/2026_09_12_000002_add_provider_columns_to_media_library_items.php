<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an item come from a platform instead of a disk.
 *
 * `provider` names the platform (null = an ordinary file on a disk) and
 * `external_id` is that platform's identifier for the asset — a Bunny Stream
 * video GUID, for example. Both are nullable, so nothing changes for the
 * disk-backed items that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = MediaLibraryConfig::table('media');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'provider')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('provider', 64)->nullable()->after('disk');
            $blueprint->string('external_id', 191)->nullable()->after('provider');

            $blueprint->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        $table = MediaLibraryConfig::table('media');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'provider')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['provider', 'external_id']);
            $blueprint->dropColumn(['provider', 'external_id']);
        });
    }
};
