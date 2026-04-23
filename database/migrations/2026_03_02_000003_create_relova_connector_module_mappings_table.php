<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'connector_module_mappings', function (Blueprint $table) use ($prefix) {
            $table->uuid('id')->primary();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            // Scopes the mapping (and all rows synced by it) to a specific premises.
            // Stored as a plain integer matching the host app's premises.id.
            // NULL = no premises restriction (global mapping).
            $table->unsignedInteger('premises_id')->nullable();

            $table->uuid('connection_id');
            $table->foreign('connection_id')
                ->references('id')
                ->on($prefix.'connections')
                ->cascadeOnDelete();

            // Host-app module key, e.g. 'assets', 'tickets', 'inventory'.
            $table->string('module_key', 64);

            // Remote-side table that feeds this module.
            $table->string('remote_table');

            // Which remote column uniquely identifies each record.
            // Stored on VirtualEntityReference as remote_pk_column.
            $table->string('remote_pk_column', 64)->default('id');

            // Local field -> remote column mapping.
            $table->jsonb('field_mappings');

            // Which fields from the mapping are included in the display snapshot.
            $table->jsonb('display_fields');

            // Static WHERE predicates pushed down on every pass-through query.
            $table->jsonb('filters')->default('{}');

            // Static fallback values for local columns that are NOT NULL but cannot
            // be meaningfully mapped from a remote column (e.g. FK columns like
            // manufacturer_id / location_id that differ across DB instances).
            // Format: { "local_column": "fallback_value" }
            // Applied during INSERT only — existing rows are never overwritten.
            $table->jsonb('default_values')->default('{}');

            // Structured JOIN hints to pull in columns from related tables
            // without requiring a view on the remote DB.
            // Format: { "joined_table": { "type": "LEFT|INNER", "foreign_key": "col_in_main", "references": "col_in_joined" } }
            $table->jsonb('joins')->default('{}');

            // How consumers should resolve references: live pass-through,
            // snapshot-cache (display snapshot only), or on-demand lazy fetch.
            $table->enum('sync_behavior', ['virtual', 'snapshot_cache', 'on_demand'])->default('virtual');

            // Snapshot TTL for 'snapshot_cache' behaviour.
            $table->unsignedSmallInteger('cache_ttl_minutes')->default(30);

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'module_key']);
            $table->index(['connection_id', 'module_key']);
        });

        // Now that this table exists, add the FK on virtual_entity_references.mapping_id.
        if (Schema::hasTable($prefix.'virtual_entity_references')) {
            Schema::table($prefix.'virtual_entity_references', function (Blueprint $table) use ($prefix) {
                $table->foreign('mapping_id')
                    ->references('id')
                    ->on($prefix.'connector_module_mappings')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        // Drop the FK from virtual_entity_references before dropping this table.
        if (Schema::hasTable($prefix.'virtual_entity_references')) {
            Schema::table($prefix.'virtual_entity_references', function (Blueprint $table) use ($prefix) {
                $table->dropForeign([$prefix.'virtual_entity_references_mapping_id_foreign']);
            });
        }

        Schema::dropIfExists($prefix.'connector_module_mappings');
    }
};
