<?php

declare(strict_types=1);

namespace Relova\Drivers;

use PDO;
use PDOException;
use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;

/**
 * Base class for all PDO-based relational database drivers.
 * Provides shared connection logic, read-only enforcement, and query execution.
 */
abstract class AbstractPdoDriver implements ConnectorDriver
{
    /**
     * Build the PDO DSN string from configuration.
     */
    abstract protected function buildDsn(array $config): string;

    /**
     * Get platform-specific SQL to retrieve table list.
     */
    abstract protected function getTablesQuery(array $config): string;

    /**
     * Get platform-specific SQL to retrieve column information.
     */
    abstract protected function getColumnsQuery(string $table, array $config): string;

    /**
     * Parse raw column metadata rows into normalized format.
     *
     * @param  array<int, array<string, mixed>>  $rawColumns
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, primary: bool, length: ?int}>
     */
    abstract protected function normalizeColumns(array $rawColumns): array;

    /**
     * Parse raw table metadata rows into normalized format.
     *
     * @param  array<int, array<string, mixed>>  $rawTables
     * @return array<int, array{name: string, schema: ?string, type: string, row_count: ?int}>
     */
    abstract protected function normalizeTables(array $rawTables): array;

    public function testConnection(array $config): bool
    {
        try {
            $pdo = $this->createPdo($config);
            $pdo = null; // close immediately

            return true;
        } catch (\Exception $e) {
            throw new ConnectionException(
                message: 'Connection test failed: '.$e->getMessage(),
                driverName: $this->getDriverName(),
                host: $config['host'] ?? null,
                previous: $e,
            );
        }
    }

    public function getTables(array $config): array
    {
        $pdo = $this->createPdo($config);
        $sql = $this->getTablesQuery($config);
        $stmt = $pdo->query($sql);
        $rawTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->normalizeTables($rawTables);
    }

    public function getColumns(array $config, string $table): array
    {
        $pdo = $this->createPdo($config);
        $sql = $this->getColumnsQuery($table, $config);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rawColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->normalizeColumns($rawColumns);
    }

    public function query(array $config, string $sql, array $bindings = []): array
    {
        $this->enforceReadOnly($sql);

        $pdo = $this->createPdo($config);
        $timeout = (int) config('relova.query_timeout', 30);

        try {
            $this->setQueryTimeout($pdo, $timeout);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            $maxRows = (int) config('relova.max_rows_per_query', 10000);
            $rows = [];
            $count = 0;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = $row;
                $count++;
                if ($count >= $maxRows) {
                    break;
                }
            }

            return $rows;
        } catch (PDOException $e) {
            throw new QueryException(
                message: 'Query execution failed: '.$e->getMessage(),
                sql: $sql,
                previous: $e,
            );
        }
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        $cols = empty($columns) ? '*' : implode(', ', array_map(
            fn (string $col) => $this->quoteIdentifier($col),
            $columns
        ));

        $quotedTable = $this->quoteIdentifier($table);

        return "SELECT {$cols} FROM {$quotedTable} LIMIT {$limit}";
    }

    public function getConfigSchema(): array
    {
        return [
            'host' => ['type' => 'string', 'label' => 'Host', 'required' => true, 'default' => 'localhost'],
            'port' => ['type' => 'integer', 'label' => 'Port', 'required' => true, 'default' => $this->getDefaultPort()],
            'database' => ['type' => 'string', 'label' => 'Database', 'required' => true, 'default' => ''],
            'username' => ['type' => 'string', 'label' => 'Username', 'required' => true, 'default' => ''],
            'password' => ['type' => 'password', 'label' => 'Password', 'required' => true, 'default' => ''],
        ];
    }

    /**
     * Create a PDO instance with read-only safety and timeout limits.
     */
    protected function createPdo(array $config): PDO
    {
        $dsn = $this->buildDsn($config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $connectTimeout = (int) config('relova.connection_timeout', 10);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $connectTimeout,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $this->setReadOnly($pdo);

            return $pdo;
        } catch (PDOException $e) {
            throw new ConnectionException(
                message: 'Failed to connect: '.$e->getMessage(),
                driverName: $this->getDriverName(),
                host: $config['host'] ?? null,
                previous: $e,
            );
        }
    }

    /**
     * Enforce read-only mode on the connection.
     * Override in subclasses for driver-specific behavior.
     */
    protected function setReadOnly(PDO $pdo): void
    {
        // Default: no-op. Subclasses implement per-platform read-only.
    }

    /**
     * Set query timeout on the connection.
     * Override in subclasses for driver-specific behavior.
     */
    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        // Default: no-op. Subclasses implement per-platform timeout.
    }

    /**
     * Enforce that a query is read-only (no INSERT, UPDATE, DELETE, DROP, etc.).
     */
    protected function enforceReadOnly(string $sql): void
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $sql)));

        $writePatterns = [
            '/^\s*(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|REPLACE|MERGE|GRANT|REVOKE|EXEC|EXECUTE|CALL)\b/i',
        ];

        foreach ($writePatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new ReadOnlyViolationException(
                    message: 'Write operations are not allowed through Relova connectors',
                    sql: $sql,
                );
            }
        }
    }

    /**
     * Quote an identifier for safe use in SQL queries.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
