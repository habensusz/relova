<?php

declare(strict_types=1);

namespace Relova\Observers;

use App\Models\Machine;
use Illuminate\Support\Facades\Log;
use Relova\Jobs\SyncMappingJob;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;

class RelovaFieldMappingObserver
{
    public function created(RelovaFieldMapping $mapping): void
    {
        if ($mapping->enabled) {
            SyncMappingJob::dispatch(null, $mapping->id)->onQueue('default');
            Log::info('Relova: field mapping created enabled — sync dispatched', ['mapping_id' => $mapping->id]);
        }
    }

    public function updated(RelovaFieldMapping $mapping): void
    {
        if (! $mapping->enabled) {
            return;
        }

        // Do not re-trigger a sync when the sync job itself writes last_synced_at.
        $ignoredFields = ['last_synced_at', 'updated_at'];
        $meaningfulChange = ! empty(array_diff(array_keys($mapping->getChanges()), $ignoredFields));

        if ($meaningfulChange) {
            SyncMappingJob::dispatch(null, $mapping->id)->onQueue('default');
            Log::info('Relova: field mapping updated — sync dispatched', ['mapping_id' => $mapping->id]);
        }
    }

    public function deleting(RelovaFieldMapping $mapping): void
    {
        $this->purgeMappingData($mapping);
    }

    private function purgeMappingData(RelovaFieldMapping $mapping): void
    {
        $r = RelovaEntityReference::where('connection_id', $mapping->connection_id)
            ->where('remote_table', $mapping->source_table)
            ->pluck('uid');

        if ($r->isEmpty()) {
            return;
        }

        $d = Machine::whereIn('relova_ref_uid', $r)->delete();

        RelovaEntityReference::where('connection_id', $mapping->connection_id)
            ->where('remote_table', $mapping->source_table)
            ->delete();

        Log::info('Relova: field mapping deleted — data purged', [
            'mapping_id' => $mapping->id,
            'source_table' => $mapping->source_table,
            'refs_deleted' => $r->count(),
            'machines_deleted' => $d,
        ]);
    }
}
