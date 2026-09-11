<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A filter the host application adds to the library toolbar.
 *
 * Deliberately one small object rather than a filter class plus a modification
 * class plus a driver callback: a filter is a label, an input and a query
 * clause, and anything more ceremonious just moves that same information
 * further from where it is read.
 *
 *     MediaFilter::make('featured')
 *         ->label('Featured only')
 *         ->boolean()
 *         ->using(fn (Builder $query) => $query->where('meta->custom->featured', true))
 *
 *     MediaFilter::make('licence')
 *         ->options(['rf' => 'Royalty free', 'rm' => 'Rights managed'])
 *         ->using(fn (Builder $query, string $value) => $query->where('meta->custom->licence', $value))
 */
class MediaFilter
{
    protected ?string $label = null;

    protected string $type = 'select';

    /** @var array<string, string> */
    protected array $options = [];

    protected ?Closure $callback = null;

    protected ?string $placeholder = null;

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

    /** @param array<string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;
        $this->type = 'select';

        return $this;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function boolean(): static
    {
        $this->type = 'boolean';

        return $this;
    }

    public function text(?string $placeholder = null): static
    {
        $this->type = 'text';
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? $this->getLabel();
    }

    /**
     * @param  Closure(Builder, mixed): mixed  $callback
     */
    public function using(Closure $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    /** Apply this filter's clause, unless the value is effectively empty. */
    public function apply(Builder $query, mixed $value): void
    {
        if ($this->callback === null) {
            return;
        }

        if ($value === null || $value === '' || $value === []) {
            return;
        }

        if ($this->type === 'boolean' && ! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        ($this->callback)($query, $value);
    }
}
