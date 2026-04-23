<?php

declare(strict_types=1);

namespace Relova\Security;

use Relova\Exceptions\TenantContextException;

/**
 * Resolves and validates the current tenant context for Relova operations.
 *
 * The tenant_id is bound into the container as `relova.current_tenant` by:
 *   - The RelovaApiAuth middleware (for SDK/API consumers — derived from API key)
 *   - The host application (for in-process use — set by the host's tenancy resolver)
 *
 * This class is the single point that READS that binding. Code that needs the
 * tenant_id should call current() rather than reading the container directly,
 * so the failure mode (TenantContextException) is consistent everywhere.
 */
final class TenantIsolationGuard
{
    /**
     * Returns the bound tenant_id, throwing if none is set.
     */
    public function current(): string
    {
        if (! app()->bound('relova.current_tenant')) {
            throw new TenantContextException(
                'No tenant context bound. Resolve the tenant server-side and call '
                ."app()->instance('relova.current_tenant', \$tenantId) first."
            );
        }

        $tenantId = app('relova.current_tenant');

        if (! is_string($tenantId) || $tenantId === '') {
            throw new TenantContextException(
                'Bound relova.current_tenant must be a non-empty string.'
            );
        }

        return $tenantId;
    }

    /**
     * Returns true if a tenant context is currently bound.
     */
    public function hasContext(): bool
    {
        if (! app()->bound('relova.current_tenant')) {
            return false;
        }

        $tenantId = app('relova.current_tenant');

        return is_string($tenantId) && $tenantId !== '';
    }

    /**
     * Bind a tenant context for the duration of a callback, then restore.
     * Useful for jobs and CLI commands that need to operate on a specific tenant.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(string $tenantId, callable $callback): mixed
    {
        $previous = app()->bound('relova.current_tenant')
            ? app('relova.current_tenant')
            : null;

        app()->instance('relova.current_tenant', $tenantId);

        try {
            return $callback();
        } finally {
            if ($previous === null) {
                app()->forgetInstance('relova.current_tenant');
            } else {
                app()->instance('relova.current_tenant', $previous);
            }
        }
    }

    /**
     * Asserts that the supplied tenant_id matches the bound context.
     * Throws TenantContextException on mismatch — used to harden cross-cutting
     * code paths against tenant_id parameters being passed in by mistake.
     */
    public function assertMatches(string $tenantId): void
    {
        if ($this->current() !== $tenantId) {
            throw new TenantContextException(
                'Tenant mismatch: bound context does not match supplied tenant_id.'
            );
        }
    }
}
