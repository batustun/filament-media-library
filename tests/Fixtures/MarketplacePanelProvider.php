<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A second tenanted panel, on a different tenant model.
 *
 * Applications really do have these — restaurants in one panel, sellers in
 * another — and their keys run in separate sequences, which is the case the
 * tenant scope has to get right.
 */
class MarketplacePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('marketplace')
            ->path('marketplace')
            ->tenant(Marketplace::class)
            ->plugins([
                FilamentMediaLibraryPlugin::make(),
            ]);
    }
}
