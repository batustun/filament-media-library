<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Filters\MediaFilter;
use Batustun\FilamentMediaLibrary\Filters\MediaSorter;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Models\MediaTag;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Narrowing the listing: search, type, tag, size, dates, and whatever filters
 * and sorters the host application registered.
 */
trait FiltersMedia
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'kind', except: '')]
    public string $kindFilter = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'tag', except: '')]
    public string $tagFilter = '';

    /** Minimum and maximum size in megabytes. */
    #[Url(as: 'min', except: '')]
    public string $sizeMin = '';

    #[Url(as: 'max', except: '')]
    public string $sizeMax = '';

    public int $perPage = 48;

    /** @var array<string, mixed> Values for filters registered by the host application. */
    public array $customFilters = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingKindFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDirectory(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedTagFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSizeMin(): void
    {
        $this->resetPage();
    }

    public function updatedSizeMax(): void
    {
        $this->resetPage();
    }

    /**
     * Livewire lets the browser assign any public property, so the page size
     * has to be forced back into the offered options — otherwise a crafted
     * request could ask for a single page of the entire library.
     */
    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, MediaLibraryConfig::pageSizes(), true)) {
            $this->perPage = MediaLibraryConfig::defaultPageSize();
        }

        $this->resetPage();
    }

    /** @return array<int, int> */
    public function pageSizeOptions(): array
    {
        return MediaLibraryConfig::pageSizes();
    }

    public function hasFilters(): bool
    {
        foreach (['search', 'kindFilter', 'dateFrom', 'dateTo', 'tagFilter', 'sizeMin', 'sizeMax'] as $property) {
            if ($this->{$property} !== '') {
                return true;
            }
        }

        return $this->customFilters !== [];
    }

    public function clearFilters(): void
    {
        foreach (['search', 'kindFilter', 'dateFrom', 'dateTo', 'tagFilter', 'sizeMin', 'sizeMax'] as $property) {
            $this->{$property} = '';
        }

        $this->customFilters = [];
        $this->resetPage();
    }

    /**
     * Cached alongside the folder tree: a SUM over the whole table on every
     * render is a full scan of a library that only changes on upload/delete.
     */
    /** @return array<int, MediaFilter> */
    public function availableFilters(): array
    {
        return MediaLibraryConfig::customFilters();
    }

    /** @return array<int, MediaSorter> */
    public function availableSorters(): array
    {
        return MediaLibraryConfig::customSorters();
    }

    protected function customSorter(string $key): ?MediaSorter
    {
        foreach ($this->availableSorters() as $sorter) {
            if ($sorter->getKey() === $key) {
                return $sorter;
            }
        }

        return null;
    }

    protected function applyCustomFilters(Builder $query): void
    {
        foreach ($this->availableFilters() as $filter) {
            $filter->apply($query, $this->customFilters[$filter->getKey()] ?? null);
        }
    }

    /** @return array<int, string> Tag slugs present on this source, for the filter. */
    public function availableTags(): array
    {
        if (! MediaLibraryConfig::tagsEnabled()) {
            return [];
        }

        return $this->remember('tags', fn (): array => MediaTag::query()
            ->whereHas('media', fn (Builder $nested) => $nested->where('disk', $this->disk))
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all());
    }

    protected function parseDate(string $value): ?CarbonInterface
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function items(): LengthAwarePaginator
    {
        $query = Media::query()->onDisk($this->disk);

        if ($this->directory !== '') {
            $query->inDirectory($this->directory);
        }

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->kindFilter !== '') {
            $query->ofKind($this->kindFilter);
        }

        // Dates arrive from a query string, so anything unparseable is ignored
        // rather than throwing the whole listing away.
        if ($from = $this->parseDate($this->dateFrom)) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($this->dateTo)) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        if ($this->tagFilter !== '') {
            $query->taggedWith($this->tagFilter);
        }

        if (is_numeric($this->sizeMin)) {
            $query->where('size', '>=', (int) ((float) $this->sizeMin * 1024 * 1024));
        }

        if (is_numeric($this->sizeMax)) {
            $query->where('size', '<=', (int) ((float) $this->sizeMax * 1024 * 1024));
        }

        $this->applyCustomFilters($query);

        $customSorter = $this->customSorter($this->sort);

        if ($customSorter !== null) {
            $customSorter->apply($query);
        } else {
            $query = match ($this->sort) {
                'oldest' => $query->orderBy('created_at'),
                'name' => $query->orderBy('name'),
                'size_desc' => $query->orderByDesc('size'),
                'size_asc' => $query->orderBy('size'),
                default => $query->orderByDesc('created_at'),
            };
        }

        // Deterministic tiebreaker so pagination cannot repeat or skip rows
        // when many records share a timestamp.
        return $query->orderBy('id')->paginate($this->perPage);
    }
}
