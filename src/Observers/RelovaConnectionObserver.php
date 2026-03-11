<?php

declare(strict_types=1);

namespace Relova\Observers;

use App\Models\Machine;
use Illuminate\Support\Facades\Log;
use Relova\Jobs\SyncMappingJob;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;

class RelovaConnectionObserver
{
    public function created(RelovaConnection $connection): void
    {
        if ($connection->enabled) {
            SyncMappingJob::dispatch($connection->id)->onQueue('default');
            Log::info('Relova: connection created enabled — sync dispatched', ['connection_id' => $connection->id, 'name' => $connection->name]);
        }
    }

    public function updated(RelovaConnection $connection): void
    {
        if (! $connection->wasChanged('enabled')) {
            return;
        }

        if ($connection->enabled) {
            SyncMappingJob::dispatch($connection->id)->onQueue('default');
            Log::info('Relova: connection enabled — sync dispatched', ['connection_id' => $connection->id, 'name' => $connection->name]);
        } else {
            $this->purgeConnectionData($connection);
        }
    }

    public function deleting(RelovaConnection $connection): void
    {
        $this->purgeConnectionData($connection);
    }

    private function purgeConnectionData(RelovaConnection $connection): void
    {
        $refUids = RelovaEntityReference::where('connection_id', $connection->id)->pluck('uid');

        if ($refUids->isEmpty()) {
            return;
        }

        $deleted = Machine::whereIn('relova_ref_uid', $refUids)->delete();

        RelovaEntityReference::where('connection_id', $connection->id)->delete();

        Log::info('Relova: connection data purged', [
            'connection_id' => $connection->id,
            'name' => $connection->name,
            'refs_deleted' => $refUids->count(),
            'machines_deleted' => $deleted,
        ]);
    }
}
