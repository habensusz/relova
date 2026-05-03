<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'trigger_rules', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            $table->uuid('mapping_id');
            $table->foreign('mapping_id')
                ->references('id')
                ->on($prefix.'connector_module_mappings')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // The field to evaluate inside the row's display_snapshot.
            // Maps to a column in field_mappings or a raw remote column.
            $table->string('target_field', 128);

            // Operator semantics:
            //   eq, neq, gt, gte, lt, lte → compare scalar value
            //   in, not_in                 → value is JSON array
            //   contains                   → value is substring match
            //   changed                    → fires when previous != current
            //   above_threshold            → numeric > value
            //   below_threshold            → numeric < value
            $table->string('operator', 32);
            $table->jsonb('value')->nullable();

            // Cooldown to prevent flooding when the same row keeps matching.
            $table->integer('cooldown_minutes')->default(1440);

            // WO defaults the host generator will use.
            $table->string('priority', 16)->default('normal');
            $table->string('wo_title_template')->nullable();
            $table->text('wo_description_template')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'mapping_id', 'active'], 'rtr_lookup_idx');
        });

        Schema::create($prefix.'trigger_log', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('tenant_id')->index();

            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')
                ->references('id')
                ->on($prefix.'trigger_rules')
                ->cascadeOnDelete();

            $table->uuid('virtual_entity_reference_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->jsonb('payload');
            $table->string('outcome', 16)->default('matched'); // matched|created|skipped|error
            $table->text('rejection_reason')->nullable();

            $table->timestamp('triggered_at')->index();
            $table->timestamps();

            $table->index(['rule_id', 'triggered_at'], 'rtl_rule_time_idx');
            $table->index(['virtual_entity_reference_id', 'triggered_at'], 'rtl_ver_time_idx');
        });
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::dropIfExists($prefix.'trigger_log');
        Schema::dropIfExists($prefix.'trigger_rules');
    }
};
