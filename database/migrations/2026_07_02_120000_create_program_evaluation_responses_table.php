<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_evaluation_responses')) {
            return;
        }

        Schema::create('program_evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('program_evaluations')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('kabataan_registrations')->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['evaluation_id', 'registration_id']);
            $table->index(['registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_evaluation_responses');
    }
};
