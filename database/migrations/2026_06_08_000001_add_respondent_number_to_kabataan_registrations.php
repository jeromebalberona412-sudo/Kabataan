<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kabataan_registrations', function (Blueprint $table) {
            $table->string('respondent_number', 32)->nullable()->after('contact_number');
            $table->unsignedInteger('respondent_sequence')->nullable()->after('respondent_number');
        });

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS kabataan_registrations_unique_respondent
            ON kabataan_registrations (tenant_id, barangay_id, respondent_number)
            WHERE respondent_number IS NOT NULL
        ');

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generate_respondent_number(
                p_tenant_id BIGINT,
                p_barangay_id BIGINT
            )
            RETURNS TEXT
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                current_year TEXT;
                next_seq INTEGER;
                barangay_prefix TEXT;
            BEGIN
                current_year := EXTRACT(YEAR FROM CURRENT_DATE)::TEXT;

                SELECT UPPER(LEFT(REGEXP_REPLACE(name, '[^a-zA-Z0-9]', '', 'g'), 8))
                INTO barangay_prefix
                FROM barangays
                WHERE id = p_barangay_id;

                IF barangay_prefix IS NULL OR barangay_prefix = '' THEN
                    barangay_prefix := 'BRGY';
                END IF;

                SELECT COALESCE(MAX(respondent_sequence), 0) + 1
                INTO next_seq
                FROM kabataan_registrations
                WHERE tenant_id = p_tenant_id
                  AND barangay_id = p_barangay_id
                  AND EXTRACT(YEAR FROM COALESCE(reviewed_at, created_at)) =
                      EXTRACT(YEAR FROM CURRENT_DATE);

                RETURN barangay_prefix || '-' || current_year || '-' || LPAD(next_seq::TEXT, 4, '0');
            END;
            \$\$;
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS generate_respondent_number(BIGINT, BIGINT);');
        DB::statement('DROP INDEX IF EXISTS kabataan_registrations_unique_respondent');

        Schema::table('kabataan_registrations', function (Blueprint $table) {
            $table->dropColumn(['respondent_number', 'respondent_sequence']);
        });
    }
};
