<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kabataan_trusted_devices')) {
            return;
        }

        Schema::create('kabataan_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 128);
            $table->string('device_token_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'kabataan_trusted_device_unique');
            $table->index(['user_id', 'expires_at'], 'kabataan_trusted_device_exp_idx');
            $table->index(['user_id', 'device_token_hash'], 'kabataan_trusted_device_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kabataan_trusted_devices');
    }
};
