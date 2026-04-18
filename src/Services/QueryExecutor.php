<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;

/**
 * Executes pass-through read-only queries against remote sources.
 *
 * Guarantees:
 *   - Every outbound host is validated by SsrfGuard first.
 *   - All SQL is built internally (table + conditions + columns) — callers
 *     never supply raw SQL.
 *   - Results are always returned as \Generator — no buffering.
 *   - Nothing from the remote response is persisted locally.
 */
class QueryExecutor
{
    public function __construct(
        private DriverRegistry $drivers,
        private ConnectionRegistry $connections,
    ) {}

    /**
     * SELECT columns FROM table WHERE conditions LIMIT limit OFFSET offset.
     *
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions  Each: [column, operator, value]
     * @param  array<int, string>  $columns  ['*'] for all.
     * @return \Generator<int, array<string, mixed>>
     */
    public function executePassThrough(
        RelovaConnection $connection,
        string $table,
        array $conditions = [],
        array $columns = ['*'],
        int $limit = 100,
        int $offset = 0,
    ): \Generator {
        $this->connections->assertHostAllowed($connection);

        $driver = $this->drivers->resolve($connection->driver);
        $config = $this->connections->buildConfig($connection);

        [$sql, $bindings] = $this->buildSelect($table, $conditions, $columns, $limit, $offset);

        yield from $driver->query($config, $sql, $bindings);
    }

    /**
     * LIKE-predicate search pushed down to the remote source.
     *
     * @param  array<int, string>  $displayColumns
     * @return \Generator<int, array<string, mixed>>
     */
    public function search(
        RelovaConnection $connection,
        string $table,
        string $searchColumn,
        string $searchTerm,
        array $displayColumns,
        int $limit = 20,
    ): \Generator {
        $this->connections->assertHostAllowed($connection);

        $driver = $this->drivers->resolve($connection->driver);
        $config = $this->connections->buildConfig($connection);

        $cols = empty($displayColumns)
            ? '*'
            : implode(', ', array_map(fn (string $c) => $this->quote($c), $displayColumns));

        $qTable = $this->quote($table);
        $qSearch = $this->quote($searchColumn);
        $limit = max(1, min($limit, (int) config('relova.max_rows_per_query', 10000)));

        $sql = "SELECT {$cols} FROM {$qTable} WHERE {$qSearch} LIKE ? LIMIT {$limit}";

        yield from $driver->query($config, $sql, ["%{$searchTerm}%"]);
    }

    /**
     * Execute a single-row fetch by primary key. Convenience used by SnapshotManager.
     *
     * @param  array<int, string>  $columns
     * @return array<string, mixed>|null
     */
    public function fetchOne(
        RelovaConnection $connection,
        string $table,
        string $pkColumn,
        string $pkValue,
        array $columns = ['*'],
    ): ?array {
        $gen = $this->executePassThrough(
            connection: $connection,
            table: $table,
            conditions: [[$pkColumn, '=', $pkValue]],
            columns: $columns,
            limit: 1,
        );

        foreach ($gen as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions
     * @param  array<int, string>  $columns
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSelect(string $table, array $conditions, array $columns, int $limit, int $offset): array
    {
        $cols = ($columns === [] || $columns === ['*'])
            ? '*'
            : implode(', ', array_map(fn (string $c) => $this->quote($c), $columns));

        $qTable = $this->quote($table);
        $sql = "SELECT {$cols} FROM {$qTable}";
        $bindings = [];

        if ($conditions !== []) {
            $parts = [];
            foreach ($conditions as [$col, $op, $val]) {
                $op = $this->normalizeOperator($op);
                $parts[] = $this->quote($col).' '.$op.' ?';
                $bindings[] = $val;
            }
            $sql .= ' WHERE '.implode(' AND ', $parts);
        }

        $limit = max(1, min($limit, (int) config('relova.max_rows_per_query', 10000)));
        $sql .= " LIMIT {$limit}";

        if ($offset > 0) {
            $sql .= " OFFSET {$offset}";
        }

        return [$sql, $bindings];
    }

    private function normalizeOperator(string $op): string
    {
        $op = strtoupper(trim($op));
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'IS', 'IS NOT'];

        return in_array($op, $allowed, true) ? $op : '=';
    }

    private function quote(string $identifier): string
    {
        // Safe identifier quoting — strip anything that isn't alnum/underscore/dot.
        $clean = preg_replace('/[^A-Za-z0-9_.]/', '', $identifier) ?? '';

        return '"'.$clean.'"';
    }
}
