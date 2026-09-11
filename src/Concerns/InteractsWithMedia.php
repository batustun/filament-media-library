<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Attach media items to any Eloquent model.
 *
 *     class Post extends Model
 *     {
 *         use InteractsWithMedia;
 *     }
 *
 *     $post->media();                          // every attachment
 *     $post->media('cover');                   // one collection
 *     $post->attachMedia($media, 'cover');
 *     $post->detachMedia($media, 'cover');
 *     $post->syncMedia([$id1, $id2], 'gallery');
 */
trait InteractsWithMedia
{
    /** @return MorphToMany<Media, $this> */
    public function media(?string $collection = null): MorphToMany
    {
        $pivot = MediaLibraryConfig::table('morph');

        $relation = $this->morphToMany(
            Media::class,
            'attachable',
            $pivot,
            null,
            'media_id',
        )->withPivot(['collection', 'sort_order'])->withTimestamps();

        if ($collection !== null) {
            $relation->wherePivot('collection', $collection);
        }

        return $relation->orderBy($pivot.'.sort_order');
    }

    /**
     * Attach an item to a collection, or update its position if it is already
     * there.
     *
     * Scoped to the collection on purpose: the pivot's primary key includes
     * `collection`, so the same item can legitimately be a post's cover AND
     * appear in its gallery. Syncing against the unscoped relation would treat
     * the second attach as an update of the first and silently collapse them
     * into one row.
     */
    public function attachMedia(Media|string $media, string $collection = 'default', int $sortOrder = 0): void
    {
        $this->media($collection)->syncWithoutDetaching([
            ($media instanceof Media ? $media->id : $media) => [
                'collection' => $collection,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    public function detachMedia(Media|string $media, string $collection = 'default'): void
    {
        $this->media()
            ->wherePivot('collection', $collection)
            ->detach($media instanceof Media ? $media->id : $media);
    }

    /** @param array<int, string> $mediaIds */
    public function syncMedia(array $mediaIds, string $collection = 'default'): void
    {
        $payload = [];

        foreach (array_values($mediaIds) as $index => $mediaId) {
            $payload[$mediaId] = ['collection' => $collection, 'sort_order' => $index];
        }

        $this->media()
            ->wherePivot('collection', $collection)
            ->sync($payload);
    }
}
