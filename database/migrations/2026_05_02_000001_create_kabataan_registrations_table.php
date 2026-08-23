<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kabataan_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('barangay_id')->constrained('barangays')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Applicant information
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contact_number', 15)->nullable();
            
            // Form payload (JSON)
            $table->json('form_data');
            
            // State machine
            $table->enum('status', [
                'pending_verification',
                'email_verified',
                'password_set',
                'active',
                'rejected'
            ])->default('pending_verification');
            
            // Timestamps for state transitions
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'barangay_id']);
            $table->index('status');
            $table->index('email');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kabataan_registrations');
    }
};
