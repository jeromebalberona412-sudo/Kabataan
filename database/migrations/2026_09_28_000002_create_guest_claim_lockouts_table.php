<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guest_claim_lockouts')) {
            return;
        }

        Schema::create('guest_claim_lockouts', function (Blueprint $table) {
            $table->id();
            $table->string('device_token', 64)->unique();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_claim_lockouts');
    }
};
