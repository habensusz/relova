<?php

declare(strict_types=1);

namespace Relova\DTO;

/**
 * Result of a list/page cache lookup.
 *
 * `data` (rows) is the array of records for the requested page.
 * `total` is the total record count (best-effort estimate when sourced live).
 */
final class CacheListResult extends CacheResult
{
    public function __construct(
        array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        string $source,
        bool $isFresh,
        ?string $cachedAt,
        bool $isStale,
        ?string $warning = null,
    ) {
        parent::__construct(
            data: $rows,
            source: $source,
            isFresh: $isFresh,
            cachedAt: $cachedAt,
            isStale: $isStale,
            found: true,
            warning: $warning,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        return (array) $this->data;
    }

    public static function fromCache(array $rows, int $total, int $page, int $perPage, ?string $cachedAt): self
    {
        return new self(
            rows: $rows,
            total: $total,
            page: $page,
            perPage: $perPage,
            source: 'redis_zone_a',
            isFresh: true,
            cachedAt: $cachedAt,
            isStale: false,
        );
    }

    public static function live(array $rows, int $total, int $page, int $perPage): self
    {
        return new self(
            rows: $rows,
            total: $total,
            page: $page,
            perPage: $perPage,
            source: 'live',
            isFresh: true,
            cachedAt: now()->toIso8601String(),
            isStale: false,
        );
    }

    public static function empty(int $page, int $perPage, string $warning): self
    {
        return new self(
            rows: [],
            total: 0,
            page: $page,
            perPage: $perPage,
            source: 'stale_snapshot',
            isFresh: false,
            cachedAt: null,
            isStale: true,
            warning: $warning,
        );
    }
}
