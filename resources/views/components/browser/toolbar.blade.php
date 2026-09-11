@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    $t = 'filament-media-library::filament-media-library';
    $selectedCount = count($selected);
@endphp

<div class="fml-panel fml-toolbar">
    <div class="fml-toolbar__search">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input
                type="search"
                wire:model.live.debounce.350ms="search"
                :placeholder="__($t.'.filters.search')"
                :aria-label="__($t.'.filters.search')"
            />
        </x-filament::input.wrapper>
    </div>

    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="kindFilter" :aria-label="__($t.'.filters.all_kinds')">
            <option value="">{{ __($t.'.filters.all_kinds') }}</option>
            @foreach (MediaKind::options() as $value => $label)
                <option
                    value="{{ $value }}"
                    @disabled(! empty($kinds ?? []) && ! in_array($value, $kinds, true))
                >{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>

    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="sort" :aria-label="__($t.'.filters.sort.newest')">
            <option value="newest">{{ __($t.'.filters.sort.newest') }}</option>
            <option value="oldest">{{ __($t.'.filters.sort.oldest') }}</option>
            <option value="name">{{ __($t.'.filters.sort.name') }}</option>
            <option value="size_desc">{{ __($t.'.filters.sort.size_desc') }}</option>
            <option value="size_asc">{{ __($t.'.filters.sort.size_asc') }}</option>
        </x-filament::input.select>
    </x-filament::input.wrapper>

    <x-filament::input.wrapper>
        <x-filament::input
            type="date"
            wire:model.live="dateFrom"
            :aria-label="__($t.'.filters.date_from')"
            :title="__($t.'.filters.date_from')"
        />
    </x-filament::input.wrapper>

    <x-filament::input.wrapper>
        <x-filament::input
            type="date"
            wire:model.live="dateTo"
            :aria-label="__($t.'.filters.date_to')"
            :title="__($t.'.filters.date_to')"
        />
    </x-filament::input.wrapper>

    @if ($this->hasFilters())
        <x-filament::icon-button
            icon="heroicon-m-x-circle"
            color="gray"
            size="sm"
            wire:click="clearFilters"
            :label="__($t.'.filters.clear')"
        />
    @endif

    <div class="fml-toolbar__end">
        @if ($selectedCount > 0)
            <span class="fml-muted fml-nowrap">{{ __($t.'.messages.selected', ['count' => $selectedCount]) }}</span>

            @if ($this->canMedia('manage'))
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-m-folder-arrow-down"
                    x-on:click="openDialog('move-selection', {}, @js($directory))"
                >
                    {{ __($t.'.actions.move') }}
                </x-filament::button>
            @endif

            @if ($this->canMedia('delete'))
                <x-filament::button
                    size="sm"
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="bulkDelete"
                    wire:confirm="{{ __($t.'.messages.confirm_delete_selected', ['count' => $selectedCount]) }}"
                >
                    {{ __($t.'.actions.delete_selected', ['count' => $selectedCount]) }}
                </x-filament::button>
            @endif

            <x-filament::link tag="button" color="gray" size="sm" wire:click="clearSelection">
                {{ __($t.'.actions.clear_selection') }}
            </x-filament::link>
        @endif

        <x-filament::icon-button
            icon="heroicon-m-squares-2x2"
            :color="$viewMode === 'grid' ? 'primary' : 'gray'"
            size="sm"
            wire:click="setView('grid')"
            :label="__($t.'.actions.grid_view')"
        />
        <x-filament::icon-button
            icon="heroicon-m-list-bullet"
            :color="$viewMode === 'list' ? 'primary' : 'gray'"
            size="sm"
            wire:click="setView('list')"
            :label="__($t.'.actions.list_view')"
        />
    </div>
</div>
