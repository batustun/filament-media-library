<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary;

use Batustun\FilamentMediaLibrary\Console\Commands\CleanChunksCommand;
use Batustun\FilamentMediaLibrary\Console\Commands\DoctorCommand;
use Batustun\FilamentMediaLibrary\Console\Commands\ImportSpatieMediaCommand;
use Batustun\FilamentMediaLibrary\Console\Commands\SyncMediaCommand;
use Batustun\FilamentMediaLibrary\Filament\Components\LibraryPickerAction;
use Batustun\FilamentMediaLibrary\Filament\Components\MediaInput;
use Batustun\FilamentMediaLibrary\Http\Controllers\ChunkedUploadController;
use Batustun\FilamentMediaLibrary\Http\Controllers\ImageEditController;
use Batustun\FilamentMediaLibrary\Http\Controllers\MediaUploadController;
use Batustun\FilamentMediaLibrary\Http\Controllers\PreviewController;
use Batustun\FilamentMediaLibrary\Http\Controllers\ProviderWebhookController;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Policies\MediaPolicy;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Services\ImageConversionService;
use Batustun\FilamentMediaLibrary\Services\MediaIndexer;
use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Services\MediaWriter;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\UploadedFile;
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
                CleanChunksCommand::class,
                DoctorCommand::class,
                ImportSpatieMediaCommand::class,
                SyncMediaCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MediaWriter::class);
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
        $this->attachToExistingFields();
    }

    /**
     * Make the library available from fields the application never changed.
     *
     * Filament's configureUsing hook runs for every instance created after it
     * is registered, which is what lets an existing form gain the picker
     * without a single edit. Everything here is additive and skips anything
     * that already opted in explicitly.
     */
    protected function attachToExistingFields(): void
    {
        // Registered unconditionally; the decisions are made per component, so
        // switching the options at runtime takes effect immediately rather than
        // needing the application to boot again.
        FileUpload::configureUsing(function (FileUpload $component): void {
            if (! MediaLibraryConfig::autoAttachesToFileUpload()) {
                return;
            }

            // MediaInput brings its own picker.
            if ($component instanceof MediaInput) {
                return;
            }

            // 'path' rather than 'url': a plain FileUpload stores a
            // disk-relative path, and auto-attachment must not change the shape
            // of what an existing form saves.
            $component->hintAction(
                fn (): Action => LibraryPickerAction::for($component, returns: 'path'),
            );

            if (! MediaLibraryConfig::autoIndexesUploads()) {
                return;
            }

            // Filament's own setUp() has already run by this point, so this
            // replaces its default writer rather than being replaced by it.
            // The returned path keeps its usual shape, so the field still
            // stores what the application expects.
            $component->saveUploadedFileUsing(
                fn (UploadedFile $file): string => app(MediaService::class)->store(
                    $file,
                    $component->getDiskName(),
                    $component->getDirectory() ?: null,
                )->path,
            );
        });

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
                Route::post('chunk', [ChunkedUploadController::class, 'store'])->name('chunk');
                Route::post('{media}/image', [ImageEditController::class, 'store'])->name('image-edit');
                Route::get('{media}/preview/text', [PreviewController::class, 'text'])->name('preview.text');
                Route::get('{media}/preview/archive', [PreviewController::class, 'archive'])->name('preview.archive');
                Route::get('{media}/download', [MediaUploadController::class, 'download'])->name('download');
                Route::delete('{media}', [MediaUploadController::class, 'destroy'])->name('destroy');
            });
    }
}
