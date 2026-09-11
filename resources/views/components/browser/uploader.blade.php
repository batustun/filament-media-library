@php
    $t = 'filament-media-library::filament-media-library';
    $target = $uploadAction ?? 'uploadFiles';
@endphp

<div
    class="fml-dropzone"
    x-bind:data-active="dragActive ? 'true' : 'false'"
    x-on:dragover.prevent="dragActive = true"
    x-on:dragleave.prevent="dragActive = false"
    x-on:drop.prevent="dropFiles($event)"
>
    <label class="fml-row">
        <x-filament::button tag="span" size="sm" icon="heroicon-m-arrow-up-tray">
            {{ __($t.'.actions.choose_files') }}
        </x-filament::button>
        <input type="file" multiple wire:model="uploads" x-ref="fileInput" class="fml-sr-only" />
    </label>

    <x-filament::button
        size="sm"
        color="success"
        icon="heroicon-m-cloud-arrow-up"
        wire:click="{{ $target }}"
        wire:loading.attr="disabled"
        wire:target="uploads,{{ $target }}"
        :disabled="empty($uploads)"
    >
        <span wire:loading.remove wire:target="uploads,{{ $target }}">{{ __($t.'.actions.upload') }}</span>
        <span wire:loading wire:target="uploads,{{ $target }}">{{ __($t.'.actions.uploading') }}</span>
    </x-filament::button>

    <p class="fml-dropzone__hint">
        {{ __($t.'.messages.drop_hint', ['folder' => $directory ?: '/']) }}
    </p>

    <div wire:loading.flex wire:target="uploads" class="fml-dropzone__overlay" hidden>
        <x-filament::loading-indicator class="fml-icon-sm" />
        {{ __($t.'.messages.preparing') }}
    </div>
</div>
