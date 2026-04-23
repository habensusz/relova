<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'sync_configs', function (Blueprint $table) use ($prefix) {
            $table->uuid('id')->primary();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            $table->uuid('connection_id');
            $table->string('remote_table');
            $table->string('pk_column');

            // 'hot' | 'warm' | 'on_demand' | 'virtual'
            $table->string('sync_mode', 16)->default('warm');
            $table->unsignedSmallInteger('ttl_minutes')->default(30);
            $table->unsignedInteger('hot_set_size')->default(500);

            $table->jsonb('sync_filter')->default('{}');
            $table->jsonb('cached_columns')->default('[]');
            $table->jsonb('display_columns');

            // 'idle' | 'syncing' | 'error'
            $table->string('sync_status', 16)->default('idle');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'connection_id', 'remote_table']);
            $table->index(['tenant_id', 'sync_status']);
            $table->index('next_sync_at');

            $table->foreign('connection_id')
                ->references('id')->on($prefix.'connections')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'sync_configs');
    }
};
