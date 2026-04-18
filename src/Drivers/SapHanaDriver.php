<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;
use PDOException;
use Relova\Exceptions\ConnectionException;

/**
 * SAP HANA connector via PDO ODBC (HDBODBC driver required on the server).
 *
 * Requires the HDBODBC driver installed and the pdo_odbc PHP extension enabled.
 * The 'database' config field holds the SAP HANA Database Name (tenant DB name).
 * The 'schema'   config field holds the HANA schema name.
 *
 * Typical SAP HANA ports:
 *   30015 — System DB (on-premise)
 *   443   — SAP HANA Cloud (TLS)
 */
class SapHanaDriver extends AbstractPdoDriver
{
    public function getDriverName(): string
    {
        return 'sap_hana';
    }

    public function getDisplayName(): string
    {
        return 'SAP HANA';
    }

    public function getDefaultPort(): int
    {
        return 30015;
    }

    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort();
        $database = $config['database'] ?? '';

        // HDBODBC ODBC DSN — SERVERNODE accepts host:port
        $dsn = "odbc:DRIVER={HDBODBC};SERVERNODE={$host}:{$port}";

        if ($database !== '') {
            $dsn .= ";DATABASENAME={$database}";
        }

        return $dsn;
    }

    public function getConfigSchema(): array
    {
        return array_merge(parent::getConfigSchema(), [
            'schema' => [
                'type' => 'string',
                'label' => 'Schema',
                'required' => false,
                'default' => '',
                'hint' => 'HANA schema name (usually matches your user name in uppercase)',
            ],
        ]);
    }

    public function testConnection(array $config): bool
    {
        if (! in_array('odbc', PDO::getAvailableDrivers(), true)) {
            throw new ConnectionException(
                message: 'SAP HANA connection requires the pdo_odbc PHP extension and HDBODBC ODBC driver. Please install them and restart your web server.',
                driverName: $this->getDriverName(),
                host: $config['host'] ?? null,
            );
        }

        return parent::testConnection($config);
    }

    /**
     * pdo_odbc does not support PDO::ATTR_EMULATE_PREPARES as a constructor option;
     * passing it causes "Driver does not support this function" errors on some ODBC drivers.
     *
     * @return array<int, mixed>
     */
    protected function getPdoConstructorOptions(int $connectTimeout): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $connectTimeout,
        ];
    }

    protected function setReadOnly(PDO $pdo): void
    {
        try {
            $pdo->exec('SET TRANSACTION READ ONLY');
        } catch (PDOException) {
            // Some HANA versions / ODBC drivers may not support this; ignore.
        }
    }

    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        try {
            // HANA SQL: SET 'optimizer' 'timeout' accepts milliseconds
            $ms = $seconds * 1000;
            $pdo->exec("SET 'optimizer' 'timeout' = '{$ms}'");
        } catch (PDOException) {
            // Not all HANA versions / ODBC drivers expose this; ignore.
        }
    }

    protected function getTablesQuery(array $config): string
    {
        $schema = strtoupper($config['schema'] ?? $config['username'] ?? '');

        return "SELECT
                    TABLE_NAME,
                    SCHEMA_NAME AS TABLE_SCHEMA,
                    'TABLE' AS TABLE_TYPE,
                    RECORD_COUNT AS ROW_COUNT
                FROM SYS.TABLES
                WHERE SCHEMA_NAME = '{$schema}'
                UNION ALL
                SELECT
                    VIEW_NAME AS TABLE_NAME,
                    SCHEMA_NAME AS TABLE_SCHEMA,
                    'VIEW' AS TABLE_TYPE,
                    NULL AS ROW_COUNT
                FROM SYS.VIEWS
                WHERE SCHEMA_NAME = '{$schema}'
                ORDER BY TABLE_NAME";
    }

    protected function getColumnsQuery(string $table, array $config): string
    {
        $schema = strtoupper($config['schema'] ?? $config['username'] ?? '');
        $table = strtoupper($table);

        return "SELECT
                    c.COLUMN_NAME,
                    c.DATA_TYPE_NAME AS DATA_TYPE,
                    c.IS_NULLABLE,
                    c.DEFAULT_VALUE AS COLUMN_DEFAULT,
                    c.LENGTH AS MAX_LENGTH,
                    c.SCALE AS NUMERIC_PRECISION,
                    CASE WHEN c.IS_PRIMARY_KEY = 'TRUE' THEN 'YES' ELSE 'NO' END AS IS_PRIMARY
                FROM SYS.TABLE_COLUMNS c
                WHERE c.SCHEMA_NAME = '{$schema}'
                  AND c.TABLE_NAME  = '{$table}'
                ORDER BY c.POSITION";
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
                'nullable' => strtoupper($row['is_nullable'] ?? 'FALSE') === 'TRUE',
                'default' => $row['column_default'] ?? null,
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

        return "SELECT {$cols} FROM {$quotedTable} LIMIT {$limit}";
    }
}
