<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Relova\Concerns\EnforcesTenantIsolation;

/**
 * Per-table sync configuration. Controls how aggressively a remote table
 * is cached in Redis (sync_mode), how often it is refreshed (ttl_minutes),
 * and which columns are pulled on each refresh.
 *
 * Stores configuration only — never row data.
 */
class RelovaSyncConfig extends Model
{
    use EnforcesTenantIsolation;
    use HasUuids;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'sync_configs';
    }

    protected function casts(): array
    {
        return [
            'ttl_minutes' => 'integer',
            'hot_set_size' => 'integer',
            'sync_filter' => 'array',
            'cached_columns' => 'array',
            'display_columns' => 'array',
            'active' => 'boolean',
            'last_full_sync_at' => 'datetime',
            'next_sync_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uid)) {
                $model->uid = Str::random(22);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(RelovaConnection::class, 'connection_id');
    }
}
