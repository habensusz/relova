<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Data\SyncResult;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\VirtualEntityReference;

/**
 * Remote-row indexer.
 *
 * Streams all rows from the remote source for a given mapping and upserts
 * one VirtualEntityReference per row. This is the only write this service
 * performs — it never touches host-app model tables.
 *
 * VirtualEntityProxy (read-through) and VirtualEntityResolver (routing)
 * handle the consumer-side data access once rows are indexed here.
 */
class ShadowSyncService
{
    public function __construct(
        private QueryExecutor $queryExecutor,
        private ?TriggerRuleEngine $triggerEngine = null,
    ) {}

    public function syncMapping(ConnectorModuleMapping $mapping): SyncResult
    {
        $connection = $mapping->connection()->firstOrFail();
        $pkColumn = $mapping->remote_pk_column ?: 'id';
        $joins = $mapping->joins ?? [];

        // Build static-filter conditions from the mapping.
        $conditions = collect($mapping->filters ?? [])
            ->map(fn ($v, $k) => [$k, '=', $v])
            ->values()
            ->all();

        $rows = $this->queryExecutor->executePassThrough(
            connection: $connection,
            table: $mapping->remote_table,
            conditions: $conditions,
            columns: $this->resolveSnapshotColumns($mapping),
            limit: (int) config('relova.max_rows_per_query', 10000),
            joins: $joins,
        );

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        foreach ($rows as $row) {
            $remotePkValue = (string) ($row[$pkColumn] ?? '');

            if ($remotePkValue === '') {
                $skipped++;

                continue;
            }

            // Enrich the row with field-mapped aliases so the proxy's __get()
            // hits on the first lookup without falling through to fetchLive().
            // e.g. field_mappings = {machine_name: name} adds machine_name key.
            $enrichedRow = $row;
            foreach ($mapping->fieldMap() as $localCol => $remoteCol) {
                if (! array_key_exists($localCol, $enrichedRow) && array_key_exists($remoteCol, $enrichedRow)) {
                    $enrichedRow[$localCol] = $enrichedRow[$remoteCol];
                }
            }

            try {
                $existing = VirtualEntityReference::where([
                    'tenant_id' => $mapping->tenant_id,
                    'connection_id' => $connection->id,
                    'remote_table' => $mapping->remote_table,
                    'remote_pk_column' => $pkColumn,
                    'remote_pk_value' => $remotePkValue,
                ])->first();

                if ($existing) {
                    $previousSnapshot = $existing->display_snapshot ?? [];
                    $existing->update([
                        'mapping_id' => $mapping->id,
                        'display_snapshot' => $enrichedRow,
                        'snapshot_taken_at' => now(),
                        'snapshot_status' => 'fresh',
                    ]);
                    $updated++;

                    if ($this->triggerEngine !== null) {
                        $this->triggerEngine->evaluate($existing->fresh(), $previousSnapshot, $enrichedRow);
                    }
                } else {
                    $newRef = VirtualEntityReference::create([
                        'tenant_id' => $mapping->tenant_id,
                        'connection_id' => $connection->id,
                        'mapping_id' => $mapping->id,
                        'remote_table' => $mapping->remote_table,
                        'remote_pk_column' => $pkColumn,
                        'remote_pk_value' => $remotePkValue,
                        'display_snapshot' => $enrichedRow,
                        'snapshot_taken_at' => now(),
                        'snapshot_status' => 'fresh',
                    ]);
                    $created++;

                    if ($this->triggerEngine !== null) {
                        // First sync — empty previous snapshot. Rules using
                        // operators like 'changed' won't fire (oldValue===newValue===null
                        // for missing fields), but threshold rules will.
                        $this->triggerEngine->evaluate($newRef, [], $enrichedRow);
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                $errorMessages[] = "pk={$remotePkValue}: ".$e->getMessage();
            }
        }

        $total = $created + $updated + $skipped + $errors;
        $parts = array_filter([
            $created ? "{$created} indexed" : null,
            $updated ? "{$updated} refreshed" : null,
            $skipped ? "{$skipped} skipped" : null,
            $errors ? "{$errors} errors" : null,
        ]);
        $msg = 'Index complete: '.implode(', ', $parts)." (of {$total} remote rows).";

        if ($errorMessages) {
            $msg .= ' First error: '.$errorMessages[0];
        }

        return new SyncResult(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
            message: $msg,
        );
    }

    private function resolveSnapshotColumns(ConnectorModuleMapping $mapping): array
    {
        return $mapping->snapshotColumns();
    }
}
