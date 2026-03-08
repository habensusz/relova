<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add local_join_key and remote_join_key to relova_field_mappings.
 *
 * These two columns store which column on the local table (e.g. machine_sn)
 * maps to which column in the remote table (e.g. SERIAL_NO). They are used
 * by VirtualRelationLoader to automatically match local records to their
 * corresponding remote rows when enriching a model collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relova_field_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('relova_field_mappings', 'local_join_key')) {
                // Column on the local Eloquent model's table that holds the remote identifier.
                // Example: 'machine_sn' on the machines table.
                $table->string('local_join_key')->nullable()->after('target_module');
            }

            if (! Schema::hasColumn('relova_field_mappings', 'remote_join_key')) {
                // Column in the remote table whose value matches local_join_key.
                // Example: 'SERIAL_NO' in the remote assets table.
                $table->string('remote_join_key')->nullable()->after('local_join_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('relova_field_mappings', function (Blueprint $table): void {
            $table->dropColumn(['local_join_key', 'remote_join_key']);
        });
    }
};
