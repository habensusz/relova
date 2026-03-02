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
            $table->id();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->nullable()->index();

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);

            // Driver & connection config
            $table->string('driver_type', 50); // mysql, pgsql, sqlsrv, oracle, csv, etc.
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('database_name')->nullable();
            $table->string('schema_name')->nullable();
            $table->string('username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->json('config_meta')->nullable(); // Extra driver-specific config

            // Cache & behavior
            $table->integer('cache_ttl')->default(300);
            $table->enum('query_mode', ['virtual', 'snapshot', 'on_demand'])->default('virtual');

            // Health tracking
            $table->enum('health_status', ['unknown', 'healthy', 'degraded', 'unhealthy'])->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
            $table->index(['driver_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'connections');
    }
};
