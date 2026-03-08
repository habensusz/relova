<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;

class PostgreSqlDriver extends AbstractPdoDriver
{
    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function getDisplayName(): string
    {
        return 'PostgreSQL';
    }

    public function getDefaultPort(): int
    {
        return 5432;
    }

    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort();
        $database = $config['database'] ?? '';

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    protected function setReadOnly(PDO $pdo): void
    {
        $pdo->exec('SET default_transaction_read_only = on');
    }

    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        $pdo->exec('SET statement_timeout = '.($seconds * 1000));
    }

    public function getConfigSchema(): array
    {
        return array_merge(parent::getConfigSchema(), [
            'schema' => ['type' => 'string', 'label' => 'Schema', 'required' => false, 'default' => 'public'],
        ]);
    }

    protected function getTablesQuery(array $config): string
    {
        $schema = $config['schema'] ?? 'public';

        return "SELECT
            t.table_name,
            t.table_schema,
            t.table_type,
            (SELECT reltuples::bigint FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relname = t.table_name AND n.nspname = t.table_schema
             LIMIT 1) AS row_count
        FROM information_schema.tables t
        WHERE t.table_schema = '{$schema}'
        ORDER BY t.table_name";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $schema = $config['schema'] ?? 'public';

        return "SELECT
            c.column_name,
            c.data_type,
            c.is_nullable,
            c.column_default,
            c.character_maximum_length AS max_length,
            c.numeric_precision,
            CASE WHEN tc.constraint_type = 'PRIMARY KEY' THEN 'YES' ELSE 'NO' END AS is_primary
        FROM information_schema.columns c
        LEFT JOIN information_schema.key_column_usage kcu
            ON c.column_name = kcu.column_name
            AND c.table_name = kcu.table_name
            AND c.table_schema = kcu.table_schema
        LEFT JOIN information_schema.table_constraints tc
            ON kcu.constraint_name = tc.constraint_name
            AND kcu.table_schema = tc.table_schema
            AND tc.constraint_type = 'PRIMARY KEY'
        WHERE c.table_schema = '{$schema}'
        AND c.table_name = '{$table}'
        ORDER BY c.ordinal_position";
    }

    protected function normalizeTables(array $rawTables): array
    {
        return array_map(fn (array $row) => [
            'name' => $row['table_name'],
            'schema' => $row['table_schema'] ?? null,
            'type' => str_contains(strtoupper($row['table_type'] ?? ''), 'VIEW') ? 'view' : 'table',
            'row_count' => isset($row['row_count']) && (int) $row['row_count'] >= 0 ? (int) $row['row_count'] : null,
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
}
