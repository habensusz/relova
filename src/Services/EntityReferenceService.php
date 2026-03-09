<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;

/**
 * Manages virtual entity references — creating, resolving,
 * and refreshing display snapshots.
 */
class EntityReferenceService
{
    public function __construct(
        protected RelovaConnectionManager $connectionManager,
    ) {}

    /**
     * Find or create a virtual entity reference for a remote record.
     */
    public function resolve(
        RelovaConnection $connection,
        string $table,
        string $primaryColumn,
        string $primaryValue,
        array $snapshotColumns = [],
    ): RelovaEntityReference {
        $reference = RelovaEntityReference::findOrCreateReference(
            connectionId: $connection->id,
            remoteTable: $table,
            remotePrimaryColumn: $primaryColumn,
            remotePrimaryValue: $primaryValue,
        );

        // Refresh snapshot if stale or empty
        if ($reference->isSnapshotStale() && ! empty($snapshotColumns)) {
            $this->refreshSnapshot($reference, $snapshotColumns);
        }

        return $reference;
    }

    /**
     * Refresh the display snapshot for an entity reference.
     */
    public function refreshSnapshot(
        RelovaEntityReference $reference,
        array $snapshotColumns = [],
    ): void {
        try {
            $connection = $reference->connection;

            if (! $connection || ! $connection->enabled) {
                return;
            }

            $columns = ! empty($snapshotColumns) ? $snapshotColumns : ['*'];
            $colList = implode(', ', $columns);

            $sql = "SELECT {$colList} FROM {$reference->remote_table} WHERE {$reference->remote_primary_column} = ?";

            $rows = $this->connectionManager->query(
                $connection,
                $sql,
                [$reference->remote_primary_value]
            );

            if (! empty($rows)) {
                $reference->refreshSnapshot($rows[0]);
            }
        } catch (\Exception $e) {
            // Snapshot refresh failures are non-critical — log and continue
            \Illuminate\Support\Facades\Log::warning('Relova snapshot refresh failed', [
                'reference_id' => $reference->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk refresh stale snapshots for a connection.
     * Only the remote columns that are explicitly mapped are fetched and stored,
     * so unmapped / foreign-key columns cannot overwrite local relation fields.
     */
    public function refreshStaleSnapshots(RelovaConnection $connection, int $batchSize = 50): int
    {
        $refreshed = 0;

        $staleReferences = RelovaEntityReference::forConnection($connection->id)
            ->stale()
            ->limit($batchSize)
            ->get();

        // Pre-load all field mappings for this connection, keyed by source_table.
        $mappings = RelovaFieldMapping::query()
            ->where('connection_id', $connection->id)
            ->get()
            ->keyBy('source_table');

        foreach ($staleReferences as $reference) {
            $mapping = $mappings->get($reference->remote_table);

            $snapshotColumns = [];
            if ($mapping !== null) {
                $snapshotColumns = collect($mapping->column_mappings ?? [])
                    ->pluck('remote_column')
                    ->filter()
                    ->values()
                    ->all();
            }

            $this->refreshSnapshot($reference, $snapshotColumns);
            $refreshed++;
        }

        return $refreshed;
    }

    /**
     * Search remote entities for the asset picker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchRemote(
        RelovaConnection $connection,
        string $table,
        string $searchColumn,
        string $searchTerm,
        array $displayColumns = [],
        int $limit = 20,
    ): array {
        $driver = $this->connectionManager->connect($connection);

        $cols = ! empty($displayColumns) ? implode(', ', $displayColumns) : '*';

        $sql = "SELECT {$cols} FROM {$table} WHERE {$searchColumn} LIKE ? LIMIT {$limit}";

        return $this->connectionManager->query(
            $connection,
            $sql,
            ['%'.$searchTerm.'%']
        );
    }
}
