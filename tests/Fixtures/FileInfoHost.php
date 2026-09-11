<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/** A stand-in host component for exercising the file-info schema. */
class FileInfoHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithMediaBrowser;
    use InteractsWithSchemas;

    public function render(): string
    {
        return '<div></div>';
    }
}
