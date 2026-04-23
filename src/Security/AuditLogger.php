<?php

declare(strict_types=1);

namespace Relova\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Relova\Jobs\WriteAuditLog;
use Relova\Models\RelovaAuditLog;

/**
 * Writes audit log entries for every Relova data access.
 *
 * Two write paths:
 *   - logAsync()        — dispatches a WriteAuditLog job (queue: relova-audit).
 *                         Used for normal, successful access. Zero user latency.
 *   - logCriticalSync() — writes synchronously. Used for security events
 *                         (blocked queries, auth failures) where we must
 *                         persist the record before the request returns,
 *                         even at the cost of a few milliseconds.
 *
 * The `query_metadata` JSONB column stores column names and operators only —
 * never filter values or returned data. Use FieldMasker if extra safety is
 * needed when assembling metadata.
 */
final class AuditLogger
{
    public function __construct(
        private readonly FieldMasker $masker,
        private readonly TenantIsolationGuard $tenantGuard,
    ) {}

    /**
     * Log a normal data access asynchronously. Returns immediately.
     *
     * @param  array<string, mixed>  $queryMetadata  Column names and operators only.
     */
    public function logAsync(
        string $action,
        ?string $connectionId = null,
        ?string $remoteTable = null,
        int $rowsAccessed = 0,
        array $queryMetadata = [],
        string $result = 'success',
        ?string $failureReason = null,
    ): void {
        $payload = $this->buildPayload(
            $action,
            $connectionId,
            $remoteTable,
            $rowsAccessed,
            $queryMetadata,
            $result,
            $failureReason,
        );

        Bus::dispatch(
            (new WriteAuditLog($payload))->onQueue(config('relova.audit_queue', 'sync'))
        );
    }

    /**
     * Log a security-critical event synchronously. Blocks until the row is
     * persisted to the database. Use only for blocked queries, auth failures,
     * tenant-context violations, etc.
     *
     * @param  array<string, mixed>  $queryMetadata
     */
    public function logCriticalSync(
        string $action,
        ?string $connectionId = null,
        ?string $remoteTable = null,
        int $rowsAccessed = 0,
        array $queryMetadata = [],
        string $result = 'blocked',
        ?string $failureReason = null,
    ): void {
        $payload = $this->buildPayload(
            $action,
            $connectionId,
            $remoteTable,
            $rowsAccessed,
            $queryMetadata,
            $result,
            $failureReason,
        );

        // Critical events bypass the global tenant scope on inserts only,
        // since the underlying constructor already binds tenant_id from the
        // bound context. The scope still constrains reads.
        RelovaAuditLog::query()->insert([
            'id' => (string) Str::uuid(),
            ...$payload,
        ]);
    }

    /**
     * Sanitize a structured filter array into metadata: keep column names
     * and operators, replace values with placeholder. Safe to feed into the
     * `query_metadata` field.
     *
     * @param  array<int, array{column?: string, op?: string, value?: mixed}>  $filters
     * @return array<int, array{column: string, op: string}>
     */
    public function sanitizeFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $f) {
            if (! isset($f['column'])) {
                continue;
            }
            $out[] = [
                'column' => (string) $f['column'],
                'op' => (string) ($f['op'] ?? '='),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $queryMetadata
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $action,
        ?string $connectionId,
        ?string $remoteTable,
        int $rowsAccessed,
        array $queryMetadata,
        string $result,
        ?string $failureReason,
    ): array {
        return [
            'tenant_id' => $this->tenantGuard->hasContext()
                ? $this->tenantGuard->current()
                : 'unknown',
            'actor_type' => $this->resolveActorType(),
            'actor_id' => $this->resolveActorId(),
            'action' => $action,
            'connection_id' => $connectionId,
            'remote_table' => $remoteTable,
            'rows_accessed' => $rowsAccessed,
            'source_ip' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
            'query_metadata' => json_encode($queryMetadata),
            'result' => $result,
            'failure_reason' => $failureReason,
            'occurred_at' => now(),
        ];
    }

    private function resolveActorType(): string
    {
        if (app()->runningInConsole()) {
            return 'system';
        }

        return Auth::check() ? 'user' : 'system';
    }

    private function resolveActorId(): string
    {
        $user = Auth::user();
        if ($user instanceof Authenticatable) {
            return (string) $user->getAuthIdentifier();
        }

        if (app()->bound('relova.current_api_key_id')) {
            return 'api_key:'.app('relova.current_api_key_id');
        }

        return 'system';
    }
}
