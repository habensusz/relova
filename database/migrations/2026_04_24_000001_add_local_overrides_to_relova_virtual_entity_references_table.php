<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'virtual_entity_references', function (Blueprint $table) {
            // Per-entity local relationship overrides.
            // Stores FK anchors for this specific remote entity (e.g. location_id, manufacturer_id).
            // Takes priority over ConnectorModuleMapping::default_values in VirtualEntityProxy::__get().
            // Format: { "location_id": 5, "manufacturer_id": 3 }
            $table->jsonb('local_overrides')->default('{}')->after('display_snapshot');
        });
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'virtual_entity_references', function (Blueprint $table) {
            $table->dropColumn('local_overrides');
        });
    }
};
