@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;
    use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;

    $paginator = $this->items();
    $folders = $this->folderTree();
    $detail = $this->detailRecord();
    $totalSize = $this->diskUsageBytes();
    $disks = $this->availableDisks();
    $canUpload = $this->canMedia('upload');
    $canDelete = $this->canMedia('delete');
    $t = 'filament-media-library::filament-media-library';
@endphp

<x-filament-panels::page>
    <div
        class="fml"
        x-on:keydown.escape.window="$wire.closeDetail()"
    >
        <div @class(['fml__layout', 'fml__layout--with-detail' => (bool) $detail])>
            {{-- Sidebar --}}
            <aside class="fml__sidebar">
                @if (count($disks) > 1)
                    <div class="fml__panel fml__panel--padded">
                        <label class="fml__label" for="fml-disk">{{ __($t.'.fields.disk') }}</label>
                        <select id="fml-disk" wire:model.live="disk" class="fml__select" style="margin-top:.25rem">
                            @foreach ($disks as $diskName)
                                <option value="{{ $diskName }}">{{ $diskName }}</option>
                            @endforeach
                        </select>
                        <p class="fml__hint">
                            {{ __($t.'.fields.total') }}: <strong>{{ MimeKindResolver::formatBytes($totalSize) }}</strong>
                        </p>
                    </div>
                @endif

                <div class="fml__panel">
                    <div class="fml__panel-header">
                        <span class="fml__label">{{ __($t.'.fields.folders') }}</span>
                        <button type="button" wire:click="clearFolder" class="fml__btn fml__btn--link">
                            {{ __($t.'.actions.show_all') }}
                        </button>
                    </div>

                    <ul class="fml__folders">
                        <li>
                            <button
                                type="button"
                                wire:click="clearFolder"
                                @class(['fml__folder', 'is-active' => $directory === ''])
                            >
                                <x-filament::icon icon="heroicon-o-folder-open" style="width:1rem;height:1rem;flex-shrink:0" />
                                <span class="fml__folder-name">{{ __($t.'.fields.root') }}</span>
                            </button>
                        </li>

                        @foreach ($folders as $folder)
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectFolder(@js($folder['path']))"
                                    @class(['fml__folder', 'is-active' => $directory === $folder['path']])
                                    style="padding-left: {{ ($folder['depth'] * 0.75) + 0.5 }}rem"
                                    title="{{ $folder['path'] }}"
                                >
                                    <x-filament::icon icon="heroicon-o-folder" style="width:1rem;height:1rem;flex-shrink:0" />
                                    <span class="fml__folder-name">{{ $folder['name'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            {{-- Main --}}
            <section class="fml__main">
                <div class="fml__panel fml__toolbar">
                    <div class="fml__search">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="fml__search-icon" />
                        <input
                            type="search"
                            wire:model.live.debounce.350ms="search"
                            placeholder="{{ __($t.'.filters.search_placeholder') }}"
                            class="fml__input"
                            aria-label="{{ __($t.'.filters.search_placeholder') }}"
                        />
                    </div>

                    <select wire:model.live="kindFilter" class="fml__select" style="width:auto" aria-label="{{ __($t.'.filters.all_kinds') }}">
                        <option value="">{{ __($t.'.filters.all_kinds') }}</option>
                        @foreach (MediaKind::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="sort" class="fml__select" style="width:auto" aria-label="{{ __($t.'.filters.sort.newest') }}">
                        <option value="newest">{{ __($t.'.filters.sort.newest') }}</option>
                        <option value="oldest">{{ __($t.'.filters.sort.oldest') }}</option>
                        <option value="name">{{ __($t.'.filters.sort.name') }}</option>
                        <option value="size_desc">{{ __($t.'.filters.sort.size_desc') }}</option>
                        <option value="size_asc">{{ __($t.'.filters.sort.size_asc') }}</option>
                    </select>

                    <div class="fml__viewtoggle fml__toolbar-spacer">
                        <button
                            type="button"
                            wire:click="setView('grid')"
                            @class(['is-active' => $viewMode === 'grid'])
                            title="{{ __($t.'.actions.grid_view') }}"
                        >
                            <x-filament::icon icon="heroicon-o-squares-2x2" style="width:1rem;height:1rem" />
                        </button>
                        <button
                            type="button"
                            wire:click="setView('list')"
                            @class(['is-active' => $viewMode === 'list'])
                            title="{{ __($t.'.actions.list_view') }}"
                        >
                            <x-filament::icon icon="heroicon-o-list-bullet" style="width:1rem;height:1rem" />
                        </button>
                    </div>

                    @if ($canDelete && count($selected) > 0)
                        <button
                            type="button"
                            wire:click="bulkDelete"
                            wire:confirm="{{ __($t.'.messages.confirm_delete_selected', ['count' => count($selected)]) }}"
                            class="fml__btn fml__btn--danger"
                        >
                            <x-filament::icon icon="heroicon-o-trash" style="width:1rem;height:1rem" />
                            {{ __($t.'.actions.delete_selected', ['count' => count($selected)]) }}
                        </button>
                        <button type="button" wire:click="clearSelection" class="fml__btn fml__btn--link">
                            {{ __($t.'.actions.clear_selection') }}
                        </button>
                    @endif
                </div>

                @if ($canUpload)
                    <div
                        x-data="fmlDropZone()"
                        x-on:dragover.prevent="active = true"
                        x-on:dragleave.prevent="active = false"
                        x-on:drop.prevent="handleDrop($event)"
                        x-bind:class="active && 'is-active'"
                        class="fml__dropzone"
                    >
                        <div class="fml__dropzone-inner">
                            <label class="fml__btn fml__btn--primary">
                                <x-filament::icon icon="heroicon-o-cloud-arrow-up" style="width:1rem;height:1rem" />
                                {{ __($t.'.actions.choose_files') }}
                                <input type="file" multiple wire:model="uploads" x-ref="fileInput" class="fml__file-input" />
                            </label>

                            <button
                                type="button"
                                wire:click="uploadFiles"
                                wire:loading.attr="disabled"
                                wire:target="uploads,uploadFiles"
                                class="fml__btn fml__btn--success"
                                @disabled(empty($uploads))
                            >
                                <span wire:loading.remove wire:target="uploads,uploadFiles">{{ __($t.'.actions.upload') }}</span>
                                <span wire:loading wire:target="uploads,uploadFiles">{{ __($t.'.actions.uploading') }}</span>
                            </button>

                            <p class="fml__dropzone-hint">
                                {{ __($t.'.messages.drop_hint', ['folder' => $directory ?: '/']) }}
                            </p>
                        </div>

                        <div wire:loading.flex wire:target="uploads" class="fml__dropzone-overlay" style="display:none">
                            {{ __($t.'.messages.preparing') }}
                        </div>
                    </div>
                @endif

                @if ($paginator->total() === 0)
                    <div class="fml__empty">
                        <x-filament::icon icon="heroicon-o-photo" style="width:2.5rem;height:2.5rem;opacity:.4" />
                        <p>{{ __($t.'.messages.empty') }}</p>
                    </div>
                @elseif ($viewMode === 'grid')
                    <div class="fml__grid">
                        @foreach ($paginator as $item)
                            @include('filament-media-library::components.media-card', ['item' => $item, 'selectable' => true])
                        @endforeach
                    </div>
                @else
                    <div class="fml__table-wrap">
                        <table class="fml__table">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem"></th>
                                    <th>{{ __($t.'.fields.file') }}</th>
                                    <th>{{ __($t.'.fields.type') }}</th>
                                    <th>{{ __($t.'.fields.size') }}</th>
                                    <th>{{ __($t.'.fields.folder') }}</th>
                                    <th>{{ __($t.'.fields.date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($paginator as $item)
                                    @include('filament-media-library::components.media-row', ['item' => $item])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div>{{ $paginator->links() }}</div>
            </section>

            @if ($detail)
                @include('filament-media-library::components.media-detail', ['item' => $detail])
            @endif
        </div>
    </div>

    @script
        <script>
            window.fmlDropZone = () => ({
                active: false,
                handleDrop (event) {
                    this.active = false

                    const files = event.dataTransfer?.files
                    if (! files || ! files.length) return

                    const input = this.$refs.fileInput
                    if (! input) return

                    // Re-assign through a DataTransfer so Livewire's wire:model
                    // observer sees a real change event.
                    const transfer = new DataTransfer()
                    for (const file of files) transfer.items.add(file)

                    input.files = transfer.files
                    input.dispatchEvent(new Event('change', { bubbles: true }))
                },
            })
        </script>
    @endscript
</x-filament-panels::page>
