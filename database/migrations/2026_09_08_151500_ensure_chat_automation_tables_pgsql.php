<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure chat automation tables exist on PostgreSQL/Supabase and use native booleans.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS chat_automations (
    id BIGSERIAL PRIMARY KEY,
    sk_official_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT chat_automations_sk_official_id_unique UNIQUE (sk_official_id)
);
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS chat_automations_is_enabled_index ON chat_automations (is_enabled)');

        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS chat_automation_faqs (
    id BIGSERIAL PRIMARY KEY,
    chat_automation_id BIGINT NOT NULL REFERENCES chat_automations(id) ON DELETE CASCADE,
    question VARCHAR(50) NOT NULL,
    automated_response VARCHAR(500) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    deleted_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS chat_faq_active_order_idx ON chat_automation_faqs (chat_automation_id, is_active, sort_order)');
    }

    public function down(): void
    {
        // Non-destructive safety migration — do not drop production tables.
    }
};
