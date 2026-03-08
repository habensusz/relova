<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Relova\Contracts\ConnectionManager as ConnectionManagerContract;
use Relova\Contracts\ConnectorDriver;
use Relova\Models\RelovaConnection;

/**
 * Central connection manager. Handles opening connections, caching schema
 * metadata, and running health checks. Connections are opened on demand,
 * used, and closed. No persistent connection pool.
 *
 * When a connection has SSH enabled, all driver operations are automatically
 * wrapped with an SSH port-forward tunnel. The tunnel is established, used
 * for a single operation, and torn down immediately after.
 */
class RelovaConnectionManager implements ConnectionManagerContract
{
    public function __construct(
        protected DriverRegistry $driverRegistry,
        protected SecurityService $securityService,
        protected SshTunnelService $sshTunnelService,
    ) {}

    public function connect(RelovaConnection $connection): ConnectorDriver
    {
        $this->validateConnectionHost($connection);

        return $this->driverRegistry->resolve($connection->driver_type);
    }

    public function test(RelovaConnection $connection): bool
    {
        try {
            $this->validateConnectionHost($connection);

            $driver = $this->driverRegistry->resolve($connection->driver_type);
            $result = $this->withTunnel($connection, fn (array $config) => $driver->testConnection($config));

            $connection->update([
                'health_status' => 'healthy',
                'health_message' => null,
                'last_tested_at' => now(),
                'last_health_check_at' => now(),
            ]);

            return $result;
        } catch (\Exception $e) {
            $connection->update([
                'health_status' => 'unhealthy',
                'health_message' => $e->getMessage(),
                'last_tested_at' => now(),
                'last_health_check_at' => now(),
            ]);

            Log::warning('Relova connection test failed', [
                'connection_id' => $connection->id,
                'driver' => $connection->driver_type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test an unsaved (transient) connection — throws on failure so the caller
     * can surface the error message without any DB writes.
     *
     * @throws \Exception
     */
    public function testUnsaved(RelovaConnection $connection): void
    {
        $this->validateConnectionHost($connection);

        $driver = $this->driverRegistry->resolve($connection->driver_type);
        $this->withTunnel($connection, fn (array $config) => $driver->testConnection($config));
    }

    public function getTables(RelovaConnection $connection): array
    {
        $cacheKey = "relova:tables:{$connection->id}";
        $ttl = $connection->cache_ttl ?? (int) config('relova.schema_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($connection) {
            $driver = $this->driverRegistry->resolve($connection->driver_type);

            return $this->withTunnel($connection, fn (array $config) => $driver->getTables($config));
        });
    }

    public function getColumns(RelovaConnection $connection, string $table): array
    {
        $cacheKey = "relova:columns:{$connection->id}:{$table}";
        $ttl = $connection->cache_ttl ?? (int) config('relova.schema_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($connection, $table) {
            $driver = $this->driverRegistry->resolve($connection->driver_type);

            return $this->withTunnel($connection, fn (array $config) => $driver->getColumns($config, $table));
        });
    }

    public function query(RelovaConnection $connection, string $sql, array $bindings = []): array
    {
        $driver = $this->driverRegistry->resolve($connection->driver_type);

        return $this->withTunnel($connection, fn (array $config) => $driver->query($config, $sql, $bindings));
    }

    public function flushCache(RelovaConnection $connection): void
    {
        // Flush all schema cache keys for this connection
        Cache::forget("relova:tables:{$connection->id}");

        // Flush column caches — we need to know which tables were cached
        $tables = $this->getTables($connection);
        foreach ($tables as $table) {
            Cache::forget("relova:columns:{$connection->id}:{$table['name']}");
        }

        Cache::forget("relova:tables:{$connection->id}");
    }

    public function healthCheck(RelovaConnection $connection): array
    {
        $startTime = microtime(true);

        try {
            $this->validateConnectionHost($connection);

            $driver = $this->driverRegistry->resolve($connection->driver_type);
            $this->withTunnel($connection, fn (array $config) => $driver->testConnection($config));

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $status = $latency > 5000 ? 'degraded' : 'healthy';
            $message = $status === 'degraded'
                ? "Connection latency high: {$latency}ms"
                : null;

            $connection->update([
                'health_status' => $status,
                'health_message' => $message,
                'last_health_check_at' => now(),
            ]);

            return [
                'status' => $status,
                'latency_ms' => $latency,
                'message' => $message,
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            $connection->update([
                'health_status' => 'unhealthy',
                'health_message' => $e->getMessage(),
                'last_health_check_at' => now(),
            ]);

            return [
                'status' => 'unhealthy',
                'latency_ms' => $latency,
                'message' => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Preview data from a remote table.
     */
    public function preview(
        RelovaConnection $connection,
        string $table,
        array $columns = [],
        int $limit = 100,
    ): array {
        $driver = $this->driverRegistry->resolve($connection->driver_type);
        $sql = $driver->buildPreviewQuery($table, $columns, $limit);

        return $this->withTunnel($connection, fn (array $config) => $driver->query($config, $sql));
    }

    /**
     * Validate the reachable host for a connection.
     *
     * When SSH is enabled, the tunnel host is the entry point that must pass
     * SSRF checks — the database host is the remote-side address accessible
     * only through the tunnel and is unreachable directly.
     */
    private function validateConnectionHost(RelovaConnection $connection): void
    {
        if ($connection->ssh_enabled) {
            $sshConfig = $connection->toSshConfig();
            $this->securityService->validateHost($sshConfig['host']);
        } else {
            $this->securityService->validateHost($connection->host);
        }
    }

    /**
     * Execute a callable with the driver config, establishing an SSH tunnel
     * first if the connection has SSH enabled.
     *
     * The callable receives the resolved config array (with host/port overridden
     * to the local tunnel endpoint when SSH is active).
     *
     * @param  callable(array): mixed  $fn
     * @return mixed
     */
    private function withTunnel(RelovaConnection $connection, callable $fn): mixed
    {
        if (! $connection->ssh_enabled) {
            return $fn($connection->toDriverConfig());
        }

        $session = $this->sshTunnelService->establish($connection);

        try {
            $config = array_merge($connection->toDriverConfig(), [
                'host' => '127.0.0.1',
                'port' => $session['localPort'],
            ]);

            return $fn($config);
        } finally {
            $this->sshTunnelService->teardown($session);
        }
    }
}
