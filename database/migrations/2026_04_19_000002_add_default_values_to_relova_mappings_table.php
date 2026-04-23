<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');
        $table = $prefix.'connector_module_mappings';

        if (Schema::hasColumn($table, 'default_values')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) {
            // Static fallback values for local columns that are NOT NULL but cannot
            // be meaningfully mapped from a remote column (e.g. FK columns like
            // manufacturer_id / location_id that differ across DB instances).
            //
            // Format: { "local_column": "fallback_value" }
            // Applied during INSERT only — existing rows are never overwritten with defaults.
            $t->jsonb('default_values')->default('{}')->after('filters');
        });
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'connector_module_mappings', function (Blueprint $table) {
            $table->dropColumn('default_values');
        });
    }
};
