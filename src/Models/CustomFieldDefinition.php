<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Relova\Services\RelovaCacheKeys;

/**
 * Defines a custom field for a given entity type.
 *
 * Each definition specifies the field name (machine key), display label,
 * data type, validation constraints, and display sort order.
 *
 * @property int $id
 * @property string $entity_type
 * @property string $name
 * @property string $label
 * @property string $field_type
 * @property bool $is_required
 * @property int|null $min_value
 * @property int|null $max_value
 * @property int|null $min_length
 * @property int|null $max_length
 * @property string|null $regex_pattern
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 */
class CustomFieldDefinition extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'custom_field_definitions';
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'min_value' => 'integer',
            'max_value' => 'integer',
            'min_length' => 'integer',
            'max_length' => 'integer',
            'sort_order' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * All stored values for this field definition.
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'field_id');
    }

    /**
     * Active definitions for a given entity type, ordered by sort_order.
     */
    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Returns the list of valid field types.
     *
     * @return string[]
     */
    public static function fieldTypes(): array
    {
        return ['text', 'number', 'date', 'boolean'];
    }

    /**
     * Get active definitions for an entity type, cached until busted.
     *
     * Self-heals a poisoned entry: rememberForever() persists indefinitely,
     * so if unserialize() ever hands back a __PHP_Incomplete_Class (stale
     * class definition from a previous deploy, dev-main package drift, etc.)
     * the stored value fails the Collection<static> shape and gets refetched
     * instead of throwing a TypeError on every request.
     *
     * @return Collection<int, static>
     */
    public static function cachedForEntity(string $entityType): Collection
    {
        $key = RelovaCacheKeys::definitions($entityType);
        $cached = Cache::get($key);

        if (! static::isValidDefinitionsCollection($cached)) {
            Cache::forget($key);
            $cached = null;
        }

        return $cached ?? Cache::rememberForever(
            $key,
            fn () => static::forEntity($entityType)->get(),
        );
    }

    /**
     * Whether a cached value is a genuine Collection of hydrated instances,
     * as opposed to a __PHP_Incomplete_Class produced by a failed unserialize.
     */
    protected static function isValidDefinitionsCollection(mixed $value): bool
    {
        return $value instanceof Collection
            && $value->every(fn ($item) => $item instanceof static);
    }
}
