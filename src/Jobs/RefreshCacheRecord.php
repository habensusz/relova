<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Relova\Cache\TwoZoneCache;
use Relova\Models\RelovaConnection;
use Relova\Security\TenantIsolationGuard;
use Relova\Services\QueryExecutor;

/**
 * Refresh a single cache record by re-fetching it from the remote source.
 *
 * Triggered by CacheManager when a record is read with sync_status='stale'
 * or by ad-hoc refresh requests. Writes the fresh row to both Zone B and
 * Zone A. 30-second timeout.
 */
class RefreshCacheRecord implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $connectionId,
        public readonly string $remoteTable,
        public readonly string $pkColumn,
        public readonly string $pkValue,
    ) {
        $this->onQueue(config('relova.sync_queue', 'sync'));
    }

    public function handle(
        QueryExecutor $executor,
        TwoZoneCache $cache,
        TenantIsolationGuard $tenantGuard,
    ): void {
        $tenantGuard->runAs($this->tenantId, function () use ($executor, $cache) {
            $connection = RelovaConnection::query()->findOrFail($this->connectionId);

            $row = $executor->fetchOne($connection, $this->remoteTable, $this->pkColumn, $this->pkValue);

            if ($row !== null) {
                $cache->put(
                    $this->tenantId,
                    $this->connectionId,
                    $this->remoteTable,
                    $this->pkValue,
                    $row,
                );
            } else {
                $cache->forget(
                    $this->tenantId,
                    $this->connectionId,
                    $this->remoteTable,
                    $this->pkValue,
                );
            }
        });
    }
}
