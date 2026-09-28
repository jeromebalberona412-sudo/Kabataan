<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kabataan_security_questions')) {
            return;
        }

        Schema::create('kabataan_security_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kabataan_registration_id')->constrained('kabataan_registrations')->cascadeOnDelete();
            $table->unsignedTinyInteger('question_number');
            $table->string('question_text');
            $table->string('selected_choice', 20)->nullable();
            $table->string('custom_answer', 15)->nullable();
            $table->string('answer_hash')->nullable();
            $table->timestamps();

            $table->unique(['kabataan_registration_id', 'question_number'], 'kabataan_secq_registration_question_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX kabataan_secq_user_question_unique ON kabataan_security_questions (user_id, question_number) WHERE user_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kabataan_security_questions');
    }
};
