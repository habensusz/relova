<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'custom_field_widget_configs', function (Blueprint $table) {
            $table->id();
            $table->string('widget_key', 100);
            $table->string('entity_type', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'custom_field_widget_configs');
    }
};
