<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            $table->string('name');
            $table->text('description')->nullable();

            // Driver identifier â€” 'mysql'|'pgsql'|'sqlsrv'|'oracle'|'sap_hana'|'csv'|'xlsx'|'rest_api'
            $table->string('driver', 32);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('database')->nullable();

            // Encrypted per-tenant credential blob (JSON).
            $table->text('credentials_encrypted');

            // Driver-specific extras: schema, charset, API base URL, file path, etc.
            $table->jsonb('options')->default('{}');

            // SSH tunnel (optional, off by default).
            $table->boolean('ssh_enabled')->default(false);
            $table->string('ssh_host')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);

            // Cache TTL override for schema metadata (seconds). Null = use config default.
            $table->unsignedInteger('cache_ttl')->nullable();

            // Runtime state.
            $table->enum('status', ['active', 'error', 'unreachable'])->default('active');
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('driver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'connections');
    }
};
