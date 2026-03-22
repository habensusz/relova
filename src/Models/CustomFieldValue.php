<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a single custom field value for an entity instance (EAV pattern).
 *
 * Each record links a CustomFieldDefinition to a specific entity row via
 * entity_type + entity_id. Values are stored in typed columns to avoid
 * runtime casting.
 *
 * @property int $id
 * @property int $field_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $value_text
 * @property float|null $value_number
 * @property string|null $value_date
 * @property bool|null $value_boolean
 */
class CustomFieldValue extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'custom_field_values';
    }

    protected function casts(): array
    {
        return [
            'field_id' => 'integer',
            'entity_id' => 'integer',
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_boolean' => 'boolean',
        ];
    }

    /**
     * The field definition this value belongs to.
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_id');
    }

    /**
     * Get the typed value based on the field definition's type.
     */
    public function getTypedValue(string $fieldType): mixed
    {
        return match ($fieldType) {
            'text' => $this->value_text,
            'number' => $this->value_number,
            'date' => $this->value_date,
            'boolean' => $this->value_boolean,
            default => $this->value_text,
        };
    }

    /**
     * Set the typed value into the correct column based on field type.
     */
    public function setTypedValue(string $fieldType, mixed $value): static
    {
        // Clear all typed columns first
        $this->value_text = null;
        $this->value_number = null;
        $this->value_date = null;
        $this->value_boolean = null;

        match ($fieldType) {
            'text' => $this->value_text = $value,
            'number' => $this->value_number = $value,
            'date' => $this->value_date = $value,
            'boolean' => $this->value_boolean = (bool) $value,
            default => $this->value_text = $value,
        };

        return $this;
    }
}
