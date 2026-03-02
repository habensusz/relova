<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;

class MySqlDriver extends AbstractPdoDriver
{
    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function getDisplayName(): string
    {
        return 'MySQL';
    }

    public function getDefaultPort(): int
    {
        return 3306;
    }

    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort();
        $database = $config['database'] ?? '';

        return "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    }

    protected function setReadOnly(PDO $pdo): void
    {
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    }

    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME = '.($seconds * 1000));
    }

    protected function getTablesQuery(array $config): string
    {
        $database = $config['database'] ?? '';

        return "SELECT
            TABLE_NAME AS table_name,
            TABLE_SCHEMA AS table_schema,
            TABLE_TYPE AS table_type,
            TABLE_ROWS AS row_count
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = '{$database}'
        ORDER BY TABLE_NAME";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $database = $config['database'] ?? '';

        return "SELECT
            COLUMN_NAME AS column_name,
            DATA_TYPE AS data_type,
            IS_NULLABLE AS is_nullable,
            COLUMN_DEFAULT AS column_default,
            COLUMN_KEY AS column_key,
            CHARACTER_MAXIMUM_LENGTH AS max_length,
            NUMERIC_PRECISION AS numeric_precision,
            COLUMN_TYPE AS column_type
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '{$database}'
        AND TABLE_NAME = '{$table}'
        ORDER BY ORDINAL_POSITION";
    }

    protected function normalizeTables(array $rawTables): array
    {
        return array_map(fn (array $row) => [
            'name' => $row['table_name'],
            'schema' => $row['table_schema'] ?? null,
            'type' => str_contains(strtoupper($row['table_type'] ?? ''), 'VIEW') ? 'view' : 'table',
            'row_count' => isset($row['row_count']) ? (int) $row['row_count'] : null,
        ], $rawTables);
    }

    protected function normalizeColumns(array $rawColumns): array
    {
        return array_map(fn (array $row) => [
            'name' => $row['column_name'],
            'type' => $row['data_type'],
            'nullable' => strtoupper($row['is_nullable'] ?? 'NO') === 'YES',
            'default' => $row['column_default'],
            'primary' => ($row['column_key'] ?? '') === 'PRI',
            'length' => isset($row['max_length']) ? (int) $row['max_length'] : null,
        ], $rawColumns);
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', array_map(
            fn (string $col) => '`'.str_replace('`', '``', $col).'`',
            $columns
        ));

        $quotedTable = '`'.str_replace('`', '``', $table).'`';

        return "SELECT {$cols} FROM {$quotedTable} LIMIT {$limit}";
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
