<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supporting_document_verifications')) {
            Schema::create('supporting_document_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kabataan_registration_id')->nullable()->index();
                $table->string('wizard_token', 64)->nullable()->index();
                $table->unsignedBigInteger('barangay_id')->nullable()->index();
                $table->string('document_type', 64)->nullable();
                $table->string('detected_document_type', 128)->nullable();
                $table->string('verification_fingerprint', 64)->nullable()->index();
                $table->string('perceptual_hash', 32)->nullable()->index();
                $table->string('verification_status', 32)->default('pending')->index();
                $table->decimal('detection_confidence', 5, 4)->nullable();
                $table->string('duplicate_status', 16)->default('none')->index();
                $table->boolean('needs_review')->default(false)->index();
                $table->string('review_reason', 255)->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->string('temp_storage_path')->nullable();
                $table->timestamp('retain_until')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supporting_document_audit_logs')) {
            Schema::create('supporting_document_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supporting_document_verification_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 64);
                $table->string('reason', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supporting_document_audit_logs');
        Schema::dropIfExists('supporting_document_verifications');
    }
};
