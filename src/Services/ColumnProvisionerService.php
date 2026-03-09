<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Ensures the Relova tracking columns (relova_ref_uid, relova_synced_at) exist
 * on a given host table. Idempotent — safe to call multiple times.
 *
 * Used by:
 *  - AddColumnsCommand   (manual: php artisan relova:add-columns {table})
 *  - FieldMappingEditor  (automatic: on first save of a new mapping)
 */
class ColumnProvisionerService
{
    /**
     * Add any missing Relova columns to $table.
     *
     * @return string[] List of column names that were actually added (empty when all already existed).
     */
    public function ensureRelovaColumns(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $added = [];

        Schema::table($table, function (\Illuminate\Database\Schema\Blueprint $blueprint) use ($table, &$added): void {
            if (! Schema::hasColumn($table, 'relova_ref_uid')) {
                $blueprint->string('relova_ref_uid', 22)->nullable()->unique();
                $added[] = 'relova_ref_uid';
            }

            if (! Schema::hasColumn($table, 'relova_synced_at')) {
                $blueprint->timestamp('relova_synced_at')->nullable();
                $added[] = 'relova_synced_at';
            }
        });

        return $added;
    }
}
