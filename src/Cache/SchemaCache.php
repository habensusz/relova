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
     * @param  callable(): array  $configBuilder
     * @return array<int, array<string, mixed>>
     */
    public function getTables(RelovaConnection $connection, ConnectorDriver $driver, callable $configBuilder): array
    {
        return $this->cache->remember(
            $this->tablesKey($connection),
            $this->ttlFor($connection),
            fn () => $driver->getTables($configBuilder()),
        );
    }

    /**
     * @param  callable(): array  $configBuilder
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
            fn () => $driver->getColumns($configBuilder(), $table),
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
            $schemaSuffix = $this->schemaSegment($connection);
            $pattern = "relova:schema:{$connection->tenant_id}:{$connection->id}{$schemaSuffix}:columns:*";
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
        $schemaSuffix = $this->schemaSegment($connection);

        return "relova:schema:{$connection->tenant_id}:{$connection->id}{$schemaSuffix}:tables";
    }

    private function columnsKey(RelovaConnection $connection, string $table): string
    {
        $schemaSuffix = $this->schemaSegment($connection);

        return "relova:schema:{$connection->tenant_id}:{$connection->id}{$schemaSuffix}:columns:{$table}";
    }

    /**
     * Return a cache-key segment that encodes the configured schema so that
     * changing the schema on a connection immediately busts the cached result
     * without requiring a manual flush.
     */
    private function schemaSegment(RelovaConnection $connection): string
    {
        $schema = trim((string) ($connection->options['schema'] ?? ''));

        return $schema !== '' ? ':'.md5($schema) : '';
    }

    private function ttlFor(RelovaConnection $connection): int
    {
        return $connection->cache_ttl ?? $this->defaultTtlSeconds;
    }
}
