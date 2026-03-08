<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Field mapping configuration for a consumer application module
 * against a Relova connection. Defines which remote table feeds
 * which module and how remote columns correspond to module fields.
 */
class RelovaFieldMapping extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'field_mappings';
    }

    protected function casts(): array
    {
        return [
            'column_mappings' => 'array',
            'transformation_rules' => 'array',
            'enabled' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
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
     * Get the local field that a remote column maps to.
     */
    public function getLocalField(string $remoteColumn): ?string
    {
        return collect($this->column_mappings)->firstWhere('remote_column', $remoteColumn)['local_field'] ?? null;
    }

    /**
     * Get the remote column that a local field maps to.
     */
    public function getRemoteColumn(string $localField): ?string
    {
        return collect($this->column_mappings)->firstWhere('local_field', $localField)['remote_column'] ?? null;
    }

    /**
     * Check whether a given column mapping entry defines a belongs_to relation.
     * Entries with relation_type = 'belongs_to' mean the raw remote value should
     * be resolved to a local model id rather than stored as-is.
     *
     * @param  array<string, mixed>  $entry  A single column_mappings entry.
     */
    public function isRelationMapping(array $entry): bool
    {
        return ($entry['relation_type'] ?? null) === 'belongs_to';
    }

    /**
     * Return all column_mapping entries that define belongs_to relations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelationMappings(): array
    {
        return collect($this->column_mappings ?? [])
            ->filter(fn (array $entry) => $this->isRelationMapping($entry))
            ->values()
            ->all();
    }

    /**
     * Return all column_mapping entries that are direct value copies (no relation).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDirectMappings(): array
    {
        return collect($this->column_mappings ?? [])
            ->reject(fn (array $entry) => $this->isRelationMapping($entry))
            ->values()
            ->all();
    }

    /**
     * Expose transformation logic publicly so MappingDataLoader can call it
     * per-field without processing the entire row.
     */
    public function applyTransformationPublic(string $field, mixed $value): mixed
    {
        return $this->applyTransformation($field, $value);
    }

    /**
     * Apply this mapping to a remote row, returning local-field-keyed data.
     *
     * @return array<string, mixed>
     */
    public function applyToRow(array $remoteRow): array
    {
        $mapped = [];

        foreach ($this->column_mappings as $mapping) {
            $remoteCol = $mapping['remote_column'] ?? null;
            $localField = $mapping['local_field'] ?? null;

            if ($remoteCol && $localField && array_key_exists($remoteCol, $remoteRow)) {
                $value = $remoteRow[$remoteCol];
                $value = $this->applyTransformation($localField, $value);
                $mapped[$localField] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Apply transformation rules to a value.
     */
    protected function applyTransformation(string $field, mixed $value): mixed
    {
        if (empty($this->transformation_rules)) {
            return $value;
        }

        foreach ($this->transformation_rules as $rule) {
            if (($rule['field'] ?? '') !== $field) {
                continue;
            }

            $value = match ($rule['type'] ?? null) {
                'uppercase' => is_string($value) ? strtoupper($value) : $value,
                'lowercase' => is_string($value) ? strtolower($value) : $value,
                'trim' => is_string($value) ? trim($value) : $value,
                'date_format' => $this->transformDate($value, $rule),
                'value_map' => $rule['mapping'][$value] ?? $value,
                'prefix' => ($rule['prefix'] ?? '').$value,
                'suffix' => $value.($rule['suffix'] ?? ''),
                default => $value,
            };
        }

        return $value;
    }

    protected function transformDate(mixed $value, array $rule): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat($rule['from_format'] ?? 'Y-m-d', $value)
                ->format($rule['to_format'] ?? 'Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Record that this mapping was used.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    // --- Scopes ---

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('target_module', $module);
    }

    public function scopeForConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }
}
