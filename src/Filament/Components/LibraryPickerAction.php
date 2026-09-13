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
            ->modalContent(function (mixed $schemaComponent = null) use ($component, $kinds, $returns): ?ViewContract {
                $field = self::fieldFor($schemaComponent, $component);

                // Filament renders an action's modal wherever the action is
                // rendered, including a re-render at the end of a Livewire
                // request, where nothing is mounted and the field is whichever
                // one the closure captured. Detached, it can say neither what it
                // holds nor where it writes — and asking takes the page down.
                if ($field === null) {
                    return null;
                }

                $current = self::currentSelection($field);

                return View::make('filament-media-library::components.picker-modal', [
                    'multiple' => $field->isMultiple(),
                    'disk' => $field->getDiskName(),
                    // Open where the chosen file lives; with nothing chosen the
                    // whole library is the sensible starting point, and
                    // uploadDirectory still steers new uploads.
                    'directory' => (string) $current->first()?->directory,
                    'uploadDirectory' => $field->getDirectory() ?: '',
                    'kinds' => $kinds,
                    'statePath' => $field->getStatePath(),
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
     * The field this modal is actually for.
     *
     * Filament mounts the action with the schema component's key — for a
     * repeater child, `form.rows.<uuid>.image` — and hands the live, attached
     * component to the closure. The one the closure captured is the instance
     * configureUsing saw, which inside a repeater is a blueprint belonging to
     * no item; it is only a fallback for a plain field.
     *
     * Null means neither is usable, which happens whenever the modal is
     * rendered without being mounted.
     */
    private static function fieldFor(mixed $mounted, FileUpload $captured): ?FileUpload
    {
        foreach ([$mounted, $captured] as $candidate) {
            if ($candidate instanceof FileUpload && self::isAttached($candidate)) {
                return $candidate;
            }
        }

        return null;
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
