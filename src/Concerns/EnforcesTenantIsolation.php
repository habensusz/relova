<?php

declare(strict_types=1);

namespace Relova\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Relova\Exceptions\TenantContextException;

/**
 * Enforces tenant isolation on every query for a Relova model.
 *
 * Adds a global scope that constrains all queries to the currently-bound
 * tenant_id. The tenant_id is resolved server-side from the container
 * binding `relova.current_tenant`, set by the RelovaApiAuth middleware
 * (for SDK consumers) or the host app (for in-process consumers).
 *
 * If no tenant is bound, queries throw TenantContextException — there is
 * no "global" mode. This prevents accidental cross-tenant data leakage.
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
                    throw new TenantContextException(
                        'Relova model '.$model::class.' queried without a bound tenant context. '
                        .'Resolve the tenant server-side and bind it via '
                        ."app()->instance('relova.current_tenant', \$tenantId) before querying."
                    );
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
