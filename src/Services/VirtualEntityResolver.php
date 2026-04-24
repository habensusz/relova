<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Database\Eloquent\Model;
use Relova\Data\VirtualEntityProxy;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\VirtualEntityReference;

/**
 * Transparent entity resolver — bridges local Eloquent and remote virtual rows.
 *
 * Host-app controllers and route model binding call resolveOrProxy() instead of
 * the plain Eloquent find. If the UID belongs to a local row it returns the
 * Eloquent model unchanged. If the UID belongs to a VirtualEntityReference it
 * returns a VirtualEntityProxy so the caller never needs to know the difference.
 */
class VirtualEntityResolver
{
    /**
     * Request-scoped in-memory cache.
     *
     * Keyed by "{modelClass}|{uid}|{tenantId}".
     * PHP does not persist static state across HTTP requests (no Octane/Swoole),
     * so this is effectively request-scoped without needing a tagged cache store.
     *
     * @var array<string, Model|VirtualEntityProxy|null>
     */
    private static array $resolveCache = [];

    public function __construct(
        private QueryExecutor $queryExecutor,
    ) {}

    /**
     * Try to resolve a UID as a local Eloquent model; fall back to a virtual proxy.
     *
     * @param  class-string<Model>  $modelClass  The host-app Eloquent model class.
     * @param  string  $uid  The 22-char UID from the URL / route binding.
     * @param  string|null  $tenantId  Current tenant identifier; null = skip tenant filter.
     */
    public function resolveOrProxy(string $modelClass, string $uid, ?string $tenantId = null): Model|VirtualEntityProxy|null
    {
        $cacheKey = $modelClass.'|'.$uid.'|'.$tenantId;
        if (array_key_exists($cacheKey, self::$resolveCache)) {
            return self::$resolveCache[$cacheKey];
        }

        $result = $this->doResolve($modelClass, $uid, $tenantId);
        self::$resolveCache[$cacheKey] = $result;

        return $result;
    }

    private function doResolve(string $modelClass, string $uid, ?string $tenantId): Model|VirtualEntityProxy|null
    {
        // 1. Try local Eloquent first (fast path — most requests).
        /** @var Model $instance */
        $instance = new $modelClass;
        $local = $modelClass::where($instance->getRouteKeyName(), $uid)->first();

        if ($local !== null) {
            return $local;
        }

        // 2. No local row — look for a VirtualEntityReference with this UID.
        $refQuery = VirtualEntityReference::where('uid', $uid);

        if ($tenantId !== null) {
            $refQuery->where('tenant_id', $tenantId);
        }

        $ref = $refQuery->with('mapping.connection')->first();

        if ($ref === null) {
            return null;
        }

        // 3. Load the mapping. Without a mapping we cannot translate field names.
        $mapping = $ref->mapping;

        if ($mapping === null) {
            // Fallback: query for a mapping that covers this remote table.
            $mapping = ConnectorModuleMapping::where('remote_table', $ref->remote_table)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
        }

        if ($mapping === null) {
            return null;
        }

        return new VirtualEntityProxy($ref, $mapping, $this->queryExecutor);
    }

    /**
     * Return all virtual entity references for a given module key (table name).
     *
     * Used by LoadModel dual-section to fetch the remote-row section.
     *
     * @param  string  $moduleKey  The host-app table / module identifier (e.g. 'machines').
     * @param  string|null  $tenantId  Current tenant identifier.
     * @param  int  $page  1-based page number.
     * @param  int  $perPage  Items per page.
     * @param  string|null  $search  Optional search term applied to snapshot values.
     * @return array{items: VirtualEntityProxy[], total: int, per_page: int, current_page: int}
     */
    public function virtualRowsForModule(
        string $moduleKey,
        ?string $tenantId = null,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
    ): array {
        $query = VirtualEntityReference::with('mapping')
            ->where(function ($q) use ($moduleKey): void {
                // Primary: match via mapping relationship (records where mapping_id is set).
                $q->whereHas('mapping', fn ($inner) => $inner->where('module_key', $moduleKey))
                    // Fallback: records synced before mapping_id column was populated or before
                    // the current mapping existed. We match by remote_table instead so that
                    // pre-existing snapshots are not hidden after a mapping is recreated.
                    ->orWhere(function ($inner) use ($moduleKey): void {
                        $inner->whereNull('mapping_id')->where('remote_table', $moduleKey);
                    });
            })
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->whereRaw('display_snapshot::text ILIKE ?', ["%{$search}%"]);
            }))
            ->orderByDesc('snapshot_taken_at');

        $total = $query->count();
        $refs = $query->forPage($page, $perPage)->get();

        // Load mappings keyed by their ID to share instances.
        $mappingIds = $refs->pluck('mapping_id')->filter()->unique();
        $mappings = ConnectorModuleMapping::whereIn('id', $mappingIds)
            ->get()
            ->keyBy('id');

        $items = $refs->map(function (VirtualEntityReference $ref) use ($mappings): VirtualEntityProxy {
            $mapping = $ref->mapping_id ? ($mappings->get($ref->mapping_id) ?? $ref->mapping) : $ref->mapping;

            return new VirtualEntityProxy($ref, $mapping, $this->queryExecutor);
        })->all();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ];
    }
}
