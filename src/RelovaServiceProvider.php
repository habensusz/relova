<?php

declare(strict_types=1);

namespace Relova;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Relova\Cache\ListCache;
use Relova\Cache\SchemaCache;
use Relova\Cache\TwoZoneCache;
use Relova\Console\Commands\AddCustomFieldsCommand;
use Relova\Console\Commands\SyncAllMappingsCommand;
use Relova\Http\Middleware\RelovaApiAuth;
use Relova\Livewire\ConnectionManager;
use Relova\Livewire\CustomFieldManager;
use Relova\Livewire\Dashboard as RelovaDashboard;
use Relova\Livewire\MappingManager;
use Relova\Livewire\SchemaBrowser;
use Relova\Livewire\WidgetConfigEditor;
use Relova\Models\CustomFieldDefinition;
use Relova\Models\CustomFieldWidgetConfig;
use Relova\Models\CustomFieldWidgetItem;
use Relova\Observers\CustomFieldDefinitionObserver;
use Relova\Observers\WidgetConfigObserver;
use Relova\Observers\WidgetItemObserver;
use Relova\Sdk\RelovaClient;
use Relova\Security\AuditLogger;
use Relova\Security\CacheEncryptor;
use Relova\Security\CredentialEncryptor;
use Relova\Security\FieldMasker;
use Relova\Security\QuerySanitizer;
use Relova\Security\SsrfGuard;
use Relova\Security\TenantIsolationGuard;
use Relova\Services\CacheManager;
use Relova\Services\ConnectionRegistry;
use Relova\Services\CustomFieldValidator;
use Relova\Services\DriverRegistry;
use Relova\Services\FormFieldMerger;
use Relova\Services\QueryExecutor;
use Relova\Services\ReferenceResolver;
use Relova\Services\SchemaInspector;
use Relova\Services\ShadowSyncService;use Relova\Services\SnapshotManager;
use Relova\Services\SshTunnelService;
use Relova\Services\SyncEngine;
use Relova\Services\TriggerRuleEngine;
use Relova\Services\VirtualDataService;
use Relova\Services\VirtualEntityResolver;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

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
                $app->make(SshTunnelService::class),
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
                $app->make(QuerySanitizer::class),
            );
        });

        $this->app->singleton(ReferenceResolver::class, function ($app) {
            return new ReferenceResolver($app->make(QueryExecutor::class));
        });

        $this->app->singleton(SnapshotManager::class, function ($app) {
            return new SnapshotManager($app->make(QueryExecutor::class));
        });

        $this->app->singleton(ShadowSyncService::class, function ($app) {
            return new ShadowSyncService(
                $app->make(QueryExecutor::class),
                $app->make(TriggerRuleEngine::class),
            );
        });

        $this->app->singleton(TriggerRuleEngine::class);

        $this->app->singleton(VirtualDataService::class, function ($app) {
            return new VirtualDataService($app->make(ShadowSyncService::class));
        });

        $this->app->singleton(SyncEngine::class, function ($app) {
            return new SyncEngine(
                $app->make(ShadowSyncService::class),
                $app->make(ListCache::class),
            );
        });

        $this->app->singleton(VirtualEntityResolver::class, function ($app) {
            return new VirtualEntityResolver($app->make(QueryExecutor::class));
        });

        // Custom fields (orthogonal feature)
        $this->app->singleton(CustomFieldValidator::class);
        $this->app->singleton(FormFieldMerger::class);

        // SDK client (in-app consumers)
        $this->app->bind(RelovaClient::class, fn () => RelovaClient::fromConfig());

        // ----- Spec §3, §7, §8, §9 — cache, security, tenant isolation -----

        $this->app->singleton(TenantIsolationGuard::class);
        $this->app->singleton(QuerySanitizer::class);
        $this->app->singleton(FieldMasker::class);

        $this->app->singleton(CacheEncryptor::class);

        $this->app->singleton(TwoZoneCache::class, function ($app) {
            return new TwoZoneCache($app->make(CacheEncryptor::class));
        });

        $this->app->singleton(ListCache::class);

        $this->app->singleton(AuditLogger::class, function ($app) {
            return new AuditLogger(
                $app->make(FieldMasker::class),
                $app->make(TenantIsolationGuard::class),
            );
        });

        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(TwoZoneCache::class),
                $app->make(ListCache::class),
                $app->make(QueryExecutor::class),
                $app->make(AuditLogger::class),
                $app->make(TenantIsolationGuard::class),
            );
        });
    }

    public function boot(): void
    {
        // API auth middleware alias
        $this->app['router']->aliasMiddleware('relova.auth', RelovaApiAuth::class);

        // Auto-bind relova.current_tenant from stancl/tenancy lifecycle when present.
        // The host app (mainkeeperx2) uses stancl/tenancy v3; binding here means
        // every tenant-scoped request automatically satisfies the tenant scope on
        // Relova models without any host-app glue code.
        if (class_exists(TenancyInitialized::class)) {
            Event::listen(
                TenancyInitialized::class,
                function ($event): void {
                    $tenantId = (string) ($event->tenancy->tenant->id ?? '');
                    if ($tenantId !== '') {
                        app()->instance('relova.current_tenant', $tenantId);
                    }
                }
            );
            Event::listen(
                TenancyEnded::class,
                function (): void {
                    if (app()->bound('relova.current_tenant')) {
                        app()->forgetInstance('relova.current_tenant');
                    }
                }
            );
        }

        // Custom field observers
        CustomFieldDefinition::observe(CustomFieldDefinitionObserver::class);
        CustomFieldWidgetConfig::observe(WidgetConfigObserver::class);
        CustomFieldWidgetItem::observe(WidgetItemObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AddCustomFieldsCommand::class,
                SyncAllMappingsCommand::class,
            ]);
        }

        // Publishables
        $this->publishes([
            __DIR__.'/../config/relova.php' => config_path('relova.php'),
        ], 'relova-config');

        // Tenant migrations — published to database/migrations/tenant/ so stancl/tenancy
        // picks them up during MigrateDatabase. Run after package install/update:
        //   php artisan vendor:publish --tag=relova-tenant-migrations --force
        // Note: loadMigrationsFrom() is intentionally NOT used here because these are
        // tenant-schema tables, not central-schema tables.
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
            Livewire::component('relova-dashboard', RelovaDashboard::class);
            Livewire::component('relova-connection-manager', ConnectionManager::class);
            Livewire::component('relova-schema-browser', SchemaBrowser::class);
            Livewire::component('relova-mapping-manager', MappingManager::class);
        }
    }
}
