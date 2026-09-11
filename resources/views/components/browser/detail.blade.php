@php
    use Batustun\FilamentMediaLibrary\Enums\MediaKind;

    /** @var \Batustun\FilamentMediaLibrary\Models\Media $item */
    $t = 'filament-media-library::filament-media-library';
    $kind = $item->kind_enum;
    $url = $item->publicUrl();
    $usage = $item->usageCount();
    $canManage = $this->canMedia('manage');
    $canDelete = $this->canMedia('delete');
    $canUpload = $this->canMedia('upload');
    $metadataFields = \Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig::metadataFields();
    $tagsEnabled = \Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig::tagsEnabled();
@endphp

<div
    class="fml-detail"
    wire:key="fml-detail-{{ $item->id }}"
    x-data="{
        title: @js($item->title ?? ''),
        alt: @js($item->alt ?? ''),
        description: @js($item->description ?? ''),
        tags: @js(implode(', ', $item->tagNames())),
        custom: @js((object) $item->customMeta()),
        save () {
            this.$wire.updateMeta(@js($item->id), {
                title: this.title,
                alt: this.alt,
                description: this.description,
                custom: this.custom,
            })

            @if ($tagsEnabled)
                this.$wire.syncTags(@js($item->id), this.tags.split(',').map((t) => t.trim()).filter(Boolean))
            @endif
        },
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

            @if ($tagsEnabled && ($tagNames = $item->tagNames()))
                <div class="fml-tags">
                    @foreach ($tagNames as $tagName)
                        <x-filament::badge color="gray" size="xs">{{ $tagName }}</x-filament::badge>
                    @endforeach
                </div>
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

                @if ($tagsEnabled)
                    <div class="fml-field">
                        <span class="fml-label">{{ __($t.'.fields.tags') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" x-model="tags" :placeholder="__($t.'.fields.tags_hint')" />
                        </x-filament::input.wrapper>
                    </div>
                @endif

                @foreach ($metadataFields as $field)
                    <div class="fml-field">
                        <span class="fml-label">{{ $field['label'] }}</span>
                        @switch($field['type'])
                            @case('textarea')
                                <x-filament::input.wrapper>
                                    <textarea rows="2" class="fi-input" x-model="custom['{{ $field['key'] }}']"></textarea>
                                </x-filament::input.wrapper>
                                @break

                            @case('select')
                                <x-filament::input.wrapper>
                                    <x-filament::input.select x-model="custom['{{ $field['key'] }}']">
                                        <option value=""></option>
                                        @foreach ($field['options'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                @break

                            @case('boolean')
                                <label class="fml-row">
                                    <x-filament::input.checkbox x-model="custom['{{ $field['key'] }}']" />
                                    <span class="fml-muted">{{ $field['label'] }}</span>
                                </label>
                                @break

                            @default
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="{{ in_array($field['type'], ['number', 'url', 'date'], true) ? $field['type'] : 'text' }}"
                                        x-model="custom['{{ $field['key'] }}']"
                                    />
                                </x-filament::input.wrapper>
                        @endswitch
                    </div>
                @endforeach

                <x-filament::button size="sm" icon="heroicon-m-check" x-on:click="save()">
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

            @if ($canManage && $kind === MediaKind::Image && $item->isEditableImage() && Route::has('filament-media-library.image-edit'))
                <div
                    x-data="fmlImageEditor({
                        src: @js($url),
                        endpoint: @js(route('filament-media-library.image-edit', $item->id)),
                        csrf: @js(csrf_token()),
                        mime: @js($item->mime_type),
                    })"
                >
                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-m-scissors"
                        class="fml-btn-block"
                        x-on:click="start()"
                    >
                        {{ __($t.'.actions.edit_image') }}
                    </x-filament::button>

                    <div x-show="open" x-cloak class="fml-modal" x-on:keydown.escape.window="open = false">
                        <div class="fml-modal__window fml-modal__window--wide">
                            <div class="fml-editor">
                                <div class="fml-editor__stage">
                                    <canvas
                                        x-ref="canvas"
                                        class="fml-editor__canvas"
                                        x-on:mousedown.prevent="startCrop($event)"
                                        x-on:mousemove="moveCrop($event)"
                                        x-on:mouseup="endCrop()"
                                        x-on:mouseleave="endCrop()"
                                    ></canvas>
                                    <div class="fml-editor__crop" x-bind:style="cropStyle()"></div>
                                </div>

                                <div class="fml-editor__tools">
                                    <x-filament::icon-button icon="heroicon-m-arrow-path" color="gray"
                                        x-on:click="rotate()" :label="__($t.'.actions.rotate')" />
                                    <x-filament::icon-button icon="heroicon-m-arrows-right-left" color="gray"
                                        x-on:click="flipX = ! flipX; draw()" :label="__($t.'.actions.flip_h')" />
                                    <x-filament::icon-button icon="heroicon-m-arrows-up-down" color="gray"
                                        x-on:click="flipY = ! flipY; draw()" :label="__($t.'.actions.flip_v')" />
                                    <x-filament::icon-button icon="heroicon-m-backspace" color="gray"
                                        x-on:click="reset()" :label="__($t.'.actions.reset')" />
                                </div>
                            </div>

                            <div class="fml-modal__footer">
                                <x-filament::button color="gray" size="sm" x-on:click="open = false">
                                    {{ __($t.'.actions.cancel') }}
                                </x-filament::button>
                                <x-filament::button size="sm" x-on:click="save()" x-bind:disabled="saving">
                                    {{ __($t.'.actions.save') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($canUpload)
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-m-document-duplicate"
                    wire:click="duplicateOne(@js($item->id))"
                >
                    {{ __($t.'.actions.duplicate') }}
                </x-filament::button>
            @endif

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

        @foreach ($this->customItemActions() as $action)
            {{ $action(['record' => $item->id]) }}
        @endforeach

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
