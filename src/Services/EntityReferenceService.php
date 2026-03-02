<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;

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
     */
    public function refreshStaleSnapshots(RelovaConnection $connection, int $batchSize = 50): int
    {
        $refreshed = 0;

        $staleReferences = RelovaEntityReference::forConnection($connection->id)
            ->stale()
            ->limit($batchSize)
            ->get();

        foreach ($staleReferences as $reference) {
            $this->refreshSnapshot($reference);
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
