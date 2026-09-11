@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $kind = $item->kind_enum;
    $isSelected = in_array($item->id, $selected ?? [], true);
    $selectable = $selectable ?? false;
@endphp

<div @class(['fml__card', 'is-selected' => $isSelected])>
    @if ($selectable)
        <button
            type="button"
            wire:click.stop="toggleSelect(@js($item->id))"
            class="fml__card-check"
            title="{{ __('filament-media-library::filament-media-library.actions.select') }}"
        >
            @if ($isSelected)
                <x-filament::icon icon="heroicon-m-check" style="width:.875rem;height:.875rem" />
            @endif
        </button>
    @endif

    <button type="button" wire:click="showDetail(@js($item->id))" class="fml__card-thumb">
        @if ($kind === MediaKind::Image)
            <img src="{{ $item->publicUrl() }}" alt="{{ $item->alt ?: $item->name }}" loading="lazy" />
        @else
            <span class="fml__card-fallback" style="color: {{ $kind->hexColor() }}">
                <x-filament::icon :icon="$kind->getIcon()" style="width:3rem;height:3rem" />
                <span>{{ $kind->getLabel() }}</span>
            </span>
        @endif

        <span class="fml__card-caption">
            <p title="{{ $item->name }}">{{ $item->name }}</p>
        </span>
    </button>

    <div class="fml__card-meta">
        <span>{{ $item->human_size }}</span>
        <span>{{ $item->created_at?->translatedFormat('d M Y') }}</span>
    </div>
</div>
