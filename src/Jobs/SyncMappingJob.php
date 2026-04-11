<?php

declare(strict_types=1);

namespace Relova\Jobs;

use App\Contracts\Relova\MachineShadowResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;
use Relova\Services\RelovaConnectionManager;

/**
 * Fetches all rows from every active mapping's remote source table,
 * upserts RelovaEntityReference snapshots, and creates/updates local
 * Machine shadow rows via MachineShadowResolver.
 *
 * Dispatched whenever a connection or mapping is enabled.
 */
class SyncMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  int|null  $connectionId  When set, only sync mappings for this connection.
     * @param  int|null  $mappingId  When set, only sync this specific mapping.
     */
    public function __construct(
        public readonly ?int $connectionId = null,
        public readonly ?int $mappingId = null,
    ) {}

    public function handle(
        RelovaConnectionManager $connectionManager,
        MachineShadowResolver $shadowService,
    ): void {
        $mappings = $this->resolveMappings();

        foreach ($mappings as $mapping) {
            $this->syncMapping($mapping, $connectionManager, $shadowService);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, RelovaFieldMapping>
     */
    private function resolveMappings(): \Illuminate\Database\Eloquent\Collection
    {
        $query = RelovaFieldMapping::with('connection')
            ->where('enabled', true)
            ->whereHas('connection', fn ($q) => $q->where('enabled', true));

        if ($this->mappingId) {
            $query->where('id', $this->mappingId);
        } elseif ($this->connectionId) {
            $query->where('connection_id', $this->connectionId);
        }

        return $query->get();
    }

    private function syncMapping(
        RelovaFieldMapping $mapping,
        RelovaConnectionManager $connectionManager,
        MachineShadowResolver $shadowService,
    ): void {
        $connection = $mapping->connection;

        if (! $connection || ! $connection->enabled) {
            return;
        }

        try {
            $timestampColumn = $mapping->timestamp_column ?? null;
            $lastSyncedAt = $mapping->last_synced_at;

            // Option 2: Change-detection probe.
            // When a timestamp column and a previous sync timestamp are both available,
            // ask the source for the most recently modified row before pulling any data.
            // If nothing is newer than our last sync, skip the full query entirely.
            if ($timestampColumn && $lastSyncedAt) {
                $probeSql = 'SELECT MAX("'.$timestampColumn.'") AS max_ts FROM "'.$mapping->source_table.'"';
                $probeResult = $connectionManager->query($connection, $probeSql, []);
                $maxTs = $probeResult[0]['max_ts'] ?? null;

                if ($maxTs !== null && $lastSyncedAt->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($maxTs))) {
                    Log::info('Relova: no changes detected, skipping sync', [
                        'mapping_id' => $mapping->id,
                        'source_table' => $mapping->source_table,
                        'last_synced_at' => $lastSyncedAt->toDateTimeString(),
                        'max_ts' => $maxTs,
                    ]);

                    return;
                }
            }

            // Option 3: Incremental sync.
            // When we have both a timestamp column and a prior sync time, only fetch
            // rows that changed after the last sync instead of the entire table.
            if ($timestampColumn && $lastSyncedAt) {
                $sql = 'SELECT * FROM "'.$mapping->source_table.'" WHERE "'.$timestampColumn.'" > ?';
                $bindings = [$lastSyncedAt->toDateTimeString()];
            } else {
                $sql = 'SELECT * FROM "'.$mapping->source_table.'"';
                $bindings = [];
            }

            $rows = $connectionManager->query($connection, $sql, $bindings);

            $synced = 0;
            $created = 0;

            foreach ($rows as $row) {
                $ref = $this->upsertEntityReference($connection, $mapping, $row);

                // Only sync machines if target module is machines
                if ($mapping->target_module === 'machines') {
                    $machine = $shadowService->resolveOrCreate($ref);
                    if ($machine) {
                        $created++;
                    }
                }

                $synced++;
            }

            $mapping->update(['last_synced_at' => now()]);

            Log::info('Relova: mapping sync completed', [
                'mapping_id' => $mapping->id,
                'mapping_name' => $mapping->name,
                'source_table' => $mapping->source_table,
                'rows_synced' => $synced,
                'machines_created_or_updated' => $created,
                'incremental' => $timestampColumn && $lastSyncedAt,
            ]);
        } catch (\Exception $e) {
            Log::warning('Relova: mapping sync failed', [
                'mapping_id' => $mapping->id,
                'mapping_name' => $mapping->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function upsertEntityReference(
        RelovaConnection $connection,
        RelovaFieldMapping $mapping,
        array $row,
    ): RelovaEntityReference {
        // Prefer the row's own 'id' as a stable, collision-free identifier.
        // Hashing the full row is unsafe for incremental sync because the
        // timestamp column changes on every update, producing a different hash
        // and causing duplicates instead of updates.
        if (isset($row['id'])) {
            $primaryColumn = 'id';
            $primaryValue = (string) $row['id'];
        } else {
            // Exclude the configured timestamp column from the hash so the
            // identifier stays stable between full and incremental syncs.
            $stable = $row;
            if ($mapping->timestamp_column && isset($stable[$mapping->timestamp_column])) {
                unset($stable[$mapping->timestamp_column]);
            }
            $primaryColumn = '_row_hash';
            $primaryValue = md5(serialize($stable));
        }

        $mappedSnapshot = $mapping->applyToRow($row);

        return RelovaEntityReference::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'remote_table' => $mapping->source_table,
                'remote_primary_column' => $primaryColumn,
                'remote_primary_value' => $primaryValue,
            ],
            [
                'display_snapshot' => array_merge($row, $mappedSnapshot),
                'snapshot_refreshed_at' => now(),
            ],
        );
    }
}
