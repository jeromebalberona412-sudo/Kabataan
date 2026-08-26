<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION generate_respondent_number(
                p_tenant_id BIGINT,
                p_barangay_id BIGINT
            )
            RETURNS TEXT
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                next_seq INTEGER;
            BEGIN
                -- Serialize concurrent assignment for the same barangay
                PERFORM id FROM barangays WHERE id = p_barangay_id FOR UPDATE;

                SELECT COALESCE(
                    MAX(
                        CASE
                            WHEN respondent_number ~ '^\\d+$' THEN respondent_number::INTEGER
                            WHEN respondent_sequence IS NOT NULL THEN respondent_sequence
                            WHEN respondent_number ~ '-(\\d+)$' THEN SUBSTRING(respondent_number FROM '-(\\d+)$')::INTEGER
                            ELSE 0
                        END
                    ), 0
                ) + 1
                INTO next_seq
                FROM kabataan_registrations
                WHERE tenant_id = p_tenant_id
                  AND barangay_id = p_barangay_id
                  AND respondent_number IS NOT NULL;

                RETURN next_seq::TEXT;
            END;
            \$\$;
        ");
    }

    public function down(): void
    {
    }
};
