<?php

declare(strict_types=1);

namespace Relova\Contracts;

/**
 * Core contract that every Relova connector driver must implement.
 *
 * Adding a new connector type means implementing this interface —
 * nothing else in the platform changes.
 */
interface ConnectorDriver
{
    /**
     * Get the unique driver identifier (e.g. 'mysql', 'pgsql', 'sqlsrv').
     */
    public function getDriverName(): string;

    /**
     * Get human-readable display name.
     */
    public function getDisplayName(): string;

    /**
     * Test whether a connection can be established with the given config.
     *
     * @param  array<string, mixed>  $config  Connection configuration
     * @return bool True if connection is successful
     *
     * @throws \Relova\Exceptions\ConnectionException On connection failure with details
     */
    public function testConnection(array $config): bool;

    /**
     * Retrieve the list of available tables/entities from the remote source.
     *
     * @param  array<string, mixed>  $config  Connection configuration
     * @return array<int, array{name: string, schema: ?string, type: string, row_count: ?int}>
     */
    public function getTables(array $config): array;

    /**
     * Retrieve column definitions for a specific table.
     *
     * @param  array<string, mixed>  $config  Connection configuration
     * @param  string  $table  Table name
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, primary: bool, length: ?int}>
     */
    public function getColumns(array $config, string $table): array;

    /**
     * Execute a read-only query against the remote source.
     *
     * @param  array<string, mixed>  $config  Connection configuration
     * @param  string  $sql  SQL query (read-only enforced)
     * @param  array<int|string, mixed>  $bindings  Query parameter bindings
     * @return array<int, array<string, mixed>> Array of row associative arrays
     *
     * @throws \Relova\Exceptions\QueryException On query failure
     * @throws \Relova\Exceptions\ReadOnlyViolationException If query attempts write operations
     */
    public function query(array $config, string $sql, array $bindings = []): array;

    /**
     * Get the default port for this driver type.
     */
    public function getDefaultPort(): int;

    /**
     * Get the configuration schema for this driver.
     * Used by the UI to render appropriate form fields.
     *
     * @return array<string, array{type: string, label: string, required: bool, default: mixed}>
     */
    public function getConfigSchema(): array;

    /**
     * Build preview query for a table with optional column selection and limit.
     *
     * @param  string  $table  Table name
     * @param  array<string>  $columns  Column names (empty = all)
     * @param  int  $limit  Row limit
     * @return string SQL query string
     */
    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string;
}
