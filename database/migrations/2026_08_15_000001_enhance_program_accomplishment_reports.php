<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programs_accomplishment_reports')) {
            Schema::table('programs_accomplishment_reports', function (Blueprint $table) {
                if (! Schema::hasColumn('programs_accomplishment_reports', 'actual_result')) {
                    $table->text('actual_result')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'actual_implementation_date')) {
                    $table->date('actual_implementation_date')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'actual_completion_date')) {
                    $table->date('actual_completion_date')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'target_beneficiaries')) {
                    $table->unsignedInteger('target_beneficiaries')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'approved_budget')) {
                    $table->decimal('approved_budget', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (! Schema::hasColumn('programs_accomplishment_reports', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
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
    }
};
