<?php

declare(strict_types=1);

namespace Relova\Drivers;

use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;

/**
 * CSV file connector.
 *
 * File path is stored in the connection's host column (or options.path).
 * Each CSV file = one "table" named after the filename without extension.
 * First row is always the header row.
 *
 * Rows are yielded one-by-one via \Generator — the file is never
 * loaded into memory as a whole array.
 */
class CsvDriver implements ConnectorDriver
{
    public function getDriverName(): string
    {
        return 'csv';
    }

    public function getDisplayName(): string
    {
        return 'CSV File';
    }

    public function getDefaultPort(): int
    {
        return 0;
    }

    public function getConfigSchema(): array
    {
        return [
            'path' => ['type' => 'string', 'label' => 'File Path', 'required' => true, 'default' => ''],
            'delimiter' => ['type' => 'string', 'label' => 'Delimiter', 'required' => false, 'default' => ','],
        ];
    }

    public function testConnection(array $config): bool
    {
        $path = $this->resolvePath($config);
        if (! is_readable($path)) {
            throw new ConnectionException(
                message: "Cannot read CSV file: {$path}",
                driverName: 'csv',
                host: $path,
            );
        }

        return true;
    }

    public function getTables(array $config): array
    {
        $path = $this->resolvePath($config);

        return [[
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'schema' => null,
            'type' => 'table',
            'row_count' => null,
        ]];
    }

    public function getColumns(array $config, string $table): array
    {
        $path = $this->resolvePath($config);
        $delimiter = $this->resolveDelimiter($config);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new ConnectionException(
                message: "Cannot open CSV file: {$path}",
                driverName: 'csv',
                host: $path,
            );
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        if (! is_array($headers)) {
            return [];
        }

        return array_values(array_map(fn (string $col) => [
            'name' => trim($col),
            'type' => 'string',
            'nullable' => true,
            'default' => null,
            'primary' => false,
            'length' => null,
        ], $headers));
    }

    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator
    {
        $this->enforceReadOnly($sql);

        $path = $this->resolvePath($config);
        $delimiter = $this->resolveDelimiter($config);
        $maxRows = (int) ($options['max_rows'] ?? config('relova.max_rows_per_query', 10000));

        [$selectCols, $limit] = $this->parseSql($sql, $maxRows);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new QueryException(
                message: "Cannot open CSV file: {$path}",
                sql: $sql,
            );
        }

        try {
            $headers = fgetcsv($handle, 0, $delimiter);
            if (! is_array($headers)) {
                return;
            }
            $headers = array_map('trim', $headers);

            $yielded = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $yielded < $limit) {
                $assoc = array_combine($headers, array_pad($row, count($headers), null));
                if (! is_array($assoc)) {
                    continue;
                }

                if ($selectCols === null) {
                    yield $assoc;
                } else {
                    $filtered = [];
                    foreach ($selectCols as $col) {
                        $filtered[$col] = $assoc[$col] ?? null;
                    }
                    yield $filtered;
                }
                $yielded++;
            }
        } finally {
            fclose($handle);
        }
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', $columns);

        return "SELECT {$cols} FROM \"{$table}\" LIMIT {$limit}";
    }

    private function resolvePath(array $config): string
    {
        return (string) ($config['path'] ?? $config['host'] ?? '');
    }

    private function resolveDelimiter(array $config): string
    {
        return (string) ($config['delimiter'] ?? $config['options']['delimiter'] ?? ',');
    }

    private function enforceReadOnly(string $sql): void
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|REPLACE)\b/i', $sql)) {
            throw new ReadOnlyViolationException(
                message: 'Write operations are not allowed through Relova connectors.',
                sql: $sql,
            );
        }
    }

    /**
     * Minimal SELECT parser — extracts column list and LIMIT.
     *
     * @return array{0: ?array<int, string>, 1: int}
     */
    private function parseSql(string $sql, int $maxRows): array
    {
        $selectCols = null;
        if (preg_match('/^\s*SELECT\s+(.+?)\s+FROM\s+/i', $sql, $m)) {
            $colPart = trim($m[1]);
            if ($colPart !== '*') {
                $selectCols = array_map(
                    fn (string $c) => trim($c, " \t`\""),
                    explode(',', $colPart),
                );
            }
        }

        $limit = $maxRows;
        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            $limit = min((int) $m[1], $maxRows);
        }

        return [$selectCols, $limit];
    }
}
