<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A minimal panel so the library's Blade views can be rendered in the test
 * suite. Without it a broken view is only discovered in a real application.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            ->plugins([
                FilamentMediaLibraryPlugin::make(),
            ]);
    }
}
