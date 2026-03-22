<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 100);
            $table->string('name', 100);
            $table->string('label');
            $table->string('field_type', 20); // text, number, date, boolean
            $table->boolean('is_required')->default(false);
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->integer('min_length')->nullable();
            $table->integer('max_length')->nullable();
            $table->string('regex_pattern')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_type', 'name']);
            $table->index(['entity_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'custom_field_definitions');
    }
};
