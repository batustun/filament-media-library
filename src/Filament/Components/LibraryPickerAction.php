<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Components;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaResolver;
use Error;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * The "Choose from Library" button that turns any FileUpload into a picker.
 *
 * Lives on its own so the dedicated MediaInput field and the automatic
 * attachment to every other FileUpload share one definition — the modal, its
 * width, its labels and the state contract are described once.
 */
final class LibraryPickerAction
{
    public const NAME = 'pickFromMediaLibrary';

    /**
     * @param  array<int, string>  $kinds
     * @param  string  $returns  'url', 'path' or 'id' — what the picker writes
     *                           into the field's state
     */
    public static function for(FileUpload $component, array $kinds = [], string $returns = 'url'): Action
    {
        $t = 'filament-media-library::filament-media-library';

        return Action::make(self::NAME)
            ->label(__($t.'.actions.select_from_library'))
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->modalHeading(__($t.'.navigation.label'))
            ->modalWidth('7xl')
            // The picker owns its own footer, so Filament renders none: two
            // stacked footer rows cost the grid a band of height for nothing.
            ->modalFooterActions([])
            ->modalContent(function () use ($component, $kinds, $returns): ?ViewContract {
                // Filament renders an action's modal wherever the action is
                // rendered, including a re-render at the end of a Livewire
                // request — and the field the closure holds is the instance
                // configureUsing saw, which for a repeater's child schema is a
                // blueprint that may never be attached to anything. Detached, it
                // can say neither what it holds nor where it writes, and asking
                // takes the whole page down with it.
                if (! self::isAttached($component)) {
                    return null;
                }

                $current = self::currentSelection($component);

                return View::make('filament-media-library::components.picker-modal', [
                    'multiple' => $component->isMultiple(),
                    'disk' => $component->getDiskName(),
                    // Open where the chosen file lives; with nothing chosen the
                    // whole library is the sensible starting point, and
                    // uploadDirectory still steers new uploads.
                    'directory' => (string) $current->first()?->directory,
                    'uploadDirectory' => $component->getDirectory() ?: '',
                    'kinds' => $kinds,
                    'statePath' => $component->getStatePath(),
                    'returns' => $returns,
                    'selectedIds' => $current->map(fn (Media $media): string => (string) $media->getKey())->all(),
                ]);
            });
    }

    /**
     * The records the field is already holding.
     *
     * State can be a UUID, a disk path or a public URL depending on how the
     * field was configured, which is exactly what MediaResolver exists to undo.
     *
     * @return Collection<int, Media>
     */
    private static function currentSelection(FileUpload $component): Collection
    {
        $disk = $component->getDiskName();

        try {
            $state = $component->getState();
        } catch (Throwable) {
            // Opening on what the field holds is a convenience. A field whose
            // state cannot be read still deserves a working picker, so it opens
            // on the whole library rather than not at all.
            return collect();
        }

        return collect(Arr::wrap($state))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): ?Media => MediaResolver::resolve($value, $disk))
            ->filter()
            ->values();
    }

    /**
     * Whether the field is still part of a schema.
     *
     * Filament types the container property without a default, so reaching for
     * it on a detached component raises an Error rather than returning null,
     * and there is no public way to ask first.
     */
    private static function isAttached(FileUpload $component): bool
    {
        try {
            $component->getContainer();

            return true;
        } catch (Error) {
            return false;
        }
    }
}
