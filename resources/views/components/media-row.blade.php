@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $kind = $item->kind_enum;
    $isSelected = in_array($item->id, $selected ?? [], true);
@endphp

<tr @class(['is-selected' => $isSelected]) wire:click="showDetail(@js($item->id))">
    <td style="width:2.5rem" wire:click.stop>
        <input
            type="checkbox"
            wire:click="toggleSelect(@js($item->id))"
            @checked($isSelected)
        />
    </td>
    <td>
        <div class="fml__row-file">
            <div class="fml__row-thumb">
                @if ($kind === MediaKind::Image)
                    <img src="{{ $item->publicUrl() }}" alt="" loading="lazy" />
                @else
                    <x-filament::icon
                        :icon="$kind->getIcon()"
                        style="width:1.25rem;height:1.25rem;color: {{ $kind->hexColor() }}"
                    />
                @endif
            </div>
            <div class="fml__row-names">
                <p>{{ $item->title ?: $item->name }}</p>
                <p>{{ $item->name }}</p>
            </div>
        </div>
    </td>
    <td>
        <x-filament::badge :color="$kind->getColor()">{{ $kind->getLabel() }}</x-filament::badge>
    </td>
    <td class="fml__muted">{{ $item->human_size }}</td>
    <td class="fml__muted">{{ $item->directory ?: '/' }}</td>
    <td class="fml__muted">{{ $item->created_at?->translatedFormat('d M Y H:i') }}</td>
</tr>
