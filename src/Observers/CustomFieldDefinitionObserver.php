<?php

declare(strict_types=1);

namespace Relova\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Relova\Models\CustomFieldDefinition;
use Relova\Services\RelovaCacheKeys;

/**
 * Fires on saved and deleted events of CustomFieldDefinition.
 *
 * On soft delete: removes the corresponding key from all entity JSONB
 * snapshots for that entity type, chunked in batches of 500.
 */
class CustomFieldDefinitionObserver
{
    /**
     * Handle the created event.
     */
    public function created(CustomFieldDefinition $definition): void
    {
        $this->bustCache($definition);
    }

    /**
     * Handle the updated event.
     */
    public function updated(CustomFieldDefinition $definition): void
    {
        $this->bustCache($definition);
    }

    /**
     * Handle the soft-deleted event.
     *
     * Removes the field key from all JSONB snapshots for the affected
     * entity type, processed in batches to avoid memory issues.
     */
    public function deleted(CustomFieldDefinition $definition): void
    {
        $this->bustCache($definition);
        $this->removeKeyFromSnapshots($definition);
    }

    /**
     * Handle the restored event (un-soft-delete).
     */
    public function restored(CustomFieldDefinition $definition): void
    {
        $this->bustCache($definition);
    }

    /**
     * Bust all caches for the definition's entity type.
     */
    private function bustCache(CustomFieldDefinition $definition): void
    {
        foreach (RelovaCacheKeys::keysForEntity($definition->entity_type) as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Remove a field key from all entity JSONB snapshot columns.
     *
     * Uses PostgreSQL's - operator for JSONB key removal.
     * Falls back to PHP-based removal for non-PostgreSQL databases.
     */
    private function removeKeyFromSnapshots(CustomFieldDefinition $definition): void
    {
        $entityType = $definition->entity_type;
        $fieldName = $definition->name;

        // Resolve the table name from the entity type.
        // Convention: entity_type is snake_case singular, table is plural.
        $table = $this->resolveTableName($entityType);

        if (! $table || ! $this->tableHasCustomFieldsColumn($table)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->removeKeyPostgres($table, $fieldName);
        } else {
            $this->removeKeyGeneric($table, $fieldName);
        }

        Log::info('Relova: removed custom field key from snapshots', [
            'entity_type' => $entityType,
            'field_name' => $fieldName,
            'table' => $table,
        ]);
    }

    private function removeKeyPostgres(string $table, string $fieldName): void
    {
        // Use PostgreSQL's - operator for JSONB key removal in batches
        DB::table($table)
            ->whereRaw("custom_fields ? ?", [$fieldName])
            ->chunkById(500, function ($records) use ($table, $fieldName): void {
                DB::table($table)
                    ->whereIn('id', $records->pluck('id'))
                    ->update([
                        'custom_fields' => DB::raw("custom_fields - " . DB::connection()->getPdo()->quote($fieldName)),
                    ]);
            });
    }

    private function removeKeyGeneric(string $table, string $fieldName): void
    {
        DB::table($table)
            ->where('custom_fields', '!=', '{}')
            ->chunkById(500, function ($records) use ($table, $fieldName): void {
                foreach ($records as $record) {
                    $snapshot = json_decode($record->custom_fields, true) ?? [];

                    if (array_key_exists($fieldName, $snapshot)) {
                        unset($snapshot[$fieldName]);

                        DB::table($table)
                            ->where('id', $record->id)
                            ->update(['custom_fields' => json_encode($snapshot)]);
                    }
                }
            });
    }

    /**
     * Resolve a database table name from an entity type string.
     *
     * Convention: entity_type is snake_case singular (e.g., 'machine'),
     * table name is plural (e.g., 'machines').
     */
    private function resolveTableName(string $entityType): ?string
    {
        $plural = \Illuminate\Support\Str::plural($entityType);

        if (\Illuminate\Support\Facades\Schema::hasTable($plural)) {
            return $plural;
        }

        // Fallback: try the entity type as-is (already plural or custom table)
        if (\Illuminate\Support\Facades\Schema::hasTable($entityType)) {
            return $entityType;
        }

        return null;
    }

    private function tableHasCustomFieldsColumn(string $table): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn($table, 'custom_fields');
    }
}
