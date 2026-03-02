<?php

declare(strict_types=1);

namespace Relova\Contracts;

use Relova\Models\RelovaConnection;

/**
 * Connection manager contract — handles opening, pooling, and lifecycle
 * of remote connections.
 */
interface ConnectionManager
{
    /**
     * Open a connection and return the driver instance configured for the given connection.
     */
    public function connect(RelovaConnection $connection): ConnectorDriver;

    /**
     * Test connectivity for a connection.
     */
    public function test(RelovaConnection $connection): bool;

    /**
     * Get cached schema metadata or fetch fresh.
     *
     * @return array<int, array{name: string, schema: ?string, type: string, row_count: ?int}>
     */
    public function getTables(RelovaConnection $connection): array;

    /**
     * Get cached column metadata or fetch fresh.
     *
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, primary: bool, length: ?int}>
     */
    public function getColumns(RelovaConnection $connection, string $table): array;

    /**
     * Execute a read-only query through the connection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function query(RelovaConnection $connection, string $sql, array $bindings = []): array;

    /**
     * Flush cached schema metadata for a connection.
     */
    public function flushCache(RelovaConnection $connection): void;

    /**
     * Run health check on a connection. Updates connection status.
     */
    public function healthCheck(RelovaConnection $connection): array;
}
