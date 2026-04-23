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

        if (Schema::hasColumn($table, 'premises_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) {
            // Scopes the mapping (and all rows synced by it) to a specific premises.
            // Stored as a plain integer matching the host app's premises.id.
            // NULL = no premises restriction (global mapping).
            $t->unsignedInteger('premises_id')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'connector_module_mappings', function (Blueprint $table) {
            $table->dropColumn('premises_id');
        });
    }
};
