<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $disk
 * @property string $name
 * @property string $path
 * @property int $depth
 * @property int $sort_order
 * @property array<string, mixed>|null $meta
 */
class MediaFolder extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return MediaLibraryConfig::table('folders');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }
}
