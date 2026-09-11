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
        $table = MediaLibraryConfig::table('folders');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('parent_id')->nullable()->index();
            $blueprint->string('disk', 64)->index();
            $blueprint->string('name', 255);
            $blueprint->string('path', 512)->index();
            $blueprint->unsignedInteger('depth')->default(0);
            $blueprint->unsignedInteger('sort_order')->default(0);
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaLibraryConfig::table('folders'));
    }
};
