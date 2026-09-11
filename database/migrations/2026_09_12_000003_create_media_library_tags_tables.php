<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags for media items.
 *
 * A real table rather than a JSON array on the item: tagging is only useful if
 * you can filter and count by it, and both are miserable against JSON on MySQL
 * and SQLite alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tags = MediaLibraryConfig::table('tags');
        $pivot = MediaLibraryConfig::table('taggables');
        $media = MediaLibraryConfig::table('media');

        if (! Schema::hasTable($tags)) {
            Schema::create($tags, function (Blueprint $blueprint): void {
                $blueprint->uuid('id')->primary();
                $blueprint->string('name', 120);
                $blueprint->string('slug', 120);
                $blueprint->string('color', 32)->nullable();
                $blueprint->timestamps();

                $blueprint->unique('slug');
            });
        }

        if (! Schema::hasTable($pivot)) {
            Schema::create($pivot, function (Blueprint $blueprint) use ($tags, $media): void {
                $blueprint->uuid('media_id');
                $blueprint->uuid('tag_id');

                $blueprint->primary(['media_id', 'tag_id']);
                $blueprint->index('tag_id');

                $blueprint->foreign('media_id')->references('id')->on($media)->cascadeOnDelete();
                $blueprint->foreign('tag_id')->references('id')->on($tags)->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaLibraryConfig::table('taggables'));
        Schema::dropIfExists(MediaLibraryConfig::table('tags'));
    }
};
