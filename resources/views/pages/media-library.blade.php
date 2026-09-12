@php
    use Batustun\FilamentMediaLibrary\Support\MimeKindResolver;

    $t = 'filament-media-library::filament-media-library';

    $paginator = $this->items();
    $folders = $this->folderTree();
    $detail = $this->detailRecord();
    $sources = $this->sourceOptions();
@endphp

<x-filament-panels::page>
    <div
        class="fml"
        x-data="fmlBrowser({ mode: 'page' })"
        x-on:keydown.escape.window="dialog ? closeDialog() : $wire.closeDetail()"
        x-on:keydown.right.window="moveCursor(1)"
        x-on:keydown.left.window="moveCursor(-1)"
        x-on:keydown.down.window.prevent="moveCursor(columns())"
        x-on:keydown.up.window.prevent="moveCursor(-columns())"
    >
        <div @class(['fml-layout', 'fml-layout--detail' => (bool) $detail])>
            <aside class="fml-layout__aside">
                @if (count($sources) > 1)
                    <div class="fml-panel fml-panel__body">
                        <div class="fml-field">
                            <span class="fml-label">{{ __($t.'.fields.disk') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="disk" :aria-label="__($t.'.fields.disk')">
                                    @foreach ($sources as $sourceKey => $sourceLabel)
                                        <option value="{{ $sourceKey }}">{{ $sourceLabel }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <p class="fml-hint fml-muted">
                                {{ __($t.'.fields.total') }}: {{ MimeKindResolver::formatBytes($this->diskUsageBytes()) }}
                            </p>
                        </div>
                    </div>
                @endif

                @include('filament-media-library::components.browser.folders', ['folders' => $folders])
            </aside>

            <section class="fml-layout__main">
                @include('filament-media-library::components.browser.toolbar')

                @if ($this->canMedia('upload'))
                    @include('filament-media-library::components.browser.uploader')
                @endif

                @include('filament-media-library::components.browser.results', [
                    'paginator' => $paginator,
                    'showUsage' => true,
                ])
            </section>

            @if ($detail)
                <aside class="fml-layout__detail">
                    @include('filament-media-library::components.browser.detail', ['item' => $detail])
                </aside>
            @endif
        </div>

        @include('filament-media-library::components.browser.lightbox')

        @include('filament-media-library::components.browser.dialogs', ['folders' => $folders])
    </div>
</x-filament-panels::page>
