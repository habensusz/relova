<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');

        Schema::create($prefix.'custom_field_values', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('field_id');
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 16, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['field_id', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);

            $table->foreign('field_id')
                ->references('id')
                ->on($prefix.'custom_field_definitions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('relova.table_prefix', 'relova_').'custom_field_values');
    }
};
