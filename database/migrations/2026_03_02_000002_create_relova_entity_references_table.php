<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'entity_references', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('uid', 22)->unique();

            $table->foreignId('connection_id')
                ->constrained($prefix.'connections')
                ->onDelete('cascade');

            $table->string('remote_table');
            $table->string('remote_primary_column');
            $table->string('remote_primary_value');

            // Display snapshot — last-known-good display data, not authoritative
            $table->json('display_snapshot')->nullable();
            $table->timestamp('snapshot_refreshed_at')->nullable();

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // One reference row per unique remote entity
            $table->unique(
                ['connection_id', 'remote_table', 'remote_primary_column', 'remote_primary_value'],
                $prefix.'entity_ref_unique'
            );

            $table->index(['connection_id', 'remote_table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'entity_references');
    }
};
