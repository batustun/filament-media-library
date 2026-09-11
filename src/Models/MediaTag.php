<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 */
class MediaTag extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return MediaLibraryConfig::table('tags');
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, MediaLibraryConfig::table('taggables'), 'tag_id', 'media_id');
    }

    /**
     * Find or create a tag by name.
     *
     * Matching is on the slug, so "Product Shots", "product shots" and
     * "product-shots" are the same tag rather than three near-duplicates.
     */
    public static function findOrCreateByName(string $name): ?self
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        return static::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
    }
}
