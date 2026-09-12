@php
    $t = 'filament-media-library::filament-media-library';
@endphp

{{--
    The full-size view. Everything it renders comes from the card's own data
    attributes, so it needs no round trip and stays in step with the grid.
--}}
<div
    class="fml-lightbox"
    x-ref="lightbox"
    x-show="preview"
    x-cloak
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    x-bind:aria-label="preview?.name"
    {{-- Caught here rather than on the window: inside the picker a bubbling
         Escape would close the whole modal along with the preview. --}}
    x-on:keydown.escape.stop.prevent="closePreview()"
    x-on:click.self="closePreview()"
>
    <header class="fml-lightbox__bar">
        <p class="fml-lightbox__name" x-text="preview?.name"></p>

        <div class="fml-row">
            <x-filament::icon-button
                icon="heroicon-m-arrow-top-right-on-square"
                color="gray"
                size="sm"
                tag="a"
                target="_blank"
                x-bind:href="preview?.url"
                :label="__($t.'.actions.open_in_new_tab')"
            />
            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                size="sm"
                x-on:click="closePreview()"
                :label="__($t.'.actions.close')"
            />
        </div>
    </header>

    <button
        type="button"
        class="fml-lightbox__step fml-lightbox__step--prev"
        x-on:click.stop="movePreview(-1)"
        aria-label="{{ __($t.'.actions.previous') }}"
    >
        <x-filament::icon icon="heroicon-m-chevron-left" class="fml-icon-sm" />
    </button>

    <button
        type="button"
        class="fml-lightbox__step fml-lightbox__step--next"
        x-on:click.stop="movePreview(1)"
        aria-label="{{ __($t.'.actions.next') }}"
    >
        <x-filament::icon icon="heroicon-m-chevron-right" class="fml-icon-sm" />
    </button>

    <figure class="fml-lightbox__frame" x-on:click.stop>
        <template x-if="preview?.kind === 'image'">
            <img class="fml-lightbox__media" x-bind:src="preview.url" x-bind:alt="preview.name" />
        </template>

        <template x-if="preview?.kind === 'video'">
            <video class="fml-lightbox__media" controls preload="metadata" x-bind:src="preview.url"></video>
        </template>

        <template x-if="preview?.kind === 'audio'">
            <audio class="fml-lightbox__audio" controls x-bind:src="preview.url"></audio>
        </template>

        <template x-if="preview?.kind === 'pdf'">
            <iframe class="fml-lightbox__media fml-lightbox__frame--document" x-bind:src="preview.url" x-bind:title="preview.name"></iframe>
        </template>

        {{-- Nothing a browser can render inline: say so rather than show a void. --}}
        <template x-if="preview && ! ['image', 'video', 'audio', 'pdf'].includes(preview.kind)">
            <div class="fml-lightbox__placeholder">
                <x-filament::icon icon="heroicon-o-document" class="fml-icon-lg fml-muted" />
                <p x-text="preview.name"></p>
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-down-tray"
                    target="_blank"
                    x-bind:href="preview.url"
                >
                    {{ __($t.'.actions.open_in_new_tab') }}
                </x-filament::button>
            </div>
        </template>
    </figure>
</div>
