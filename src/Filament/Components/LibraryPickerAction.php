<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Filament\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__($t.'.actions.close'))
            ->modalContent(fn (): mixed => View::make('filament-media-library::components.picker-modal', [
                'multiple' => $component->isMultiple(),
                'disk' => $component->getDiskName(),
                // Always open at "All files" so the whole library is visible;
                // uploadDirectory keeps new uploads landing in the directory
                // this field was configured with.
                'directory' => '',
                'uploadDirectory' => $component->getDirectory() ?: '',
                'kinds' => $kinds,
                'statePath' => $component->getStatePath(),
                'returns' => $returns,
            ]));
    }
}
