<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_applications')) {
            return;
        }

        Schema::create('program_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id');
            $table->foreignId('kabataan_id')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 50)->nullable();
            $table->date('birthdate');
            $table->integer('age');
            $table->string('sex', 50);
            $table->string('civil_status', 50)->nullable();
            $table->string('purok')->nullable();
            $table->string('barangay');
            $table->string('email')->nullable();
            $table->string('contact_number', 50);
            $table->string('parent_guardian_name')->nullable();
            $table->string('parent_occupation')->nullable();
            $table->decimal('parent_income', 12, 2)->nullable();
            $table->string('school_name')->nullable();
            $table->string('grade_level', 100)->nullable();
            $table->string('course')->nullable();
            $table->decimal('gwa', 5, 2)->nullable();
            $table->json('custom_answers')->nullable();
            $table->json('required_documents')->nullable();
            $table->text('purpose')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('cancel_reason')->nullable();
            $table->string('payment_status', 20)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('rejection_reasons')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['kabataan_id', 'program_id'], 'program_applications_kabataan_program_unique');
            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_applications');
    }
};
