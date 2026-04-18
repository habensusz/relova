<?php

declare(strict_types=1);

namespace Relova;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Relova\Cache\SchemaCache;
use Relova\Console\Commands\AddCustomFieldsCommand;
use Relova\Http\Middleware\RelovaApiAuth;
use Relova\Livewire\CustomFieldManager;
use Relova\Livewire\WidgetConfigEditor;
use Relova\Models\CustomFieldDefinition;
use Relova\Models\CustomFieldWidgetConfig;
use Relova\Models\CustomFieldWidgetItem;
use Relova\Observers\CustomFieldDefinitionObserver;
use Relova\Observers\WidgetConfigObserver;
use Relova\Observers\WidgetItemObserver;
use Relova\Sdk\RelovaClient;
use Relova\Security\CredentialEncryptor;
use Relova\Security\SsrfGuard;
use Relova\Services\ConnectionRegistry;
use Relova\Services\CustomFieldValidator;
use Relova\Services\DriverRegistry;
use Relova\Services\FormFieldMerger;
use Relova\Services\QueryExecutor;
use Relova\Services\ReferenceResolver;
use Relova\Services\SchemaInspector;
use Relova\Services\SnapshotManager;
use Relova\Services\SshTunnelService;

/**
 * Relova service provider.
 *
 * Wires the virtual-first federation stack:
 *   - SSRF guard + credential encryption on every outbound call.
 *   - Redis schema cache (metadata only â€” never row data).
 *   - Driver registry + connection registry + query executor.
 *   - Virtual entity reference resolver + snapshot manager (graceful degradation).
 */
class RelovaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/relova.php', 'relova');

        // Security primitives
        $this->app->singleton(SsrfGuard::class, function ($app) {
            return new SsrfGuard(
                enabled: (bool) config('relova.ssrf_protection', true),
                blockedRanges: (array) config('relova.blocked_ip_ranges', []),
                allowedHosts: (array) config('relova.ssrf_allowed_hosts', []),
            );
        });

        $this->app->singleton(CredentialEncryptor::class, function ($app) {
            $masterKey = (string) (config('relova.encryption_key') ?: config('app.key'));

            return new CredentialEncryptor($masterKey);
        });

        // Metadata cache
        $this->app->singleton(SchemaCache::class, function ($app) {
            return new SchemaCache(
                cache: $app->make(CacheRepository::class),
                defaultTtlSeconds: (int) config('relova.schema_cache_ttl', 1800),
            );
        });

        // Driver + connection registries
        $this->app->singleton(DriverRegistry::class);
        $this->app->singleton(SshTunnelService::class);

        $this->app->singleton(ConnectionRegistry::class, function ($app) {
            return new ConnectionRegistry(
                $app->make(CredentialEncryptor::class),
                $app->make(SsrfGuard::class),
            );
        });

        // Core services
        $this->app->singleton(SchemaInspector::class, function ($app) {
            return new SchemaInspector(
                $app->make(DriverRegistry::class),
                $app->make(ConnectionRegistry::class),
                $app->make(SchemaCache::class),
            );
        });

        $this->app->singleton(QueryExecutor::class, function ($app) {
            return new QueryExecutor(
                $app->make(DriverRegistry::class),
                $app->make(ConnectionRegistry::class),
            );
        });

        $this->app->singleton(ReferenceResolver::class, function ($app) {
            return new ReferenceResolver($app->make(QueryExecutor::class));
        });

        $this->app->singleton(SnapshotManager::class, function ($app) {
            return new SnapshotManager($app->make(QueryExecutor::class));
        });

        // Custom fields (orthogonal feature)
        $this->app->singleton(CustomFieldValidator::class);
        $this->app->singleton(FormFieldMerger::class);

        // SDK client (in-app consumers)
        $this->app->bind(RelovaClient::class, fn () => RelovaClient::fromConfig());
    }

    public function boot(): void
    {
        // API auth middleware alias
        $this->app['router']->aliasMiddleware('relova.auth', RelovaApiAuth::class);

        // Custom field observers
        CustomFieldDefinition::observe(CustomFieldDefinitionObserver::class);
        CustomFieldWidgetConfig::observe(WidgetConfigObserver::class);
        CustomFieldWidgetItem::observe(WidgetItemObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AddCustomFieldsCommand::class,
            ]);
        }

        // Publishables
        $this->publishes([
            __DIR__.'/../config/relova.php' => config_path('relova.php'),
        ], 'relova-config');

        // Migrations (applied by host app's migrate / tenants:migrate)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations/tenant'),
        ], 'relova-tenant-migrations');

        // API routes, views, translations
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'relova');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'relova');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/relova'),
        ], 'relova-views');

        $this->publishes([
            __DIR__.'/../lang' => lang_path(),
        ], 'relova-lang');

        // Livewire components (custom-field management UI)
        if (class_exists(Livewire::class)) {
            Livewire::component('relova-custom-field-manager', CustomFieldManager::class);
            Livewire::component('relova-widget-config-editor', WidgetConfigEditor::class);
        }
    }
}
