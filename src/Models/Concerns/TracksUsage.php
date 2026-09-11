<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;

/**
 * Answers "where is this used?" from the attachables pivot, so the library can
 * warn before deleting an asset a page still points at.
 */
trait TracksUsage
{
    /**
     * @param  class-string<Model>  $type
     * @return MorphToMany<Model, $this>
     */
    public function attachables(string $type): MorphToMany
    {
        return $this->morphedByMany(
            $type,
            'attachable',
            MediaLibraryConfig::table('morph'),
            'media_id',
        )->withPivot(['collection', 'sort_order'])->withTimestamps();
    }

    /**
     * How many models this item is currently attached to. Answered from the
     * eager-loaded aggregate when scopeWithUsageCount() was applied.
     */
    public function usageCount(): int
    {
        if (array_key_exists('usage_count', $this->attributes)) {
            return (int) $this->attributes['usage_count'];
        }

        return (int) DB::table(MediaLibraryConfig::table('morph'))
            ->where('media_id', $this->getKey())
            ->count();
    }

    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }

    /**
     * The models this item is attached to, grouped by class and collection.
     *
     * @return array<int, array{type: string, collection: string, count: int}>
     */
    public function usageBreakdown(): array
    {
        return DB::table(MediaLibraryConfig::table('morph'))
            ->where('media_id', $this->getKey())
            ->groupBy('attachable_type', 'collection')
            ->select('attachable_type', 'collection')
            ->selectRaw('count(*) as total')
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->attachable_type,
                'collection' => (string) $row->collection,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Eager-load how many models each item is attached to, as `usage_count`.
     * Reading it off a listing costs one extra aggregate instead of one query
     * per row.
     */
    public function scopeWithUsageCount(Builder $query): Builder
    {
        $pivot = MediaLibraryConfig::table('morph');
        $media = MediaLibraryConfig::table('media');

        return $query->selectRaw(
            "{$media}.*, (select count(*) from {$pivot} where {$pivot}.media_id = {$media}.id) as usage_count",
        );
    }
}
