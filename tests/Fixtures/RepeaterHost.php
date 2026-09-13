<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/** A form whose FileUpload lives inside a repeater, as the failing page's did. */
class RepeaterHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithMediaBrowser;
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form(Schema::make($this))->fill(['rows' => [['image' => null]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('rows')->schema([
                    FileUpload::make('image')->disk('public')->directory('categories'),
                ]),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}<x-filament-actions::modals /></div>';
    }
}
