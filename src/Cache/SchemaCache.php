<?php

declare(strict_types=1);

namespace Relova\Cache;

use Illuminate\Contracts\Cache\Repository;
use Relova\Contracts\ConnectorDriver;
use Relova\Models\RelovaConnection;

/**
 * Redis-backed schema metadata cache.
 *
 * Only structural information (table names, column definitions) is cached —
 * never row data. Entries expire after a conservative TTL because remote
 * schema changes rarely.
 *
 * Key format: relova:schema:{tenantId}:{connectionId}:{kind}[:table]
 */
class SchemaCache
{
    public function __construct(
        private Repository $cache,
        private int $defaultTtlSeconds = 1800,
    ) {}

    /**
     * @param  callable(array): array  $configBuilder
     * @return array<int, array<string, mixed>>
     */
    public function getTables(RelovaConnection $connection, ConnectorDriver $driver, callable $configBuilder): array
    {
        return $this->cache->remember(
            $this->tablesKey($connection),
            $this->ttlFor($connection),
            fn () => $driver->getTables($configBuilder($connection)),
        );
    }

    /**
     * @param  callable(array): array  $configBuilder
     * @return array<int, array<string, mixed>>
     */
    public function getColumns(
        RelovaConnection $connection,
        string $table,
        ConnectorDriver $driver,
        callable $configBuilder,
    ): array {
        return $this->cache->remember(
            $this->columnsKey($connection, $table),
            $this->ttlFor($connection),
            fn () => $driver->getColumns($configBuilder($connection), $table),
        );
    }

    /**
     * Invalidate every cached schema entry for a connection.
     */
    public function invalidate(RelovaConnection $connection): void
    {
        $this->cache->forget($this->tablesKey($connection));

        $store = $this->cache->getStore();
        if (method_exists($store, 'connection')) {
            $pattern = "relova:schema:{$connection->tenant_id}:{$connection->id}:columns:*";
            try {
                $redis = $store->connection();
                $keys = $redis->keys($pattern);
                if (! empty($keys)) {
                    $redis->del(...(array) $keys);
                }

                return;
            } catch (\Throwable) {
                // Fall through to best-effort forget of known entries.
            }
        }
    }

    private function tablesKey(RelovaConnection $connection): string
    {
        return "relova:schema:{$connection->tenant_id}:{$connection->id}:tables";
    }

    private function columnsKey(RelovaConnection $connection, string $table): string
    {
        return "relova:schema:{$connection->tenant_id}:{$connection->id}:columns:{$table}";
    }

    private function ttlFor(RelovaConnection $connection): int
    {
        return $connection->cache_ttl ?? $this->defaultTtlSeconds;
    }
}
