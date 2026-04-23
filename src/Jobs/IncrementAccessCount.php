<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Increment the in-Redis access counter for a cached record.
 *
 * Used purely for hotness scoring (which records get pre-warmed by the next
 * scheduled sync). Stored in Redis with a 24h TTL — counters automatically
 * decay so unused records lose priority.
 *
 * Queued on 'relova-stats' — never blocks user requests.
 */
class IncrementAccessCount implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $connectionId,
        public readonly string $remoteTable,
        public readonly string $pkValue,
    ) {
        $this->onQueue(config('relova.stats_queue', 'sync'));
    }

    public function handle(): void
    {
        $key = "relova:stats:{$this->tenantId}:{$this->connectionId}:{$this->remoteTable}:{$this->pkValue}";
        $store = Cache::store(config('relova.persistent_cache', 'array'));

        $store->increment($key, 1);
        // Re-touch the TTL so active records keep their score; cold records expire.
        $existing = (int) $store->get($key, 0);
        $store->put($key, $existing, 86400);
    }
}
