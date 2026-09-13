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
            $table->index(['evaluation_id']);
        });

        // Attach FKs only when parent tables exist (production DBs may miss optional tables).
        try {
            if (Schema::hasTable('program_evaluations') && Schema::hasTable('program_evaluation_responses')) {
                Schema::table('program_evaluation_responses', function (Blueprint $table) {
                    $table->foreign('evaluation_id')
                        ->references('id')
                        ->on('program_evaluations')
                        ->cascadeOnDelete();
                });
            }
        } catch (\Throwable) {
            // Keep the table usable even if FK creation is blocked by the DB host.
        }

        try {
            if (Schema::hasTable('kabataan_registrations') && Schema::hasTable('program_evaluation_responses')) {
                Schema::table('program_evaluation_responses', function (Blueprint $table) {
                    $table->foreign('registration_id')
                        ->references('id')
                        ->on('kabataan_registrations')
                        ->cascadeOnDelete();
                });
            }
        } catch (\Throwable) {
            // Keep the table usable even if FK creation is blocked by the DB host.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_evaluation_responses');
    }
};
