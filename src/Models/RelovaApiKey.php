<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * API key for authenticating external consumers against Relova's API.
 * Each key is scoped to a tenant and optionally to specific connections.
 */
class RelovaApiKey extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'api_keys';
    }

    protected function casts(): array
    {
        return [
            'scoped_connections' => 'array',
            'permissions' => 'array',
            'enabled' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'key_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uid)) {
                $model->uid = Str::random(22);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /**
     * Generate a new API key. Returns the plaintext key (shown once).
     */
    public static function generate(string $tenantId, string $name, array $permissions = ['*']): array
    {
        $plaintext = 'rlv_'.Str::random(48);

        $apiKey = static::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_hash' => hash('sha256', $plaintext),
            'key_prefix' => substr($plaintext, 0, 8),
            'permissions' => $permissions,
            'enabled' => true,
        ]);

        return [
            'api_key' => $apiKey,
            'plaintext_key' => $plaintext,
        ];
    }

    /**
     * Find an API key by its plaintext value.
     */
    public static function findByKey(string $plaintext): ?self
    {
        $hash = hash('sha256', $plaintext);

        return static::where('key_hash', $hash)
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Check if this key has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (in_array('*', $this->permissions ?? [])) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if this key can access a specific connection.
     */
    public function canAccessConnection(int $connectionId): bool
    {
        if (empty($this->scoped_connections)) {
            return true; // No scope restriction → access all
        }

        return in_array($connectionId, $this->scoped_connections);
    }

    /**
     * Record usage of this key.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if this key is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
