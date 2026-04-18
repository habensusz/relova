<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Local pointer to a remote entity in a Relova-connected source.
 *
 * Consumers store the uid/id of this record as their FK — NOT the remote
 * primary key. The display_snapshot holds a small last-known snapshot of
 * label fields for resilience; it is never authoritative data.
 *
 * One row per unique remote entity per tenant, enforced by a composite
 * unique index.
 */
class VirtualEntityReference extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'virtual_entity_references';
    }

    protected function casts(): array
    {
        return [
            'display_snapshot' => 'array',
            'snapshot_taken_at' => 'datetime',
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

    public function connection(): BelongsTo
    {
        return $this->belongsTo(RelovaConnection::class, 'connection_id');
    }

    /**
     * Convenience accessor: first snapshot value (typically a label).
     */
    public function getDisplayLabel(): string
    {
        $snapshot = $this->display_snapshot ?? [];
        if ($snapshot === []) {
            return "#{$this->remote_pk_value}";
        }

        $label = collect($snapshot)->first();

        return is_scalar($label) ? (string) $label : "#{$this->remote_pk_value}";
    }
}
