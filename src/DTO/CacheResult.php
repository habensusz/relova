<?php

declare(strict_types=1);

namespace Relova\DTO;

/**
 * Generic cache read result. Used as the parent shape for both single-record
 * (CacheGetResult) and list (CacheListResult) reads. The host application uses
 * `source` and `isStale` to render freshness UI badges.
 */
class CacheResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly string $source,
        public readonly bool $isFresh,
        public readonly ?string $cachedAt,
        public readonly bool $isStale,
        public readonly bool $found,
        public readonly ?string $warning = null,
    ) {}

    /** @var list<string> Allowed source values. */
    public const SOURCES = [
        'redis_zone_b',
        'redis_zone_a',
        'live',
        'stale_snapshot',
    ];
}
