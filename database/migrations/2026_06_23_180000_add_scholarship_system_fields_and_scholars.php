<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('program_applications', 'system_field_answers')) {
            Schema::table('program_applications', function (Blueprint $table) {
                $table->json('system_field_answers')->nullable()->after('custom_answers');
                $table->string('scholar_status', 50)->nullable()->after('system_field_answers');
            });
        }

        if (! Schema::hasTable('scholars')) {
            Schema::create('scholars', function (Blueprint $table) {
                $table->id();
                $table->string('scholar_id', 20)->unique();
                $table->foreignId('kabataan_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('program_application_id')->nullable()->constrained('program_applications')->nullOnDelete();
                $table->string('scholar_status', 50)->default('FOR REVIEW');
                $table->timestamps();

                $table->unique('kabataan_id');
            });
        }

        if (! Schema::hasTable('scholar_educational_histories')) {
            Schema::create('scholar_educational_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scholar_id')->constrained('scholars')->cascadeOnDelete();
                $table->unsignedSmallInteger('academic_year');
                $table->string('educational_level', 100)->nullable();
                $table->string('grade_year_level', 100)->nullable();
                $table->string('course_program', 255)->nullable();
                $table->string('school_name', 255)->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();

                $table->index(['scholar_id', 'academic_year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scholar_educational_histories');
        Schema::dropIfExists('scholars');

        if (Schema::hasColumn('program_applications', 'system_field_answers')) {
            Schema::table('program_applications', function (Blueprint $table) {
                $table->dropColumn(['system_field_answers', 'scholar_status']);
            });
        }
    }
};
