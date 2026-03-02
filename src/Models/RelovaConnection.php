<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Represents a connection to a remote data source.
 * Stores configuration only — host, port, driver type, credentials.
 * Credentials are encrypted at rest.
 */
class RelovaConnection extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'connections';
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'config_meta' => 'array',
            'cache_ttl' => 'integer',
            'last_health_check_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'encrypted_password',
    ];

    /**
     * Boot: auto-generate uid on creation.
     */
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

    // --- Credential encryption ---

    public function getPasswordAttribute(): ?string
    {
        return $this->encrypted_password
            ? Crypt::decryptString($this->encrypted_password)
            : null;
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['encrypted_password'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    // --- Relationships ---

    public function entityReferences(): HasMany
    {
        return $this->hasMany(RelovaEntityReference::class, 'connection_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(RelovaFieldMapping::class, 'connection_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(RelovaApiKey::class, 'connection_id');
    }

    // --- Helper methods ---

    /**
     * Get the full configuration array needed by the driver.
     *
     * @return array<string, mixed>
     */
    public function toDriverConfig(): array
    {
        return array_filter([
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database_name,
            'schema' => $this->schema_name,
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }

    /**
     * Check if this connection's health status is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    /**
     * Check if this connection is degraded or unhealthy.
     */
    public function isDegraded(): bool
    {
        return in_array($this->health_status, ['degraded', 'unhealthy']);
    }

    // --- Scopes ---

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    public function scopeByDriver($query, string $driver)
    {
        return $query->where('driver_type', $driver);
    }

    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
