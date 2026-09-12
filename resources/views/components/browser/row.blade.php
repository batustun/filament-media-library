@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    $t = 'filament-media-library::filament-media-library';

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $kind = $item->kind_enum;
    $isSelected = in_array($item->id, $selected ?? [], true);
@endphp

<tr
    data-media-id="{{ $item->id }}"
    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
    data-name="{{ $item->name }}"
    data-kind="{{ $item->kind }}"
    data-directory="{{ $item->directory }}"
    data-url="{{ $item->publicUrl() }}"
    x-on:click="pick({{ $loop->index }}, {!! $js($item->id) !!}, $event)"
    x-on:dblclick.prevent="openPreviewAt({{ $loop->index }})"
    x-on:contextmenu.prevent="openContextMenu({{ $loop->index }}, $event)"
>
    <td class="fml-table__check" x-on:click.stop>
        <x-filament::input.checkbox
            :checked="$isSelected"
            wire:click="toggleSelect({!! $js($item->id) !!})"
            :aria-label="$item->name"
        />
    </td>
    <td>
        <div class="fml-table__file">
            <span class="fml-table__thumb">
                @if ($item->isRenderableImage())
                    <img
                        src="{{ $item->thumbnailUrl() }}"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        onerror="this.replaceWith(Object.assign(document.createElement('span'), { className: 'fml-thumb-fallback' }))"
                    />
                @else
                    <x-filament::icon :icon="$kind->getIcon()" class="fml-icon-sm fml-muted" />
                @endif
            </span>
            <span class="fml-table__names">
                <p>{{ $item->title ?: $item->name }}</p>
                <p>{{ $item->name }}</p>
            </span>
        </div>
    </td>
    <td>
        <x-filament::badge :color="$kind->getColor()" size="xs">{{ $kind->getLabel() }}</x-filament::badge>
    </td>
    <td class="fml-muted">{{ $item->human_size }}</td>
    <td class="fml-muted">{{ $item->directory ?: '/' }}</td>
    <td class="fml-muted">{{ $item->created_at?->translatedFormat('d M Y') }}</td>
    <td class="fml-table__peek" x-on:click.stop>
        <x-filament::icon-button
            icon="heroicon-m-magnifying-glass-plus"
            color="gray"
            size="sm"
            x-on:click="openPreviewAt({{ $loop->index }})"
            :label="__($t.'.actions.preview')"
        />
    </td>
</tr>
