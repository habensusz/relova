<?php

declare(strict_types=1);

namespace Relova\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Pre-built list-page cache.
 *
 * Stores the full JSON payload (rows + total) for a paginated, filtered,
 * sorted view of a remote table under a key derived from the filter+sort+page
 * triple. A single Redis GET serves the entire page in <1ms.
 *
 *   Key: relova:list:{tenant}:{conn}:{table}:{md5(filter)}:{sort}:{page}:{per_page}
 *   TTL: 300 seconds (configurable via relova.list_page_ttl)
 *
 * Invalidated wholesale when a sync completes via forgetTable(), which uses
 * an index set to track keys for a given (tenant, conn, table) tuple. This
 * works on both the array driver (development) and Redis without resorting
 * to KEYS or SCAN scans.
 */
final class ListCache
{
    public function get(
        string $tenantId,
        string $connectionId,
        string $table,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
    ): ?array {
        return $this->store()->get($this->key($tenantId, $connectionId, $table, $filters, $sort, $page, $perPage));
    }

    public function put(
        string $tenantId,
        string $connectionId,
        string $table,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
        array $payload,
    ): void {
        $key = $this->key($tenantId, $connectionId, $table, $filters, $sort, $page, $perPage);
        $this->store()->put($key, $payload, $this->ttl());

        $this->trackKey($tenantId, $connectionId, $table, $key);
    }

    /**
     * Invalidate every cached page for a given table. Called from SyncEngine
     * after a successful sync so the next list read rebuilds with fresh data.
     */
    public function forgetTable(string $tenantId, string $connectionId, string $table): void
    {
        $indexKey = $this->indexKey($tenantId, $connectionId, $table);
        $keys = (array) $this->store()->get($indexKey, []);

        foreach ($keys as $key) {
            $this->store()->forget($key);
        }

        $this->store()->forget($indexKey);
    }

    public function key(
        string $tenantId,
        string $connectionId,
        string $table,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
    ): string {
        $filterHash = md5(json_encode($filters) ?: '{}');

        return "relova:list:{$tenantId}:{$connectionId}:{$table}:{$filterHash}:{$sort}:{$page}:{$perPage}";
    }

    private function indexKey(string $tenantId, string $connectionId, string $table): string
    {
        return "relova:list-index:{$tenantId}:{$connectionId}:{$table}";
    }

    private function trackKey(string $tenantId, string $connectionId, string $table, string $key): void
    {
        $indexKey = $this->indexKey($tenantId, $connectionId, $table);
        $store = $this->store();

        $existing = (array) $store->get($indexKey, []);
        $existing[$key] = true;

        // Index lives slightly longer than the longest individual page TTL to
        // ensure we can always find and invalidate every page under our index.
        $store->put($indexKey, array_keys($existing), $this->ttl() * 2);
    }

    private function store(): CacheRepository
    {
        return Cache::store(config('relova.persistent_cache', 'array'));
    }

    private function ttl(): int
    {
        return (int) config('relova.list_page_ttl', 300);
    }
}
