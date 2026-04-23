<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Relova\Models\RelovaConnection;
use Relova\Models\VirtualEntityReference;
use Relova\Security\TenantIsolationGuard;
use Relova\Services\QueryExecutor;

/**
 * Daily orphan-detection job.
 *
 * Walks every VirtualEntityReference and verifies the remote primary key
 * still resolves on the upstream source. References that no longer resolve
 * are marked snapshot_status='unavailable' so the host application can
 * degrade gracefully (show the last-known display snapshot with a warning
 * badge, rather than throw on render).
 *
 * Runs daily at 03:00 (low-traffic). 600-second timeout per run.
 */
class ValidateOrphanedReferences implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * Optional tenant scope. If null, the job iterates all tenants — caller
     * is responsible for binding the tenant context per-iteration.
     */
    public function __construct(
        public readonly ?string $tenantId = null,
    ) {
        $this->onQueue(config('relova.sync_queue', 'sync'));
    }

    public function handle(QueryExecutor $executor, TenantIsolationGuard $tenantGuard): void
    {
        if ($this->tenantId === null) {
            return;
        }

        $tenantGuard->runAs($this->tenantId, function () use ($executor): void {
            VirtualEntityReference::query()
                ->where('snapshot_status', '!=', 'unavailable')
                ->chunkById(200, function ($refs) use ($executor): void {
                    foreach ($refs as $ref) {
                        $connection = RelovaConnection::query()->find($ref->connection_id);
                        if (! $connection) {
                            continue;
                        }

                        try {
                            $row = $executor->fetchOne(
                                $connection,
                                $ref->remote_table,
                                $ref->remote_pk_column,
                                $ref->remote_pk_value,
                            );
                        } catch (\Throwable) {
                            // A transient connection failure is not orphan evidence;
                            // skip and let HealthCheckConnector flag the connection.
                            continue;
                        }

                        if ($row === null) {
                            $ref->update(['snapshot_status' => 'unavailable']);
                        }
                    }
                });
        });
    }
}
