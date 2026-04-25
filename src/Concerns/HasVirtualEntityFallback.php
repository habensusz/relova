<?php

declare(strict_types=1);

namespace Relova\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Relova\Data\VirtualEntityProxy;
use Relova\Services\VirtualEntityResolver;

/**
 * Drop this trait onto any Eloquent model to give its route model binding
 * a Relova-aware fallback.
 *
 * Lookup order:
 *   1. Local DB row (fast path — covers 99 % of requests).
 *   2. VirtualEntityReference snapshot (Relova remote row).
 *   3. ModelNotFoundException (standard 404 behaviour).
 *
 * Usage:
 *   class Part extends Model
 *   {
 *       use HasVirtualEntityFallback;
 *   }
 *
 * Optional eager-load hook — override in your model to add relations:
 *   protected function relovaRouteBindingWith(): array
 *   {
 *       return ['location.premises', 'supplier'];
 *   }
 */
trait HasVirtualEntityFallback
{
    /**
     * Relations to eager-load when the local row is found during route binding.
     * Override per-model; default is no eager loads.
     *
     * @return array<int, string>
     */
    protected function relovaRouteBindingWith(): array
    {
        return [];
    }

    /**
     * Resolve the route binding with a Relova virtual-entity fallback.
     *
     *
     * @throws ModelNotFoundException
     */
    public function resolveRouteBinding($value, $field = null): static|VirtualEntityProxy
    {
        // Livewire's SupportPageComponents re-runs implicit route binding after the
        // initial resolution. By that point the route parameter is already the resolved
        // object, not the raw URL string. Return it directly to avoid passing the
        // proxy into a SQL WHERE clause which causes a PDO type-conversion error.
        if ($value instanceof VirtualEntityProxy) {
            return $value;
        }

        $key = $field ?? $this->getRouteKeyName();
        $eagerLoads = $this->relovaRouteBindingWith();

        $query = $this->where($key, $value);

        if ($eagerLoads !== []) {
            $query = $query->with($eagerLoads);
        }

        $local = $query->first();

        if ($local !== null) {
            return $local;
        }

        // Fall back to a VirtualEntityProxy when the UID belongs to a
        // Relova-indexed remote row and VirtualEntityResolver is available.
        if (class_exists(VirtualEntityResolver::class)) {
            try {
                $tenantId = function_exists('tenant') && tenant()
                    ? (string) tenant('id')
                    : null;

                $proxy = app(VirtualEntityResolver::class)
                    ->resolveOrProxy(static::class, (string) $value, $tenantId);

                if ($proxy !== null) {
                    return $proxy;
                }
            } catch (\Throwable $e) {
                Log::warning('VirtualEntityResolver failed in route binding', [
                    'model' => static::class,
                    'uid' => $value,
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw (new ModelNotFoundException)->setModel(static::class, [$value]);
    }
}
