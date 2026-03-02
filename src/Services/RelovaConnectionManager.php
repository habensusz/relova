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
 */
class RelovaConnectionManager implements ConnectionManagerContract
{
    public function __construct(
        protected DriverRegistry $driverRegistry,
        protected SecurityService $securityService,
    ) {}

    public function connect(RelovaConnection $connection): ConnectorDriver
    {
        $this->securityService->validateHost($connection->host);

        return $this->driverRegistry->resolve($connection->driver_type);
    }

    public function test(RelovaConnection $connection): bool
    {
        try {
            $this->securityService->validateHost($connection->host);

            $driver = $this->driverRegistry->resolve($connection->driver_type);
            $result = $driver->testConnection($connection->toDriverConfig());

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

    public function getTables(RelovaConnection $connection): array
    {
        $cacheKey = "relova:tables:{$connection->id}";
        $ttl = $connection->cache_ttl ?? (int) config('relova.schema_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($connection) {
            $driver = $this->connect($connection);

            return $driver->getTables($connection->toDriverConfig());
        });
    }

    public function getColumns(RelovaConnection $connection, string $table): array
    {
        $cacheKey = "relova:columns:{$connection->id}:{$table}";
        $ttl = $connection->cache_ttl ?? (int) config('relova.schema_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($connection, $table) {
            $driver = $this->connect($connection);

            return $driver->getColumns($connection->toDriverConfig(), $table);
        });
    }

    public function query(RelovaConnection $connection, string $sql, array $bindings = []): array
    {
        $driver = $this->connect($connection);

        return $driver->query($connection->toDriverConfig(), $sql, $bindings);
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
            $this->securityService->validateHost($connection->host);

            $driver = $this->driverRegistry->resolve($connection->driver_type);
            $driver->testConnection($connection->toDriverConfig());

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
        $driver = $this->connect($connection);
        $sql = $driver->buildPreviewQuery($table, $columns, $limit);

        return $driver->query($connection->toDriverConfig(), $sql);
    }
}
