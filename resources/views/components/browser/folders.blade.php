@php
    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
    $canUpload = $this->canMedia('upload');

    // The tree arrives flat and depth-first, so a folder has children exactly
    // when the next row sits one level deeper.
    $flat = $folders->values()->all();
    $branches = [];

    foreach ($flat as $i => $folder) {
        $next = $flat[$i + 1] ?? null;

        if ($next !== null && $next['depth'] > $folder['depth']) {
            $branches[] = $folder['path'];
        }
    }
@endphp

<div class="fml-panel fml-panel--folders" x-data="{ open: false }">
    <div class="fml-panel__header">
        <span class="fml-label">{{ __($t.'.fields.folders') }}</span>

        <div class="fml-row">
            @if ($branches !== [])
                <x-filament::icon-button
                    icon="heroicon-m-chevron-up-down"
                    color="gray"
                    size="sm"
                    x-on:click="toggleAllFolders({!! $js($branches) !!})"
                    x-bind:aria-expanded="allFoldersOpen({!! $js($branches) !!}) ? 'true' : 'false'"
                    :label="__($t.'.actions.toggle_folders')"
                />
            @endif

            @if ($canUpload)
                <x-filament::icon-button
                    icon="heroicon-m-folder-plus"
                    color="gray"
                    size="sm"
                    x-on:click="openDialog('new-folder')"
                    :label="__($t.'.actions.new_folder')"
                />
            @endif

            <x-filament::icon-button
                icon="heroicon-m-chevron-down"
                color="gray"
                size="sm"
                class="fml-aside-toggle"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                :label="__($t.'.actions.show_folders')"
            />
        </div>
    </div>

    <ul class="fml-folders fml-aside-body" x-bind:hidden="! open && window.innerWidth < 768">
        <li>
            <button
                type="button"
                class="fml-folder"
                aria-current="{{ $directory === '' ? 'true' : 'false' }}"
                wire:click="clearFolder"
                x-on:dragover.prevent="dropFolder = ''"
                x-on:dragleave="dropFolder = null"
                x-on:drop.prevent="dropOnFolder('')"
                x-bind:class="dropFolder === '' && draggingId ? 'fml-folder--drop-target' : ''"
            >
                <span class="fml-folder__twist fml-folder__twist--leaf" aria-hidden="true"></span>
                <x-filament::icon icon="heroicon-m-folder-open" class="fml-icon-sm" />
                <span class="fml-folder__name">{{ __($t.'.fields.root') }}</span>
            </button>
        </li>

        @foreach ($flat as $folder)
            @php
                $path = $folder['path'];
                $isBranch = in_array($path, $branches, true);
                // Never hide the folder being browsed, or the way back up to it.
                $onActivePath = $directory !== '' && str_starts_with($directory.'/', $path.'/');
            @endphp

            <li
                class="fml-folder-item"
                style="padding-inline-start: {{ $folder['depth'] * 0.75 }}rem"
                x-show="isFolderVisible({!! $js($path) !!}, {{ $onActivePath ? 'true' : 'false' }})"
            >
                @if ($isBranch)
                    <button
                        type="button"
                        class="fml-folder__twist"
                        x-on:click.stop="toggleFolder({!! $js($path) !!})"
                        x-bind:class="isFolderOpen({!! $js($path) !!}) ? 'fml-folder__twist--open' : ''"
                        x-bind:aria-expanded="isFolderOpen({!! $js($path) !!}) ? 'true' : 'false'"
                        aria-label="{{ __($t.'.actions.toggle_folders') }}"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-right" class="fml-icon-xs" />
                    </button>
                @else
                    <span class="fml-folder__twist fml-folder__twist--leaf" aria-hidden="true"></span>
                @endif

                <button
                    type="button"
                    class="fml-folder"
                    aria-current="{{ $directory === $path ? 'true' : 'false' }}"
                    title="{{ $path }}"
                    wire:click="selectFolder({!! $js($path) !!})"
                    x-on:dragover.prevent="dropFolder = {!! $js($path) !!}"
                    x-on:dragleave="dropFolder = null"
                    x-on:drop.prevent="dropOnFolder({!! $js($path) !!})"
                    x-bind:class="dropFolder === {!! $js($path) !!} && draggingId ? 'fml-folder--drop-target' : ''"
                >
                    <x-filament::icon icon="heroicon-m-folder" class="fml-icon-sm" />
                    <span class="fml-folder__name">{{ $folder['name'] }}</span>
                </button>

                @if ($canManage || $canDelete)
                    <x-filament::dropdown placement="bottom-end" width="xs">
                        <x-slot name="trigger">
                            <x-filament::icon-button
                                icon="heroicon-m-ellipsis-horizontal"
                                color="gray"
                                size="sm"
                                :label="$folder['name']"
                            />
                        </x-slot>

                        <x-filament::dropdown.list>
                            @if ($canManage)
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-pencil-square"
                                    x-on:click="openDialog('rename-folder', { path: {!! $js($path) !!} }, {!! $js($folder['name']) !!})"
                                >
                                    {{ __($t.'.actions.rename_folder') }}
                                </x-filament::dropdown.list.item>
                            @endif

                            @if ($canDelete)
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-trash"
                                    color="danger"
                                    x-on:click="openDialog('delete-folder', { path: {!! $js($path) !!}, name: {!! $js($folder['name']) !!} })"
                                >
                                    {{ __($t.'.actions.delete_folder') }}
                                </x-filament::dropdown.list.item>
                            @endif
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @endif
            </li>
        @endforeach
    </ul>
</div>
