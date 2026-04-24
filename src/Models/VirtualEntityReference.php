<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Relova\Concerns\EnforcesTenantIsolation;

class VirtualEntityReference extends Model
{
    use EnforcesTenantIsolation;
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
            'local_overrides' => 'array',
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

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ConnectorModuleMapping::class, 'mapping_id');
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
