<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Relova\Jobs\SyncMappingJob;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\VirtualEntityReference;

/**
 * Decides when to auto-sync remote data into the local shadow table.
 *
 * Call autoSyncForTable() before executing any query on a module that may
 * have an active Relova mapping. It respects each mapping's sync_behavior:
 *
 *   virtual        — always syncs (live pass-through on every request)
 *   snapshot_cache — syncs once, then waits for cache_ttl_minutes to expire
 *   on_demand      — never auto-syncs (user must trigger via artisan / job)
 */
class VirtualDataService
{
    public function __construct(
        private ShadowSyncService $syncService,
    ) {}

    /**
     * Auto-sync all active mappings for the given local table name.
     *
     * @param  string  $tenantId  Raw tenant UUID (as stored in connector_module_mappings.tenant_id)
     * @param  string  $tableName  Local DB table name, e.g. 'machines'
     * @param  int|null  $premisesId  When set, only mappings for this premises (or null) are synced
     */
    public function autoSyncForTable(string $tenantId, string $tableName, ?int $premisesId = null): void
    {
        foreach ($this->activeMappingsFor($tenantId, $tableName, $premisesId) as $mapping) {
            $this->syncIfNeeded($mapping);
        }
    }

    /**
     * Non-blocking variant: dispatches a queued SyncMappingJob for each mapping
     * that needs syncing instead of running inline.
     *
     * A per-mapping dispatch-lock (same TTL as the sync interval) prevents
     * duplicate jobs from being enqueued within the same window.
     * The SyncMappingJob fires RelovaSyncCompleted when done, triggering a
     * Livewire broadcast refresh on any listening components.
     */
    public function autoSyncForTableAsync(string $tenantId, string $tableName, ?int $premisesId = null): void
    {
        foreach ($this->activeMappingsFor($tenantId, $tableName, $premisesId) as $mapping) {
            $this->dispatchIfNeeded($mapping);
        }
    }

    /**
     * Fetch all active mappings for a given table, optionally scoped to a premises.
     *
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

    /**
     * Dispatch a SyncMappingJob for a mapping if not already dispatched in this TTL window.
     *
     * Uses a two-layer freshness check:
     *  1. Cache (fast path) — works within the same process or when a shared cache
     *     driver (Redis) is available (staging / production).
     *  2. DB snapshot_taken_at (reliable fallback) — works on dev/CI where the cache
     *     driver is `array` and dies between the scheduler process and the web process.
     */
    private function dispatchIfNeeded(ConnectorModuleMapping $mapping): void
    {
        // When the queue driver is 'sync' there is no real background worker — dispatching
        // blocks the HTTP request just like the synchronous variant. Skip entirely; the
        // scheduler or a manual trigger will handle the sync when a real worker is available.
        if (config('queue.default') === 'sync') {
            return;
        }

        $ttlSeconds = max(($mapping->cache_ttl_minutes ?? 1) * 60, 30);
        $lockKey = 'relova.sync_dispatched.'.$mapping->tenant_id.'.'.$mapping->uid;

        if (Cache::has($lockKey) || $this->isFreshInDb($mapping, $ttlSeconds)) {
            return;
        }

        SyncMappingJob::dispatch($mapping);
        Cache::put($lockKey, now()->timestamp, $ttlSeconds);
    }

    /**
     * Sync a single mapping according to its behavior.
     *
     * Uses the same two-layer freshness check as dispatchIfNeeded so that
     * snapshots written by the scheduler are respected even when the process-local
     * cache (array driver) has no knowledge of them.
     */
    public function syncIfNeeded(ConnectorModuleMapping $mapping): void
    {
        if ($mapping->sync_behavior === 'virtual') {
            // Live mode — re-sync, but respect a short TTL to avoid hammering the
            // remote source on every Livewire re-render within the same session.
            // Default: 30 seconds (same as snapshot_cache floor).
            $ttlSeconds = max(($mapping->cache_ttl_minutes ?? 1) * 60, 30);
            $cacheKey = 'relova.virtual_sync.'.$mapping->tenant_id.'.'.$mapping->uid;

            if (Cache::has($cacheKey) || $this->isFreshInDb($mapping, $ttlSeconds)) {
                return;
            }

            $this->syncService->syncMapping($mapping);
            Cache::put($cacheKey, now()->timestamp, $ttlSeconds);

            return;
        }

        if ($mapping->sync_behavior === 'snapshot_cache') {
            $cacheKey = 'relova.snapshot_cache.'.$mapping->tenant_id.'.'.$mapping->uid;
            $ttlSeconds = ($mapping->cache_ttl_minutes ?? 30) * 60;

            if (Cache::has($cacheKey) || $this->isFreshInDb($mapping, $ttlSeconds)) {
                return;
            }

            $this->syncService->syncMapping($mapping);
            Cache::put($cacheKey, now()->timestamp, $ttlSeconds);
        }

        // on_demand: do nothing — only synced when explicitly called.
    }

    /**
     * Check whether the DB already holds a fresh snapshot for this mapping.
     *
     * A snapshot is considered fresh when at least one VirtualEntityReference
     * row for this mapping_id has snapshot_taken_at within the TTL window.
     * This check survives process restarts because it reads from the shared DB,
     * making it reliable regardless of which cache driver is configured.
     */
    private function isFreshInDb(ConnectorModuleMapping $mapping, int $ttlSeconds): bool
    {
        return VirtualEntityReference::where('mapping_id', $mapping->id)
            ->where('snapshot_taken_at', '>=', now()->subSeconds($ttlSeconds))
            ->exists();
    }

    /**
     * Invalidate the snapshot cache for a mapping, forcing a re-sync on next request.
     * Useful after an on_demand trigger or after the user edits the mapping.
     */
    public function invalidateCache(ConnectorModuleMapping $mapping): void
    {
        Cache::forget('relova.virtual_sync.'.$mapping->tenant_id.'.'.$mapping->uid);
        Cache::forget('relova.snapshot_cache.'.$mapping->tenant_id.'.'.$mapping->uid);
        Cache::forget('relova.sync_dispatched.'.$mapping->tenant_id.'.'.$mapping->uid);
    }
}
