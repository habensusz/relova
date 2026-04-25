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

    protected function createPdo(array $config): PDO
    {
        $pdo = parent::createPdo($config);

        $schema = trim((string) ($config['schema'] ?? ''));
        if ($schema !== '') {
            $pdo->exec('SET search_path TO "'.str_replace('"', '""', $schema).'"');
        }

        return $pdo;
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
        $schema = str_replace("'", "''", $config['schema'] ?? 'public');

        return "SELECT
            t.tablename AS table_name,
            t.schemaname AS table_schema,
            'BASE TABLE' AS table_type,
            (SELECT reltuples::bigint
             FROM pg_catalog.pg_class c2
             JOIN pg_catalog.pg_namespace n2 ON n2.oid = c2.relnamespace
             WHERE c2.relname = t.tablename AND n2.nspname = t.schemaname
             LIMIT 1) AS row_count
        FROM pg_catalog.pg_tables t
        WHERE t.schemaname = '{$schema}'
        UNION ALL
        SELECT
            v.viewname AS table_name,
            v.schemaname AS table_schema,
            'VIEW' AS table_type,
            NULL::bigint AS row_count
        FROM pg_catalog.pg_views v
        WHERE v.schemaname = '{$schema}'
        ORDER BY table_name";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $schema = str_replace("'", "''", $config['schema'] ?? 'public');
        $table = str_replace("'", "''", $table);

        return "SELECT
            a.attname AS column_name,
            pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type,
            CASE a.attnotnull WHEN TRUE THEN 'NO' ELSE 'YES' END AS is_nullable,
            pg_catalog.pg_get_expr(ad.adbin, ad.adrelid) AS column_default,
            CASE
                WHEN typ.typname IN ('bpchar', 'varchar') AND a.atttypmod > 0
                THEN (a.atttypmod - 4)::integer
                ELSE NULL
            END AS max_length,
            CASE
                WHEN typ.typname = 'numeric' AND a.atttypmod > 0
                THEN ((a.atttypmod - 4) >> 16)::integer
                ELSE NULL
            END AS numeric_precision,
            CASE WHEN EXISTS (
                SELECT 1 FROM pg_catalog.pg_constraint con
                WHERE con.conrelid = c.oid
                AND con.contype = 'p'
                AND a.attnum = ANY(con.conkey)
            ) THEN 'YES' ELSE 'NO' END AS is_primary
        FROM pg_catalog.pg_attribute a
        JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
        JOIN pg_catalog.pg_type typ ON typ.oid = a.atttypid
        LEFT JOIN pg_catalog.pg_attrdef ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
        WHERE n.nspname = '{$schema}'
        AND c.relname = '{$table}'
        AND a.attnum > 0
        AND NOT a.attisdropped
        ORDER BY a.attnum";
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
