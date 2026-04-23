<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Cache\ListCache;
use Relova\Cache\TwoZoneCache;
use Relova\DTO\CacheGetResult;
use Relova\DTO\CacheListResult;
use Relova\Models\RelovaConnection;
use Relova\Security\AuditLogger;
use Relova\Security\TenantIsolationGuard;

/**
 * Front door for all cached row-data reads.
 *
 * Implements the Section §7 read decision tree:
 *
 *   Single record:
 *     1. Zone B Redis (plaintext, 60s)        → CacheGetResult source='redis_zone_b'
 *     2. Zone A Redis (encrypted, 30min)      → CacheGetResult source='redis_zone_a'
 *     3. Pass-through to remote               → CacheGetResult source='live'
 *        (writes both zones before returning)
 *     X. Remote unreachable + no cache        → CacheGetResult::notFound() / staleSnapshot()
 *
 *   List page:
 *     1. Pre-built page Redis key (300s)      → CacheListResult source='redis_zone_a'
 *     2. Pass-through to remote               → CacheListResult source='live'
 *        (writes list page before returning)
 *
 * Every hit dispatches an async audit log entry. Misses dispatched on the
 * pass-through path. Errors during pass-through return a stale-snapshot DTO
 * with a warning rather than throwing — the host app shows a degraded UI badge.
 */
final class CacheManager
{
    public function __construct(
        private readonly TwoZoneCache $twoZone,
        private readonly ListCache $listCache,
        private readonly QueryExecutor $executor,
        private readonly AuditLogger $audit,
        private readonly TenantIsolationGuard $tenantGuard,
    ) {}

    /**
     * Read a single record by primary key. Always returns a CacheGetResult —
     * never throws on remote failure.
     */
    public function get(
        RelovaConnection $connection,
        string $remoteTable,
        string $pkColumn,
        string $pkValue,
    ): CacheGetResult {
        $tenantId = $this->tenantGuard->current();

        // Steps 1 & 2 — Zone B then Zone A.
        [$row, $source] = $this->twoZone->get($tenantId, (string) $connection->id, $remoteTable, $pkValue);
        if ($row !== null && $source !== null) {
            $this->audit->logAsync(
                action: 'cache_hit',
                connectionId: (string) $connection->id,
                remoteTable: $remoteTable,
                rowsAccessed: 1,
                queryMetadata: ['pk' => $pkColumn, 'source' => $source],
            );

            return CacheGetResult::fromCache($row, $source, now()->toIso8601String());
        }

        // Step 3 — Pass-through to remote, write both zones.
        try {
            $row = $this->executor->fetchOne($connection, $remoteTable, $pkColumn, $pkValue);
        } catch (\Throwable $e) {
            $this->audit->logAsync(
                action: 'query',
                connectionId: (string) $connection->id,
                remoteTable: $remoteTable,
                rowsAccessed: 0,
                queryMetadata: ['pk' => $pkColumn],
                result: 'failure',
                failureReason: $e->getMessage(),
            );

            return CacheGetResult::notFound('Remote source unavailable: '.$e->getMessage());
        }

        if ($row === null) {
            return CacheGetResult::notFound();
        }

        $this->twoZone->put($tenantId, (string) $connection->id, $remoteTable, $pkValue, $row);

        $this->audit->logAsync(
            action: 'cache_miss',
            connectionId: (string) $connection->id,
            remoteTable: $remoteTable,
            rowsAccessed: 1,
            queryMetadata: ['pk' => $pkColumn],
        );

        return CacheGetResult::live($row);
    }

    /**
     * Read a paginated list. On cache hit, returns immediately. On miss,
     * pulls from remote and stores the page back to ListCache.
     */
    public function list(
        RelovaConnection $connection,
        string $remoteTable,
        array $filters = [],
        string $sortColumn = 'id',
        string $sortDirection = 'asc',
        int $page = 1,
        int $perPage = 50,
    ): CacheListResult {
        $tenantId = $this->tenantGuard->current();
        $sortKey = $sortColumn.' '.strtolower($sortDirection);

        $cached = $this->listCache->get(
            $tenantId,
            (string) $connection->id,
            $remoteTable,
            $filters,
            $sortKey,
            $page,
            $perPage,
        );

        if ($cached !== null && isset($cached['rows'])) {
            $this->audit->logAsync(
                action: 'cache_hit',
                connectionId: (string) $connection->id,
                remoteTable: $remoteTable,
                rowsAccessed: count($cached['rows']),
                queryMetadata: $this->audit->sanitizeFilters($filters),
            );

            return CacheListResult::fromCache(
                rows: $cached['rows'],
                total: (int) ($cached['total'] ?? count($cached['rows'])),
                page: $page,
                perPage: $perPage,
                cachedAt: $cached['cached_at'] ?? null,
            );
        }

        // Pass-through.
        try {
            $rows = [];
            $conditions = $this->filtersToConditions($filters);
            $offset = ($page - 1) * $perPage;
            foreach ($this->executor->executePassThrough(
                $connection, $remoteTable, $conditions, ['*'], $perPage, $offset
            ) as $row) {
                $rows[] = $row;
            }
        } catch (\Throwable $e) {
            $this->audit->logAsync(
                action: 'query',
                connectionId: (string) $connection->id,
                remoteTable: $remoteTable,
                rowsAccessed: 0,
                queryMetadata: $this->audit->sanitizeFilters($filters),
                result: 'failure',
                failureReason: $e->getMessage(),
            );

            return CacheListResult::empty($page, $perPage, 'Remote source unavailable: '.$e->getMessage());
        }

        $payload = [
            'rows' => $rows,
            'total' => count($rows),
            'cached_at' => now()->toIso8601String(),
        ];

        $this->listCache->put(
            $tenantId,
            (string) $connection->id,
            $remoteTable,
            $filters,
            $sortKey,
            $page,
            $perPage,
            $payload,
        );

        // Promote each row to Zone B + Zone A so subsequent show-view reads are hot.
        foreach ($rows as $row) {
            if (isset($row[$sortColumn])) {
                $this->twoZone->put(
                    $tenantId,
                    (string) $connection->id,
                    $remoteTable,
                    (string) $row[$sortColumn],
                    $row,
                );
            }
        }

        $this->audit->logAsync(
            action: 'cache_miss',
            connectionId: (string) $connection->id,
            remoteTable: $remoteTable,
            rowsAccessed: count($rows),
            queryMetadata: $this->audit->sanitizeFilters($filters),
        );

        return CacheListResult::live($rows, count($rows), $page, $perPage);
    }

    /**
     * Convert a filter array into [column, op, value] tuples expected by
     * QueryExecutor::executePassThrough.
     *
     * @param  array<int, array{column: string, op?: string, value: mixed}>  $filters
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function filtersToConditions(array $filters): array
    {
        $out = [];
        foreach ($filters as $f) {
            if (! isset($f['column'])) {
                continue;
            }
            $out[] = [
                (string) $f['column'],
                (string) ($f['op'] ?? '='),
                $f['value'] ?? null,
            ];
        }

        return $out;
    }
}
