<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'field_mappings', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->nullable()->index();

            $table->foreignId('connection_id')
                ->constrained($prefix.'connections')
                ->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);

            // What module this mapping feeds (e.g. 'machines', 'tickets', 'schedules')
            $table->string('target_module');
            // Which remote table is the source
            $table->string('source_table');

            // Column mapping: [{remote_column: "ASSET_ID", local_field: "machine_name", ...}]
            $table->json('column_mappings');
            // Transformation rules per field
            $table->json('transformation_rules')->nullable();

            // Query mode: how this mapping behaves
            $table->enum('query_mode', ['virtual', 'snapshot', 'on_demand'])->default('virtual');

            // Usage tracking
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['connection_id', 'target_module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'field_mappings');
    }
};
