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

            $table->uuid('connection_id');
            $table->foreign('connection_id')
                ->references('id')
                ->on($prefix.'connections')
                ->cascadeOnDelete();

            // Host-app module key, e.g. 'assets', 'tickets', 'inventory'.
            $table->string('module_key', 64);

            // Remote-side table that feeds this module.
            $table->string('remote_table');

            // Local field -> remote column mapping.
            $table->jsonb('field_mappings');

            // Which fields from the mapping are included in the display snapshot.
            $table->jsonb('display_fields');

            // Static WHERE predicates pushed down on every pass-through query.
            $table->jsonb('filters')->default('{}');

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
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'connector_module_mappings');
    }
};
