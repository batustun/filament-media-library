@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;
    use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;

    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $onEachSide = 1;

    $pageWindow = function (int $current, int $last, int $eachSide): array {
        if ($last <= 1) {
            return [];
        }
        $window = range(max(1, $current - $eachSide), min($last, $current + $eachSide));
        $pages = [];
        if (! in_array(1, $window, true)) {
            $pages[] = 1;
            if ($window[0] > 2) {
                $pages[] = '…';
            }
        }
        foreach ($window as $p) {
            $pages[] = $p;
        }
        if (! in_array($last, $window, true)) {
            if (end($window) < $last - 1) {
                $pages[] = '…';
            }
            $pages[] = $last;
        }

        return $pages;
    };

    // Show folder cards in the grid only when at the library root and no filters applied.
    $showFolderCards = $directory === '' && $search === '' && $kindFilter === '';
@endphp

@assets
<style>
    .ml-picker { display: flex; flex-direction: column; gap: 1rem; min-height: 50vh; max-height: calc(100vh - 12rem); width: 100%; position: relative; overflow: hidden; }

    .ml-picker__toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .ml-picker__toolbar-left { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
    .ml-picker__toolbar-right { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
    .ml-picker__count { font-size: 0.875rem; color: rgb(107 114 128); }
    .dark .ml-picker__count { color: rgb(156 163 175); }

    .ml-picker__layout { display: grid; grid-template-columns: minmax(0, 240px) minmax(0, 1fr); gap: 1rem; flex: 1; min-height: 0; }
    @media (max-width: 640px) { .ml-picker__layout { grid-template-columns: 1fr; } }

    .ml-picker__panel { background: transparent; border: 1px solid rgb(229 231 235); border-radius: 0.75rem; overflow: hidden; }
    .dark .ml-picker__panel { border-color: rgba(255,255,255,0.08); }
    .ml-picker__panel-header { display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 0.875rem; border-bottom: 1px solid rgb(229 231 235); font-size: 0.8125rem; font-weight: 600; color: rgb(17 24 39); }
    .dark .ml-picker__panel-header { color: rgb(243 244 246); border-bottom-color: rgba(255,255,255,0.08); }

    .ml-picker__folders { display: flex; flex-direction: column; gap: 0.125rem; padding: 0.5rem; max-height: 100%; overflow-y: auto; }
    .ml-picker__folder-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.625rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; color: rgb(55 65 81); border: none; background: transparent; cursor: pointer; text-align: left; width: 100%; transition: background 0.15s, color 0.15s; }
    .ml-picker__folder-btn:hover { background: rgb(243 244 246); }
    .dark .ml-picker__folder-btn { color: rgb(209 213 219); }
    .dark .ml-picker__folder-btn:hover { background: rgba(255,255,255,0.04); }
    .ml-picker__folder-btn--active { background: rgba(19,199,130,0.12); color: rgb(5 122 85); }
    .ml-picker__folder-btn--active:hover { background: rgba(19,199,130,0.18); }
    .dark .ml-picker__folder-btn--active { background: rgba(19,199,130,0.16); color: rgb(110 231 183); }

    .ml-picker__main { display: flex; flex-direction: column; gap: 0.75rem; min-height: 0; min-width: 0; }
    .ml-picker__filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: stretch; }
    .ml-picker__filters > *:first-child { flex: 1; min-width: 200px; }

    .ml-picker__grid-wrapper { background: transparent; border: 1px solid rgb(229 231 235); border-radius: 0.75rem; padding: 0.75rem; flex: 1; min-height: 0; overflow-y: auto; }
    .dark .ml-picker__grid-wrapper { border-color: rgba(255,255,255,0.08); }

    .ml-picker__section-title { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(107 114 128); margin: 0 0 0.5rem 0; padding: 0 0.125rem; }
    .dark .ml-picker__section-title { color: rgb(156 163 175); }

    .ml-picker__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; }

    /* Folder card (in grid) */
    .ml-picker__folder-card { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; padding: 1.25rem 0.75rem; border: 1px solid rgb(229 231 235); border-radius: 0.75rem; background: rgb(249 250 251); cursor: pointer; transition: all 0.15s; text-align: center; user-select: none; }
    .ml-picker__folder-card:hover { background: rgba(19,199,130,0.06); border-color: rgba(19,199,130,0.4); transform: translateY(-1px); }
    .dark .ml-picker__folder-card { background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.08); }
    .dark .ml-picker__folder-card:hover { background: rgba(19,199,130,0.08); border-color: rgba(19,199,130,0.4); }
    .ml-picker__folder-card-icon { color: rgb(245 158 11); }
    .ml-picker__folder-card-name { font-size: 0.8125rem; font-weight: 600; color: rgb(17 24 39); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .dark .ml-picker__folder-card-name { color: rgb(243 244 246); }
    .ml-picker__folder-card-count { font-size: 0.6875rem; color: rgb(107 114 128); }
    .dark .ml-picker__folder-card-count { color: rgb(156 163 175); }

    .ml-picker__card { display: flex; flex-direction: column; overflow: hidden; border-radius: 0.75rem; border: 1px solid rgb(229 231 235); background: rgb(255 255 255); cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s; padding: 0; text-align: left; position: relative; }
    .ml-picker__card:hover { border-color: rgb(156 163 175); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08); transform: translateY(-1px); }
    .dark .ml-picker__card { background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.08); }
    .dark .ml-picker__card:hover { border-color: rgba(255,255,255,0.2); }
    .ml-picker__card--selected { border-color: rgb(19,199,130); box-shadow: 0 0 0 2px rgba(19,199,130,0.45); }
    .ml-picker__card--selected:hover { border-color: rgb(19,199,130); }

    .ml-picker__thumb { aspect-ratio: 1 / 1; width: 100%; overflow: hidden; background: rgb(243 244 246); display: flex; align-items: center; justify-content: center; position: relative; }
    .dark .ml-picker__thumb { background: rgba(255,255,255,0.04); }
    .ml-picker__thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s; }
    .ml-picker__card:hover .ml-picker__thumb img { transform: scale(1.05); }
    .ml-picker__thumb-fallback { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; color: rgb(107 114 128); }
    .ml-picker__thumb-fallback span { font-size: 0.625rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }

    .ml-picker__check { position: absolute; top: 0.5rem; left: 0.5rem; display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgb(19,199,130); color: white; box-shadow: 0 0 0 2px white, 0 1px 2px rgba(0,0,0,0.2); }
    .dark .ml-picker__check { box-shadow: 0 0 0 2px rgb(17 24 39), 0 1px 2px rgba(0,0,0,0.4); }

    .ml-picker__meta { display: flex; flex-direction: column; gap: 0.125rem; padding: 0.5rem 0.625rem; border-top: 1px solid rgb(243 244 246); font-size: 0.75rem; }
    .dark .ml-picker__meta { border-top-color: rgba(255,255,255,0.06); }
    .ml-picker__meta-name { color: rgb(17 24 39); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dark .ml-picker__meta-name { color: rgb(243 244 246); }
    .ml-picker__meta-size { color: rgb(107 114 128); font-size: 0.6875rem; }
    .dark .ml-picker__meta-size { color: rgb(156 163 175); }

    .ml-picker__empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; min-height: 30vh; padding: 3rem 1rem; text-align: center; }
    .ml-picker__empty-icon { display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background: rgb(243 244 246); color: rgb(156 163 175); }
    .dark .ml-picker__empty-icon { background: rgba(255,255,255,0.05); color: rgb(156 163 175); }
    .ml-picker__empty-title { font-size: 0.9375rem; font-weight: 600; color: rgb(17 24 39); }
    .dark .ml-picker__empty-title { color: rgb(243 244 246); }
    .ml-picker__empty-text { font-size: 0.8125rem; color: rgb(107 114 128); margin-top: 0.25rem; }

    .ml-picker__footer { display: flex; flex-direction: column; gap: 0.75rem; }
    @media (min-width: 640px) { .ml-picker__footer { flex-direction: row; align-items: center; justify-content: space-between; } }
    .ml-picker__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }

    .ml-picker__upload-label { position: relative; display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; background: rgb(19,199,130); color: white; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; cursor: pointer; transition: background 0.15s; border: 0; }
    .ml-picker__upload-label:hover { background: rgb(5,150,105); }
    .ml-picker__upload-label input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }

    .ml-picker__icon-btn { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; background: transparent; border: 1px solid rgb(229 231 235); color: rgb(55 65 81); font-size: 0.8125rem; font-weight: 500; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
    .ml-picker__icon-btn:hover { background: rgb(243 244 246); border-color: rgb(209 213 219); }
    .ml-picker__icon-btn[disabled] { opacity: 0.5; cursor: wait; }
    .dark .ml-picker__icon-btn { color: rgb(209 213 219); border-color: rgba(255,255,255,0.1); }
    .dark .ml-picker__icon-btn:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.2); }
    .ml-picker__icon-btn .ml-picker__spin { animation: ml-picker-spin 0.8s linear infinite; }
    @keyframes ml-picker-spin { to { transform: rotate(360deg); } }

    /* Pagination */
    .ml-picker__pagination { display: flex; align-items: center; gap: 0.875rem; flex-wrap: wrap; }
    .ml-picker__pagination-info { font-size: 0.75rem; color: rgb(107 114 128); }
    .dark .ml-picker__pagination-info { color: rgb(156 163 175); }
    .ml-picker__pagination-list { display: inline-flex; align-items: center; gap: 0.25rem; }
    .ml-picker__page { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 0.5rem; border-radius: 0.5rem; background: transparent; border: 1px solid rgb(229 231 235); color: rgb(55 65 81); font-size: 0.8125rem; font-weight: 500; cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s; }
    .ml-picker__page:hover:not(:disabled) { background: rgb(243 244 246); border-color: rgb(209 213 219); }
    .ml-picker__page:disabled { opacity: 0.4; cursor: not-allowed; }
    .ml-picker__page--active { background: rgb(19,199,130); border-color: rgb(19,199,130); color: white; }
    .ml-picker__page--active:hover { background: rgb(5,150,105); border-color: rgb(5,150,105); }
    .ml-picker__page--ellipsis { border: 0; cursor: default; }
    .ml-picker__page--ellipsis:hover { background: transparent; }
    .dark .ml-picker__page { color: rgb(209 213 219); border-color: rgba(255,255,255,0.1); }
    .dark .ml-picker__page:hover:not(:disabled) { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.2); }
    .dark .ml-picker__page--active { color: white; }

    /* Inline modal (new folder, rename, delete confirm) */
    .ml-picker__overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 200; padding: 1rem; }
    .dark .ml-picker__overlay { background: rgba(2,6,23,0.75); backdrop-filter: blur(2px); }
    .ml-picker__dialog { width: 100%; max-width: 24rem; background: rgb(255 255 255); border: 1px solid rgb(229,231,235); border-radius: 0.875rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); overflow: hidden; }
    .dark .ml-picker__dialog { background: rgb(24 24 27); border-color: rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); }
    .ml-picker__dialog-header { display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem 1.25rem 0.5rem; }
    .ml-picker__dialog-icon { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 9999px; flex-shrink: 0; }
    .ml-picker__dialog-icon--info { background: rgba(19,199,130,0.12); color: rgb(5,150,105); }
    .ml-picker__dialog-icon--danger { background: rgba(239,68,68,0.12); color: rgb(220,38,38); }
    .ml-picker__dialog-title { font-size: 1rem; font-weight: 600; color: rgb(17 24 39); margin: 0 0 0.25rem; }
    .dark .ml-picker__dialog-title { color: rgb(243 244 246); }
    .ml-picker__dialog-desc { font-size: 0.8125rem; color: rgb(107 114 128); }
    .dark .ml-picker__dialog-desc { color: rgb(156 163 175); }
    .ml-picker__dialog-body { padding: 0.5rem 1.25rem 1rem; }
    .ml-picker__dialog-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; font-size: 0.875rem; background: white; color: rgb(17 24 39); }
    .ml-picker__dialog-input:focus { outline: none; border-color: rgb(19,199,130); box-shadow: 0 0 0 3px rgba(19,199,130,0.2); }
    .dark .ml-picker__dialog-input { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.1); color: rgb(243 244 246); }
    .ml-picker__dialog-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1.25rem 1rem; }
    .ml-picker__dialog-btn { padding: 0.5rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; }
    .ml-picker__dialog-btn--secondary { background: transparent; color: rgb(55 65 81); border-color: rgb(229 231 235); }
    .ml-picker__dialog-btn--secondary:hover { background: rgb(243 244 246); }
    .dark .ml-picker__dialog-btn--secondary { color: rgb(209 213 219); border-color: rgba(255,255,255,0.1); }
    .dark .ml-picker__dialog-btn--secondary:hover { background: rgba(255,255,255,0.04); }
    .ml-picker__dialog-btn--primary { background: rgb(19,199,130); color: white; }
    .ml-picker__dialog-btn--primary:hover { background: rgb(5,150,105); }
    .ml-picker__dialog-btn--danger { background: rgb(220,38,38); color: white; }
    .ml-picker__dialog-btn--danger:hover { background: rgb(185,28,28); }

    /* Right-click context menu */
    .ml-picker__context { position: fixed; min-width: 200px; background: rgb(255 255 255); border: 1px solid rgb(229 231 235); border-radius: 0.625rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); padding: 0.25rem; z-index: 60; }
    .dark .ml-picker__context { background: rgb(24 24 27); border-color: rgba(255,255,255,0.1); }
    .ml-picker__context-item { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.5rem 0.625rem; border-radius: 0.375rem; background: transparent; border: 0; color: rgb(55 65 81); font-size: 0.8125rem; font-weight: 500; cursor: pointer; text-align: left; }
    .ml-picker__context-item:hover { background: rgb(243 244 246); }
    .dark .ml-picker__context-item { color: rgb(209 213 219); }
    .dark .ml-picker__context-item:hover { background: rgba(255,255,255,0.05); }
    .ml-picker__context-item--danger { color: rgb(220,38,38); }
    .ml-picker__context-item--danger:hover { background: rgba(239,68,68,0.08); color: rgb(185,28,28); }
    .dark .ml-picker__context-item--danger { color: rgb(248 113 113); }
    .ml-picker__context-divider { height: 1px; background: rgb(229 231 235); margin: 0.25rem 0; }
    .dark .ml-picker__context-divider { background: rgba(255,255,255,0.08); }
</style>
@endassets

<div
    class="ml-picker"
    wire:key="media-picker-{{ $targetStatePath }}"
    x-data="{
        // Inline dialog state: { kind: 'new-folder' | 'rename-media' | 'delete-media' | 'delete-folder', payload: {...}, name: '' }
        dialog: null,
        contextMenu: null,
        openDialog (kind, payload = {}, defaultName = '') {
            this.contextMenu = null
            this.dialog = { kind, payload, name: defaultName }
            this.$nextTick(() => { this.$refs.dialogInput?.focus(); this.$refs.dialogInput?.select() })
        },
        closeDialog () { this.dialog = null },
        openContext (event, type, payload) {
            event.preventDefault()
            event.stopPropagation()
            const margin = 8
            const x = Math.min(event.clientX, window.innerWidth - 220)
            const y = Math.min(event.clientY, window.innerHeight - 180)
            this.contextMenu = { type, payload, x, y }
        },
        closeContext () { this.contextMenu = null },
        submitDialog () {
            if (! this.dialog) return
            const { kind, payload, name } = this.dialog
            const trimmed = (name || '').trim()
            if (kind === 'new-folder') {
                if (! trimmed) return
                $wire.createFolder(trimmed)
            } else if (kind === 'rename-media') {
                if (! trimmed) return
                $wire.renameMedia(payload.id, trimmed)
            } else if (kind === 'delete-media') {
                $wire.deleteMedia(payload.id)
            } else if (kind === 'delete-folder') {
                $wire.deleteFolder(payload.path)
            }
            this.closeDialog()
        },
    }"
    x-on:click.window="closeContext()"
    x-on:keydown.escape.window="dialog && closeDialog(); contextMenu && closeContext()"
>
    {{-- Toolbar --}}
    <div class="ml-picker__toolbar">
        <div class="ml-picker__toolbar-left">
            <x-filament::badge color="primary" size="lg">
                {{ count($selected) }} seçildi
            </x-filament::badge>
            <span class="ml-picker__count">{{ $paginator->total() }} dosya</span>
        </div>
        <div class="ml-picker__toolbar-right">
            <button
                type="button"
                class="ml-picker__icon-btn"
                wire:click="refresh"
                wire:loading.attr="disabled"
                wire:target="refresh"
                title="Yenile"
            >
                <span wire:loading.remove wire:target="refresh">
                    <x-filament::icon icon="heroicon-m-arrow-path" style="width:1rem;height:1rem;" />
                </span>
                <span wire:loading wire:target="refresh" class="ml-picker__spin">
                    <x-filament::icon icon="heroicon-m-arrow-path" style="width:1rem;height:1rem;" />
                </span>
                <span>{{ __('filament-media-library::filament-media-library.picker.refresh') }}</span>
            </button>
            <button
                type="button"
                class="ml-picker__icon-btn"
                x-on:click="openDialog('new-folder', {}, '')"
                title="{{ __('filament-media-library::filament-media-library.picker.new_folder_title') }}"
            >
                <x-filament::icon icon="heroicon-m-folder-plus" style="width:1rem;height:1rem;" />
                <span>{{ __('filament-media-library::filament-media-library.picker.new_folder') }}</span>
            </button>
            <label class="ml-picker__upload-label">
                <x-filament::icon icon="heroicon-m-arrow-up-tray" style="width:1rem;height:1rem;" />
                <span>{{ __('filament-media-library::filament-media-library.picker.upload_new') }}</span>
                <input type="file" multiple wire:model="uploads" />
            </label>
            @if (! empty($uploads))
                <x-filament::button wire:click="uploadAndApply" wire:loading.attr="disabled" size="sm" color="success" icon="heroicon-m-cloud-arrow-up">
                    {{ trans_choice('filament-media-library::filament-media-library.picker.upload_count', count($uploads), ['count' => count($uploads)]) }}
                </x-filament::button>
            @endif
        </div>
    </div>

    {{-- Layout: sidebar + main --}}
    <div class="ml-picker__layout">
        {{-- Folder sidebar --}}
        <aside class="ml-picker__panel">
            <div class="ml-picker__panel-header">
                <span>{{ __('filament-media-library::filament-media-library.picker.folders') }}</span>
                @if ($directory !== '')
                    <span class="ml-picker__count" style="font-weight:400;text-transform:none;">{{ $directory }}</span>
                @endif
            </div>
            <nav class="ml-picker__folders">
                <button
                    type="button"
                    wire:click="clearFolder"
                    class="ml-picker__folder-btn @if($directory === '') ml-picker__folder-btn--active @endif"
                >
                    <x-filament::icon icon="heroicon-m-folder-open" style="width:1rem;height:1rem;flex-shrink:0;" />
                    <span>{{ __('filament-media-library::filament-media-library.picker.all_files') }}</span>
                </button>
                @foreach ($folders as $folder)
                    <button
                        type="button"
                        wire:click="selectFolder(@js($folder['path']))"
                        x-on:contextmenu="openContext($event, 'folder', { path: @js($folder['path']), name: @js($folder['name']) })"
                        class="ml-picker__folder-btn @if($directory === $folder['path']) ml-picker__folder-btn--active @endif"
                        style="padding-left: {{ ($folder['depth'] * 0.75) + 0.625 }}rem;"
                    >
                        <x-filament::icon icon="heroicon-m-folder" style="width:1rem;height:1rem;flex-shrink:0;" />
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $folder['name'] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- Main content --}}
        <main class="ml-picker__main">
            {{-- Filters --}}
            <div class="ml-picker__filters">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('filament-media-library::filament-media-library.picker.search_placeholder') }}" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="kindFilter">
                        <option value="">{{ __('filament-media-library::filament-media-library.filters.all_kinds') }}</option>
                        @foreach (MediaKind::options() as $value => $label)
                            <option value="{{ $value }}" @if (! empty($kinds) && ! in_array($value, $kinds, true)) disabled @endif>{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sort">
                        <option value="newest">{{ __('filament-media-library::filament-media-library.picker.sort_newest') }}</option>
                        <option value="oldest">{{ __('filament-media-library::filament-media-library.picker.sort_oldest') }}</option>
                        <option value="name">{{ __('filament-media-library::filament-media-library.picker.sort_name') }}</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            {{-- Grid --}}
            <div class="ml-picker__grid-wrapper" wire:loading.class="opacity-50">
                {{-- Folder cards (only on root view, no filters) --}}
                @if ($showFolderCards && count($rootFolders) > 0)
                    <p class="ml-picker__section-title">{{ __('filament-media-library::filament-media-library.picker.folders') }}</p>
                    <div class="ml-picker__grid" style="margin-bottom: 1rem;">
                        @foreach ($rootFolders as $folder)
                            <div
                                class="ml-picker__folder-card"
                                wire:click="selectFolder(@js($folder['path']))"
                                x-on:contextmenu="openContext($event, 'folder', { path: @js($folder['path']), name: @js($folder['name']) })"
                                role="button"
                                tabindex="0"
                            >
                                <x-filament::icon icon="heroicon-s-folder" class="ml-picker__folder-card-icon" style="width:2.5rem;height:2.5rem;" />
                                <span class="ml-picker__folder-card-name" title="{{ $folder['name'] }}">{{ $folder['name'] }}</span>
                                <span class="ml-picker__folder-card-count">{{ trans_choice('filament-media-library::filament-media-library.picker.file_count', $folder['count'], ['count' => $folder['count']]) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Files --}}
                @if ($paginator->total() === 0)
                    <div class="ml-picker__empty">
                        <div class="ml-picker__empty-icon">
                            <x-filament::icon icon="heroicon-o-photo" style="width:1.75rem;height:1.75rem;" />
                        </div>
                        <div>
                            <p class="ml-picker__empty-title">{{ __('filament-media-library::filament-media-library.picker.empty_title') }}</p>
                            <p class="ml-picker__empty-text">{{ __('filament-media-library::filament-media-library.picker.empty_text') }}</p>
                        </div>
                    </div>
                @else
                    @if ($showFolderCards && count($rootFolders) > 0)
                        <p class="ml-picker__section-title">{{ __('filament-media-library::filament-media-library.picker.files') }}</p>
                    @endif
                    <div class="ml-picker__grid">
                        @foreach ($paginator as $item)
                            @php
                                $isSel = in_array($item->id, $selected, true);
                                $kindEnum = $item->kind_enum;
                            @endphp
                            <div
                                wire:key="media-{{ $item->id }}"
                                wire:click="toggleSelectFor(@js($item->id))"
                                x-on:contextmenu="openContext($event, 'file', { id: @js($item->id), name: @js($item->name) })"
                                class="ml-picker__card @if($isSel) ml-picker__card--selected @endif"
                                role="button"
                                tabindex="0"
                            >
                                <div class="ml-picker__thumb">
                                    @if ($kindEnum === MediaKind::Image)
                                        <img src="{{ $item->publicUrl() }}" alt="{{ $item->alt ?: $item->name }}" loading="lazy" />
                                    @else
                                        <div class="ml-picker__thumb-fallback">
                                            <x-filament::icon :icon="$kindEnum->getIcon()" style="width:2.25rem;height:2.25rem;" />
                                            <span>{{ strtoupper((string) pathinfo($item->name, PATHINFO_EXTENSION)) ?: $kindEnum->getLabel() }}</span>
                                        </div>
                                    @endif
                                    @if ($isSel)
                                        <span class="ml-picker__check">
                                            <x-filament::icon icon="heroicon-m-check" style="width:0.875rem;height:0.875rem;" />
                                        </span>
                                    @endif
                                </div>
                                <div class="ml-picker__meta">
                                    <span class="ml-picker__meta-name" title="{{ $item->name }}">{{ $item->name }}</span>
                                    <span class="ml-picker__meta-size">{{ MimeKindResolver::formatBytes((int) $item->size) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="ml-picker__footer">
                @if ($paginator->total() > 0)
                    <div class="ml-picker__pagination">
                        <span class="ml-picker__pagination-info">
                            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
                            / <strong>{{ $paginator->total() }}</strong> {{ __('filament-media-library::filament-media-library.picker.total_files') }}
                        </span>
                        @if ($lastPage > 1)
                            <nav class="ml-picker__pagination-list" aria-label="{{ __('filament-media-library::filament-media-library.picker.pagination') }}">
                                <button type="button" class="ml-picker__page" wire:click="previousPage" @disabled($paginator->onFirstPage()) aria-label="{{ __('filament-media-library::filament-media-library.picker.previous_page') }}" title="{{ __('filament-media-library::filament-media-library.picker.previous') }}">
                                    <x-filament::icon icon="heroicon-m-chevron-left" style="width:1rem;height:1rem;" />
                                </button>
                                @foreach ($pageWindow($currentPage, $lastPage, $onEachSide) as $page)
                                    @if ($page === '…')
                                        <span class="ml-picker__page ml-picker__page--ellipsis">…</span>
                                    @else
                                        <button type="button" class="ml-picker__page @if($page === $currentPage) ml-picker__page--active @endif" wire:click="gotoPage({{ $page }})" aria-label="{{ __('filament-media-library::filament-media-library.picker.page', ['page' => $page]) }}" @if ($page === $currentPage) aria-current="page" @endif>{{ $page }}</button>
                                    @endif
                                @endforeach
                                <button type="button" class="ml-picker__page" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) aria-label="{{ __('filament-media-library::filament-media-library.picker.next_page') }}" title="{{ __('filament-media-library::filament-media-library.picker.next') }}">
                                    <x-filament::icon icon="heroicon-m-chevron-right" style="width:1rem;height:1rem;" />
                                </button>
                            </nav>
                        @endif
                    </div>
                @else
                    <div></div>
                @endif

                <div class="ml-picker__actions">
                    @if (count($selected) > 0)
                        <x-filament::button wire:click="clearSelection" color="gray" size="sm" icon="heroicon-m-x-mark">{{ __('filament-media-library::filament-media-library.actions.clear_selection') }}</x-filament::button>
                    @endif
                    <x-filament::button wire:click="confirmSelection" size="md" icon="heroicon-m-check" :disabled="count($selected) === 0">
                        @if (count($selected) > 0)
                            {{ __('filament-media-library::filament-media-library.picker.select_count', ['count' => count($selected)]) }}
                        @else
                            {{ __('filament-media-library::filament-media-library.picker.select_prompt') }}
                        @endif
                    </x-filament::button>
                </div>
            </div>
        </main>
    </div>

    {{-- Right-click context menu --}}
    <template x-if="contextMenu">
        <div
            class="ml-picker__context"
            x-bind:style="`left: ${contextMenu.x}px; top: ${contextMenu.y}px;`"
            x-on:click.stop
            x-on:contextmenu.prevent
        >
            <template x-if="contextMenu.type === 'file'">
                <div>
                    <button type="button" class="ml-picker__context-item" x-on:click="openDialog('rename-media', { id: contextMenu.payload.id }, contextMenu.payload.name)">
                        <x-filament::icon icon="heroicon-m-pencil-square" style="width:1rem;height:1rem;" />
                        <span>{{ __('filament-media-library::filament-media-library.actions.rename') }}</span>
                    </button>
                    <div class="ml-picker__context-divider"></div>
                    <button type="button" class="ml-picker__context-item ml-picker__context-item--danger" x-on:click="openDialog('delete-media', { id: contextMenu.payload.id, name: contextMenu.payload.name })">
                        <x-filament::icon icon="heroicon-m-trash" style="width:1rem;height:1rem;" />
                        <span>{{ __('filament-media-library::filament-media-library.actions.delete') }}</span>
                    </button>
                </div>
            </template>
            <template x-if="contextMenu.type === 'folder'">
                <div>
                    <button type="button" class="ml-picker__context-item" x-on:click="$wire.selectFolder(contextMenu.payload.path); closeContext()">
                        <x-filament::icon icon="heroicon-m-folder-open" style="width:1rem;height:1rem;" />
                        <span>{{ __('filament-media-library::filament-media-library.picker.open') }}</span>
                    </button>
                    <div class="ml-picker__context-divider"></div>
                    <button type="button" class="ml-picker__context-item ml-picker__context-item--danger" x-on:click="openDialog('delete-folder', { path: contextMenu.payload.path, name: contextMenu.payload.name })">
                        <x-filament::icon icon="heroicon-m-trash" style="width:1rem;height:1rem;" />
                        <span>{{ __('filament-media-library::filament-media-library.actions.delete_folder') }}</span>
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- Inline dialog --}}
    <template x-if="dialog">
        <div class="ml-picker__overlay" x-on:click.self="closeDialog()">
            <div class="ml-picker__dialog" x-on:keydown.enter.prevent="submitDialog()">
                {{-- New folder --}}
                <template x-if="dialog.kind === 'new-folder'">
                    <div>
                        <div class="ml-picker__dialog-header">
                            <span class="ml-picker__dialog-icon ml-picker__dialog-icon--info">
                                <x-filament::icon icon="heroicon-m-folder-plus" style="width:1.25rem;height:1.25rem;" />
                            </span>
                            <div>
                                <h3 class="ml-picker__dialog-title">{{ __('filament-media-library::filament-media-library.picker.new_folder_title') }}</h3>
                                <p class="ml-picker__dialog-desc">{{ __('filament-media-library::filament-media-library.picker.new_folder_desc') }}</p>
                            </div>
                        </div>
                        <div class="ml-picker__dialog-body">
                            <input
                                type="text"
                                class="ml-picker__dialog-input"
                                x-model="dialog.name"
                                x-ref="dialogInput"
                                placeholder="{{ __('filament-media-library::filament-media-library.picker.new_folder_placeholder') }}"
                                autocomplete="off"
                            />
                        </div>
                        <div class="ml-picker__dialog-footer">
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--secondary" x-on:click="closeDialog()">{{ __('filament-media-library::filament-media-library.actions.cancel') }}</button>
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--primary" x-on:click="submitDialog()" x-bind:disabled="!(dialog.name||'').trim()">{{ __('filament-media-library::filament-media-library.picker.create') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Rename media --}}
                <template x-if="dialog.kind === 'rename-media'">
                    <div>
                        <div class="ml-picker__dialog-header">
                            <span class="ml-picker__dialog-icon ml-picker__dialog-icon--info">
                                <x-filament::icon icon="heroicon-m-pencil-square" style="width:1.25rem;height:1.25rem;" />
                            </span>
                            <div>
                                <h3 class="ml-picker__dialog-title">{{ __('filament-media-library::filament-media-library.picker.rename_title') }}</h3>
                                <p class="ml-picker__dialog-desc">{{ __('filament-media-library::filament-media-library.picker.rename_desc') }}</p>
                            </div>
                        </div>
                        <div class="ml-picker__dialog-body">
                            <input type="text" class="ml-picker__dialog-input" x-model="dialog.name" x-ref="dialogInput" autocomplete="off" />
                        </div>
                        <div class="ml-picker__dialog-footer">
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--secondary" x-on:click="closeDialog()">{{ __('filament-media-library::filament-media-library.actions.cancel') }}</button>
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--primary" x-on:click="submitDialog()" x-bind:disabled="!(dialog.name||'').trim()">{{ __('filament-media-library::filament-media-library.actions.save') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Delete media --}}
                <template x-if="dialog.kind === 'delete-media'">
                    <div>
                        <div class="ml-picker__dialog-header">
                            <span class="ml-picker__dialog-icon ml-picker__dialog-icon--danger">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width:1.25rem;height:1.25rem;" />
                            </span>
                            <div>
                                <h3 class="ml-picker__dialog-title">{{ __('filament-media-library::filament-media-library.picker.delete_title') }}</h3>
                                <p class="ml-picker__dialog-desc">
                                    <span x-html="@js(__('filament-media-library::filament-media-library.picker.delete_desc', ['name' => '<strong>:name</strong>'])).replace(':name', dialog.payload.name)"></span>
                                </p>
                            </div>
                        </div>
                        <div class="ml-picker__dialog-footer" style="padding-top: 0.5rem;">
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--secondary" x-on:click="closeDialog()">{{ __('filament-media-library::filament-media-library.picker.dismiss') }}</button>
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--danger" x-on:click="submitDialog()">{{ __('filament-media-library::filament-media-library.picker.delete_confirm') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Delete folder --}}
                <template x-if="dialog.kind === 'delete-folder'">
                    <div>
                        <div class="ml-picker__dialog-header">
                            <span class="ml-picker__dialog-icon ml-picker__dialog-icon--danger">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width:1.25rem;height:1.25rem;" />
                            </span>
                            <div>
                                <h3 class="ml-picker__dialog-title">{{ __('filament-media-library::filament-media-library.picker.delete_folder_title') }}</h3>
                                <p class="ml-picker__dialog-desc">
                                    <span x-html="@js(__('filament-media-library::filament-media-library.picker.delete_folder_desc', ['name' => '<strong>:name</strong>'])).replace(':name', dialog.payload.name)"></span>
                                </p>
                            </div>
                        </div>
                        <div class="ml-picker__dialog-footer" style="padding-top: 0.5rem;">
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--secondary" x-on:click="closeDialog()">{{ __('filament-media-library::filament-media-library.picker.dismiss') }}</button>
                            <button type="button" class="ml-picker__dialog-btn ml-picker__dialog-btn--danger" x-on:click="submitDialog()">{{ __('filament-media-library::filament-media-library.picker.delete_folder_confirm') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
