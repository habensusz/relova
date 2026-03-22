<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'custom_field_widget_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->string('field_key', 150);
            $table->string('label_override')->nullable();
            $table->string('group_header')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['config_id', 'field_key']);
            $table->index(['config_id', 'sort_order']);

            $table->foreign('config_id')
                ->references('id')
                ->on($prefix.'custom_field_widget_configs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'custom_field_widget_items');
    }
};
