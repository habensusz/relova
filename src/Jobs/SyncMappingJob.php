<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Relova\Models\ConnectorModuleMapping;
use Relova\Services\SyncEngine;

/**
 * Queued wrapper around SyncEngine::forceSync().
 *
 * SyncEngine handles ListCache invalidation, freshness stamping, and
 * RelovaSyncCompleted dispatch — this job is a thin queue adapter.
 *
 *   SyncMappingJob::dispatch($mapping);
 */
class SyncMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private ConnectorModuleMapping $mapping,
    ) {
        $this->onQueue((string) config('relova.sync_queue', 'relova-sync'));
    }

    public function handle(SyncEngine $sync): void
    {
        $sync->forceSync($this->mapping);

        // Set legacy dedup locks so older read paths (LoadModel autoSync,
        // VirtualDataService) don't immediately re-dispatch.
        $ttlSeconds = max(($this->mapping->cache_ttl_minutes ?? 1) * 60, 30);
        $tenantId = $this->mapping->tenant_id;
        $uid = $this->mapping->uid;
        Cache::put("relova.sync_dispatched.{$tenantId}.{$uid}", now()->timestamp, $ttlSeconds);
        Cache::put("relova.virtual_sync.{$tenantId}.{$uid}", now()->timestamp, $ttlSeconds);
        Cache::put("relova.snapshot_cache.{$tenantId}.{$uid}", now()->timestamp, $ttlSeconds);
    }
}
