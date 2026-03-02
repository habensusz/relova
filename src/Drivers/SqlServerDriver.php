<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;

class SqlServerDriver extends AbstractPdoDriver
{
    public function getDriverName(): string
    {
        return 'sqlsrv';
    }

    public function getDisplayName(): string
    {
        return 'SQL Server';
    }

    public function getDefaultPort(): int
    {
        return 1433;
    }

    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort();
        $database = $config['database'] ?? '';

        return "sqlsrv:Server={$host},{$port};Database={$database}";
    }

    protected function setReadOnly(PDO $pdo): void
    {
        // SQL Server: set transaction to read-only via snapshot isolation
        // or simply rely on read-only enforced credentials
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }

    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        $pdo->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $seconds);
    }

    public function getConfigSchema(): array
    {
        return array_merge(parent::getConfigSchema(), [
            'schema' => ['type' => 'string', 'label' => 'Schema', 'required' => false, 'default' => 'dbo'],
        ]);
    }

    protected function getTablesQuery(array $config): string
    {
        $schema = $config['schema'] ?? 'dbo';

        return "SELECT
            t.TABLE_NAME AS table_name,
            t.TABLE_SCHEMA AS table_schema,
            t.TABLE_TYPE AS table_type,
            p.rows AS row_count
        FROM INFORMATION_SCHEMA.TABLES t
        LEFT JOIN sys.partitions p
            ON p.object_id = OBJECT_ID(t.TABLE_SCHEMA + '.' + t.TABLE_NAME)
            AND p.index_id IN (0, 1)
        WHERE t.TABLE_SCHEMA = '{$schema}'
        ORDER BY t.TABLE_NAME";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $schema = $config['schema'] ?? 'dbo';

        return "SELECT
            c.COLUMN_NAME AS column_name,
            c.DATA_TYPE AS data_type,
            c.IS_NULLABLE AS is_nullable,
            c.COLUMN_DEFAULT AS column_default,
            c.CHARACTER_MAXIMUM_LENGTH AS max_length,
            c.NUMERIC_PRECISION AS numeric_precision,
            CASE WHEN pk.COLUMN_NAME IS NOT NULL THEN 'YES' ELSE 'NO' END AS is_primary
        FROM INFORMATION_SCHEMA.COLUMNS c
        LEFT JOIN (
            SELECT ku.COLUMN_NAME, ku.TABLE_NAME, ku.TABLE_SCHEMA
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
            JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku
                ON tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
        ) pk ON c.COLUMN_NAME = pk.COLUMN_NAME
            AND c.TABLE_NAME = pk.TABLE_NAME
            AND c.TABLE_SCHEMA = pk.TABLE_SCHEMA
        WHERE c.TABLE_SCHEMA = '{$schema}'
        AND c.TABLE_NAME = '{$table}'
        ORDER BY c.ORDINAL_POSITION";
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
            'primary' => ($row['is_primary'] ?? 'NO') === 'YES',
            'length' => isset($row['max_length']) ? (int) $row['max_length'] : null,
        ], $rawColumns);
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', array_map(
            fn (string $col) => '['.str_replace(']', ']]', $col).']',
            $columns
        ));

        $quotedTable = '['.str_replace(']', ']]', $table).']';

        return "SELECT TOP {$limit} {$cols} FROM {$quotedTable}";
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }
}
