<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary;

use Batustun\FilamentMediaLibrary\Console\Commands\SyncMediaCommand;
use Batustun\FilamentMediaLibrary\Http\Controllers\MediaUploadController;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Policies\MediaPolicy;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMediaLibraryServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-media-library';

    public static string $viewNamespace = 'filament-media-library';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasCommands([
                SyncMediaCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MediaService::class);
        $this->app->singleton(MediaIndexer::class);
    }

    public function packageBooted(): void
    {
        // Migrations are auto-loaded rather than published: table names are
        // already configurable, and publishing them would let a second copy
        // run against the same tables.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Gate::policy(Media::class, MediaPolicy::class);

        Livewire::component('filament-media-library-picker', MediaPicker::class);

        FilamentAsset::register(
            assets: [
                Css::make('filament-media-library', __DIR__.'/../resources/dist/filament-media-library.css'),
            ],
            package: 'batustun/filament-media-library',
        );

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (! MediaLibraryConfig::routesEnabled()) {
            return;
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware((array) MediaLibraryConfig::get('routes.middleware', ['web', 'auth']))
            ->prefix((string) MediaLibraryConfig::get('routes.prefix', 'media-library'))
            ->name('filament-media-library.')
            ->group(function (): void {
                Route::post('upload', [MediaUploadController::class, 'store'])->name('upload');
                Route::delete('{media}', [MediaUploadController::class, 'destroy'])->name('destroy');
            });
    }
}
