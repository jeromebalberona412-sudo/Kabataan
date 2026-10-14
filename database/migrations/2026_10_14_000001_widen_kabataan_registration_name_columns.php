<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE kabataan_registrations MODIFY first_name VARCHAR(150) NOT NULL');
            DB::statement('ALTER TABLE kabataan_registrations MODIFY middle_name VARCHAR(150) NULL');
            DB::statement('ALTER TABLE kabataan_registrations MODIFY last_name VARCHAR(150) NOT NULL');

            if (Schema::hasTable('kk_survey_responses')) {
                DB::statement('ALTER TABLE kk_survey_responses MODIFY first_name VARCHAR(150) NOT NULL');
                DB::statement('ALTER TABLE kk_survey_responses MODIFY middle_name VARCHAR(150) NULL');
                DB::statement('ALTER TABLE kk_survey_responses MODIFY last_name VARCHAR(150) NOT NULL');
            }

            if (Schema::hasTable('users')) {
                DB::statement('ALTER TABLE users MODIFY name VARCHAR(500) NOT NULL');
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN first_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN middle_name TYPE VARCHAR(150)');
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN last_name TYPE VARCHAR(150)');

            if (Schema::hasTable('kk_survey_responses')) {
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN first_name TYPE VARCHAR(150)');
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN middle_name TYPE VARCHAR(150)');
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN last_name TYPE VARCHAR(150)');
            }

            if (Schema::hasTable('users')) {
                DB::statement('ALTER TABLE users ALTER COLUMN name TYPE VARCHAR(500)');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE kabataan_registrations MODIFY first_name VARCHAR(100) NOT NULL');
            DB::statement('ALTER TABLE kabataan_registrations MODIFY middle_name VARCHAR(100) NULL');
            DB::statement('ALTER TABLE kabataan_registrations MODIFY last_name VARCHAR(100) NOT NULL');

            if (Schema::hasTable('kk_survey_responses')) {
                DB::statement('ALTER TABLE kk_survey_responses MODIFY first_name VARCHAR(100) NOT NULL');
                DB::statement('ALTER TABLE kk_survey_responses MODIFY middle_name VARCHAR(100) NULL');
                DB::statement('ALTER TABLE kk_survey_responses MODIFY last_name VARCHAR(100) NOT NULL');
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN first_name TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN middle_name TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE kabataan_registrations ALTER COLUMN last_name TYPE VARCHAR(100)');

            if (Schema::hasTable('kk_survey_responses')) {
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN first_name TYPE VARCHAR(100)');
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN middle_name TYPE VARCHAR(100)');
                DB::statement('ALTER TABLE kk_survey_responses ALTER COLUMN last_name TYPE VARCHAR(100)');
            }
        }
    }
};
