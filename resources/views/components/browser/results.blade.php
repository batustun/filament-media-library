@php
    $t = 'filament-media-library::filament-media-library';
    $isFiltered = $this->hasFilters();
@endphp

<div
    wire:loading.class="fml-loading"
    wire:target="search,kindFilter,sort,disk,directory,dateFrom,dateTo,gotoPage,previousPage,nextPage"
>
    @if ($paginator->total() === 0)
        <x-filament::empty-state
            icon="heroicon-o-photo"
            icon-color="gray"
            :heading="$isFiltered ? __($t.'.messages.no_results') : __($t.'.messages.empty')"
            :description="$isFiltered ? __($t.'.messages.no_results_hint') : __($t.'.messages.empty_hint')"
        />
    @elseif ($viewMode === 'grid')
        <div class="fml-grid" role="listbox" aria-multiselectable="true" x-ref="grid">
            @foreach ($paginator as $item)
                @include('filament-media-library::components.browser.card', [
                    'item' => $item,
                    'showUsage' => $showUsage ?? false,
                ])
            @endforeach
        </div>
    @else
        <div class="fml-table-wrap" x-ref="grid">
            <table class="fml-table">
                <thead>
                    <tr>
                        <th class="fml-table__check"></th>
                        <th>{{ __($t.'.fields.file') }}</th>
                        <th>{{ __($t.'.fields.type') }}</th>
                        <th>{{ __($t.'.fields.size') }}</th>
                        <th>{{ __($t.'.fields.folder') }}</th>
                        <th>{{ __($t.'.fields.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $item)
                        @include('filament-media-library::components.browser.row', ['item' => $item])
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($paginator->hasPages() || $paginator->total() > $paginator->perPage())
        <div class="fml-pagination">
            <x-filament::pagination
                :paginator="$paginator"
                :page-options="$this->pageSizeOptions()"
                current-page-option-property="perPage"
            />
        </div>
    @endif
</div>
