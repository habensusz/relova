<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Cache\SchemaCache;
use Relova\Models\RelovaConnection;

/**
 * Thin wrapper around SchemaCache + DriverRegistry for listing
 * remote tables and columns.
 *
 * The cache layer owns TTL and invalidation; this service only
 * wires the driver config builder into it.
 */
class SchemaInspector
{
    public function __construct(
        private DriverRegistry $drivers,
        private ConnectionRegistry $connections,
        private SchemaCache $cache,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tables(RelovaConnection $connection): array
    {
        $this->connections->assertHostAllowed($connection);
        $driver = $this->drivers->resolve($connection->driver);

        return $this->cache->getTables(
            $connection,
            $driver,
            fn (RelovaConnection $c) => $this->connections->buildConfig($c),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(RelovaConnection $connection, string $table): array
    {
        $this->connections->assertHostAllowed($connection);
        $driver = $this->drivers->resolve($connection->driver);

        return $this->cache->getColumns(
            $connection,
            $table,
            $driver,
            fn (RelovaConnection $c) => $this->connections->buildConfig($c),
        );
    }

    public function invalidate(RelovaConnection $connection): void
    {
        $this->cache->invalidate($connection);
    }
}
