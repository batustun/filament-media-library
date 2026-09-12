@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $kind = $item->kind_enum;
    $isSelected = in_array($item->id, $selected ?? [], true);
    $extension = strtoupper((string) pathinfo($item->name, PATHINFO_EXTENSION));
    $usage = $showUsage ?? false ? $item->usageCount() : 0;
@endphp

<div
    role="option"
    tabindex="0"
    data-media-id="{{ $item->id }}"
    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
    aria-label="{{ $item->name }}"
    class="fml-card"
    draggable="true"
    x-on:dragstart="startDragging({!! $js($item->id) !!})"
    x-on:dragend="stopDragging()"
    data-name="{{ $item->name }}"
    data-kind="{{ $item->kind }}"
    data-url="{{ $item->publicUrl() }}"
    x-on:click="pick({{ $loop->index }}, {!! $js($item->id) !!}, $event)"
    x-on:dblclick.prevent="openPreviewAt({{ $loop->index }})"
    x-on:keydown.enter.prevent="openPreviewAt({{ $loop->index }})"
    x-on:keydown.space.prevent="pick({{ $loop->index }}, {!! $js($item->id) !!}, $event)"
>
    <span class="fml-card__check" aria-hidden="true">
        <x-filament::icon icon="heroicon-m-check" class="fml-icon-xs" />
    </span>

    {{-- Clicking a card selects it, so anything else needs its own target. --}}
    <div class="fml-card__tools">
        @if ($usage > 0)
            <x-filament::badge color="gray" size="xs">{{ $usage }}</x-filament::badge>
        @endif

        {{-- Only the page has a detail panel to open. --}}
        <button
            type="button"
            class="fml-card__tool"
            x-show="mode === 'page'"
            x-cloak
            title="{{ __($t.'.actions.details') }}"
            aria-label="{{ __($t.'.actions.details') }}"
            x-on:click.stop="$wire.showDetail({!! $js($item->id) !!})"
        >
            <x-filament::icon icon="heroicon-m-information-circle" class="fml-icon-xs" />
        </button>

        <button
            type="button"
            class="fml-card__tool"
            title="{{ __($t.'.actions.preview') }}"
            aria-label="{{ __($t.'.actions.preview') }}"
            x-on:click.stop="openPreviewAt({{ $loop->index }})"
        >
            <x-filament::icon icon="heroicon-m-magnifying-glass-plus" class="fml-icon-xs" />
        </button>
    </div>

    <div class="fml-card__thumb">
        @if ($item->isRenderableImage())
            <img
                src="{{ $item->thumbnailUrl() }}"
                @if ($srcset = $item->srcset()) srcset="{{ $srcset }}" sizes="160px" @endif
                alt="{{ $item->alt ?: $item->name }}"
                loading="lazy"
                decoding="async"
                @if ($item->width && $item->height) width="{{ $item->width }}" height="{{ $item->height }}" @endif
                onerror="this.closest('.fml-card__thumb')?.classList.add('fml-card__thumb--broken'); this.remove()"
            />
        @else
            <span class="fml-card__fallback">
                <x-filament::icon :icon="$kind->getIcon()" class="fml-icon-lg fml-muted" />
                <span class="fml-card__ext">{{ $extension ?: $kind->getLabel() }}</span>
            </span>
        @endif
    </div>

    <div class="fml-card__meta">
        <span class="fml-card__name" title="{{ $item->name }}">{{ $item->name }}</span>
        <span class="fml-card__sub">
            {{ $item->human_size }}@if ($showExtensions ?? true) @if ($extension) · {{ $extension }} @endif @endif
        </span>
    </div>
</div>
