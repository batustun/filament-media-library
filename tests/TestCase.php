<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests;

use Batustun\FilamentMediaLibrary\FilamentMediaLibraryServiceProvider;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\MarketplacePanelProvider;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionedUser;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\TenantPanelProvider;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\TestPanelProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Livewire's validation support reads the shared `errors` bag, which a
        // real application gets from ShareErrorsFromSession. Testbench does not
        // run that middleware for Livewire::test(), so start a session and
        // share the bag ourselves — otherwise rendering any component fails.
        $this->startSession();
        view()->share('errors', new ViewErrorBag);

        // Plugin settings are scoped to the panel being served. A real request
        // gets that from Filament's SetUpPanel middleware, which does not run
        // for a unit test — so the test panel is made current explicitly.
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->artisan('migrate')->run();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            ActionsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            class_exists(SchemasServiceProvider::class) ? SchemasServiceProvider::class : null,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,

            // Must register after Filament: SupportServiceProvider rebinds
            // Livewire's DataStore to its own subclass with a non-shared
            // binding, which discards Livewire's shared instance. Livewire
            // registering last restores the singleton — the order a real
            // application gets from Composer, and the order component
            // rendering depends on.
            LivewireServiceProvider::class,

            FilamentMediaLibraryServiceProvider::class,
            TestPanelProvider::class,
            TenantPanelProvider::class,
            MarketplacePanelProvider::class,
        ]));
    }

    public function getEnvironmentSetUp($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Testbench ships no user model; the package resolves one for the
        // `uploaded_by` relation the detail pane renders.
        $app['config']->set('auth.providers.users.model', PermissionedUser::class);

        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);

        // A disk with no `url` key at all — the "plain local" case that used to
        // make publicUrl() throw.
        $app['config']->set('filesystems.disks.private', [
            'driver' => 'local',
            'root' => storage_path('app/private'),
        ]);

        $app['config']->set('filesystems.disks.cdn', [
            'driver' => 'local',
            'root' => storage_path('app/cdn'),
            'url' => 'http://storage.example.test',
        ]);

        $app['config']->set('session.driver', 'array');
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
        $app['config']->set('filament-media-library.default_disk', 'public');
        $app['config']->set('filament-media-library.permissions.enabled', false);
    }
}
