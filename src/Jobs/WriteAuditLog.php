<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Relova\Models\RelovaAuditLog;

/**
 * Append-only audit log writer (asynchronous path).
 *
 * Receives a pre-built payload from AuditLogger::logAsync() and inserts it
 * directly via the query builder, bypassing the global tenant scope (which
 * is for reads). The tenant_id is already in the payload, so isolation is
 * preserved at the row level.
 */
class WriteAuditLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload)
    {
        $this->onQueue(config('relova.audit_queue', 'sync'));
    }

    public function handle(): void
    {
        DB::table((new RelovaAuditLog)->getTable())->insert([
            'id' => (string) Str::uuid(),
            ...$this->payload,
        ]);
    }
}
