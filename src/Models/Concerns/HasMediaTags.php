<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Models\MediaTag;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Free-form labels an editor can attach to an item and filter the library by. */
trait HasMediaTags
{
    /** @return BelongsToMany<MediaTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaTag::class,
            MediaLibraryConfig::table('taggables'),
            'media_id',
            'tag_id',
        )->orderBy('name');
    }

    /**
     * Replace this item's tags with the given names, creating any that do not
     * exist yet.
     *
     * @param  array<int, mixed>  $names  Tag names; non-strings are ignored
     */
    public function syncTagNames(array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            if (is_string($name) && ($tag = MediaTag::findOrCreateByName($name))) {
                $ids[] = $tag->getKey();
            }
        }

        $this->tags()->sync(array_values(array_unique($ids)));
        $this->unsetRelation('tags');
    }

    /** @return array<int, string> */
    public function tagNames(): array
    {
        return $this->tags()->pluck('name')->all();
    }

    public function scopeTaggedWith(Builder $query, string $slug): Builder
    {
        return $query->whereHas('tags', fn (Builder $nested): Builder => $nested->where('slug', $slug));
    }
}
