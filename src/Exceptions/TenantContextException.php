<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

/**
 * Thrown when a Relova model query is attempted without a bound tenant context.
 *
 * The tenant_id MUST be resolved server-side (host app session or RelovaApiAuth
 * middleware) and bound via app()->instance('relova.current_tenant', $tenantId)
 * before any Relova model is queried.
 */
class TenantContextException extends RuntimeException {}
