<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;

/**
 * MappingDataLoader — loads and applies field mapping data to local record sets.
 *
 * ┌────────────────────────────────────────────────────────────────────────────────────┐
 * │  How it works                                                                      │
 * │                                                                                    │
 * │  1. VIRTUAL (default)                                                              │
 * │     Live query: fetches all matching remote rows in a single batch SELECT, keyed   │
 * │     by the remote join column. Merges mapped values into each local record.        │
 * │     Best for: small-to-medium datasets, infrequent access, real-time accuracy.     │
 * │                                                                                    │
 * │  2. SNAPSHOT                                                                       │
 * │     Reads from relova_entity_references.display_snapshot — no live remote query.  │
 * │     The snapshot was written when the reference was last resolved/refreshed.       │
 * │     Best for: high-frequency reads, resilience against remote downtime.            │
 * │                                                                                    │
 * │  3. ON_DEMAND                                                                      │
 * │     Fetches each record individually, caching per (connection, table, pk_value).   │
 * │     Best for: detail views, low-traffic pages, large remote datasets.              │
 * └────────────────────────────────────────────────────────────────────────────────────┘
 *
 * Relation resolution strategies
 * ───────────────────────────────
 * Each entry in column_mappings can include optional relation metadata:
 *
 *   {
 *     "remote_column":    "ASSET_ID",
 *     "local_field":      "machine_id",
 *     "relation_type":    "belongs_to",     // null = direct value copy
 *     "relation_model":   "Machine",        // App\Models\Machine
 *     "relation_match_by": "uid"            // local model column to match against
 *   }
 *
 * When relation_type = "belongs_to":
 *   The raw remote value is looked up in the local model's relation_match_by column.
 *   If a match is found, the local model's id is written to local_field (the FK).
 *   If no match is found, the raw value is stored in a relova_ref_{field_base} column
 *   (if it exists on the target table) via RelovaEntityReference.
 *
 * This means you can link e.g. remote ERP asset IDs to local Machine records by UID,
 * without requiring the IDs to match — a "soft foreign key" pattern.
 */
class MappingDataLoader
{
    /** Cache TTL for on-demand single-record fetches (seconds). */
    private const ON_DEMAND_TTL = 300;

    public function __construct(
        private readonly RelovaConnectionManager $connectionManager,
    ) {}

    // ─── Public API ──────────────────────────────────────────────────────────────

    /**
     * Enrich a collection of local records with data from a remote source.
     *
     * @param  Collection<int, array<string, mixed>>  $localRecords   Keyed local rows.
     * @param  string  $localJoinKey    Column in each local record used to join to remote.
     * @param  string  $remoteJoinColumn  Remote column that matches the local join values.
     * @return Collection<int, array<string, mixed>>  Same records enriched with remote values.
     */
    public function enrich(
        RelovaFieldMapping $mapping,
        Collection $localRecords,
        string $localJoinKey,
        string $remoteJoinColumn,
    ): Collection {
        if ($localRecords->isEmpty() || ! $mapping->enabled) {
            return $localRecords;
        }

        return match ($mapping->query_mode) {
            'snapshot' => $this->enrichFromSnapshots($mapping, $localRecords, $localJoinKey),
            'on_demand' => $this->enrichOnDemand($mapping, $localRecords, $localJoinKey, $remoteJoinColumn),
            default => $this->enrichBatch($mapping, $localRecords, $localJoinKey, $remoteJoinColumn),
        };
    }

    /**
     * Enrich a single local record.
     * Convenience wrapper around enrich() for detail/show views.
     *
     * @param  array<string, mixed>  $localRecord
     * @return array<string, mixed>
     */
    public function enrichOne(
        RelovaFieldMapping $mapping,
        array $localRecord,
        string $localJoinKey,
        string $remoteJoinColumn,
    ): array {
        return $this->enrich(
            $mapping,
            collect([$localRecord]),
            $localJoinKey,
            $remoteJoinColumn,
        )->first() ?? $localRecord;
    }

    /**
     * Get all required local fields that are NOT yet covered by this mapping's
     * column_mappings entries. Useful for UI validation.
     *
     * @param  array<int, array{name: string, required: bool}>  $localColumns  From HostSchemaService.
     * @return array<int, string>
     */
    public function getUncoveredRequiredFields(
        RelovaFieldMapping $mapping,
        array $localColumns,
    ): array {
        $mappedFields = collect($mapping->column_mappings ?? [])->pluck('local_field')->filter()->all();

        return collect($localColumns)
            ->filter(fn (array $col) => $col['required'] ?? false)
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, $mappedFields, true))
            ->values()
            ->all();
    }

    // ─── Private: loading strategies ─────────────────────────────────────────────

    /**
     * VIRTUAL: single batch SELECT from remote, join locally in PHP.
     *
     * @param  Collection<int, array<string, mixed>>  $localRecords
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichBatch(
        RelovaFieldMapping $mapping,
        Collection $localRecords,
        string $localJoinKey,
        string $remoteJoinColumn,
    ): Collection {
        $joinValues = $localRecords
            ->pluck($localJoinKey)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($joinValues)) {
            return $localRecords;
        }

        $remoteColumns = collect($mapping->column_mappings ?? [])
            ->pluck('remote_column')
            ->filter()
            ->push($remoteJoinColumn)
            ->unique()
            ->values()
            ->all();

        $placeholders = implode(',', array_fill(0, count($joinValues), '?'));
        $colList = implode(', ', array_map(fn (string $c) => '"'.$c.'"', $remoteColumns));

        $sql = "SELECT {$colList} FROM \"{$mapping->source_table}\"
                WHERE \"{$remoteJoinColumn}\" IN ({$placeholders})";

        $remoteRows = $this->connectionManager->query($mapping->connection, $sql, $joinValues);

        // Index remote rows by join column value for O(1) local merge.
        $remoteIndex = collect($remoteRows)->keyBy(fn (array $r) => $r[$remoteJoinColumn] ?? null);

        return $localRecords->map(function (array $local) use (
            $localJoinKey,
            $remoteIndex,
            $mapping,
        ): array {
            $joinValue = $local[$localJoinKey] ?? null;
            $remoteRow = $joinValue !== null ? ($remoteIndex->get((string) $joinValue) ?? []) : [];

            return empty($remoteRow)
                ? $local
                : $this->applyMappedRow($mapping, $local, $remoteRow);
        });
    }

    /**
     * SNAPSHOT: read from relova_entity_references.display_snapshot — no live query.
     *
     * @param  Collection<int, array<string, mixed>>  $localRecords
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichFromSnapshots(
        RelovaFieldMapping $mapping,
        Collection $localRecords,
        string $localJoinKey,
    ): Collection {
        $joinValues = $localRecords->pluck($localJoinKey)->filter()->unique()->values()->all();

        if (empty($joinValues)) {
            return $localRecords;
        }

        $references = RelovaEntityReference::where('connection_id', $mapping->connection_id)
            ->where('remote_table', $mapping->source_table)
            ->whereIn('remote_primary_value', $joinValues)
            ->get()
            ->keyBy('remote_primary_value');

        return $localRecords->map(function (array $local) use ($localJoinKey, $references, $mapping): array {
            $joinValue = (string) ($local[$localJoinKey] ?? '');
            /** @var RelovaEntityReference|null $ref */
            $ref = $references->get($joinValue);

            if ($ref === null || empty($ref->display_snapshot)) {
                return $local;
            }

            return $this->applyMappedRow($mapping, $local, $ref->display_snapshot);
        });
    }

    /**
     * ON_DEMAND: fetch + cache each record individually.
     *
     * @param  Collection<int, array<string, mixed>>  $localRecords
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichOnDemand(
        RelovaFieldMapping $mapping,
        Collection $localRecords,
        string $localJoinKey,
        string $remoteJoinColumn,
    ): Collection {
        $remoteColumns = collect($mapping->column_mappings ?? [])
            ->pluck('remote_column')
            ->filter()
            ->push($remoteJoinColumn)
            ->unique()
            ->values()
            ->all();

        $colList = implode(', ', array_map(fn (string $c) => '"'.$c.'"', $remoteColumns));

        return $localRecords->map(function (array $local) use (
            $localJoinKey,
            $remoteJoinColumn,
            $colList,
            $mapping,
        ): array {
            $joinValue = $local[$localJoinKey] ?? null;

            if ($joinValue === null) {
                return $local;
            }

            $cacheKey = "relova:on_demand:{$mapping->connection_id}:{$mapping->source_table}:{$remoteJoinColumn}:{$joinValue}";

            $remoteRow = Cache::remember($cacheKey, self::ON_DEMAND_TTL, function () use (
                $mapping,
                $colList,
                $remoteJoinColumn,
                $joinValue,
            ): ?array {
                $sql = "SELECT {$colList} FROM \"{$mapping->source_table}\"
                        WHERE \"{$remoteJoinColumn}\" = ?
                        LIMIT 1";

                $rows = $this->connectionManager->query($mapping->connection, $sql, [$joinValue]);

                return $rows[0] ?? null;
            });

            return $remoteRow
                ? $this->applyMappedRow($mapping, $local, $remoteRow)
                : $local;
        });
    }

    // ─── Private: apply + resolve ─────────────────────────────────────────────────

    /**
     * Apply a remote row to a local record using the mapping's column_mappings.
     * Handles direct value copy and belongs_to relation resolution.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remoteRow
     * @return array<string, mixed>
     */
    private function applyMappedRow(
        RelovaFieldMapping $mapping,
        array $local,
        array $remoteRow,
    ): array {
        foreach ($mapping->column_mappings as $entry) {
            $remoteCol = $entry['remote_column'] ?? null;
            $localField = $entry['local_field'] ?? null;

            if (! $remoteCol || ! $localField) {
                continue;
            }

            if (! array_key_exists($remoteCol, $remoteRow)) {
                continue;
            }

            $rawValue = $remoteRow[$remoteCol];
            $relationType = $entry['relation_type'] ?? null;

            if ($relationType === 'belongs_to') {
                $resolved = $this->resolveBelongsTo($entry, $rawValue, $mapping->connection_id);
                if ($resolved !== null) {
                    $local[$localField] = $resolved;
                } else {
                    // Store the raw remote key in a _relova_ref side-channel column if it exists
                    $sideChannel = 'relova_ref_'.rtrim($localField, '_id');
                    $local[$sideChannel] = $rawValue;
                }
            } else {
                // Direct value — apply any transformation rules from the mapping
                $local[$localField] = $mapping->applyToRow([$remoteCol => $rawValue])[$localField] ?? $rawValue;
            }
        }

        return $local;
    }

    /**
     * Resolve a belongs_to relation by looking up the local model by the remote value.
     *
     * Tries:
     *   1. Look up App\Models\{relation_model} where {relation_match_by} = $remoteValue
     *   2. Fall back to a DB query if the model class doesn't exist
     *
     * @return int|null  Local model id if resolved, null otherwise.
     */
    private function resolveBelongsTo(array $entry, mixed $remoteValue, int $connectionId): ?int
    {
        $modelClass = 'App\\Models\\'.($entry['relation_model'] ?? '');
        $matchBy = $entry['relation_match_by'] ?? 'uid';
        $table = $entry['relation_table'] ?? null;

        if (class_exists($modelClass)) {
            $id = $modelClass::where($matchBy, $remoteValue)->value('id');

            return $id !== null ? (int) $id : null;
        }

        if ($table) {
            $id = DB::table($table)->where($matchBy, $remoteValue)->value('id');

            return $id !== null ? (int) $id : null;
        }

        return null;
    }
}
