<?php

declare(strict_types=1);

namespace Relova;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Relova\Contracts\ConnectionManager;
use Relova\Services\DriverRegistry;
use Relova\Services\EntityReferenceService;
use Relova\Services\HostSchemaService;
use Relova\Services\MappingDataLoader;
use Relova\Services\RelovaConnectionManager;
use Relova\Services\SecurityService;
use Relova\Services\SshTunnelService;
use Relova\Services\VirtualRelationLoader;

class RelovaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/relova.php', 'relova');

        // Bind core services
        $this->app->singleton(SecurityService::class);

        $this->app->singleton(SshTunnelService::class);

        $this->app->singleton(DriverRegistry::class);

        $this->app->singleton(HostSchemaService::class);

        $this->app->singleton(RelovaConnectionManager::class, function ($app) {
            return new RelovaConnectionManager(
                $app->make(DriverRegistry::class),
                $app->make(SecurityService::class),
                $app->make(SshTunnelService::class),
            );
        });

        $this->app->alias(RelovaConnectionManager::class, ConnectionManager::class);

        $this->app->singleton(EntityReferenceService::class, function ($app) {
            return new EntityReferenceService(
                $app->make(RelovaConnectionManager::class),
            );
        });

        $this->app->singleton(MappingDataLoader::class, function ($app) {
            return new MappingDataLoader(
                $app->make(RelovaConnectionManager::class),
            );
        });

        $this->app->singleton(VirtualRelationLoader::class, function ($app) {
            return new VirtualRelationLoader(
                $app->make(RelovaConnectionManager::class),
            );
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/relova.php' => config_path('relova.php'),
        ], 'relova-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load views under the 'relova' namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'relova');

        // Load translations (package ships with en/de/hu; host app lang files take priority via publishable)
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'relova');

        // Publish views (optional override)
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/relova'),
        ], 'relova-views');

        // Publish lang files to host app lang directory
        $this->publishes([
            __DIR__.'/../lang' => lang_path(),
        ], 'relova-lang');

        // Register Livewire components
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('relova-dashboard', \Relova\Livewire\Dashboard::class);
            \Livewire\Livewire::component('relova-connection-manager', \Relova\Livewire\ConnectionManager::class);
            \Livewire\Livewire::component('relova-field-mapping-editor', \Relova\Livewire\FieldMappingEditor::class);
            \Livewire\Livewire::component('relova-schema-browser', \Relova\Livewire\SchemaBrowser::class);
            \Livewire\Livewire::component('relova-asset-picker', \Relova\Livewire\AssetPicker::class);
            \Livewire\Livewire::component('relova-remote-record', \Relova\Livewire\RemoteRecord::class);
        }

        // Blade directive: sets $isRemote = true when $record is a RelovaRow (remote UNION row).
        // Usage in host Blade views: @relovaRow($record)
        Blade::directive('relovaRow', function (string $expression): string {
            return "<?php \$isRemote = {$expression} instanceof \\Relova\\Data\\RelovaRow; ?>";
        });
    }
}
