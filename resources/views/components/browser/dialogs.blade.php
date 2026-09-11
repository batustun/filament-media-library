@php
    $t = 'filament-media-library::filament-media-library';
@endphp

{{--
    One modal drives every prompt in the browser. The Alpine `dialog` object
    carries the kind, the payload and the editable value, so adding a prompt is
    a case in submitDialog() rather than another modal in the markup.
--}}
<div
    x-show="dialog"
    x-cloak
    class="fml-modal"
    x-on:keydown.escape.window="closeDialog()"
    x-on:click.self="closeDialog()"
>
    <div class="fml-modal__window" x-on:keydown.enter.prevent="submitDialog()">
        <template x-if="dialog && dialog.kind === 'new-folder'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title">{{ __($t.'.actions.new_folder') }}</h3>
                <p class="fml-modal__desc">{{ __($t.'.messages.new_folder_hint') }}</p>
                <input type="text" class="fi-input fml-modal__input" x-model="dialog.value" x-ref="dialogInput" />
            </div>
        </template>

        <template x-if="dialog && dialog.kind === 'rename-folder'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title">{{ __($t.'.actions.rename_folder') }}</h3>
                <p class="fml-modal__desc">{{ __($t.'.messages.move_hint') }}</p>
                <input type="text" class="fi-input fml-modal__input" x-model="dialog.value" x-ref="dialogInput" />
            </div>
        </template>

        <template x-if="dialog && dialog.kind === 'rename-media'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title">{{ __($t.'.actions.rename') }}</h3>
                <p class="fml-modal__desc">{{ __($t.'.messages.rename_hint') }}</p>
                <input type="text" class="fi-input fml-modal__input" x-model="dialog.value" x-ref="dialogInput" />
            </div>
        </template>

        <template x-if="dialog && dialog.kind === 'move-selection'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title">{{ __($t.'.actions.move_to') }}</h3>
                <p class="fml-modal__desc">{{ __($t.'.messages.move_hint') }}</p>
                <input
                    type="text"
                    class="fi-input fml-modal__input"
                    x-model="dialog.value"
                    x-ref="dialogInput"
                    list="fml-folder-options"
                    placeholder="{{ __($t.'.fields.destination') }}"
                />
                <datalist id="fml-folder-options">
                    @foreach ($folders as $folder)
                        <option value="{{ $folder['path'] }}"></option>
                    @endforeach
                </datalist>
            </div>
        </template>

        <template x-if="dialog && dialog.kind === 'delete-media'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title fml-danger">{{ __($t.'.actions.delete') }}</h3>
                <p class="fml-modal__desc" x-text="@js(__($t.'.messages.confirm_delete', ['name' => ':name'])).replace(':name', dialog.payload.name)"></p>
            </div>
        </template>

        <template x-if="dialog && dialog.kind === 'delete-folder'">
            <div class="fml-modal__body">
                <h3 class="fml-modal__title fml-danger">{{ __($t.'.actions.delete_folder') }}</h3>
                <p class="fml-modal__desc" x-text="@js(__($t.'.messages.confirm_delete_folder', ['name' => ':name'])).replace(':name', dialog.payload.name)"></p>
            </div>
        </template>

        <div class="fml-modal__footer">
            <x-filament::button color="gray" size="sm" x-on:click="closeDialog()">
                {{ __($t.'.actions.cancel') }}
            </x-filament::button>

            <x-filament::button
                size="sm"
                x-bind:color="dialog && dialog.kind.startsWith('delete') ? 'danger' : 'primary'"
                x-on:click="submitDialog()"
                x-bind:disabled="dialog && ! dialog.kind.startsWith('delete') && ! String(dialog.value ?? '').trim() && dialog.kind !== 'move-selection'"
            >
                <span x-text="dialog && dialog.kind.startsWith('delete')
                    ? @js(__($t.'.actions.confirm_delete'))
                    : @js(__($t.'.actions.save'))"></span>
            </x-filament::button>
        </div>
    </div>
</div>
