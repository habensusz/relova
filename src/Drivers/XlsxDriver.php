<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;

/**
 * Connector driver for Excel XLSX files via PhpSpreadsheet.
 *
 * - File path stored in the connection's `host` column.
 * - Each worksheet in the workbook becomes a queryable "table".
 * - First row of every sheet is treated as the column header row.
 * - SSH tunnel and port/username/password fields are not applicable.
 *
 * Requires: phpoffice/phpspreadsheet (already present in the host application).
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

    public function query(array $config, string $sql, array $bindings = []): array
    {
        $this->enforceReadOnly($sql);

        $maxRows = (int) config('relova.max_rows_per_query', 10000);
        [$selectCols, $limit, $tableName] = $this->parseSql($sql, $maxRows);

        $spreadsheet = $this->loadSpreadsheet($config);
        $sheet = $tableName
            ? ($spreadsheet->getSheetByName($tableName) ?? $spreadsheet->getActiveSheet())
            : $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow < 2) {
            return [];
        }

        $all = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, false);
        $headers = array_map(fn ($v) => trim((string) ($v ?? '')), $all[0] ?? []);

        $rows = [];
        $count = 0;

        for ($r = 1; $r < count($all) && $count < $limit; $r++) {
            $assoc = [];
            foreach ($headers as $hidx => $hname) {
                $assoc[$hname] = $all[$r][$hidx] ?? null;
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
        ];
    }

    // --- Helpers ---

    protected function loadSpreadsheet(array $config): Spreadsheet
    {
        $path = $config['host'] ?? '';

        if (! file_exists($path) || ! is_readable($path)) {
            throw new ConnectionException(
                message: "Cannot read Excel file: {$path}",
                driverName: 'xlsx',
                host: $path,
            );
        }

        try {
            return IOFactory::load($path);
        } catch (\Exception $e) {
            throw new ConnectionException(
                message: 'Failed to parse Excel file: '.$e->getMessage(),
                driverName: 'xlsx',
                host: $path,
                previous: $e,
            );
        }
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
     *
     * @return array{0: ?array<string>, 1: int, 2: ?string}
     */
    protected function parseSql(string $sql, int $defaultLimit): array
    {
        $selectCols = null;
        $limit = $defaultLimit;
        $tableName = null;

        if (preg_match('/\bLIMIT\s+(\d+)/i', $sql, $m)) {
            $limit = min((int) $m[1], $defaultLimit);
        }

        if (preg_match('/\bFROM\s+[`"]?(\w[\w\s]*)[`"]?(?:\s+LIMIT|\s*$)/i', $sql, $m)) {
            $tableName = trim($m[1]);
        }

        if (preg_match('/^\s*SELECT\s+(.*?)\s+FROM\b/is', $sql, $m)) {
            $colsPart = trim($m[1]);
            if ($colsPart !== '*') {
                $selectCols = array_map('trim', explode(',', $colsPart));
            }
        }

        return [$selectCols, $limit, $tableName];
    }
}
