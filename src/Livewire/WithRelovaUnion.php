<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Relova\Services\VirtualRelationLoader;

/**
 * Hooks into a Livewire component's records loading to transparently UNION
 * remote rows from a Relova mapping after local Eloquent rows.
 *
 * ## Host component requirements
 *
 * The consuming class (e.g. LoadModel) must:
 *   1. Add `use \Relova\Livewire\WithRelovaUnion;`
 *   2. Rename its `getRecordsProperty()` to `buildBaseQuery()` and return
 *      the un-paginated Builder (remove the `->paginate()` call).
 *   3. Expose a `$model` string property so this trait can resolve the table name.
 *
 * ## What this trait does
 *
 * - Calls `buildBaseQuery()` to get the host component's local query.
 * - Looks up any enabled Relova FieldMapping for that table.
 * - If none exists (or fetch fails), falls back to plain `->paginate(25)`.
 * - If remote rows exist, builds a LengthAwarePaginator that places local
 *   rows on early pages and appends remote RelovaRow objects on later pages.
 * - Remote rows are RelovaRow instances (null-safe property access), not stdClass.
 */
trait WithRelovaUnion
{
    /**
     * Paginated record list consumed by the Blade view.
     * Replaces the host component's own getRecordsProperty().
     */
    public function getRecordsProperty(): LengthAwarePaginator
    {
        $baseQuery = $this->buildBaseQuery();

        $remoteRows = collect();
        $tableName = $this->resolveTableName();

        try {
            /** @var VirtualRelationLoader $loader */
            $loader = app(VirtualRelationLoader::class);
            $mapping = $loader->findMappingForTable($tableName);
            if ($mapping) {
                $remoteRows = $loader->fetchUnionRows($mapping);
            }
        } catch (\Exception $e) {
            Log::warning('Relova union fetch failed for '.$tableName.': '.$e->getMessage());
        }

        if ($remoteRows->isEmpty()) {
            return $baseQuery->paginate(25);
        }

        // Build a combined paginator: local pages first, remote rows fill the tail.
        $perPage = 25;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($currentPage - 1) * $perPage;

        $localTotal = (clone $baseQuery)->count();
        $totalCount = $localTotal + $remoteRows->count();

        $localSkip = min($offset, $localTotal);
        $localTake = max(0, min($perPage, $localTotal - $localSkip));
        $localItems = $localTake > 0
            ? (clone $baseQuery)->skip($localSkip)->take($localTake)->get()
            : collect();

        $remoteSkip = max(0, $offset - $localTotal);
        $remoteTake = $perPage - $localItems->count();
        $remoteItems = $remoteTake > 0
            ? $remoteRows->slice($remoteSkip, $remoteTake)->values()
            : collect();

        return new LengthAwarePaginator(
            collect($localItems)->merge($remoteItems),
            $totalCount,
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Resolve the DB table name for the current model string.
     * Works for any host component that exposes a `$model` property.
     */
    private function resolveTableName(): string
    {
        $modelClass = 'App\\Models\\'.str()->studly($this->model);

        return (new $modelClass)->getTable();
    }

    /**
     * Contract: the host component must implement this method.
     * It should return an un-paginated query Builder scoped and sorted for the
     * current component state (filters, dates, sort column, visibility, etc.).
     */
    abstract protected function buildBaseQuery(): Builder;
}
