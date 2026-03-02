<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A virtual entity reference — a small local pointer record linking
 * a consumer application record to a remote entity. Contains:
 * - Which connector the entity lives in
 * - Which remote table and primary key column
 * - The remote primary key value
 * - A display snapshot for resilience
 *
 * One reference row per unique remote entity. If 100 tickets reference
 * the same Oracle asset, they all point to the same reference row.
 */
class RelovaEntityReference extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'entity_references';
    }

    protected function casts(): array
    {
        return [
            'display_snapshot' => 'array',
            'snapshot_refreshed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

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

    // --- Relationships ---

    public function connection(): BelongsTo
    {
        return $this->belongsTo(RelovaConnection::class, 'connection_id');
    }

    // --- Helper methods ---

    /**
     * Get a display value from the snapshot.
     */
    public function getSnapshotValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->display_snapshot, $key, $default);
    }

    /**
     * Get the display label (first non-null field from snapshot).
     */
    public function getDisplayLabel(): string
    {
        if (empty($this->display_snapshot)) {
            return "#{$this->remote_primary_value}";
        }

        $label = collect($this->display_snapshot)->first();

        return is_string($label) ? $label : "#{$this->remote_primary_value}";
    }

    /**
     * Check if the snapshot is stale and needs refresh.
     */
    public function isSnapshotStale(): bool
    {
        if (! $this->snapshot_refreshed_at) {
            return true;
        }

        $refreshInterval = (int) config('relova.snapshot_refresh_interval', 86400);

        return $this->snapshot_refreshed_at->addSeconds($refreshInterval)->isPast();
    }

    /**
     * Refresh the display snapshot from the remote source.
     */
    public function refreshSnapshot(array $remoteRow): void
    {
        $this->update([
            'display_snapshot' => $remoteRow,
            'snapshot_refreshed_at' => now(),
        ]);
    }

    // --- Scopes ---

    public function scopeForConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }

    public function scopeForTable($query, string $table)
    {
        return $query->where('remote_table', $table);
    }

    public function scopeStale($query)
    {
        $refreshInterval = (int) config('relova.snapshot_refresh_interval', 86400);

        return $query->where(function ($q) use ($refreshInterval) {
            $q->whereNull('snapshot_refreshed_at')
                ->orWhere('snapshot_refreshed_at', '<', now()->subSeconds($refreshInterval));
        });
    }

    /**
     * Find or create a reference for a specific remote entity.
     * Ensures one reference row per unique remote entity.
     */
    public static function findOrCreateReference(
        int $connectionId,
        string $remoteTable,
        string $remotePrimaryColumn,
        string $remotePrimaryValue,
        array $displaySnapshot = [],
    ): self {
        return static::firstOrCreate(
            [
                'connection_id' => $connectionId,
                'remote_table' => $remoteTable,
                'remote_primary_column' => $remotePrimaryColumn,
                'remote_primary_value' => $remotePrimaryValue,
            ],
            [
                'display_snapshot' => $displaySnapshot,
                'snapshot_refreshed_at' => ! empty($displaySnapshot) ? now() : null,
            ]
        );
    }
}
