<?php

declare(strict_types=1);

namespace Relova\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Relova\Security\CacheEncryptor;

/**
 * Two-tier Redis cache for single-record lookups.
 *
 *   Zone B — Plaintext, 60s TTL, sub-millisecond reads.
 *            Key: relova:hot:{tenant}:{conn}:{table}:{pk}
 *
 *   Zone A — AES-256-GCM encrypted with per-tenant key, 30-min TTL.
 *            Key: relova:enc:{tenant}:{conn}:{table}:{pk}
 *
 * Reads check Zone B first; on miss they fall through to Zone A, decrypt,
 * and re-write Zone B before returning. Writes (`put`) write to BOTH zones
 * so that the encrypted layer always survives Zone B expiry.
 *
 * Both zones use Laravel's Cache facade so the array driver is fully
 * supported in development with no special-casing.
 */
final class TwoZoneCache
{
    public function __construct(
        private readonly CacheEncryptor $encryptor,
    ) {}

    /**
     * Read a row, checking Zone B then Zone A.
     * Returns [row, source] where source is 'redis_zone_b' | 'redis_zone_a' | null on miss.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    public function get(string $tenantId, string $connectionId, string $table, string $pkValue): array
    {
        $zoneB = $this->zoneBStore()->get($this->zoneBKey($tenantId, $connectionId, $table, $pkValue));
        if ($zoneB !== null) {
            return [$zoneB, 'redis_zone_b'];
        }

        $payload = $this->zoneAStore()->get($this->zoneAKey($tenantId, $connectionId, $table, $pkValue));
        if ($payload === null) {
            return [null, null];
        }

        $row = $this->encryptor->decrypt($payload, $tenantId);
        if (! is_array($row)) {
            return [null, null];
        }

        // Repopulate Zone B from Zone A hit.
        $this->zoneBStore()->put(
            $this->zoneBKey($tenantId, $connectionId, $table, $pkValue),
            $row,
            $this->zoneBTtl()
        );

        return [$row, 'redis_zone_a'];
    }

    /**
     * Write a row to BOTH zones atomically.
     */
    public function put(string $tenantId, string $connectionId, string $table, string $pkValue, array $row): void
    {
        $this->zoneBStore()->put(
            $this->zoneBKey($tenantId, $connectionId, $table, $pkValue),
            $row,
            $this->zoneBTtl()
        );

        $this->zoneAStore()->put(
            $this->zoneAKey($tenantId, $connectionId, $table, $pkValue),
            $this->encryptor->encrypt($row, $tenantId),
            $this->zoneATtl()
        );
    }

    /**
     * Forget a single record from both zones.
     */
    public function forget(string $tenantId, string $connectionId, string $table, string $pkValue): void
    {
        $this->zoneBStore()->forget($this->zoneBKey($tenantId, $connectionId, $table, $pkValue));
        $this->zoneAStore()->forget($this->zoneAKey($tenantId, $connectionId, $table, $pkValue));
    }

    public function zoneBKey(string $tenantId, string $connectionId, string $table, string $pkValue): string
    {
        return "relova:hot:{$tenantId}:{$connectionId}:{$table}:{$pkValue}";
    }

    public function zoneAKey(string $tenantId, string $connectionId, string $table, string $pkValue): string
    {
        return "relova:enc:{$tenantId}:{$connectionId}:{$table}:{$pkValue}";
    }

    private function zoneBStore(): CacheRepository
    {
        return Cache::store(config('relova.hot_cache', 'array'));
    }

    private function zoneAStore(): CacheRepository
    {
        return Cache::store(config('relova.persistent_cache', 'array'));
    }

    private function zoneBTtl(): int
    {
        return (int) config('relova.zone_b_ttl', 60);
    }

    private function zoneATtl(): int
    {
        return (int) config('relova.zone_a_ttl', 1800);
    }
}
