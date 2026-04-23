<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Relova\Concerns\EnforcesTenantIsolation;

/**
 * Append-only audit log of every Relova data access.
 *
 * Records WHO accessed WHAT (table, column names, operators), HOW MANY rows,
 * and WHEN — but NEVER the actual filter values or returned data.
 *
 * Written asynchronously by WriteAuditLog job for normal access; synchronously
 * by AuditLogger::logCriticalSync() for security events (blocked queries,
 * authentication failures).
 */
class RelovaAuditLog extends Model
{
    use EnforcesTenantIsolation;
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'audit_logs';
    }

    protected function casts(): array
    {
        return [
            'rows_accessed' => 'integer',
            'query_metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
