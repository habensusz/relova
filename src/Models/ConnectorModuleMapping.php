<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Relova\Concerns\EnforcesTenantIsolation;

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
    use EnforcesTenantIsolation;
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
            'joins' => 'array',
            'default_values' => 'array',
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

    /**
     * Build the SELECT column list for queries that must include joined columns.
     *
     * When a mapping has joins and display_fields, returns:
     *   ["{remote_table}.*", "joinTable.col", ...]
     *
     * QueryExecutor turns "locations.location_name" into `"locations"."location_name"`
     * which the remote DB returns as the flat key "location_name" in the result row.
     * Ambiguous PK/timestamp columns on joined tables are excluded to prevent
     * overwriting the main table's own values.
     *
     * @return array<int, string>
     */
    public function snapshotColumns(): array
    {
        if (empty($this->joins) || empty($this->display_fields)) {
            return ['*'];
        }

        $ambiguous = ['id', 'uid', 'created_at', 'updated_at', 'deleted_at', 'tenant_id'];

        $joinCols = collect($this->display_fields)
            ->filter(fn (string $f) => str_contains($f, '.'))
            ->reject(fn (string $f) => in_array(substr(strrchr($f, '.'), 1), $ambiguous, true))
            ->values()
            ->all();

        if (empty($joinCols)) {
            return ['*'];
        }

        return array_merge([$this->remote_table.'.*'], $joinCols);
    }
}
