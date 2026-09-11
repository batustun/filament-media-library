<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary;

use Batustun\FilamentMediaLibrary\Console\Commands\DoctorCommand;
use Batustun\FilamentMediaLibrary\Console\Commands\SyncMediaCommand;
use Batustun\FilamentMediaLibrary\Http\Controllers\MediaUploadController;
use Batustun\FilamentMediaLibrary\Http\Controllers\ProviderWebhookController;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Policies\MediaPolicy;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Services\ImageConversionService;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
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
                DoctorCommand::class,
                SyncMediaCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MediaService::class);
        $this->app->singleton(MediaIndexer::class);
        $this->app->singleton(ImageConversionService::class);
        $this->app->singleton(MediaProviderRegistry::class);
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
                Js::make('filament-media-library', __DIR__.'/../resources/dist/filament-media-library.js'),
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

        // Platform callbacks are unauthenticated by nature and carry their own
        // shared secret, so they sit outside the panel's auth middleware.
        Route::middleware(['api'])
            ->prefix((string) MediaLibraryConfig::get('routes.prefix', 'media-library'))
            ->name('filament-media-library.webhooks.')
            ->group(function (): void {
                Route::post('webhooks/bunny-stream', [ProviderWebhookController::class, 'bunnyStream'])
                    ->name('bunny-stream');
            });

        Route::middleware((array) MediaLibraryConfig::get('routes.middleware', ['web', 'auth']))
            ->prefix((string) MediaLibraryConfig::get('routes.prefix', 'media-library'))
            ->name('filament-media-library.')
            ->group(function (): void {
                Route::post('upload', [MediaUploadController::class, 'store'])->name('upload');
                Route::get('{media}/download', [MediaUploadController::class, 'download'])->name('download');
                Route::delete('{media}', [MediaUploadController::class, 'destroy'])->name('destroy');
            });
    }
}
