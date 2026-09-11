@php
    $t = 'filament-media-library::filament-media-library';
    $selectedCount = count($selected);
@endphp

<div
    class="fml fml-picker"
    x-data="fmlBrowser({ mode: 'picker' })"
    x-on:keydown.escape.window="dialog && closeDialog()"
    x-on:keydown.right.window="moveCursor(1)"
    x-on:keydown.left.window="moveCursor(-1)"
    x-on:keydown.down.window.prevent="moveCursor(columns())"
    x-on:keydown.up.window.prevent="moveCursor(-columns())"
    wire:key="fml-picker-{{ $targetStatePath }}"
>
    <div class="fml-layout">
        <aside class="fml-layout__aside">
            @include('filament-media-library::components.browser.folders', ['folders' => $folders])
        </aside>

        <section class="fml-layout__main">
            @include('filament-media-library::components.browser.toolbar')

            @if ($this->canMedia('upload'))
                @include('filament-media-library::components.browser.uploader', ['uploadAction' => 'uploadAndApply'])
            @endif

            <div class="fml-picker__scroll">
                @include('filament-media-library::components.browser.results', [
                    'paginator' => $paginator,
                    'showUsage' => false,
                ])
            </div>

            <footer class="fml-picker__footer">
                <span class="fml-muted">
                    {{ __($t.'.messages.selected', ['count' => $selectedCount]) }}
                </span>

                <div class="fml-row">
                    @if ($selectedCount > 0)
                        <x-filament::link tag="button" color="gray" size="sm" wire:click="clearSelection">
                            {{ __($t.'.actions.clear_selection') }}
                        </x-filament::link>
                    @endif

                    <x-filament::button
                        icon="heroicon-m-check"
                        wire:click="confirmSelection"
                        :disabled="$selectedCount === 0"
                    >
                        {{ $selectedCount > 0
                            ? __($t.'.actions.select_count', ['count' => $selectedCount])
                            : __($t.'.actions.select_prompt') }}
                    </x-filament::button>
                </div>
            </footer>
        </section>
    </div>

    @include('filament-media-library::components.browser.dialogs', ['folders' => $folders])
</div>
