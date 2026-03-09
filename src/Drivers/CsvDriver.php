<?php

declare(strict_types=1);

namespace Relova\Drivers;

use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;

/**
 * Connector driver for CSV (comma-separated values) files.
 *
 * - File path is stored in the connection's `host` column.
 * - Optional delimiter stored in `config_meta.delimiter` (defaults to ",").
 * - Each CSV file = one "table" named after the filename without extension.
 * - First row is always treated as the header row.
 * - SSH tunnel and port fields are not applicable.
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

    public function testConnection(array $config): bool
    {
        $path = $this->resolvePath($config);

        if (! file_exists($path) || ! is_readable($path)) {
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
        $name = pathinfo($path, PATHINFO_FILENAME);

        return [[
            'name' => $name,
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

    public function query(array $config, string $sql, array $bindings = []): array
    {
        $this->enforceReadOnly($sql);

        $path = $this->resolvePath($config);
        $delimiter = $this->resolveDelimiter($config);
        $maxRows = (int) config('relova.max_rows_per_query', 10000);

        [$selectCols, $limit] = $this->parseSql($sql, $maxRows);

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new QueryException(
                message: "Cannot open CSV file: {$path}",
                sql: $sql,
            );
        }

        $headers = fgetcsv($handle, 0, $delimiter);

        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $headers = array_map('trim', $headers);
        $rows = [];
        $count = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $count < $limit) {
            $assoc = array_combine($headers, array_pad($row, count($headers), null));

            if (! is_array($assoc)) {
                continue;
            }

            if ($selectCols === null) {
                $rows[] = $assoc;
            } else {
                $filtered = [];
                foreach ($selectCols as $col) {
                    $filtered[$col] = $assoc[$col] ?? null;
                }
                $rows[] = $filtered;
            }

            $count++;
        }

        fclose($handle);

        return $rows;
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', $columns);

        return "SELECT {$cols} FROM {$table} LIMIT {$limit}";
    }

    public function getConfigSchema(): array
    {
        return [
            'file_path' => ['type' => 'string', 'label' => 'File Path', 'required' => true, 'default' => ''],
            'delimiter' => ['type' => 'string', 'label' => 'Delimiter', 'required' => false, 'default' => ','],
        ];
    }

    // --- Helpers ---

    protected function resolvePath(array $config): string
    {
        return $config['host'] ?? '';
    }

    protected function resolveDelimiter(array $config): string
    {
        $meta = $config['config_meta'] ?? [];

        return (string) ($meta['delimiter'] ?? ',');
    }

    protected function enforceReadOnly(string $sql): void
    {
        $normalized = strtolower(trim($sql));
        $writeKeywords = ['insert', 'update', 'delete', 'drop', 'truncate', 'alter', 'create', 'replace'];

        foreach ($writeKeywords as $keyword) {
            if (str_starts_with($normalized, $keyword)) {
                throw new ReadOnlyViolationException(
                    message: "Write operations are not permitted through Relova connectors: {$sql}",
                    sql: $sql,
                );
            }
        }
    }

    /**
     * Parse a simple "SELECT cols FROM table LIMIT n" statement.
     * Returns [selectedColumns|null, limit] — null means SELECT *.
     *
     * @return array{0: ?array<string>, 1: int}
     */
    protected function parseSql(string $sql, int $defaultLimit): array
    {
        $selectCols = null;
        $limit = $defaultLimit;

        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            $limit = min((int) $m[1], $defaultLimit);
        }

        if (preg_match('/^\s*SELECT\s+(.*?)\s+FROM\b/is', $sql, $m)) {
            $colsPart = trim($m[1]);
            if ($colsPart !== '*') {
                $selectCols = array_map('trim', explode(',', $colsPart));
            }
        }

        return [$selectCols, $limit];
    }
}
