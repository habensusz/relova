<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');
        $table = $prefix.'virtual_entity_references';

        if (! Schema::hasColumn($table, 'mapping_id')) {
            Schema::table($table, function (Blueprint $t) use ($prefix) {
                $t->uuid('mapping_id')->nullable()->after('tenant_id');
                $t->foreign('mapping_id')
                    ->references('id')
                    ->on($prefix.'connector_module_mappings')
                    ->nullOnDelete();
                $t->index('mapping_id');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::table($prefix.'virtual_entity_references', function (Blueprint $table) {
            $table->dropForeign(['mapping_id']);
            $table->dropIndex(['mapping_id']);
            $table->dropColumn('mapping_id');
        });
    }
};
