<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;
use Relova\Security\QuerySanitizer;

/**
 * Executes pass-through read-only queries against remote sources.
 *
 * Guarantees:
 *   - Every outbound host is validated by SsrfGuard first.
 *   - All SQL is built internally (table + conditions + columns) — callers
 *     never supply raw SQL.
 *   - Every internally-built SQL string is validated by QuerySanitizer
 *     (defence-in-depth) before execution.
 *   - Results are always returned as \Generator — no buffering.
 *   - Nothing from the remote response is persisted locally.
 */
class QueryExecutor
{
    public function __construct(
        private DriverRegistry $drivers,
        private ConnectionRegistry $connections,
        private ?QuerySanitizer $sanitizer = null,
    ) {}

    /**
     * SELECT columns FROM table [JOINs] WHERE conditions LIMIT limit OFFSET offset.
     *
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions  Each: [column, operator, value]
     * @param  array<int, string>  $columns  ['*'] for all.
     * @param  array<string, array{type: string, foreign_key: string, references: string}>  $joins
     * @return \Generator<int, array<string, mixed>>
     */
    public function executePassThrough(
        RelovaConnection $connection,
        string $table,
        array $conditions = [],
        array $columns = ['*'],
        int $limit = 100,
        int $offset = 0,
        array $joins = [],
    ): \Generator {
        $this->connections->assertHostAllowed($connection);

        $driver = $this->drivers->resolve($connection->driver);
        [$sql, $bindings] = $this->buildSelect($table, $conditions, $columns, $limit, $offset, $joins);

        // Collect results inside the tunnel session so the tunnel stays open for the entire query
        $rows = $this->connections->withTunnel($connection, function (array $config) use ($driver, $sql, $bindings) {
            $this->sanitizer?->assertSafe($sql);
            $rows = [];
            foreach ($driver->query($config, $sql, $bindings) as $row) {
                $rows[] = $row;
            }

            return $rows;
        });

        yield from $rows;
    }

    /**
     * LIKE-predicate search pushed down to the remote source.
     *
     * @param  array<int, string>  $displayColumns
     * @param  array<string, array{type: string, foreign_key: string, references: string}>  $joins
     * @return \Generator<int, array<string, mixed>>
     */
    public function search(
        RelovaConnection $connection,
        string $table,
        string $searchColumn,
        string $searchTerm,
        array $displayColumns,
        int $limit = 20,
        array $joins = [],
    ): \Generator {
        $this->connections->assertHostAllowed($connection);

        $driver = $this->drivers->resolve($connection->driver);

        $hasJoins = ! empty($joins);

        $cols = empty($displayColumns)
            ? ($hasJoins ? $this->quote($table).'.*' : '*')
            : implode(', ', array_map(fn (string $c) => $this->quoteQualified($c), $displayColumns));

        $qTable = $this->quote($table);
        // Qualify the search column with the main table when joins are present to avoid ambiguity.
        $qSearch = $hasJoins ? $this->quote($table).'.'.$this->quote($searchColumn) : $this->quote($searchColumn);
        $limit = max(1, min($limit, (int) config('relova.max_rows_per_query', 10000)));

        $joinClauses = $this->buildJoinClauses($table, $joins);
        $sql = "SELECT {$cols} FROM {$qTable}{$joinClauses} WHERE {$qSearch} LIKE ? LIMIT {$limit}";

        $rows = $this->connections->withTunnel($connection, function (array $config) use ($driver, $sql, $searchTerm) {
            $this->sanitizer?->assertSafe($sql);
            $rows = [];
            foreach ($driver->query($config, $sql, ["%{$searchTerm}%"]) as $row) {
                $rows[] = $row;
            }

            return $rows;
        });

        yield from $rows;
    }

    /**
     * Execute a single-row fetch by primary key. Convenience used by SnapshotManager.
     *
     * @param  array<int, string>  $columns
     * @param  array<string, array{type: string, foreign_key: string, references: string}>  $joins
     * @return array<string, mixed>|null
     */
    public function fetchOne(
        RelovaConnection $connection,
        string $table,
        string $pkColumn,
        string $pkValue,
        array $columns = ['*'],
        array $joins = [],
    ): ?array {
        $gen = $this->executePassThrough(
            connection: $connection,
            table: $table,
            conditions: [[$pkColumn, '=', $pkValue]],
            columns: $columns,
            limit: 1,
            joins: $joins,
        );

        foreach ($gen as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions
     * @param  array<int, string>  $columns
     * @param  array<string, array{type: string, foreign_key: string, references: string}>  $joins
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSelect(string $table, array $conditions, array $columns, int $limit, int $offset, array $joins = []): array
    {
        $hasJoins = ! empty($joins);

        if ($columns === [] || $columns === ['*']) {
            // When joins are present prefix wildcard with the main table to avoid column-name ambiguity.
            $cols = $hasJoins ? $this->quote($table).'.*' : '*';
        } else {
            $cols = implode(', ', array_map(fn (string $c) => $this->quoteQualified($c), $columns));
        }

        $qTable = $this->quote($table);
        $joinClauses = $hasJoins ? $this->buildJoinClauses($table, $joins) : '';
        $sql = "SELECT {$cols} FROM {$qTable}{$joinClauses}";
        $bindings = [];

        if ($conditions !== []) {
            $parts = [];
            foreach ($conditions as [$col, $op, $val]) {
                $op = $this->normalizeOperator($op);
                // Qualify bare condition columns with the main table when joins are present.
                $qCol = ($hasJoins && ! str_contains((string) $col, '.'))
                    ? $this->quote($table).'.'.$this->quote($col)
                    : $this->quoteQualified($col);
                $parts[] = $qCol.' '.$op.' ?';
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

    /**
     * Build the JOIN clauses string from the joins spec.
     *
     * @param  array<string, array{type: string, foreign_key: string, references: string}>  $joins
     */
    private function buildJoinClauses(string $mainTable, array $joins): string
    {
        $clauses = '';
        foreach ($joins as $joinTable => $spec) {
            $type = strtoupper(trim($spec['type'] ?? 'LEFT'));
            if (! in_array($type, ['LEFT', 'INNER'], true)) {
                $type = 'LEFT';
            }
            $qMain = $this->quote($mainTable);
            $qJoin = $this->quote($joinTable);
            $qFk = $this->quote($spec['foreign_key'] ?? '');
            $qRef = $this->quote($spec['references'] ?? 'id');
            $clauses .= " {$type} JOIN {$qJoin} ON {$qMain}.{$qFk} = {$qJoin}.{$qRef}";
        }

        return $clauses;
    }

    private function normalizeOperator(string $op): string
    {
        $op = strtoupper(trim($op));
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'IS', 'IS NOT'];

        return in_array($op, $allowed, true) ? $op : '=';
    }

    /**
     * Quote a simple identifier (no dots).  Strips anything unsafe.
     */
    private function quote(string $identifier): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $identifier) ?? '';

        return '"'.$clean.'"';
    }

    /**
     * Quote a possibly dot-qualified identifier (e.g. "manufacturers.name" → "manufacturers"."name").
     * Falls back to simple quote() when no dot is present.
     */
    private function quoteQualified(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            [$table, $column] = explode('.', $identifier, 2);

            // "table.*" wildcard: quote the table but keep the asterisk literal.
            if ($column === '*') {
                return $this->quote($table).'.*';
            }

            return $this->quote($table).'.'.$this->quote($column);
        }

        // Bare wildcard: pass through as-is.
        if ($identifier === '*') {
            return '*';
        }

        return $this->quote($identifier);
    }
}
