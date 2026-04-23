<?php

declare(strict_types=1);

namespace Relova\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Relova\Jobs\SyncMappingJob;
use Relova\Models\ConnectorModuleMapping;

/**
 * Proactively warm Relova snapshot caches for all active mappings.
 *
 * Schedule this every 15 minutes (or to match your shortest cache_ttl_minutes)
 * so that index and show pages always serve from the local shadow table rather
 * than opening a live SSH tunnel on the request thread.
 *
 * Usage:
 *   php artisan relova:sync-all
 *   php artisan relova:sync-all --tenant=uuid          # single tenant
 *   php artisan relova:sync-all --dry-run              # preview without dispatching
 */
class SyncAllMappingsCommand extends Command
{
    protected $signature = 'relova:sync-all
                            {--tenant= : Sync a specific tenant only (by ID)}
                            {--dry-run : List mappings that would be synced without dispatching jobs}';

    protected $description = 'Proactively warm Relova snapshot caches for all active virtual/snapshot_cache mappings';

    public function handle(): int
    {
        $specificTenant = $this->option('tenant') ?: null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] No jobs will be dispatched.');
        }

        $query = Tenant::query();

        if ($specificTenant !== null) {
            $query->where('id', $specificTenant);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $totalDispatched = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $mappings = ConnectorModuleMapping::query()
                    ->where('active', true)
                    ->whereIn('sync_behavior', ['virtual', 'snapshot_cache'])
                    ->with('connection')
                    ->get();

                if ($mappings->isEmpty()) {
                    continue;
                }

                foreach ($mappings as $mapping) {
                    if ($dryRun) {
                        $this->line("  [dry-run] tenant={$tenant->id} module={$mapping->module_key}");

                        continue;
                    }

                    SyncMappingJob::dispatch($mapping);
                    $totalDispatched++;
                }

                if (! $dryRun) {
                    $this->line("Tenant {$tenant->id}: dispatched {$mappings->count()} job(s).");
                }
            } finally {
                tenancy()->end();
            }
        }

        if (! $dryRun) {
            $this->info("✓ Done. Dispatched {$totalDispatched} sync job(s) across {$tenants->count()} tenant(s).");
        }

        return self::SUCCESS;
    }
}
