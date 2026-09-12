<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Components;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\MediaResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

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
            ->modalContent(function () use ($component, $kinds, $returns): mixed {
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

        return collect(Arr::wrap($component->getState()))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): ?Media => MediaResolver::resolve($value, $disk))
            ->filter()
            ->values();
    }
}
