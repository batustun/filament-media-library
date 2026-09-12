@php
    // A raw echo rather than the @js directive: directives are not compiled
    // inside component-tag attributes, and they swallow the newline after them.
    $js = fn (mixed $value): \Illuminate\Support\Js => \Illuminate\Support\Js::from($value);

    /** @var bool $multiple */
    /** @var string $disk */
    /** @var string $directory */
    /** @var array<int, string> $kinds */
    /** @var string $statePath */
    /** @var string $returns */

    $t = 'filament-media-library::filament-media-library';
@endphp

{{--
    The bridge itself lives in the package's JavaScript. Alpine expressions are
    compiled in the browser, so a mistake in one is invisible to PHP: the page
    renders, the tests pass, and the button does nothing.
--}}
<div
    x-data="fmlPickerBridge({
        statePath: {!! $js($statePath) !!},
        returns: {!! $js($returns) !!},
        multiple: {!! $js($multiple) !!},
        failureTitle: {!! $js(__($t.'.messages.selection_failed')) !!},
    })"
    x-on:filament-media-library:picked.window="write($event)"
    x-on:filament-media-library:failed.window="notifyFailure($event)"
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
