@php
    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';
    $isFiltered = $this->hasFilters();
    // Folders are only meaningful while browsing; a filtered search is a search
    // across everything, not a place you are standing in.
    $childFolders = $isFiltered ? [] : $this->childFolders();
@endphp

<div
    class="fml-results"
    wire:loading.class="fml-loading"
    wire:target="search,kindFilter,sort,disk,directory,dateFrom,dateTo,gotoPage,previousPage,nextPage"
>
    @if ($childFolders !== [])
        <div class="fml-grid fml-grid--folders">
            @foreach ($childFolders as $folder)
                <button
                    type="button"
                    class="fml-folder-card"
                    wire:click="selectFolder({!! $js($folder['path']) !!})"
                    title="{{ $folder['path'] }}"
                >
                    <x-filament::icon icon="heroicon-o-folder" class="fml-icon-lg" />
                    <span class="fml-folder-card__name">{{ $folder['name'] }}</span>
                    <span class="fml-folder-card__count">
                        {{ trans_choice($t.'.messages.items_count', $folder['count'], ['count' => $folder['count']]) }}
                    </span>
                </button>
            @endforeach
        </div>
    @endif

    @if ($paginator->total() === 0 && $childFolders !== [])
        {{-- Folders above, no files at this level: saying "empty" would be wrong. --}}
    @elseif ($paginator->total() === 0)
        {{-- Fills the space a grid would, so the modal keeps one height. --}}
        <div class="fml-empty">
            <x-filament::empty-state
                icon="heroicon-o-photo"
                icon-color="gray"
                :heading="$isFiltered ? __($t.'.messages.no_results') : __($t.'.messages.empty')"
                :description="$isFiltered ? __($t.'.messages.no_results_hint') : __($t.'.messages.empty_hint')"
            />
        </div>
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
                        <th class="fml-table__peek"><span class="fml-sr-only">{{ __($t.'.actions.preview') }}</span></th>
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
