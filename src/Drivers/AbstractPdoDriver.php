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
 * Base class for PDO-based relational drivers.
 *
 * Guarantees:
 *   - Queries are streamed via \Generator — no fetchAll on large result sets.
 *   - Read-only is enforced at SQL parse time AND transaction level.
 *   - Connect timeout is respected via PDO::ATTR_TIMEOUT.
 *   - Query timeout is enforced per dialect where possible.
 *   - The driver holds no state between calls.
 */
abstract class AbstractPdoDriver implements ConnectorDriver
{
    abstract protected function buildDsn(array $config): string;

    abstract protected function getTablesQuery(array $config): string;

    abstract protected function getColumnsQuery(string $table, array $config): string;

    /**
     * @param  array<int, array<string, mixed>>  $rawColumns
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, primary: bool, length: ?int}>
     */
    abstract protected function normalizeColumns(array $rawColumns): array;

    /**
     * @param  array<int, array<string, mixed>>  $rawTables
     * @return array<int, array{name: string, schema: ?string, type: string, row_count: ?int}>
     */
    abstract protected function normalizeTables(array $rawTables): array;

    public function testConnection(array $config): bool
    {
        try {
            $this->createPdo($config);

            return true;
        } catch (\Throwable $e) {
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
        $stmt = $pdo->query($this->getTablesQuery($config));
        $raw = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->normalizeTables($raw);
    }

    public function getColumns(array $config, string $table): array
    {
        $pdo = $this->createPdo($config);
        $stmt = $pdo->prepare($this->getColumnsQuery($table, $config));
        $stmt->execute();
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->normalizeColumns($raw);
    }

    /**
     * Stream rows from the remote source one at a time via \Generator.
     */
    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator
    {
        $this->enforceReadOnly($sql);

        $pdo = $this->createPdo($config);
        $timeout = (int) ($options['timeout'] ?? $config['timeout'] ?? config('relova.query_timeout', 30));
        $maxRows = (int) ($options['max_rows'] ?? config('relova.max_rows_per_query', 10000));

        try {
            $this->setQueryTimeout($pdo, $timeout);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            $yielded = 0;
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $row;
                $yielded++;
                if ($yielded >= $maxRows) {
                    break;
                }
            }
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
     * @return array<int, mixed>
     */
    protected function getPdoConstructorOptions(int $connectTimeout): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $connectTimeout,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    protected function createPdo(array $config): PDO
    {
        $dsn = $this->buildDsn($config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $connectTimeout = (int) ($config['timeout'] ?? config('relova.connection_timeout', 10));

        try {
            $pdo = new PDO($dsn, $username, $password, $this->getPdoConstructorOptions($connectTimeout));
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

    protected function setReadOnly(PDO $pdo): void
    {
        // Subclasses override with dialect-specific read-only enforcement.
    }

    protected function setQueryTimeout(PDO $pdo, int $seconds): void
    {
        // Subclasses override with dialect-specific timeout enforcement.
    }

    /**
     * Reject any SQL that attempts to mutate the remote source.
     */
    protected function enforceReadOnly(string $sql): void
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|REPLACE|MERGE|GRANT|REVOKE|EXEC|EXECUTE|CALL)\b/i', $sql)) {
            throw new ReadOnlyViolationException(
                message: 'Write operations are not allowed through Relova connectors.',
                sql: $sql,
            );
        }
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
