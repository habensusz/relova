<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A declarative rule that fires when a synced VirtualEntityReference's
 * snapshot matches a condition. The host application (mainkeeperx2) listens
 * for the resulting event and decides what to do — usually create a WO.
 */
class TriggerRule extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'array',
        'active' => 'boolean',
        'cooldown_minutes' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'trigger_rules';
    }

    protected static function booted(): void
    {
        static::creating(function (TriggerRule $rule) {
            if (empty($rule->uid)) {
                $rule->uid = Str::random(22);
            }
        });
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ConnectorModuleMapping::class, 'mapping_id');
    }

    public function inCooldown(): bool
    {
        if (! $this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->copy()
            ->addMinutes((int) $this->cooldown_minutes)
            ->isFuture();
    }
}
