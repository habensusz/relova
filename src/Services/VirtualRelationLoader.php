<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Relova\Concerns\HasRelovaData;
use Relova\Data\RelovaRow;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;

/**
 * Loads remote-mapped data onto Eloquent model instances.
 *
 * This is the host-facing entry point for Relova virtual data loading.
 * Given a collection of Eloquent models that use the HasRelovaData trait,
 * it automatically fetches the matching remote rows through the configured
 * RelovaFieldMapping and merges them onto each model instance with caching.
 *
 * Usage (controller / service / Livewire):
 *
 *   $machines = Machine::all();
 *   app(VirtualRelationLoader::class)->enrichModels(
 *       models: $machines,
 *       localJoinKey: 'erp_id',        // local column that holds the remote PK
 *       remoteJoinColumn: 'ASSET_ID',  // remote column it maps to
 *   );
 *
 *   // Now each $machine has remote data merged:
 *   $machine->machine_name;           // direct-mapped attribute (if mapping configured)
 *   $machine->relovaValue('COST');    // raw remote column value
 *   $machine->hasRelovaData();        // true
 *
 * The fetch strategy is controlled by the mapping's query_mode:
 *   virtual  — single batch SELECT … IN (…), result cached by join-value hash
 *   snapshot — reads existing display_snapshot from relova_entity_references (no live query)
 *   on_demand — individual SELECT per record, each result cached separately
 *
 * Cache TTL defaults to RelovaConnection::cache_ttl (seconds), falling back to DEFAULT_TTL.
 */
class VirtualRelationLoader
{
    public const int DEFAULT_TTL = 300;

    public function __construct(
        protected RelovaConnectionManager $connectionManager,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────────

    /**
     * Enrich a collection of Eloquent models with remote data.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  string  $localJoinKey  Attribute on the local model that holds the remote key value
     * @param  string  $remoteJoinColumn  Column in the remote table that the local key maps to
     * @param  RelovaFieldMapping|null  $mapping  Auto-detected from the model table when null
     * @return Collection<int, TModel>
     */
    public function enrichModels(
        Collection $models,
        string $localJoinKey,
        string $remoteJoinColumn,
        ?RelovaFieldMapping $mapping = null,
    ): Collection {
        if ($models->isEmpty()) {
            return $models;
        }

        if ($mapping === null) {
            /** @var Model $firstModel */
            $firstModel = $models->first();
            $mapping = $this->findMappingForTable($firstModel->getTable());
        }

        if ($mapping === null || ! $mapping->enabled) {
            return $models;
        }

        $joinValues = $models
            ->pluck($localJoinKey)
            ->filter()
            ->map(fn (mixed $v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        if (empty($joinValues)) {
            return $models;
        }

        // Fetch remote data as [joinValue => remoteRow] index
        $remoteIndex = $this->fetchRemoteIndex($mapping, $joinValues, $remoteJoinColumn);

        if (empty($remoteIndex)) {
            return $models;
        }

        return $models->each(function (Model $model) use ($localJoinKey, $remoteIndex, $mapping): void {
            $joinValue = (string) ($model->getAttribute($localJoinKey) ?? '');
            $remoteRow = $remoteIndex[$joinValue] ?? null;

            if ($remoteRow === null) {
                return;
            }

            // Store the raw remote row — readable via $model->relovaValue('COL')
            /** @var HasRelovaData $model */
            $model->setRelovaData($remoteRow);

            // Merge direct-mapped fields into model attributes
            $this->applyDirectMappings($model, $mapping, $remoteRow);
        });
    }

    /**
     * Enrich a single Eloquent model instance.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    public function enrichModel(
        Model $model,
        string $localJoinKey,
        string $remoteJoinColumn,
        ?RelovaFieldMapping $mapping = null,
    ): Model {
        $this->enrichModels(collect([$model]), $localJoinKey, $remoteJoinColumn, $mapping);

        return $model;
    }

    /**
     * Find the active RelovaFieldMapping for a given local table name.
     * Returns the first enabled mapping; ignores disabled ones.
     */
    public function findMappingForTable(string $tableName): ?RelovaFieldMapping
    {
        return RelovaFieldMapping::where('target_module', $tableName)
            ->where('enabled', true)
            ->with('connection')
            ->first();
    }

    /**
     * Fetch ALL rows from the remote source table for a mapping and translate
     * column names using column_mappings. Returns a Collection of \stdClass
     * objects ready to be appended (UNION) to a local record list.
     *
     * Each row receives a stable uid of the form `relova-{mappingId}-{index}`
     * and a boolean `_relova = true` marker so the host UI can distinguish
     * remote rows from local Eloquent models.
     *
     * Results are cached for the connection's configured TTL.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function fetchUnionRows(RelovaFieldMapping $mapping): Collection
    {
        if (! $mapping->enabled) {
            return collect();
        }

        $cacheKey = 'relova:union:'.$mapping->id;
        $ttl = $mapping->connection->cache_ttl ?? self::DEFAULT_TTL;

        /**
         * @var array{rows: array<int, array<string, mixed>>, uids: array<int, string>} $cached
         */
        $cached = Cache::remember($cacheKey, $ttl, function () use ($mapping): array {
            $sql = 'SELECT * FROM "'.$mapping->source_table.'"';

            /** @var array<int, array<string, mixed>> $rows */
            $rows = $this->connectionManager->query($mapping->connection, $sql, []);

            // Upsert each row as a RelovaEntityReference snapshot so remote rows have
            // a stable uid and their data persists for the detail page even when offline.
            $uids = [];
            foreach ($rows as $row) {
                $hash = md5(serialize($row));
                $ref = RelovaEntityReference::updateOrCreate(
                    [
                        'connection_id' => $mapping->connection_id,
                        'remote_table' => $mapping->source_table,
                        'remote_primary_column' => '_row_hash',
                        'remote_primary_value' => $hash,
                    ],
                    [
                        'display_snapshot' => $row,
                        'snapshot_refreshed_at' => now(),
                    ],
                );
                $uids[] = $ref->uid;
            }

            return ['rows' => $rows, 'uids' => $uids];
        });

        $rows = $cached['rows'];
        $uids = $cached['uids'];
        $columnMappings = $mapping->column_mappings ?? [];

        return collect($rows)->map(function (array $row, int $index) use ($uids, $columnMappings): RelovaRow {
            $uid = $uids[$index] ?? 'relova-unknown-'.$index;
            $attrs = [];

            foreach ($columnMappings as $cm) {
                $remoteCol = $cm['remote_column'] ?? '';
                $localField = $cm['local_field'] ?? '';
                if ($remoteCol !== '' && $localField !== '' && array_key_exists($remoteCol, $row)) {
                    $attrs[$localField] = $row[$remoteCol];
                }
            }

            return new RelovaRow($uid, $attrs, $row);
        });
    }

    /**
     * Flush all batch-mode cache entries for a mapping.
     * Call this from a sync job or when remote data is known to have changed.
     */
    public function flushCache(RelovaFieldMapping $mapping): void
    {
        // We use tagged cache when the driver supports it; otherwise no-op.
        // Callers that need guaranteed invalidation should configure a taggable
        // cache driver (Redis, Memcached) and call this after remote updates.
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags($this->cacheTag($mapping))->flush();
        }
    }

    // ─── Private: strategy dispatch ──────────────────────────────────────────────

    /**
     * Dispatch to the correct fetch strategy and return a [joinValue => row] map.
     *
     * @param  string[]  $joinValues
     * @return array<string, array<string, mixed>>
     */
    private function fetchRemoteIndex(
        RelovaFieldMapping $mapping,
        array $joinValues,
        string $remoteJoinColumn,
    ): array {
        return match ($mapping->query_mode) {
            'snapshot' => $this->fetchFromSnapshots($mapping, $joinValues),
            'on_demand' => $this->fetchOnDemandCached($mapping, $joinValues, $remoteJoinColumn),
            default => $this->fetchBatchCached($mapping, $joinValues, $remoteJoinColumn), // 'virtual'
        };
    }

    // ─── Private: virtual (batch) ─────────────────────────────────────────────────

    /**
     * Single batch SELECT … IN (…), result set cached as one unit.
     *
     * @param  string[]  $joinValues
     * @return array<string, array<string, mixed>>
     */
    private function fetchBatchCached(
        RelovaFieldMapping $mapping,
        array $joinValues,
        string $remoteJoinColumn,
    ): array {
        $sortedValues = $joinValues;
        sort($sortedValues);

        $cacheKey = 'relova:batch:'.$mapping->id.':'.$remoteJoinColumn.':'.md5(implode(',', $sortedValues));
        $ttl = $mapping->connection->cache_ttl ?? self::DEFAULT_TTL;

        $rows = Cache::remember($cacheKey, $ttl, function () use ($mapping, $joinValues, $remoteJoinColumn): array {
            return $this->liveSelectBatch($mapping, $joinValues, $remoteJoinColumn);
        });

        return $rows;
    }

    /**
     * Execute a live batch SELECT and return a [joinValue => row] map.
     *
     * @param  string[]  $joinValues
     * @return array<string, array<string, mixed>>
     */
    private function liveSelectBatch(
        RelovaFieldMapping $mapping,
        array $joinValues,
        string $remoteJoinColumn,
    ): array {
        $colList = $this->buildColumnList($mapping, $remoteJoinColumn);
        $placeholders = implode(', ', array_fill(0, count($joinValues), '?'));

        $sql = "SELECT {$colList} FROM \"{$mapping->source_table}\" WHERE \"{$remoteJoinColumn}\" IN ({$placeholders})";

        $rows = $this->connectionManager->query($mapping->connection, $sql, $joinValues);

        $index = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$remoteJoinColumn] ?? '');
            if ($key !== '') {
                $index[$key] = $row;
            }
        }

        return $index;
    }

    // ─── Private: snapshot ────────────────────────────────────────────────────────

    /**
     * Read cached display_snapshot rows from relova_entity_references.
     * No live remote query is made; uses last-known-good data.
     *
     * @param  string[]  $joinValues
     * @return array<string, array<string, mixed>>
     */
    private function fetchFromSnapshots(
        RelovaFieldMapping $mapping,
        array $joinValues,
    ): array {
        $references = RelovaEntityReference::where('connection_id', $mapping->connection_id)
            ->where('remote_table', $mapping->source_table)
            ->whereIn('remote_primary_value', $joinValues)
            ->get();

        $index = [];
        foreach ($references as $ref) {
            if (! empty($ref->display_snapshot)) {
                $index[(string) $ref->remote_primary_value] = $ref->display_snapshot;
            }
        }

        return $index;
    }

    // ─── Private: on_demand ───────────────────────────────────────────────────────

    /**
     * Fetch each join value individually with its own cache entry.
     * Mirrors the per-record cache used by MappingDataLoader::enrichOnDemand()
     * so the two share a cache key space.
     *
     * @param  string[]  $joinValues
     * @return array<string, array<string, mixed>>
     */
    private function fetchOnDemandCached(
        RelovaFieldMapping $mapping,
        array $joinValues,
        string $remoteJoinColumn,
    ): array {
        $colList = $this->buildColumnList($mapping, $remoteJoinColumn);
        $ttl = $mapping->connection->cache_ttl ?? self::DEFAULT_TTL;

        $index = [];

        foreach ($joinValues as $joinValue) {
            // Reuse same key schema as MappingDataLoader::enrichOnDemand()
            $cacheKey = "relova:on_demand:{$mapping->connection_id}:{$mapping->source_table}:{$remoteJoinColumn}:{$joinValue}";

            $row = Cache::remember($cacheKey, $ttl, function () use (
                $mapping, $colList, $remoteJoinColumn, $joinValue
            ): ?array {
                $sql = "SELECT {$colList} FROM \"{$mapping->source_table}\" WHERE \"{$remoteJoinColumn}\" = ? LIMIT 1";
                $rows = $this->connectionManager->query($mapping->connection, $sql, [$joinValue]);

                return $rows[0] ?? null;
            });

            if ($row !== null) {
                $index[$joinValue] = $row;
            }
        }

        return $index;
    }

    // ─── Private: helpers ────────────────────────────────────────────────────────

    /**
     * Build a quoted column list for a SELECT statement,
     * including all mapped remote columns plus the join column.
     */
    private function buildColumnList(RelovaFieldMapping $mapping, string $remoteJoinColumn): string
    {
        $columns = collect($mapping->column_mappings ?? [])
            ->pluck('remote_column')
            ->filter()
            ->push($remoteJoinColumn)
            ->unique()
            ->values()
            ->map(fn (string $c) => '"'.$c.'"')
            ->all();

        return implode(', ', $columns);
    }

    /**
     * Apply direct (non-relation) column mappings from a remote row
     * into the model's attribute bag using HasRelovaData::mergeRelovaAttributes().
     *
     * @param  array<string, mixed>  $remoteRow
     */
    private function applyDirectMappings(
        Model $model,
        RelovaFieldMapping $mapping,
        array $remoteRow,
    ): void {
        if (! in_array(HasRelovaData::class, class_uses_recursive($model), true)) {
            return;
        }

        $toMerge = [];

        foreach ($mapping->getDirectMappings() as $entry) {
            $remoteCol = $entry['remote_column'] ?? null;
            $localField = $entry['local_field'] ?? null;

            if (! $remoteCol || ! $localField) {
                continue;
            }

            if (array_key_exists($remoteCol, $remoteRow)) {
                $toMerge[$localField] = $remoteRow[$remoteCol];
            }
        }

        if (! empty($toMerge)) {
            /** @var HasRelovaData $model */
            $model->mergeRelovaAttributes($toMerge);
        }
    }

    /**
     * Cache tag used when the driver supports tags (Redis / Memcached).
     */
    private function cacheTag(RelovaFieldMapping $mapping): string
    {
        return 'relova:mapping:'.$mapping->id;
    }
}
