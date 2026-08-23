<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('abyip_items')) {
            return;
        }

        Schema::create('abyip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abyip_id')->constrained('abyips')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('row_type', ['section', 'subsection', 'item'])->default('item');
            $table->string('label')->nullable();
            $table->string('ppa')->nullable();
            $table->text('description')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('performance_indicator')->nullable();
            $table->string('period')->nullable();
            $table->decimal('mooe', 14, 2)->default(0);
            $table->decimal('co', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('person_responsible')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abyip_items');
    }
};
