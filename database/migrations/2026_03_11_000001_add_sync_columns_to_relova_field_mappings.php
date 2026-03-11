<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add sync-configuration columns to relova_field_mappings:
 *
 * - timestamp_column: the remote column used for change-detection (Option 2) and
 *   incremental sync (Option 3). Defaults to 'updated_at'. NULL means always
 *   perform a full sync without change detection.
 * - last_synced_at: timestamp of the last successful sync run; used as the
 *   lower-bound for the incremental WHERE clause.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('relova.table_prefix', 'relova_').'field_mappings';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'timestamp_column')) {
                $table->string('timestamp_column')->nullable()->default('updated_at')->after('source_table');
            }

            if (! Schema::hasColumn($tableName, 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('timestamp_column');
            }
        });
    }

    public function down(): void
    {
        $tableName = config('relova.table_prefix', 'relova_').'field_mappings';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['timestamp_column', 'last_synced_at']);
        });
    }
};
