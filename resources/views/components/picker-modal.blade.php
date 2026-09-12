@php
    /** @var bool $multiple */
    /** @var string $disk */
    /** @var string $directory */
    /** @var array<int, string> $kinds */
    /** @var string $statePath */
    /** @var string $returns */

    $t = 'filament-media-library::filament-media-library';
@endphp

{{--
    Bridges the picker to the form field it was opened from.

    The picker is a nested Livewire component, so it cannot write to the field
    directly; it dispatches, and this listener — which lives inside the FIELD's
    component — does the writing through $wire, Livewire's supported handle on
    the closest component. Reaching for Livewire.find() and a wire:id lookup
    was one assumption too many, and when it missed, it missed in silence.
--}}
<div
    x-data="{
        write (event) {
            if (event.detail?.statePath !== @js($statePath)) return

            const returns = @js($returns)
            const values = (event.detail.items || [])
                .map((item) => returns === 'id' ? item.id : (returns === 'path' ? item.path : item.url))
                .filter(Boolean)

            if (! values.length) {
                this.fail('the library returned nothing to insert')

                return
            }

            if (typeof $wire === 'undefined' || $wire === null) {
                this.fail('the form component could not be reached')

                return
            }

            // FileUpload stores raw state as a UUID-keyed object; a plain
            // string or an indexed array breaks getRawState() on the next
            // Livewire round trip.
            const uuid = () => (crypto.randomUUID
                ? crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                    const r = (Math.random() * 16) | 0
                    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
                }))

            const keyed = (vals) => Object.fromEntries(vals.map((value) => [uuid(), value]))

            try {
                if (@js($multiple)) {
                    const current = $wire.get(@js($statePath))
                    const isKeyed = current && typeof current === 'object' && ! Array.isArray(current)

                    $wire.set(@js($statePath), isKeyed ? { ...current, ...keyed(values) } : keyed(values))
                } else {
                    $wire.set(@js($statePath), keyed([values[0]]))
                }
            } catch (error) {
                this.fail(error.message)

                return
            }

            // Close the action modal the picker was opened from. Optional: the
            // field is already filled, so a failure here must not look like the
            // selection failed.
            try {
                $wire.unmountAction()
            } catch (error) {
                //
            }
        },

        /** Never fail quietly: a picker that does nothing is unexplainable. */
        fail (reason) {
            console.error('[filament-media-library] could not insert the selection:', reason)

            window.dispatchEvent(new CustomEvent('filament-media-library:failed', {
                detail: { reason },
            }))
        },
    }"
    x-on:filament-media-library:picked.window="write($event)"
    x-on:filament-media-library:failed.window="
        new FilamentNotification()
            .title(@js(__($t.'.messages.selection_failed')))
            .body($event.detail.reason)
            .danger()
            .send()
    "
>
    @livewire('filament-media-library-picker', [
        'multiple' => $multiple,
        'disk' => $disk,
        'directory' => $directory,
        'kinds' => $kinds,
        'targetStatePath' => $statePath,
        'uploadDirectory' => $uploadDirectory ?? '',
    ], key('fml-picker-'.$statePath))
</div>
