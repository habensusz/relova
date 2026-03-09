<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Relova\Concerns\HasRelovaData;
use Relova\Models\RelovaFieldMapping;

/**
 * RelovaEnrichmentService
 *
 * Transparently enriches Eloquent model instances (or plain arrays) with
 * data fetched from remote sources through the configured RelovaFieldMappings.
 *
 * This service is the engine behind RelovaEnrichmentMiddleware and can also
 * be used directly in controllers, jobs, or service classes.
 *
 * ## How it works
 *
 * 1. For each Eloquent model (or array of models), look up the enabled
 *    RelovaFieldMapping whose `target_module` matches the model's table name.
 * 2. If the mapping has `local_join_key` / `remote_join_key` configured,
 *    call VirtualRelationLoader::enrichModels() to batch-fetch the remote rows
 *    and merge them into the model instances.
 * 3. If no join keys are stored on the mapping, fall back to inspecting the
 *    column_mappings for a pair where local_field matches an existing attribute
 *    on the model (heuristic auto-detect).
 * 4. Cache the active mappings per table name for the duration of the request
 *    to avoid N+1 DB look-ups when multiple model types need enrichment.
 *
 * ## Remote data access after enrichment
 *
 *   $machine->relovaValue('ASSET_NAME');   // raw remote column
 *   $machine->machine_name;                // direct-mapped local attribute
 *   $machine->hasRelovaData();             // true when remote row was found
 */
class RelovaEnrichmentService
{
    /** Per-request cache of RelovaFieldMapping keyed by table name. */
    private array $mappingCache = [];

    public function __construct(
        private readonly VirtualRelationLoader $loader,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────────

    /**
     * Enrich a single Eloquent model instance.
     * Silently returns the original model on any error.
     *
     * @template TModel of Model
     * @param  TModel  $model
     * @return TModel
     */
    public function enrichModel(Model $model): Model
    {
        if (! $this->usesHasRelovaData($model)) {
            return $model;
        }

        $mapping = $this->resolveMapping($model->getTable());
        if ($mapping === null) {
            return $model;
        }

        [$localKey, $remoteKey] = $this->resolveJoinKeys($mapping, $model);
        if ($localKey === null || $remoteKey === null) {
            return $model;
        }

        try {
            $this->loader->enrichModel($model, $localKey, $remoteKey, $mapping);
        } catch (\Exception $e) {
            Log::warning('Relova enrichment failed for single model', [
                'table' => $model->getTable(),
                'error' => $e->getMessage(),
            ]);
        }

        return $model;
    }

    /**
     * Enrich a collection of Eloquent model instances.
     * Silently returns the original collection on any error.
     *
     * @template TModel of Model
     * @param  Collection<int, TModel>|EloquentCollection<int, TModel>  $models
     * @return Collection<int, TModel>|EloquentCollection<int, TModel>
     */
    public function enrichCollection(Collection $models): Collection
    {
        if ($models->isEmpty()) {
            return $models;
        }

        /** @var Model|null $first */
        $first = $models->first();
        if ($first === null || ! $this->usesHasRelovaData($first)) {
            return $models;
        }

        $mapping = $this->resolveMapping($first->getTable());
        if ($mapping === null) {
            return $models;
        }

        [$localKey, $remoteKey] = $this->resolveJoinKeys($mapping, $first);
        if ($localKey === null || $remoteKey === null) {
            return $models;
        }

        try {
            $this->loader->enrichModels($models, $localKey, $remoteKey, $mapping);
        } catch (\Exception $e) {
            Log::warning('Relova enrichment failed for collection', [
                'table' => $first->getTable(),
                'count' => $models->count(),
                'error' => $e->getMessage(),
            ]);
        }

        return $models;
    }

    /**
     * Enrich any value that may be a Model, a Collection of Models, or something else.
     * Returns the value unchanged when it cannot be enriched.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function enrichAny(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $this->enrichModel($value);
        }

        if ($value instanceof Collection) {
            return $this->enrichCollection($value);
        }

        return $value;
    }

    /**
     * Reset the per-request mapping look-up cache.
     * Called automatically at the start of each request by the middleware.
     */
    public function resetCache(): void
    {
        $this->mappingCache = [];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────────

    /**
     * Resolve the enabled RelovaFieldMapping for a table, using a per-request
     * in-memory cache to prevent repeated DB queries for the same table.
     */
    private function resolveMapping(string $table): ?RelovaFieldMapping
    {
        if (array_key_exists($table, $this->mappingCache)) {
            return $this->mappingCache[$table];
        }

        $mapping = $this->loader->findMappingForTable($table);
        $this->mappingCache[$table] = $mapping;

        return $mapping;
    }

    /**
     * Determine the local and remote join key pair for a mapping + model instance.
     *
     * Priority order:
     *   1. Explicit `local_join_key` / `remote_join_key` columns on the mapping.
     *   2. Auto-detect: scan column_mappings for the first entry whose `local_field`
     *      matches an attribute that is already set (non-null) on the model.
     *
     * Returns [localKey, remoteKey] or [null, null] when resolution fails.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveJoinKeys(RelovaFieldMapping $mapping, Model $model): array
    {
        // 1. Explicit keys stored on the mapping row
        if (! empty($mapping->local_join_key) && ! empty($mapping->remote_join_key)) {
            return [$mapping->local_join_key, $mapping->remote_join_key];
        }

        // 2. Heuristic: first column_mapping entry whose local_field exists on the model
        foreach ($mapping->column_mappings ?? [] as $entry) {
            $localField = $entry['local_field'] ?? null;
            $remoteColumn = $entry['remote_column'] ?? null;

            if ($localField && $remoteColumn && $model->getAttribute($localField) !== null) {
                return [$localField, $remoteColumn];
            }
        }

        return [null, null];
    }

    /**
     * Check whether a model uses the HasRelovaData trait.
     */
    private function usesHasRelovaData(Model $model): bool
    {
        return in_array(HasRelovaData::class, class_uses_recursive($model), true);
    }
}

