<?php

declare(strict_types=1);

namespace Relova\DTO;

/**
 * Result of a single-record cache lookup. The `data` property is the row
 * itself (associative array) or null when not found.
 */
final class CacheGetResult extends CacheResult
{
    public static function notFound(string $warning = 'Record not found in cache or remote source.'): self
    {
        return new self(
            data: null,
            source: 'live',
            isFresh: false,
            cachedAt: null,
            isStale: false,
            found: false,
            warning: $warning,
        );
    }

    public static function staleSnapshot(array $snapshot, ?string $cachedAt, string $warning): self
    {
        return new self(
            data: $snapshot,
            source: 'stale_snapshot',
            isFresh: false,
            cachedAt: $cachedAt,
            isStale: true,
            found: true,
            warning: $warning,
        );
    }

    public static function fromCache(array $row, string $source, ?string $cachedAt): self
    {
        return new self(
            data: $row,
            source: $source,
            isFresh: true,
            cachedAt: $cachedAt,
            isStale: false,
            found: true,
        );
    }

    public static function live(array $row): self
    {
        return new self(
            data: $row,
            source: 'live',
            isFresh: true,
            cachedAt: now()->toIso8601String(),
            isStale: false,
            found: true,
        );
    }
}
