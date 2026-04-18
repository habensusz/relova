<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\ReadOnlyViolationException;

/**
 * Excel XLSX connector via PhpSpreadsheet.
 *
 * Each worksheet becomes a queryable "table". The first row of every sheet
 * is treated as the column header row. Rows are yielded one-by-one.
 */
class XlsxDriver implements ConnectorDriver
{
    public function getDriverName(): string
    {
        return 'xlsx';
    }

    public function getDisplayName(): string
    {
        return 'Excel (XLSX)';
    }

    public function getDefaultPort(): int
    {
        return 0;
    }

    public function getConfigSchema(): array
    {
        return [
            'path' => ['type' => 'string', 'label' => 'File Path', 'required' => true, 'default' => ''],
        ];
    }

    public function testConnection(array $config): bool
    {
        $this->loadSpreadsheet($config);

        return true;
    }

    public function getTables(array $config): array
    {
        $spreadsheet = $this->loadSpreadsheet($config);

        return array_map(fn (string $name) => [
            'name' => $name,
            'schema' => null,
            'type' => 'table',
            'row_count' => null,
        ], $spreadsheet->getSheetNames());
    }

    public function getColumns(array $config, string $table): array
    {
        $spreadsheet = $this->loadSpreadsheet($config);
        $sheet = $spreadsheet->getSheetByName($table) ?? $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestDataColumn();
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];

        $columns = [];
        foreach ($headerRow as $col) {
            if ($col === null || (string) $col === '') {
                break;
            }
            $columns[] = [
                'name' => (string) $col,
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'primary' => false,
                'length' => null,
            ];
        }

        return $columns;
    }

    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator
    {
        $this->enforceReadOnly($sql);

        $maxRows = (int) ($options['max_rows'] ?? config('relova.max_rows_per_query', 10000));
        [$selectCols, $limit, $tableName] = $this->parseSql($sql, $maxRows);

        $spreadsheet = $this->loadSpreadsheet($config);
        $sheet = $tableName
            ? ($spreadsheet->getSheetByName($tableName) ?? $spreadsheet->getActiveSheet())
            : $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();
        if ($highestRow < 2) {
            return;
        }

        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $headers = array_map(fn ($v) => trim((string) ($v ?? '')), $headerRow);

        $yielded = 0;
        for ($r = 2; $r <= $highestRow && $yielded < $limit; $r++) {
            $rowRange = "A{$r}:{$highestColumn}{$r}";
            $rowData = $sheet->rangeToArray($rowRange, null, true, false)[0] ?? [];

            $assoc = [];
            foreach ($headers as $hidx => $hname) {
                $assoc[$hname] = $rowData[$hidx] ?? null;
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
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', $columns);

        return "SELECT {$cols} FROM \"{$table}\" LIMIT {$limit}";
    }

    private function loadSpreadsheet(array $config): Spreadsheet
    {
        $path = $this->resolvePath($config);

        if (! is_readable($path)) {
            throw new ConnectionException(
                message: "Cannot read spreadsheet: {$path}",
                driverName: 'xlsx',
                host: $path,
            );
        }

        try {
            return IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new ConnectionException(
                message: 'Failed to load spreadsheet: '.$e->getMessage(),
                driverName: 'xlsx',
                host: $path,
                previous: $e,
            );
        }
    }

    private function resolvePath(array $config): string
    {
        return (string) ($config['path'] ?? $config['host'] ?? '');
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
     * Minimal SELECT parser — extracts column list, LIMIT, and FROM table name.
     *
     * @return array{0: ?array<int, string>, 1: int, 2: ?string}
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

        $tableName = null;
        if (preg_match('/\bFROM\s+["`]?([A-Za-z0-9_\s]+?)["`]?(?:\s+WHERE|\s+LIMIT|\s*$)/i', $sql, $m)) {
            $tableName = trim($m[1]);
        }

        $limit = $maxRows;
        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            $limit = min((int) $m[1], $maxRows);
        }

        return [$selectCols, $limit, $tableName];
    }
}
