<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');
        $table = $prefix.'connector_module_mappings';

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'remote_pk_column')) {
                // Which remote column uniquely identifies each record.
                $t->string('remote_pk_column', 64)->default('id')->after('remote_table');
            }

            if (! Schema::hasColumn($table, 'joins')) {
                // Structured JOIN hints to pull in columns from related tables
                // without requiring a view on the remote DB.
                $t->jsonb('joins')->default('{}')->after('filters');
            }
        });
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'connector_module_mappings', function (Blueprint $table) {
            $table->dropColumn(['remote_pk_column', 'joins']);
        });
    }
};
