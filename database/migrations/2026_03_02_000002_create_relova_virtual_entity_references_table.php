<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'virtual_entity_references', function (Blueprint $table) use ($prefix) {
            $table->uuid('id')->primary();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            $table->uuid('connection_id');
            $table->foreign('connection_id')
                ->references('id')
                ->on($prefix.'connections')
                ->cascadeOnDelete();

            // Pointer to the remote entity — never the entity itself.
            $table->string('remote_table');
            $table->string('remote_pk_column');
            $table->string('remote_pk_value');

            // Last-known display fields. Not authoritative. Not a data copy.
            $table->jsonb('display_snapshot')->default('{}');
            $table->timestamp('snapshot_taken_at')->nullable();
            $table->enum('snapshot_status', ['fresh', 'stale', 'unavailable'])->default('stale');

            $table->timestamps();

            // One reference row per unique remote entity per tenant.
            $table->unique(
                ['tenant_id', 'connection_id', 'remote_table', 'remote_pk_column', 'remote_pk_value'],
                $prefix.'virt_entity_ref_unique',
            );

            $table->index(['tenant_id', 'connection_id', 'remote_table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'virtual_entity_references');
    }
};
