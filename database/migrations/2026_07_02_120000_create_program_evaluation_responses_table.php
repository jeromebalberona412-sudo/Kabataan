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
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('registration_id');
            $table->json('answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['evaluation_id', 'registration_id']);
            $table->index(['registration_id']);

            if (Schema::hasTable('program_evaluations')) {
                $table->foreign('evaluation_id')
                    ->references('id')
                    ->on('program_evaluations')
                    ->cascadeOnDelete();
            }

            if (Schema::hasTable('kabataan_registrations')) {
                $table->foreign('registration_id')
                    ->references('id')
                    ->on('kabataan_registrations')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_evaluation_responses');
    }
};
