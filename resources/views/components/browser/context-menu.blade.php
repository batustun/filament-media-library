@php
    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
@endphp

{{--
    One menu for the whole grid, positioned at the pointer. Rendering it per
    card would put hundreds of identical menus in the DOM.
--}}
<div
    class="fml-context"
    x-ref="contextMenu"
    x-show="contextMenu"
    x-cloak
    tabindex="-1"
    role="menu"
    x-bind:style="contextMenu ? `inset-block-start:${contextMenu.y}px; inset-inline-start:${contextMenu.x}px` : ''"
    x-on:click.outside="closeContextMenu()"
    x-on:keydown.escape.stop.prevent="closeContextMenu()"
    x-on:contextmenu.prevent.stop
>
    <p class="fml-context__title" x-text="contextMenu?.name"></p>

    <button type="button" role="menuitem" class="fml-context__item"
        x-on:click="fromContextMenu((item) => openPreviewAt(item.index))">
        <x-filament::icon icon="heroicon-m-magnifying-glass-plus" class="fml-icon-xs" />
        {{ __($t.'.actions.preview') }}
    </button>

    <button type="button" role="menuitem" class="fml-context__item" x-show="mode === 'page'"
        x-on:click="fromContextMenu((item) => $wire.showDetail(item.mediaId))">
        <x-filament::icon icon="heroicon-m-information-circle" class="fml-icon-xs" />
        {{ __($t.'.actions.details') }}
    </button>

    <button type="button" role="menuitem" class="fml-context__item"
        x-on:click="fromContextMenu((item) => mode === 'page' ? $wire.toggleSelect(item.mediaId) : $wire.toggleSelectFor(item.mediaId))">
        <x-filament::icon icon="heroicon-m-check-circle" class="fml-icon-xs" />
        <span x-text="contextMenu?.selected
            ? {!! $js(__($t.'.actions.deselect')) !!}
            : {!! $js(__($t.'.actions.select')) !!}"></span>
    </button>

    <div class="fml-context__divider" role="separator"></div>

    <button type="button" role="menuitem" class="fml-context__item"
        x-on:click="fromContextMenu((item) => copy(item.url))">
        <x-filament::icon icon="heroicon-m-link" class="fml-icon-xs" />
        {{ __($t.'.actions.copy_url') }}
    </button>

    <button type="button" role="menuitem" class="fml-context__item"
        x-on:click="fromContextMenu((item) => window.open(item.url, '_blank', 'noopener'))">
        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="fml-icon-xs" />
        {{ __($t.'.actions.open_in_new_tab') }}
    </button>

    <button type="button" role="menuitem" class="fml-context__item"
        x-on:click="fromContextMenu((item) => download(item.url, item.name))">
        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="fml-icon-xs" />
        {{ __($t.'.actions.download') }}
    </button>

    @if ($canManage)
        <div class="fml-context__divider" role="separator"></div>

        <button type="button" role="menuitem" class="fml-context__item"
            x-on:click="fromContextMenu((item) => openDialog('rename-media', { id: item.mediaId }, item.name))">
            <x-filament::icon icon="heroicon-m-pencil-square" class="fml-icon-xs" />
            {{ __($t.'.actions.rename') }}
        </button>

        <button type="button" role="menuitem" class="fml-context__item"
            x-on:click="fromContextMenu((item) => openDialog('move-media', { id: item.mediaId }, item.directory ?? ''))">
            <x-filament::icon icon="heroicon-m-folder-arrow-down" class="fml-icon-xs" />
            {{ __($t.'.actions.move') }}
        </button>
    @endif

    @if ($canDelete)
        <div class="fml-context__divider" role="separator"></div>

        <button type="button" role="menuitem" class="fml-context__item fml-context__item--danger"
            x-on:click="fromContextMenu((item) => openDialog('delete-media', { id: item.mediaId, name: item.name }))">
            <x-filament::icon icon="heroicon-m-trash" class="fml-icon-xs" />
            {{ __($t.'.actions.delete') }}
        </button>
    @endif
</div>
