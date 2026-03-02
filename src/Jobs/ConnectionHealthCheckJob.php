<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Relova\Models\RelovaConnection;
use Relova\Services\RelovaConnectionManager;

/**
 * Background job to validate that enabled remote connections are
 * still reachable and healthy.
 */
class ConnectionHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(RelovaConnectionManager $connectionManager): void
    {
        $connections = RelovaConnection::enabled()->get();

        Log::info('Relova health check started', ['connections' => $connections->count()]);

        foreach ($connections as $connection) {
            try {
                $result = $connectionManager->healthCheck($connection);

                if ($result['status'] !== 'healthy') {
                    Log::warning('Relova connection unhealthy', [
                        'connection_id' => $connection->id,
                        'name' => $connection->name,
                        'status' => $result['status'],
                        'message' => $result['message'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Relova health check failed for connection', [
                    'connection_id' => $connection->id,
                    'name' => $connection->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relova health check completed');
    }
}
