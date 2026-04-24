<?php

declare(strict_types=1);

namespace Relova\Data;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\VirtualEntityReference;
use Relova\Services\QueryExecutor;

/**
 * Read-through wrapper for a remote entity row.
 *
 * Presents as an Eloquent-like object to host-app code so that show pages,
 * policies, and Livewire components receive a drop-in stand-in for the real
 * model. No host-app table is written — data is served from the snapshot in
 * VirtualEntityReference or, on cache miss, fetched live via QueryExecutor.
 *
 * Identifies itself:
 *   $proxy->is_virtual       → true
 *   $proxy->uid              → VirtualEntityReference::uid (22-char string)
 *   $proxy->id               → null (no host-app PK)
 *   $proxy->relova_ref       → the VirtualEntityReference instance
 *
 * Property reads use field_mappings to translate local→remote column names:
 *   $proxy->machine_model    → snapshot['model_name'] (if mapping says so)
 */
class VirtualEntityProxy
{
    /** @var array<string, mixed>|null  Lazily-populated live row from remote. */
    private ?array $liveRow = null;

    /**
     * Per-instance cache of synthesised relationship objects.
     * Keyed by join-table name (e.g. 'locations', 'manufacturers').
     *
     * @var array<string, \stdClass|null>
     */
    private array $relationCache = [];

    public readonly bool $is_virtual;

    public readonly string $uid;

    /** @var int|null Always null — virtual entities have no host-app PK. */
    public readonly ?int $id;

    public function __construct(
        public readonly VirtualEntityReference $relova_ref,
        public readonly ConnectorModuleMapping $mapping,
        private readonly QueryExecutor $queryExecutor,
    ) {
        $this->is_virtual = true;
        $this->uid = $relova_ref->uid;
        $this->id = null;
    }

    /**
     * Read a property from the snapshot (or live if the snapshot misses).
     *
     * Lookup order:
     *   1. Direct key in relova_ref->local_overrides (per-entity override, highest priority).
     *   2. FK resolution from local_overrides: {stem}_id → App\Models\{Stem}::{stem}_name.
     *   3. Direct key in mapping->default_values (mapping-level fallback).
     *   4. FK resolution from mapping->default_values.
     *   5. Direct key in display_snapshot (remote column name).
     *   6. Translated key via field_mappings (local → remote).
     *   7. Live fetch from the remote source (populates $liveRow).
     *   8. null.
     */
    public function __get(string $name): mixed
    {
        // 1. Per-entity local_overrides — highest priority, set per-entity by the user.
        $overrides = $this->relova_ref->local_overrides ?? [];
        if (array_key_exists($name, $overrides) && $overrides[$name] !== null && $overrides[$name] !== '') {
            return $overrides[$name];
        }

        // 2. FK → display-name resolution from local_overrides.
        if (str_ends_with($name, '_name') && ! empty($overrides)) {
            $stem = substr($name, 0, -5);
            $fkKey = $stem.'_id';
            if (isset($overrides[$fkKey]) && is_numeric($overrides[$fkKey])) {
                $cacheKey = 'ovk_'.$fkKey.'_'.$overrides[$fkKey].'_'.$name;
                if (! array_key_exists($cacheKey, $this->relationCache)) {
                    $modelClass = 'App\\Models\\'.Str::studly($stem);
                    $related = class_exists($modelClass) ? $modelClass::find((int) $overrides[$fkKey]) : null;
                    $this->relationCache[$cacheKey] = ($related !== null && isset($related->{$name})) ? $related->{$name} : null;
                }
                if ($this->relationCache[$cacheKey] !== null) {
                    return $this->relationCache[$cacheKey];
                }
            }
        }

        // 3. Mapping default_values — mapping-level fallback shared across all entities.
        $defaults = $this->mapping->default_values ?? [];
        if (array_key_exists($name, $defaults) && $defaults[$name] !== null && $defaults[$name] !== '') {
            return $defaults[$name];
        }

        // 4. FK → display-name resolution from mapping->default_values.
        //    Result is cached per-instance so multi-row lists only hit the DB once.
        if (str_ends_with($name, '_name') && ! empty($defaults)) {
            $stem = substr($name, 0, -5);                     // 'location' from 'location_name'
            $fkKey = $stem.'_id';
            if (isset($defaults[$fkKey]) && is_numeric($defaults[$fkKey])) {
                $cacheKey = 'dfk_'.$fkKey.'_'.$defaults[$fkKey].'_'.$name;
                if (! array_key_exists($cacheKey, $this->relationCache)) {
                    $modelClass = 'App\\Models\\'.Str::studly($stem);
                    $related = class_exists($modelClass) ? $modelClass::find((int) $defaults[$fkKey]) : null;
                    $this->relationCache[$cacheKey] = ($related !== null && isset($related->{$name})) ? $related->{$name} : null;
                }
                if ($this->relationCache[$cacheKey] !== null) {
                    return $this->relationCache[$cacheKey];
                }
            }
        }

        $snapshot = $this->relova_ref->display_snapshot ?? [];

        // Direct hit on remote column name stored verbatim in snapshot.
        if (array_key_exists($name, $snapshot)) {
            return $snapshot[$name];
        }

        // Translate via field_mappings: local_col => remote_col.
        $remoteCol = $this->mapping->remoteColumnFor($name);
        if ($remoteCol !== null && array_key_exists($remoteCol, $snapshot)) {
            return $snapshot[$remoteCol];
        }

        // Dotted-key fallback: the snapshot may store joined columns as
        // "locations.location_name". Scan for any key with suffix ".{name}".
        // This handles older snapshots built before resolveSnapshotColumns existed.
        foreach ($snapshot as $key => $value) {
            if (is_string($key) && str_ends_with($key, '.'.$name)) {
                return $value;
            }
        }

        // Relationship synthesis: if $name is the singular of a join-table name,
        // build a Fluent object from the flat snapshot keys that belong to that table.
        // Fluent returns null for any undefined property, so chained access like
        // $proxy->location?->premises?->premises_name never throws ErrorException.
        foreach (array_keys($this->mapping->joins ?? []) as $joinTable) {
            if (Str::singular($joinTable) === $name) {
                if (! array_key_exists($joinTable, $this->relationCache)) {
                    $attrs = [];
                    foreach ($this->mapping->display_fields ?? [] as $field) {
                        if (str_starts_with($field, $joinTable.'.')) {
                            $col = substr($field, strlen($joinTable) + 1);
                            $attrs[$col] = $snapshot[$col] ?? null;
                        }
                    }
                    $hasAny = collect($attrs)->filter()->isNotEmpty();
                    $this->relationCache[$joinTable] = $hasAny ? new Fluent($attrs) : null;
                }

                return $this->relationCache[$joinTable];
            }
        }

        // Snapshot miss — try live fetch (one network round-trip, cached on instance).
        $live = $this->fetchLive();
        if ($live !== null) {
            if (array_key_exists($name, $live)) {
                return $live[$name];
            }
            if ($remoteCol !== null && array_key_exists($remoteCol, $live)) {
                return $live[$remoteCol];
            }
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /**
     * Absorb Eloquent relation and query-builder method calls.
     *
     * Returns a VirtualNullRelation so that the full chain
     *   $proxy->workOrders()->with('category')->latest()->get()
     * succeeds and yields an empty collection instead of throwing.
     */
    public function __call(string $name, array $arguments): VirtualNullRelation
    {
        return new VirtualNullRelation;
    }

    /**
     * Return a display-safe array of all snapshot keys merged with field-mapped names.
     *
     * Useful for Blade views that iterate over properties.
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        $snapshot = $this->relova_ref->display_snapshot ?? [];
        $result = $snapshot;

        // Also add field-mapped local names so callers can reference either name.
        foreach ($this->mapping->fieldMap() as $localCol => $remoteCol) {
            if (array_key_exists($remoteCol, $snapshot)) {
                $result[$localCol] = $snapshot[$remoteCol];
            }
        }

        $result['uid'] = $this->uid;
        $result['id'] = null;
        $result['is_virtual'] = true;

        return $result;
    }

    /**
     * Return the model class name this proxy stands in for (from the mapping).
     */
    public function getMorphClass(): string
    {
        return $this->mapping->module_key ?? 'virtual';
    }

    /**
     * Return the local location ID (host-app locations.id) anchored to this
     * mapping.  Null means no location was assigned at mapping time, so this
     * virtual entity cannot participate in location-filtered queries.
     */
    public function getLocalLocationId(): ?int
    {
        return $this->mapping->getLocalFkId('location_id');
    }

    /**
     * Resolve the local premises ID for this virtual entity by walking through
     * the anchored location.  Returns null when no location is set or when the
     * host app does not expose App\Models\Location.
     */
    public function getLocalPremisesId(): ?int
    {
        $locationId = $this->getLocalLocationId();

        if ($locationId === null) {
            return null;
        }

        /** @phpstan-ignore-next-line */
        return \App\Models\Location::find($locationId)?->premises_id;
    }

    /**
     * Fetch a fresh single row from the remote source.
     * Result is cached on this instance so subsequent __get() calls don't
     * issue additional queries.
     *
     * @return array<string, mixed>|null
     */
    private function fetchLive(): ?array
    {
        if ($this->liveRow !== null) {
            return $this->liveRow;
        }

        $connection = $this->relova_ref->connection()->first();
        if ($connection === null) {
            return null;
        }

        $pkColumn = $this->relova_ref->remote_pk_column;
        $pkValue = $this->relova_ref->remote_pk_value;

        try {
            $rows = $this->queryExecutor->executePassThrough(
                connection: $connection,
                table: $this->relova_ref->remote_table,
                conditions: [[$pkColumn, '=', $pkValue]],
                columns: $this->mapping->snapshotColumns(),
                limit: 1,
                joins: $this->mapping->joins ?? [],
            );

            foreach ($rows as $row) {
                $this->liveRow = $row;

                return $this->liveRow;
            }
        } catch (\Throwable) {
            // Live fetch failed — fall through to null.
        }

        return null;
    }
}
