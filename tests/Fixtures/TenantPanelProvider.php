<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A tenanted panel, so the tenant scope can be tested where it actually has to
 * work: inside a panel serving one tenant, not in a unit test holding a key.
 */
class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('workspace')
            ->path('workspace')
            ->tenant(Workspace::class)
            ->plugins([
                FilamentMediaLibraryPlugin::make(),
            ]);
    }
}
