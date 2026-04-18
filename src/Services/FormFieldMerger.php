<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Facades\Cache;
use Relova\Models\CustomFieldDefinition;

/**
 * Merges standard database schema columns with custom field definitions
 * into a unified field list that ResourceFormModal can render.
 *
 * Custom field definitions are converted to stdClass objects matching
 * the column structure returned by SchemaQueryTrait (column_name,
 * data_type, is_nullable, character_maximum_length, ordinal_position).
 */
class FormFieldMerger
{
    /**
     * Map of custom field types to PostgreSQL-compatible data types
     * used by ResourceFormModal's rendering logic.
     */
    private const FIELD_TYPE_MAP = [
        'text' => 'character varying',
        'number' => 'numeric',
        'date' => 'date',
        'boolean' => 'boolean',
    ];

    /**
     * Merge schema columns with custom field definitions for a given entity type.
     *
     * Custom fields are appended after schema columns, with ordinal_position
     * continuing from the last schema column.
     *
     * @param  array<int, \stdClass>  $schemaColumns  Columns from SchemaQueryTrait
     * @return array<int, \stdClass> Merged columns + custom fields
     */
    public function merge(array $schemaColumns, string $entityType): array
    {
        $customFields = $this->getCachedFieldsAsColumns($entityType, $schemaColumns);

        return array_merge($schemaColumns, $customFields);
    }

    /**
     * Get custom field definitions converted to column-style stdClass objects.
     *
     * Results are cached until busted by the observer.
     *
     * @param  array<int, \stdClass>  $schemaColumns
     * @return array<int, \stdClass>
     */
    private function getCachedFieldsAsColumns(string $entityType, array $schemaColumns): array
    {
        return Cache::rememberForever(
            RelovaCacheKeys::formFields($entityType),
            fn () => $this->buildFieldColumns($entityType, $schemaColumns),
        );
    }

    /**
     * Convert active custom field definitions into stdClass column objects.
     *
     * @param  array<int, \stdClass>  $schemaColumns
     * @return array<int, \stdClass>
     */
    private function buildFieldColumns(string $entityType, array $schemaColumns): array
    {
        $definitions = CustomFieldDefinition::cachedForEntity($entityType);

        if ($definitions->isEmpty()) {
            return [];
        }

        // Continue ordinal_position after the last schema column
        $lastOrdinal = collect($schemaColumns)
            ->max(fn ($col) => $col->ordinal_position ?? 0);

        $columns = [];

        foreach ($definitions as $index => $definition) {
            $columns[] = $this->definitionToColumn($definition, $lastOrdinal + $index + 1);
        }

        return $columns;
    }

    /**
     * Convert a single CustomFieldDefinition into a stdClass column object
     * compatible with ResourceFormModal's rendering pipeline.
     */
    private function definitionToColumn(CustomFieldDefinition $definition, int $ordinalPosition): \stdClass
    {
        $column = new \stdClass;

        // Prefix with cf_ to distinguish from schema columns and avoid collisions
        $column->column_name = 'cf_'.$definition->name;
        $column->data_type = self::FIELD_TYPE_MAP[$definition->field_type] ?? 'character varying';
        $column->is_nullable = $definition->is_required ? 'NO' : 'YES';
        $column->character_maximum_length = $definition->max_length;
        $column->column_default = null;
        $column->ordinal_position = $ordinalPosition;

        // Extra metadata for the rendering layer to identify custom fields
        $column->is_custom_field = true;
        $column->custom_field_label = $definition->label;
        $column->custom_field_definition_id = $definition->id;

        return $column;
    }
}
