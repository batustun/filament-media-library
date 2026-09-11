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
        $table = MediaLibraryConfig::table('morph');
        $mediaTable = MediaLibraryConfig::table('media');
        $keyType = MediaLibraryConfig::morphKeyType();

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($mediaTable, $keyType): void {
            $blueprint->uuid('media_id');

            // Matches the primary-key type of the models you attach media to.
            match ($keyType) {
                'uuid' => $blueprint->uuidMorphs('attachable'),
                'ulid' => $blueprint->ulidMorphs('attachable'),
                default => $blueprint->morphs('attachable'),
            };

            $blueprint->string('collection', 64)->default('default');
            $blueprint->unsignedInteger('sort_order')->default(0);
            $blueprint->timestamps();

            $blueprint->primary(['media_id', 'attachable_id', 'attachable_type', 'collection']);
            $blueprint->index(['media_id', 'collection']);

            $blueprint->foreign('media_id')
                ->references('id')
                ->on($mediaTable)
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MediaLibraryConfig::table('morph'));
    }
};
