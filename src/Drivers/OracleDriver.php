<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;
use Relova\Exceptions\ConnectionException;

/**
 * Oracle Database connector using PDO OCI (pdo_oci PHP extension required).
 *
 * The 'database' config field holds the Oracle Service Name or SID.
 * The 'schema'   config field holds the owner/schema name (defaults to username).
 *
 * Oracle PDO returns column names in UPPERCASE; normalise with array_change_key_case().
 */
class OracleDriver extends AbstractPdoDriver
{
    public function getDriverName(): string
    {
        return 'oracle';
    }

    public function getDisplayName(): string
    {
        return 'Oracle';
    }

    public function getDefaultPort(): int
    {
        return 1521;
    }

    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort();
        $database = $config['database'] ?? '';

        // database = Service Name or SID
        return "oci:dbname=//{$host}:{$port}/{$database}";
    }

    public function getConfigSchema(): array
    {
        return array_merge(parent::getConfigSchema(), [
            'schema' => [
                'type' => 'string',
                'label' => 'Schema (Owner)',
                'required' => false,
                'default' => '',
                'hint' => 'Defaults to the connected username if left blank',
            ],
        ]);
    }

    public function testConnection(array $config): bool
    {
        if (! in_array('oci', PDO::getAvailableDrivers(), true)) {
            throw new ConnectionException(
                message: 'Oracle connection requires the pdo_oci PHP extension. Please install it and restart your web server.',
                driverName: $this->getDriverName(),
                host: $config['host'] ?? null,
            );
        }

        return parent::testConnection($config);
    }

    /**
     * pdo_oci does not support PDO::ATTR_EMULATE_PREPARES as a constructor option.
     * Connection timeout is governed by Oracle's sqlnet / server-side resource plans.
     *
     * @return array<int, mixed>
     */
    protected function getPdoConstructorOptions(int $connectTimeout): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }

    /**
     * Oracle has no single PDO attribute for a query timeout.
     * We rely on enforceReadOnly() and database-level resource plans instead.
     */
    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        // No-op: Oracle per-session timeout requires DBMS_SESSION, not available via PDO
    }

    protected function getTablesQuery(array $config): string
    {
        $owner = strtoupper($config['schema'] ?? $config['username'] ?? '');

        return "SELECT table_name, owner AS table_schema, 'TABLE' AS table_type, num_rows AS row_count
                FROM all_tables
                WHERE owner = '{$owner}'
                UNION ALL
                SELECT view_name AS table_name, owner AS table_schema, 'VIEW' AS table_type, NULL AS row_count
                FROM all_views
                WHERE owner = '{$owner}'
                ORDER BY table_name";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $owner = strtoupper($config['schema'] ?? $config['username'] ?? '');
        $table = strtoupper($table);

        return "SELECT
                    c.column_name,
                    c.data_type,
                    CASE c.nullable WHEN 'Y' THEN 'YES' ELSE 'NO' END AS is_nullable,
                    c.data_default AS column_default,
                    c.char_length AS max_length,
                    c.data_precision AS numeric_precision,
                    CASE WHEN pk.column_name IS NOT NULL THEN 'YES' ELSE 'NO' END AS is_primary
                FROM all_tab_columns c
                LEFT JOIN (
                    SELECT cc.column_name
                    FROM all_constraints con
                    JOIN all_cons_columns cc
                        ON con.constraint_name = cc.constraint_name
                        AND con.owner = cc.owner
                    WHERE con.constraint_type = 'P'
                      AND con.owner = '{$owner}'
                      AND cc.table_name = '{$table}'
                ) pk ON c.column_name = pk.column_name
                WHERE c.owner = '{$owner}'
                  AND c.table_name = '{$table}'
                ORDER BY c.column_id";
    }

    protected function normalizeTables(array $rawTables): array
    {
        return array_map(function (array $row): array {
            $row = array_change_key_case($row, CASE_LOWER);

            return [
                'name' => $row['table_name'],
                'schema' => $row['table_schema'] ?? null,
                'type' => strtolower($row['table_type'] ?? 'table') === 'view' ? 'view' : 'table',
                'row_count' => isset($row['row_count']) && $row['row_count'] !== null ? (int) $row['row_count'] : null,
            ];
        }, $rawTables);
    }

    protected function normalizeColumns(array $rawColumns): array
    {
        return array_map(function (array $row): array {
            $row = array_change_key_case($row, CASE_LOWER);

            return [
                'name' => $row['column_name'],
                'type' => $row['data_type'],
                'nullable' => strtoupper($row['is_nullable'] ?? 'NO') === 'YES',
                'default' => isset($row['column_default']) ? trim((string) $row['column_default']) : null,
                'primary' => strtoupper($row['is_primary'] ?? 'NO') === 'YES',
                'length' => isset($row['max_length']) && $row['max_length'] !== null ? (int) $row['max_length'] : null,
            ];
        }, $rawColumns);
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', array_map(
            fn (string $col) => $this->quoteIdentifier(strtoupper($col)),
            $columns
        ));

        $quotedTable = $this->quoteIdentifier(strtoupper($table));

        // FETCH FIRST … ROWS ONLY requires Oracle 12c+
        return "SELECT {$cols} FROM {$quotedTable} FETCH FIRST {$limit} ROWS ONLY";
    }
}
