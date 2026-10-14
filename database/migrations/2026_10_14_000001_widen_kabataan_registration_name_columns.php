<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
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

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'name')) {
                $this->widenUsersName();
            }

            Cache::forget('kkprofiling.registration_name_max');
        }
    }

    private function widenUsersName(): void
    {
        $row = DB::selectOne(
            "SELECT character_maximum_length AS name_max
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = 'users'
               AND column_name = 'name'"
        );
        if ((int) ($row->name_max ?? 0) >= 500) {
            return;
        }

        $this->dropViewIfPresent('deleted_sk_officials');
        $this->dropViewIfPresent('deleted_sk_federations');
        DB::statement('ALTER TABLE users ALTER COLUMN name TYPE VARCHAR(500)');
        $this->recreateDeletedViews();
    }

    private function recreateDeletedViews(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'deleted_at') || ! Schema::hasTable('official_profiles')) {
            return;
        }

        if (! $this->isBaseTable('deleted_sk_officials')) {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE VIEW deleted_sk_officials AS
                SELECT
                    u.id, u.tenant_id, u.barangay_id, u.name, u.email, u.status,
                    u.email_verified_at, u.deleted_at,
                    op.first_name, op.middle_name, op.last_name, op.suffix, op.sex,
                    op.date_of_birth, op.age, op.contact_number, op.position,
                    op.municipality, op.province, op.region
                FROM users u
                LEFT JOIN official_profiles op ON op.user_id = u.id
                WHERE u.deleted_at IS NOT NULL AND u.role = 'sk_official'
            SQL);
        }

        if ($this->isBaseTable('deleted_sk_federations')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW deleted_sk_federations AS
            SELECT
                u.id, u.tenant_id, u.barangay_id, u.name, u.email, u.status,
                u.email_verified_at, u.deleted_at,
                op.first_name, op.middle_name, op.last_name, op.suffix, op.sex,
                op.date_of_birth, op.age, op.contact_number, op.position,
                op.municipality, op.province, op.region
            FROM users u
            LEFT JOIN official_profiles op ON op.user_id = u.id
            WHERE u.deleted_at IS NOT NULL AND u.role = 'sk_fed'
        SQL);
    }

    private function dropViewIfPresent(string $name): void
    {
        DB::statement(
            "do $$ begin if exists (select 1 from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = current_schema() and c.relname = '{$name}' and c.relkind = 'v') then execute 'drop view {$name} cascade'; end if; end $$;"
        );
    }

    private function isBaseTable(string $name): bool
    {
        $row = DB::selectOne(
            'select c.relkind from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = current_schema() and c.relname = ?',
            [$name]
        );

        return $row !== null && $row->relkind === 'r';
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
