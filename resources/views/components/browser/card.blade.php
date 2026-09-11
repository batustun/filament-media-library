@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

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
    x-on:dragstart="startDragging(@js($item->id))"
    x-on:dragend="stopDragging()"
    x-on:click="pick({{ $loop->index }}, @js($item->id), $event)"
    x-on:keydown.enter.prevent="$wire.showDetail(@js($item->id))"
    x-on:keydown.space.prevent="pick({{ $loop->index }}, @js($item->id), $event)"
    x-on:contextmenu.prevent="openDialog('context', { id: @js($item->id), name: @js($item->name) })"
>
    <span class="fml-card__check" aria-hidden="true">
        <x-filament::icon icon="heroicon-m-check" class="fml-icon-xs" />
    </span>

    @if ($usage > 0)
        <span class="fml-card__badge">
            <x-filament::badge color="gray" size="xs">{{ $usage }}</x-filament::badge>
        </span>
    @endif

    <div class="fml-card__thumb">
        @if ($kind === MediaKind::Image)
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
