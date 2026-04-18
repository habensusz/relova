<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Carbon;
use Relova\Models\VirtualEntityReference;

/**
 * Display-data resolver for virtual entity references.
 *
 * Decision tree:
 *   1. Snapshot fresh    → serve snapshot (local read, ~1ms).
 *   2. Snapshot stale
 *        connection up   → fetch live, update snapshot, return live.
 *        connection down → return stale snapshot + warning (never throws).
 *
 * This class NEVER throws on remote failure. It degrades gracefully to
 * the last-known snapshot so that consumer UIs keep functioning even
 * when the remote source is unreachable.
 */
class SnapshotManager
{
    public function __construct(
        private QueryExecutor $executor,
    ) {}

    /**
     * Resolve display data for a reference.
     *
     * @param  array<int, string>  $displayFields
     * @return array{data: array<string, mixed>, source: string, taken_at: Carbon|null, warning?: string}
     */
    public function resolve(VirtualEntityReference $reference, array $displayFields): array
    {
        if ($this->isFresh($reference)) {
            return [
                'data' => $reference->display_snapshot ?? [],
                'source' => 'snapshot',
                'taken_at' => $reference->snapshot_taken_at,
            ];
        }

        try {
            $live = $this->fetchLive($reference, $displayFields);
            $this->updateSnapshot($reference, $live);

            return [
                'data' => $live,
                'source' => 'live',
                'taken_at' => $reference->snapshot_taken_at,
            ];
        } catch (\Throwable $e) {
            $reference->forceFill(['snapshot_status' => 'unavailable'])->save();

            return [
                'data' => $reference->display_snapshot ?? [],
                'source' => 'stale_snapshot',
                'taken_at' => $reference->snapshot_taken_at,
                'warning' => 'Remote source unavailable. Showing last known data.',
            ];
        }
    }

    /**
     * Force-refresh a reference's snapshot from the live source.
     * Used by the RefreshSnapshot job.
     *
     * @param  array<int, string>  $displayFields
     */
    public function refresh(VirtualEntityReference $reference, array $displayFields): void
    {
        try {
            $live = $this->fetchLive($reference, $displayFields);
            $this->updateSnapshot($reference, $live);
        } catch (\Throwable) {
            $reference->forceFill(['snapshot_status' => 'unavailable'])->save();
        }
    }

    /**
     * @param  array<int, string>  $displayFields
     * @return array<string, mixed>
     */
    private function fetchLive(VirtualEntityReference $reference, array $displayFields): array
    {
        $connection = $reference->connection;
        if ($connection === null) {
            throw new \RuntimeException('Virtual entity reference has no associated connection.');
        }

        $columns = $displayFields === [] ? ['*'] : $displayFields;

        $row = $this->executor->fetchOne(
            connection: $connection,
            table: $reference->remote_table,
            pkColumn: $reference->remote_pk_column,
            pkValue: (string) $reference->remote_pk_value,
            columns: $columns,
        );

        if ($row === null) {
            throw new \RuntimeException('Remote entity no longer exists.');
        }

        if ($displayFields === []) {
            return $row;
        }

        return array_intersect_key($row, array_flip($displayFields));
    }

    private function updateSnapshot(VirtualEntityReference $reference, array $data): void
    {
        $reference->forceFill([
            'display_snapshot' => $data,
            'snapshot_taken_at' => now(),
            'snapshot_status' => 'fresh',
        ])->save();
    }

    private function isFresh(VirtualEntityReference $reference): bool
    {
        if ($reference->snapshot_status !== 'fresh') {
            return false;
        }

        $takenAt = $reference->snapshot_taken_at;
        if (! $takenAt instanceof Carbon) {
            return false;
        }

        $ttlMinutes = (int) config('relova.snapshot_fresh_minutes', 30);

        return $takenAt->gt(now()->subMinutes($ttlMinutes));
    }
}
