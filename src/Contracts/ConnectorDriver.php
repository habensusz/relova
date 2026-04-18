<?php

declare(strict_types=1);

namespace Relova\Contracts;

use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;
use Relova\Exceptions\TimeoutException;

/**
 * Core contract that every Relova connector driver must implement.
 *
 * Implementations MUST:
 *   - Never copy remote row data into any local database or disk cache.
 *   - Never perform write operations against the remote source.
 *   - Stream query results via a \Generator — never buffer into arrays.
 *   - Respect the configured connect/query timeouts.
 *   - Treat each call as stateless.
 */
interface ConnectorDriver
{
    public function getDriverName(): string;

    public function getDisplayName(): string;

    public function getDefaultPort(): int;

    /**
     * @return array<string, array{type: string, label: string, required: bool, default: mixed}>
     */
    public function getConfigSchema(): array;

    /**
     * @throws ConnectionException
     */
    public function testConnection(array $config): bool;

    /**
     * @return array<int, array{name: string, schema: ?string, type: string, row_count: ?int}>
     */
    public function getTables(array $config): array;

    /**
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, primary: bool, length: ?int}>
     */
    public function getColumns(array $config, string $table): array;

    /**
     * Execute a read-only query and yield rows one at a time.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int|string, mixed>  $bindings
     * @param  array<string, mixed>  $options
     * @return \Generator<int, array<string, mixed>>
     *
     * @throws QueryException
     * @throws ReadOnlyViolationException
     * @throws TimeoutException
     */
    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator;

    /**
     * Build a safe read-only preview SELECT for a table.
     *
     * @param  array<string>  $columns  Empty means SELECT *.
     */
    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string;
}
