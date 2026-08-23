<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programs_accomplishment_reports')) {
            Schema::create('programs_accomplishment_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('barangay_id');
                $table->unsignedBigInteger('program_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->text('objectives')->nullable();
                $table->text('implementation_summary')->nullable();
                $table->text('actual_result')->nullable();
                $table->text('lessons_learned')->nullable();
                $table->text('recommendations')->nullable();
                $table->unsignedInteger('participants_count')->default(0);
                $table->unsignedInteger('target_beneficiaries')->nullable();
                $table->decimal('actual_expense', 15, 2)->default(0);
                $table->decimal('approved_budget', 15, 2)->nullable();
                $table->date('actual_implementation_date')->nullable();
                $table->date('actual_completion_date')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 32)->default('Draft');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->unique('program_id');
                $table->index('barangay_id');
                $table->index('status');

                $table->foreign('barangay_id')->references('id')->on('barangays')->cascadeOnDelete();
                $table->foreign('program_id')->references('id')->on('schedule_programs')->restrictOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('programs_accomplishment')) {
            Schema::create('programs_accomplishment', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('accomplishment_report_id');
                $table->string('cloudinary_public_id')->nullable();
                $table->text('image_url')->nullable();
                $table->text('secure_url')->nullable();
                $table->string('display_name')->nullable();
                $table->string('caption')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->string('status', 32)->nullable();
                $table->timestamps();

                $table->index('accomplishment_report_id');
                $table->foreign('accomplishment_report_id', 'pa_images_report_fk')
                    ->references('id')
                    ->on('programs_accomplishment_reports')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('programs_accomplishment_documents')) {
            Schema::create('programs_accomplishment_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('accomplishment_report_id');
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('document_type', 80)->nullable();
                $table->string('visibility', 20)->default('internal');
                $table->timestamps();

                $table->foreign('accomplishment_report_id', 'pa_docs_report_fk')
                    ->references('id')
                    ->on('programs_accomplishment_reports')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programs_accomplishment_documents');
        Schema::dropIfExists('programs_accomplishment');
        Schema::dropIfExists('programs_accomplishment_reports');
    }
};
