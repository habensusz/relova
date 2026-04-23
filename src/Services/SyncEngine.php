<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Relova\Cache\ListCache;
use Relova\Data\SyncResult;
use Relova\Events\RelovaSyncCompleted;
use Relova\Jobs\SyncMappingJob;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\VirtualEntityReference;

/**
 * SyncEngine — orchestrated mass-sync facade (Spec §7, §11).
 *
 * Single entry point that:
 *   1. Decides whether a mapping needs syncing (TTL + DB freshness check).
 *   2. Either syncs inline ({@see syncIfNeeded}) or dispatches a queued job
 *      ({@see dispatchIfNeeded}).
 *   3. Invalidates the ListCache for the affected table after a successful
 *      sync so cached list pages do not serve stale rows.
 *   4. Fires {@see RelovaSyncCompleted}.
 *
 * Internally delegates row-streaming work to {@see ShadowSyncService} (kept as
 * the low-level "stream remote rows → write VirtualEntityReference" worker)
 * but no consumer should call ShadowSyncService directly any more — go through
 * SyncEngine so cache invalidation always happens.
 */
class SyncEngine
{
    public function __construct(
        private ShadowSyncService $shadow,
        private ListCache $listCache,
    ) {}

    /**
     * Sync all active mappings for the given local table name (inline).
     *
     * @param  string  $tenantId  Raw tenant UUID
     * @param  string  $tableName  Local DB table name (e.g. 'machines')
     * @param  int|null  $premisesId  When set, only mappings for this premises (or null) are synced
     */
    public function syncTable(string $tenantId, string $tableName, ?int $premisesId = null): void
    {
        foreach ($this->activeMappingsFor($tenantId, $tableName, $premisesId) as $mapping) {
            $this->syncIfNeeded($mapping);
        }
    }

    /**
     * Non-blocking variant: dispatch a queued SyncMappingJob per mapping.
     */
    public function syncTableAsync(string $tenantId, string $tableName, ?int $premisesId = null): void
    {
        foreach ($this->activeMappingsFor($tenantId, $tableName, $premisesId) as $mapping) {
            $this->dispatchIfNeeded($mapping);
        }
    }

    /**
     * Inline sync of a single mapping when its TTL has elapsed.
     * Honours sync_behavior (virtual / snapshot_cache / on_demand).
     */
    public function syncIfNeeded(ConnectorModuleMapping $mapping): ?SyncResult
    {
        if ($mapping->sync_behavior === 'on_demand') {
            return null;
        }

        $ttlSeconds = $this->ttlSecondsFor($mapping);
        $cacheKey = $this->freshnessKey($mapping);

        if (Cache::has($cacheKey) || $this->isFreshInDb($mapping, $ttlSeconds)) {
            return null;
        }

        return $this->forceSync($mapping);
    }

    /**
     * Force-sync a mapping NOW, ignoring freshness checks. Always invalidates
     * the ListCache for the affected table on success.
     */
    public function forceSync(ConnectorModuleMapping $mapping): SyncResult
    {
        $result = $this->shadow->syncMapping($mapping);

        $this->invalidateListCache($mapping);
        $this->stampFreshness($mapping);

        RelovaSyncCompleted::dispatch($mapping->tenant_id, $mapping->module_key);

        return $result;
    }

    /**
     * Dispatch a queued sync job if not already dispatched in this TTL window.
     * The job itself calls {@see forceSync} internally, so cache invalidation
     * still runs.
     */
    public function dispatchIfNeeded(ConnectorModuleMapping $mapping): void
    {
        if ($mapping->sync_behavior === 'on_demand') {
            return;
        }

        // When the queue driver is 'sync' there is no background worker — the job would block
        // the HTTP request inline. Skip; the scheduler or an explicit forceSync() call handles it.
        if (config('queue.default') === 'sync') {
            return;
        }

        $ttlSeconds = $this->ttlSecondsFor($mapping);
        $lockKey = 'relova.sync_dispatched.'.$mapping->tenant_id.'.'.$mapping->uid;

        if (Cache::has($lockKey) || $this->isFreshInDb($mapping, $ttlSeconds)) {
            return;
        }

        SyncMappingJob::dispatch($mapping);
        Cache::put($lockKey, now()->timestamp, $ttlSeconds);
    }

    /**
     * Drop all freshness/dispatch locks for a mapping so the next request re-syncs.
     */
    public function invalidate(ConnectorModuleMapping $mapping): void
    {
        Cache::forget('relova.virtual_sync.'.$mapping->tenant_id.'.'.$mapping->uid);
        Cache::forget('relova.snapshot_cache.'.$mapping->tenant_id.'.'.$mapping->uid);
        Cache::forget('relova.sync_dispatched.'.$mapping->tenant_id.'.'.$mapping->uid);
        $this->invalidateListCache($mapping);
    }

    /**
     * Drop every cached list page for the mapping's connection + remote table.
     * Safe to call from queued jobs or controllers.
     */
    public function invalidateListCache(ConnectorModuleMapping $mapping): void
    {
        try {
            $this->listCache->forgetTable(
                tenantId: (string) $mapping->tenant_id,
                connectionId: (string) $mapping->connection_id,
                table: (string) $mapping->remote_table,
            );
        } catch (\Throwable) {
            // ListCache invalidation must never break a sync. Audit instead.
        }
    }

    /**
     * @return Collection<int, ConnectorModuleMapping>
     */
    private function activeMappingsFor(string $tenantId, string $tableName, ?int $premisesId = null): Collection
    {
        return ConnectorModuleMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('module_key', $tableName)
            ->where('active', true)
            ->whereIn('sync_behavior', ['virtual', 'snapshot_cache'])
            ->when($premisesId !== null, fn ($q) => $q->where(function ($q) use ($premisesId) {
                $q->where('premises_id', $premisesId)->orWhereNull('premises_id');
            }))
            ->with('connection')
            ->get();
    }

    private function ttlSecondsFor(ConnectorModuleMapping $mapping): int
    {
        $minutes = (int) ($mapping->cache_ttl_minutes ?? ($mapping->sync_behavior === 'snapshot_cache' ? 30 : 1));

        return max($minutes * 60, 30);
    }

    private function freshnessKey(ConnectorModuleMapping $mapping): string
    {
        $bucket = $mapping->sync_behavior === 'virtual' ? 'virtual_sync' : 'snapshot_cache';

        return 'relova.'.$bucket.'.'.$mapping->tenant_id.'.'.$mapping->uid;
    }

    private function stampFreshness(ConnectorModuleMapping $mapping): void
    {
        $ttl = $this->ttlSecondsFor($mapping);
        Cache::put($this->freshnessKey($mapping), now()->timestamp, $ttl);
    }

    private function isFreshInDb(ConnectorModuleMapping $mapping, int $ttlSeconds): bool
    {
        return VirtualEntityReference::where('mapping_id', $mapping->id)
            ->where('snapshot_taken_at', '>=', now()->subSeconds($ttlSeconds))
            ->exists();
    }
}
