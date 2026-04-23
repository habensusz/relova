<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Relova\Models\RelovaConnection;
use Relova\Services\ConnectionRegistry;
use Relova\Services\DriverRegistry;

/**
 * Periodic connector health check.
 *
 * Schedule per connection (e.g. every 15 minutes). Updates
 * RelovaConnection.status and last_error.
 */
class HealthCheckConnector implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 15;

    public int $tries = 1;

    public function __construct(public string $connectionId) {}

    public function handle(DriverRegistry $drivers, ConnectionRegistry $connections): void
    {
        $connection = RelovaConnection::find($this->connectionId);
        if ($connection === null) {
            return;
        }

        try {
            $connections->assertHostAllowed($connection);

            $driver = $drivers->resolve($connection->driver);

            $connections->withTunnel($connection, function (array $config) use ($driver) {
                $driver->testConnection($config);
            });

            $connections->markHealthy($connection);
        } catch (\Throwable $e) {
            $status = str_contains(strtolower($e->getMessage()), 'host') ? 'unreachable' : 'error';
            $connections->markError($connection, $e->getMessage(), $status);
        }
    }
}
