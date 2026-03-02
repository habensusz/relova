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
use Relova\Services\EntityReferenceService;

/**
 * Background job to refresh stale display snapshots on entity references.
 */
class RefreshSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $batchSize = 50,
    ) {}

    public function handle(EntityReferenceService $referenceService): void
    {
        $connections = RelovaConnection::enabled()->healthy()->get();

        $totalRefreshed = 0;

        foreach ($connections as $connection) {
            try {
                $refreshed = $referenceService->refreshStaleSnapshots($connection, $this->batchSize);
                $totalRefreshed += $refreshed;
            } catch (\Exception $e) {
                Log::warning('Relova snapshot refresh failed for connection', [
                    'connection_id' => $connection->id,
                    'name' => $connection->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relova snapshot refresh completed', ['total_refreshed' => $totalRefreshed]);
    }
}
