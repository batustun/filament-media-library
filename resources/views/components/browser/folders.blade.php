@php
    $t = 'filament-media-library::filament-media-library';
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
    $canUpload = $this->canMedia('upload');
@endphp

<div class="fml-panel" x-data="{ open: false }">
    <div class="fml-panel__header">
        <span class="fml-label">{{ __($t.'.fields.folders') }}</span>

        <div class="fml-row">
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
                <x-filament::icon icon="heroicon-m-folder-open" class="fml-icon-sm" />
                <span class="fml-folder__name">{{ __($t.'.fields.root') }}</span>
            </button>
        </li>

        @foreach ($folders as $folder)
            <li class="fml-folder-item">
                <button
                    type="button"
                    class="fml-folder"
                    aria-current="{{ $directory === $folder['path'] ? 'true' : 'false' }}"
                    style="padding-inline-start: {{ ($folder['depth'] * 0.75) + 0.5 }}rem"
                    title="{{ $folder['path'] }}"
                    wire:click="selectFolder(@js($folder['path']))"
                    x-on:dragover.prevent="dropFolder = @js($folder['path'])"
                    x-on:dragleave="dropFolder = null"
                    x-on:drop.prevent="dropOnFolder(@js($folder['path']))"
                    x-bind:class="dropFolder === @js($folder['path']) && draggingId ? 'fml-folder--drop-target' : ''"
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
                                    x-on:click="openDialog('rename-folder', { path: @js($folder['path']) }, @js($folder['name']))"
                                >
                                    {{ __($t.'.actions.rename_folder') }}
                                </x-filament::dropdown.list.item>
                            @endif

                            @if ($canDelete)
                                <x-filament::dropdown.list.item
                                    icon="heroicon-m-trash"
                                    color="danger"
                                    x-on:click="openDialog('delete-folder', { path: @js($folder['path']), name: @js($folder['name']) })"
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
