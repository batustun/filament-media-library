<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * An ordering the host application adds to the library's sort menu.
 *
 *     MediaSorter::make('most_used')
 *         ->label('Most used')
 *         ->using(fn (Builder $query) => $query->withUsageCount()->orderByDesc('usage_count'))
 */
class MediaSorter
{
    protected ?string $label = null;

    protected ?Closure $callback = null;

    final public function __construct(protected string $key) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(str_replace(['_', '-'], ' ', $this->key));
    }

    /** @param Closure(Builder): mixed $callback */
    public function using(Closure $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    public function apply(Builder $query): void
    {
        if ($this->callback !== null) {
            ($this->callback)($query);
        }
    }
}
