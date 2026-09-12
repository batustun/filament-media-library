@php
    use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';

    // Only files too large for a single POST take the chunked route; without a
    // configured endpoint the uploader simply never offers it.
    $chunkEndpoint = MediaLibraryConfig::chunkedUploadsEnabled() && Route::has('filament-media-library.chunk')
        ? route('filament-media-library.chunk')
        : null;

    $destination = $this->uploadTargetDirectory();
@endphp

{{--
    Files upload the moment they are chosen. There is no second button: staging
    them behind one changed nothing on screen but that button's tint, so
    choosing a file was indistinguishable from the upload having failed.
--}}
<div
    class="fml-dropzone"
    x-data="fmlUploader({
        endpoint: {!! $js($chunkEndpoint) !!},
        csrf: {!! $js(csrf_token()) !!},
        chunkSize: {{ MediaLibraryConfig::chunkSizeBytes() }},
        threshold: {{ MediaLibraryConfig::chunkThresholdBytes() }},
        disk: {!! $js($disk) !!},
        directory: {!! $js($destination) !!},
        progressLabel: {!! $js(__($t.'.messages.uploading_percent', ['percent' => ':percent'])) !!},
        chunkLabel: {!! $js(__($t.'.messages.uploading_chunks', ['done' => ':done', 'total' => ':total'])) !!},
    })"
    x-on:change.capture="interceptChange($event)"
    x-on:livewire-upload-start="percent = 0"
    x-on:livewire-upload-progress="percent = $event.detail.progress"
    x-on:livewire-upload-finish="percent = null"
    x-on:livewire-upload-error="percent = null"
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

    <p class="fml-dropzone__hint">
        {{ __($t.'.messages.drop_hint', ['folder' => $destination ?: '/']) }}
    </p>

    {{-- Sending the bytes, then writing them to the library: one busy state. --}}
    <div x-show="busyLabel() !== null" x-cloak class="fml-dropzone__overlay">
        <x-filament::loading-indicator class="fml-icon-sm" />
        <span x-text="busyLabel()"></span>
    </div>

    <div wire:loading.flex wire:target="storeUploads,adoptUploads" class="fml-dropzone__overlay" hidden>
        <x-filament::loading-indicator class="fml-icon-sm" />
        {{ __($t.'.messages.indexing') }}
    </div>

    <p x-show="error" x-cloak x-text="error" class="fml-dropzone__hint fml-danger"></p>
</div>
