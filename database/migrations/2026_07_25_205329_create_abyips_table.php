<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('abyips')) {
            return;
        }

        Schema::create('abyips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->decimal('sk_fund', 14, 2)->default(0);
            $table->decimal('total_expenditure', 14, 2)->default(0);
            $table->string('chairperson_name')->nullable();
            $table->string('chairperson_title')->default('SK Chairperson');
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_title')->default('Barangay Chairman');
            $table->string('source_pdf_path')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abyips');
    }
};
