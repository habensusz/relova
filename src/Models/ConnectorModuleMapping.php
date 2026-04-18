<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Maps a Relova connection to a consuming host-app module.
 *
 * Defines:
 *   - Which remote table feeds the module.
 *   - How local field names translate to remote column names.
 *   - Which fields are captured in the display snapshot.
 *   - Static filters applied to every pass-through query.
 *   - How the module should resolve references (virtual, snapshot_cache, on_demand).
 */
class ConnectorModuleMapping extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'connector_module_mappings';
    }

    protected function casts(): array
    {
        return [
            'field_mappings' => 'array',
            'display_fields' => 'array',
            'filters' => 'array',
            'cache_ttl_minutes' => 'integer',
            'active' => 'boolean',
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
     * Local field (module-facing) -> remote column (source-side).
     *
     * @return array<string, string>
     */
    public function fieldMap(): array
    {
        return $this->field_mappings ?? [];
    }

    public function remoteColumnFor(string $localField): ?string
    {
        return $this->fieldMap()[$localField] ?? null;
    }

    public function localFieldFor(string $remoteColumn): ?string
    {
        return array_search($remoteColumn, $this->fieldMap(), true) ?: null;
    }
}
