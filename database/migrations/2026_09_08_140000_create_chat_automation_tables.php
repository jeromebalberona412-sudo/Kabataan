<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_automations')) {
            Schema::create('chat_automations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sk_official_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique('sk_official_id');
                $table->index('is_enabled');
            });
        }

        if (! Schema::hasTable('chat_automation_faqs')) {
            Schema::create('chat_automation_faqs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_automation_id')->constrained('chat_automations')->cascadeOnDelete();
                $table->string('question', 50);
                $table->string('automated_response', 500);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['chat_automation_id', 'is_active', 'sort_order'], 'chat_faq_active_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_automation_faqs');
        Schema::dropIfExists('chat_automations');
    }
};
