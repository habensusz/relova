<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;
use Relova\Models\VirtualEntityReference;

/**
 * Resolves or creates VirtualEntityReference pointers.
 *
 * The host application stores the reference UUID as its foreign key —
 * never the remote primary key. One reference row per unique remote
 * entity per tenant.
 */
class ReferenceResolver
{
    public function __construct(
        private QueryExecutor $executor,
    ) {}

    /**
     * Resolve (or create) the virtual reference for a remote entity.
     *
     * @param  array<int, string>  $displayFields
     * @param  array<string, mixed>  $displayData  Snapshot data to seed the reference with.
     */
    public function resolveOrCreate(
        string $tenantId,
        RelovaConnection $connection,
        string $remoteTable,
        string $remotePkColumn,
        string $remotePkValue,
        array $displayFields,
        array $displayData,
    ): VirtualEntityReference {
        $filteredSnapshot = $displayFields === []
            ? $displayData
            : array_intersect_key($displayData, array_flip($displayFields));

        $reference = VirtualEntityReference::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'connection_id' => $connection->id,
                'remote_table' => $remoteTable,
                'remote_pk_column' => $remotePkColumn,
                'remote_pk_value' => $remotePkValue,
            ],
            [
                'display_snapshot' => $filteredSnapshot,
                'snapshot_taken_at' => now(),
                'snapshot_status' => 'fresh',
            ],
        );

        if (! $reference->wasRecentlyCreated && $filteredSnapshot !== []) {
            $reference->forceFill([
                'display_snapshot' => $filteredSnapshot,
                'snapshot_taken_at' => now(),
                'snapshot_status' => 'fresh',
            ])->save();
        }

        return $reference;
    }

    /**
     * Validate that a set of reference IDs still resolve to live remote entities.
     * Orphaned references are marked as 'unavailable'; the method returns the orphans.
     *
     * @param  array<int, string>  $referenceIds
     * @return array<int, string> IDs of references whose remote entity no longer exists.
     */
    public function validate(array $referenceIds): array
    {
        $orphans = [];

        foreach ($referenceIds as $id) {
            $reference = VirtualEntityReference::find($id);
            if ($reference === null) {
                continue;
            }

            $connection = $reference->connection;
            if ($connection === null) {
                continue;
            }

            try {
                $row = $this->executor->fetchOne(
                    connection: $connection,
                    table: $reference->remote_table,
                    pkColumn: $reference->remote_pk_column,
                    pkValue: (string) $reference->remote_pk_value,
                    columns: [$reference->remote_pk_column],
                );

                if ($row === null) {
                    $orphans[] = (string) $reference->id;
                    $reference->forceFill(['snapshot_status' => 'unavailable'])->save();
                }
            } catch (\Throwable) {
                // Connection unreachable — mark unavailable but do not declare orphan.
                $reference->forceFill(['snapshot_status' => 'unavailable'])->save();
            }
        }

        return $orphans;
    }
}
