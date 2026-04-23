<?php

declare(strict_types=1);

namespace Relova\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
/**
 * Enforces tenant isolation on every query for a Relova model.
 *
 * Adds a global scope that constrains all queries to the currently-bound
 * tenant_id. The tenant_id is resolved server-side from the container
 * binding `relova.current_tenant`, set by the RelovaApiAuth middleware
 * (for SDK consumers) or the host app (for in-process consumers).
 *
 * If no tenant is bound the scope adds WHERE false, returning an empty
 * collection. This is safe — no cross-tenant data can leak — and allows
 * the Relova UI to render an empty state both on the central domain and
 * before any tenants have been registered.
 */
trait EnforcesTenantIsolation
{
    public static function bootEnforcesTenantIsolation(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $tenantId = app()->bound('relova.current_tenant')
                    ? app('relova.current_tenant')
                    : null;

                if (empty($tenantId)) {
                    // No tenant context — return empty results unconditionally.
                    // whereRaw('false') leaks nothing and lets the UI show an empty state
                    // on the central domain and before any tenants are registered.
                    $builder->whereRaw('false');

                    return;
                }

                $builder->where($model->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('tenant_id')) && app()->bound('relova.current_tenant')) {
                $model->setAttribute('tenant_id', app('relova.current_tenant'));
            }
        });
    }
}
