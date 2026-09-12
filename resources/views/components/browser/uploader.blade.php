@php
    use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';
    $target = $uploadAction ?? 'uploadFiles';
    $chunked = MediaLibraryConfig::chunkedUploadsEnabled() && Route::has('filament-media-library.chunk');
@endphp

<div
    class="fml-dropzone"
    @if ($chunked)
        x-data="fmlChunkedUpload({
            endpoint: {!! $js(route('filament-media-library.chunk')) !!},
            csrf: {!! $js(csrf_token()) !!},
            chunkSize: {{ MediaLibraryConfig::chunkSizeBytes() }},
            threshold: {{ MediaLibraryConfig::chunkThresholdBytes() }},
            disk: {!! $js($disk) !!},
            directory: {!! $js($directory) !!},
        })"
        x-on:change.capture="interceptChange($event)"
    @endif
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

    @if ($chunked)
        <div x-show="busy" x-cloak class="fml-dropzone__overlay">
            <x-filament::loading-indicator class="fml-icon-sm" />
            <span x-text="{!! $js(__($t.'.messages.uploading_chunks', ['done' => ':done', 'total' => ':total'])) !!}
                .replace(':done', done).replace(':total', total)"></span>
        </div>

        <p x-show="error" x-cloak x-text="error" class="fml-dropzone__hint fml-danger"></p>
    @endif
</div>
