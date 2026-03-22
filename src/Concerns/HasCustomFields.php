<?php

declare(strict_types=1);

namespace Relova\Concerns;

use Illuminate\Support\Str;
use Relova\Models\CustomFieldDefinition;
use Relova\Models\CustomFieldValue;
use Relova\Services\CustomFieldValidator;

/**
 * Adds custom-field support to an Eloquent model via EAV + JSONB snapshot.
 *
 * Apply this trait to any model that has opted into custom fields.
 * The model's table should have a `custom_fields` JSONB column (added
 * via `php artisan relova:add-custom-fields {table}`). If the column
 * is absent, the trait gracefully falls back to EAV-only reads.
 *
 * Usage:
 *   class Machine extends Model {
 *       use HasCustomFields;
 *   }
 *
 *   $machine->setCustomField('serial_number', 'SN-12345');
 *   $machine->getCustomField('serial_number'); // 'SN-12345'
 *   $machine->getAllCustomFields(); // merged definitions + current values
 */
trait HasCustomFields
{
    /**
     * Automatically merge the 'custom_fields' → 'array' cast when this
     * trait is used. Laravel calls initialize{TraitName}() on every new
     * model instance, which lets us add the cast without requiring the
     * host model to declare it.
     */
    public function initializeHasCustomFields(): void
    {
        $this->mergeCasts(['custom_fields' => 'array']);
    }

    /**
     * Get the entity type string used to scope custom field definitions.
     * Defaults to the snake_case class name. Override per model if needed.
     */
    public function getEntityType(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * Set a custom field value for this entity instance.
     *
     * Validates the value against the field definition, writes to the EAV
     * table via updateOrCreate, then updates the JSONB snapshot column
     * if it exists on the table.
     *
     * @throws \Relova\Exceptions\CustomFieldValidationException
     */
    public function setCustomField(string $name, mixed $value): void
    {
        $definition = $this->resolveFieldDefinition($name);

        // Validate before writing
        app(CustomFieldValidator::class)->validate($definition, $value);

        // Write to EAV table
        $fieldValue = CustomFieldValue::updateOrCreate(
            [
                'field_id' => $definition->id,
                'entity_id' => $this->getKey(),
            ],
            [
                'entity_type' => $this->getEntityType(),
            ],
        );

        $fieldValue->setTypedValue($definition->field_type, $value);
        $fieldValue->save();

        // Update JSONB snapshot if the column exists
        if ($this->hasCustomFieldsColumn()) {
            $this->updateCustomFieldSnapshot($name, $value);
        }
    }

    /**
     * Get a single custom field value.
     *
     * Reads from JSONB snapshot if available, falls back to EAV table.
     */
    public function getCustomField(string $name): mixed
    {
        // Fast path: read from JSONB snapshot
        if ($this->hasCustomFieldsColumn()) {
            $snapshot = $this->custom_fields ?? [];

            return $snapshot[$name] ?? null;
        }

        // Fallback: read from EAV
        return $this->getCustomFieldFromEav($name);
    }

    /**
     * Get all custom fields with their definitions and current values.
     *
     * Reads values from JSONB snapshot if available, falls back to EAV.
     *
     * @return \Illuminate\Support\Collection<int, array{definition: CustomFieldDefinition, value: mixed}>
     */
    public function getAllCustomFields(): \Illuminate\Support\Collection
    {
        $definitions = CustomFieldDefinition::cachedForEntity($this->getEntityType());

        if ($this->hasCustomFieldsColumn()) {
            $snapshot = $this->custom_fields ?? [];

            return $definitions->map(fn (CustomFieldDefinition $definition) => [
                'definition' => $definition,
                'value' => $snapshot[$definition->name] ?? null,
            ]);
        }

        // Fallback: batch-read from EAV
        $values = CustomFieldValue::where('entity_type', $this->getEntityType())
            ->where('entity_id', $this->getKey())
            ->get()
            ->keyBy('field_id');

        return $definitions->map(function (CustomFieldDefinition $definition) use ($values) {
            $fieldValue = $values->get($definition->id);

            return [
                'definition' => $definition,
                'value' => $fieldValue?->getTypedValue($definition->field_type),
            ];
        });
    }

    /**
     * Full snapshot rebuild from EAV values.
     *
     * Reads all EAV values for this entity instance and rebuilds the
     * JSONB snapshot column. Used after bulk imports or definition changes.
     */
    public function syncCustomFieldSnapshot(): void
    {
        if (! $this->hasCustomFieldsColumn()) {
            return;
        }

        $definitions = CustomFieldDefinition::cachedForEntity($this->getEntityType());

        $snapshot = [];

        foreach ($definitions as $definition) {
            $fieldValue = CustomFieldValue::where('field_id', $definition->id)
                ->where('entity_id', $this->getKey())
                ->first();

            if ($fieldValue) {
                $snapshot[$definition->name] = $fieldValue->getTypedValue($definition->field_type);
            }
        }

        $this->forceFill(['custom_fields' => $snapshot])->saveQuietly();
    }

    /**
     * Relationship: custom field values for this entity instance (EAV rows).
     */
    public function customFieldValues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', $this->getEntityType());
    }

    /**
     * Resolve a field definition by name for this entity type.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function resolveFieldDefinition(string $name): CustomFieldDefinition
    {
        return CustomFieldDefinition::where('entity_type', $this->getEntityType())
            ->where('name', $name)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Read a single custom field value from the EAV table.
     */
    private function getCustomFieldFromEav(string $name): mixed
    {
        $definition = CustomFieldDefinition::where('entity_type', $this->getEntityType())
            ->where('name', $name)
            ->first();

        if (! $definition) {
            return null;
        }

        $fieldValue = CustomFieldValue::where('field_id', $definition->id)
            ->where('entity_id', $this->getKey())
            ->first();

        if (! $fieldValue) {
            return null;
        }

        return $fieldValue->getTypedValue($definition->field_type);
    }

    /**
     * Update the JSONB snapshot column with a single field change.
     *
     * Uses PostgreSQL's || merge operator for atomic update when available,
     * falls back to PHP merge for SQLite/other DBs.
     */
    private function updateCustomFieldSnapshot(string $name, mixed $value): void
    {
        $driver = $this->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $jsonPatch = json_encode([$name => $value], JSON_THROW_ON_ERROR);

            $this->getConnection()
                ->table($this->getTable())
                ->where($this->getKeyName(), $this->getKey())
                ->update([
                    'custom_fields' => $this->getConnection()->raw(
                        "custom_fields || ?::jsonb",
                        [$jsonPatch],
                    ),
                ]);
        } else {
            $snapshot = $this->custom_fields ?? [];
            $snapshot[$name] = $value;
            $this->forceFill(['custom_fields' => $snapshot])->saveQuietly();
        }

        // Refresh the in-memory attribute
        $this->refresh();
    }

    /**
     * Cache for the column existence check.
     *
     * @var array<string, bool>
     */
    private static array $customFieldsColumnCache = [];

    /**
     * Check if the model's table has the custom_fields JSONB column.
     *
     * Result is cached per table name within the current request.
     * Call clearCustomFieldsColumnCache() if the schema changes at runtime.
     */
    private function hasCustomFieldsColumn(): bool
    {
        $table = $this->getTable();

        if (! isset(self::$customFieldsColumnCache[$table])) {
            self::$customFieldsColumnCache[$table] = \Illuminate\Support\Facades\Schema::hasColumn($table, 'custom_fields');
        }

        return self::$customFieldsColumnCache[$table];
    }

    /**
     * Clear the static column existence cache. Useful in tests when
     * columns are added/dropped dynamically between assertions.
     */
    public static function clearCustomFieldsColumnCache(): void
    {
        self::$customFieldsColumnCache = [];
    }
}
