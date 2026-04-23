<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');

            // 'user' | 'system' | 'sync_job'
            $table->string('actor_type', 16);
            $table->string('actor_id');

            // 'query' | 'sync' | 'connection_test' | 'schema_browse' | 'cache_hit' | 'cache_miss'
            $table->string('action', 32);

            $table->uuid('connection_id')->nullable();
            $table->string('remote_table')->nullable();
            $table->unsignedInteger('rows_accessed')->default(0);

            $table->string('source_ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Column names + operators only — NEVER values.
            $table->jsonb('query_metadata');

            // 'success' | 'failure' | 'blocked'
            $table->string('result', 16);
            $table->text('failure_reason')->nullable();

            $table->timestamp('occurred_at')->index();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'audit_logs');
    }
};
