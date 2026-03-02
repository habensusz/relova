<?php

declare(strict_types=1);

namespace Relova;

use Illuminate\Support\ServiceProvider;
use Relova\Contracts\ConnectionManager;
use Relova\Services\DriverRegistry;
use Relova\Services\EntityReferenceService;
use Relova\Services\RelovaConnectionManager;
use Relova\Services\SecurityService;

class RelovaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/relova.php', 'relova');

        // Bind core services
        $this->app->singleton(SecurityService::class);

        $this->app->singleton(DriverRegistry::class);

        $this->app->singleton(RelovaConnectionManager::class, function ($app) {
            return new RelovaConnectionManager(
                $app->make(DriverRegistry::class),
                $app->make(SecurityService::class),
            );
        });

        $this->app->alias(RelovaConnectionManager::class, ConnectionManager::class);

        $this->app->singleton(EntityReferenceService::class, function ($app) {
            return new EntityReferenceService(
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
    }
}
