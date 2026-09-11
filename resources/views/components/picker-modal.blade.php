@php
    /** @var bool $multiple */
    /** @var string $disk */
    /** @var string $directory */
    /** @var array<int, string> $kinds */
    /** @var string $statePath */
    /** @var string $returns */
@endphp

<div
    x-data="{
        init () {
            window.addEventListener('filament-media-library:picked', (event) => {
                if (event.detail?.statePath !== @js($statePath)) return

                const returns = @js($returns)
                const items = event.detail.items || []
                const values = items
                    .map((item) => returns === 'id' ? item.id : (returns === 'path' ? item.path : item.url))
                    .filter(Boolean)

                if (! values.length) return

                const livewireEl = $el.closest('[wire\\:id]')
                const component = livewireEl ? window.Livewire.find(livewireEl.getAttribute('wire:id')) : null
                if (! component) return

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

                if (@js($multiple)) {
                    const current = component.get(@js($statePath))
                    const isKeyedObject = current && typeof current === 'object' && ! Array.isArray(current)

                    component.set(@js($statePath), isKeyedObject
                        ? { ...current, ...keyed(values) }
                        : keyed(values))
                } else {
                    component.set(@js($statePath), keyed([values[0]]))
                }

                if (typeof component.call === 'function') {
                    component.call('unmountAction')
                }
            })
        },
    }"
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
