@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $kind = $item->kind_enum;
    $url = $item->publicUrl();
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
@endphp

<div
    x-data="{
        title: @js($item->title ?? ''),
        alt: @js($item->alt ?? ''),
        description: @js($item->description ?? ''),
        renaming: false,
        newName: @js($item->name),
        copy () {
            navigator.clipboard?.writeText(this.$refs.urlInput.value)
        },
    }"
    class="fml__detail"
>
    <div class="fml__detail-header">
        <h3>{{ __('filament-media-library::filament-media-library.fields.detail') }}</h3>
        <button type="button" wire:click="closeDetail" class="fml__btn fml__btn--link" aria-label="{{ __('filament-media-library::filament-media-library.actions.close') }}">
            <x-filament::icon icon="heroicon-m-x-mark" style="width:1.25rem;height:1.25rem" />
        </button>
    </div>

    <div class="fml__detail-preview">
        @switch($kind)
            @case(MediaKind::Image)
                <img src="{{ $url }}" alt="{{ $item->alt ?: $item->name }}" />
                @break
            @case(MediaKind::Video)
                <video src="{{ $url }}" controls preload="metadata"></video>
                @break
            @case(MediaKind::Audio)
                <audio src="{{ $url }}" controls preload="metadata"></audio>
                @break
            @case(MediaKind::Pdf)
                <iframe src="{{ $url }}" loading="lazy" title="{{ $item->name }}"></iframe>
                @break
            @default
                <div class="fml__detail-fallback" style="color: {{ $kind->hexColor() }}">
                    <x-filament::icon :icon="$kind->getIcon()" style="width:4rem;height:4rem" />
                </div>
        @endswitch
    </div>

    <dl class="fml__dl">
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.name') }}</dt>
            <dd>{{ $item->name }}</dd>
        </div>
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.type') }}</dt>
            <dd>{{ $kind->getLabel() }} · {{ $item->mime_type ?: '—' }}</dd>
        </div>
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.size') }}</dt>
            <dd>{{ $item->human_size }}</dd>
        </div>
        @if ($item->width && $item->height)
            <div>
                <dt>{{ __('filament-media-library::filament-media-library.fields.dimensions') }}</dt>
                <dd>{{ $item->width }}×{{ $item->height }} px</dd>
            </div>
        @endif
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.disk') }}</dt>
            <dd>{{ $item->disk }}</dd>
        </div>
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.folder') }}</dt>
            <dd>{{ $item->directory ?: '/' }}</dd>
        </div>
        <div>
            <dt>{{ __('filament-media-library::filament-media-library.fields.uploaded_at') }}</dt>
            <dd>{{ $item->created_at?->translatedFormat('d M Y H:i') }}</dd>
        </div>
    </dl>

    <div class="fml__field">
        <label class="fml__label">{{ __('filament-media-library::filament-media-library.fields.url') }}</label>
        <div class="fml__inline">
            <input readonly value="{{ $url }}" x-ref="urlInput" class="fml__input fml__input--readonly" />
            <button type="button" @click="copy()" class="fml__btn fml__btn--primary">
                {{ __('filament-media-library::filament-media-library.actions.copy') }}
            </button>
        </div>
    </div>

    @if ($canManage)
        <div class="fml__field">
            <label class="fml__label">{{ __('filament-media-library::filament-media-library.fields.title') }}</label>
            <input x-model="title" class="fml__input" />

            <label class="fml__label">{{ __('filament-media-library::filament-media-library.fields.alt') }}</label>
            <input x-model="alt" class="fml__input" />

            <label class="fml__label">{{ __('filament-media-library::filament-media-library.fields.description') }}</label>
            <textarea x-model="description" rows="2" class="fml__textarea"></textarea>

            <button
                type="button"
                @click="$wire.updateMeta(@js($item->id), { title, alt, description })"
                class="fml__btn fml__btn--success fml__btn--block"
            >
                {{ __('filament-media-library::filament-media-library.actions.save_meta') }}
            </button>
        </div>
    @endif

    @if ($canManage || $canDelete)
        <div class="fml__divider">
            @if ($canManage)
                <button
                    type="button"
                    @click="renaming = !renaming; if (renaming) $nextTick(() => $refs.renameInput?.focus())"
                    class="fml__btn fml__btn--ghost fml__btn--block"
                >
                    <x-filament::icon icon="heroicon-m-pencil-square" style="width:1rem;height:1rem" />
                    {{ __('filament-media-library::filament-media-library.actions.rename') }}
                </button>

                <div x-show="renaming" x-cloak class="fml__inline">
                    <input x-model="newName" x-ref="renameInput" class="fml__input" />
                    <button
                        type="button"
                        @click="$wire.renameOne(@js($item->id), newName); renaming = false"
                        class="fml__btn fml__btn--primary"
                    >
                        {{ __('filament-media-library::filament-media-library.actions.save') }}
                    </button>
                </div>
            @endif

            @if ($canDelete)
                <button
                    type="button"
                    wire:click="deleteOne(@js($item->id))"
                    wire:confirm="{{ __('filament-media-library::filament-media-library.messages.confirm_delete') }}"
                    class="fml__btn fml__btn--danger fml__btn--block"
                >
                    <x-filament::icon icon="heroicon-m-trash" style="width:1rem;height:1rem" />
                    {{ __('filament-media-library::filament-media-library.actions.delete_file') }}
                </button>
            @endif
        </div>
    @endif
</div>
