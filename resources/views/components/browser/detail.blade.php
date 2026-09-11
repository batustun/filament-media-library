@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $t = 'filament-media-library::filament-media-library';
    $kind = $item->kind_enum;
    $url = $item->publicUrl();
    $usage = $item->usageCount();
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
@endphp

<div
    class="fml-detail"
    wire:key="fml-detail-{{ $item->id }}"
    x-data="{
        title: @js($item->title ?? ''),
        alt: @js($item->alt ?? ''),
        description: @js($item->description ?? ''),
    }"
>
    <x-filament::section compact>
        <x-slot name="heading">{{ __($t.'.fields.details') }}</x-slot>

        <x-slot name="headerEnd">
            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                size="sm"
                wire:click="closeDetail"
                :label="__($t.'.actions.close')"
            />
        </x-slot>

        <div class="fml-stack">
            @if ($item->isProviderBacked() && ! $item->isReady())
                <x-filament::callout color="warning" icon="heroicon-m-arrow-path">
                    {{ __($t.'.messages.transcoding') }}
                </x-filament::callout>
            @endif

            <div class="fml-preview">
                @if ($item->isProviderBacked())
                    @if ($item->isReady())
                        <video
                            src="{{ $url }}"
                            @if ($poster = $item->posterUrl()) poster="{{ $poster }}" @endif
                            controls
                            playsinline
                            preload="metadata"
                        ></video>
                    @elseif ($poster = $item->posterUrl())
                        <img src="{{ $poster }}" alt="{{ $item->name }}" />
                    @else
                        <x-filament::loading-indicator class="fml-icon-lg fml-muted" />
                    @endif
                @else
                @switch($kind)
                    @case(MediaKind::Image)
                        <img
                            src="{{ $item->conversionUrl('medium') ?? $url }}"
                            @if ($srcset = $item->srcset()) srcset="{{ $srcset }}" sizes="(min-width: 1280px) 320px, 100vw" @endif
                            alt="{{ $item->alt ?: $item->name }}"
                            decoding="async"
                        />
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
                        <x-filament::icon :icon="$kind->getIcon()" class="fml-icon-xl fml-muted" />
                @endswitch
                @endif
            </div>

            @if ($usage > 0)
                <x-filament::badge color="info" icon="heroicon-m-link">
                    {{ trans_choice($t.'.messages.in_use', $usage, ['count' => $usage]) }}
                </x-filament::badge>
            @else
                <x-filament::badge color="gray">{{ __($t.'.messages.not_used') }}</x-filament::badge>
            @endif

            <dl class="fml-facts">
                <div>
                    <dt>{{ __($t.'.fields.name') }}</dt>
                    <dd>{{ $item->name }}</dd>
                </div>
                <div>
                    <dt>{{ __($t.'.fields.type') }}</dt>
                    <dd>{{ $kind->getLabel() }} · {{ $item->mime_type ?: '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __($t.'.fields.size') }}</dt>
                    <dd>{{ $item->human_size }}</dd>
                </div>
                @if ($item->width && $item->height)
                    <div>
                        <dt>{{ __($t.'.fields.dimensions') }}</dt>
                        <dd>{{ $item->width }}×{{ $item->height }}</dd>
                    </div>
                @endif
                <div>
                    <dt>{{ __($t.'.fields.folder') }}</dt>
                    <dd>{{ $item->directory ?: '/' }}</dd>
                </div>
                @if ($item->duration)
                    <div>
                        <dt>{{ __($t.'.fields.duration') }}</dt>
                        <dd>{{ gmdate($item->duration >= 3600 ? 'H:i:s' : 'i:s', (int) $item->duration) }}</dd>
                    </div>
                @endif
                <div>
                    <dt>{{ __($t.'.fields.uploaded_at') }}</dt>
                    <dd>{{ $item->created_at?->translatedFormat('d M Y H:i') }}</dd>
                </div>
                @if ($uploader = $item->uploader)
                    <div>
                        <dt>{{ __($t.'.fields.uploaded_by') }}</dt>
                        <dd>{{ $uploader->name ?? $uploader->email ?? $item->uploaded_by }}</dd>
                    </div>
                @endif
            </dl>

            <div class="fml-field">
                <span class="fml-label">{{ __($t.'.fields.url') }}</span>
                <div class="fml-row">
                    <span class="fml-row__grow">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" readonly :value="$url" x-ref="url" />
                        </x-filament::input.wrapper>
                    </span>
                    <x-filament::icon-button
                        icon="heroicon-m-clipboard-document"
                        color="gray"
                        x-on:click="copy($refs.url.value)"
                        x-bind:icon="copied ? 'heroicon-m-check' : undefined"
                        :label="__($t.'.actions.copy_url')"
                    />

                    @if (! $item->isProviderBacked() && Route::has('filament-media-library.download'))
                        <x-filament::icon-button
                            tag="a"
                            icon="heroicon-m-arrow-down-tray"
                            color="gray"
                            :href="route('filament-media-library.download', $item->id)"
                            :label="__($t.'.actions.download')"
                        />
                    @endif
                </div>
            </div>
        </div>
    </x-filament::section>

    @if ($canManage)
        <x-filament::section compact collapsible collapsed>
            <x-slot name="heading">{{ __($t.'.fields.details') }}</x-slot>

            <div class="fml-stack">
                <div class="fml-field">
                    <span class="fml-label">{{ __($t.'.fields.title') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" x-model="title" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fml-field">
                    <span class="fml-label">{{ __($t.'.fields.alt') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" x-model="alt" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fml-field">
                    <span class="fml-label">{{ __($t.'.fields.description') }}</span>
                    <x-filament::input.wrapper>
                        <textarea rows="2" x-model="description" class="fi-input"></textarea>
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button
                    size="sm"
                    icon="heroicon-m-check"
                    x-on:click="$wire.updateMeta(@js($item->id), { title, alt, description })"
                >
                    {{ __($t.'.actions.save_details') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <div class="fml-stack">
        @if ($canManage)
            <x-filament::button
                size="sm"
                color="gray"
                icon="heroicon-m-pencil-square"
                x-on:click="openDialog('rename-media', { id: @js($item->id) }, @js($item->name))"
            >
                {{ __($t.'.actions.rename') }}
            </x-filament::button>

            <label class="fml-row">
                <x-filament::button tag="span" size="sm" color="gray" icon="heroicon-m-arrow-path">
                    {{ __($t.'.actions.replace') }}
                </x-filament::button>
                <input
                    type="file"
                    class="fml-sr-only"
                    wire:model="uploads"
                    x-on:change="$nextTick(() => $wire.replaceFile(@js($item->id)))"
                />
            </label>
            <p class="fml-hint fml-muted">{{ __($t.'.messages.replace_hint') }}</p>
        @endif

        @if ($canDelete)
            <div class="fml-divider">
                @if ($usage > 0)
                    <x-filament::callout color="warning" icon="heroicon-m-exclamation-triangle" class="fml-callout">
                        {{ trans_choice($t.'.messages.in_use_warning', $usage, ['count' => $usage]) }}
                    </x-filament::callout>
                @endif

                <x-filament::button
                    size="sm"
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="deleteOne(@js($item->id))"
                    wire:confirm="{{ __($t.'.messages.confirm_delete', ['name' => $item->name]) }}"
                >
                    {{ __($t.'.actions.delete') }}
                </x-filament::button>
            </div>
        @endif
    </div>
</div>
