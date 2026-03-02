<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 22)->unique();
            $table->string('tenant_id')->index();

            $table->string('name');
            $table->string('key_hash', 64)->unique(); // SHA-256 of plaintext key
            $table->string('key_prefix', 12); // First chars for identification

            $table->json('permissions')->nullable(); // ['*'] or ['connections.read', 'query', ...]
            $table->json('scoped_connections')->nullable(); // null = all, [1,2,3] = specific
            $table->boolean('enabled')->default(true);

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'api_keys');
    }
};
