<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = MediaLibraryConfig::table('media');
        $foldersTable = MediaLibraryConfig::table('folders');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($foldersTable): void {
            $blueprint->uuid('id')->primary();
            $blueprint->string('disk', 64)->index();
            $blueprint->string('directory', 512)->nullable()->index();
            // 512 rather than 1024: MySQL's utf8mb4 index prefix limit makes a
            // unique(disk, path) impossible at 1024.
            $blueprint->string('path', 512);
            $blueprint->text('url')->nullable();
            $blueprint->string('name', 255);
            $blueprint->string('title', 255)->nullable();
            $blueprint->string('alt', 500)->nullable();
            $blueprint->text('description')->nullable();
            $blueprint->string('mime_type', 127)->nullable()->index();
            $blueprint->string('kind', 16)->default('other')->index();
            $blueprint->unsignedBigInteger('size')->default(0);
            $blueprint->unsignedInteger('width')->nullable();
            $blueprint->unsignedInteger('height')->nullable();
            $blueprint->unsignedInteger('duration')->nullable();
            $blueprint->uuid('folder_id')->nullable();
            $blueprint->string('hash', 64)->nullable()->index();
            $blueprint->json('meta')->nullable();
            $blueprint->string('uploaded_by')->nullable()->index();
            $blueprint->timestamps();

            $blueprint->unique(['disk', 'path']);
            $blueprint->index(['disk', 'created_at']);

            $blueprint->foreign('folder_id')
                ->references('id')
                ->on($foldersTable)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaLibraryConfig::table('media'));
    }
};
