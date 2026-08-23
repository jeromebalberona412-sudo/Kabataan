<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return;
        }

        if (Schema::hasColumn('kk_survey_responses', 'facebook_account')
            && ! Schema::hasColumn('kk_survey_responses', 'facebook_profile_url')) {
            Schema::table('kk_survey_responses', function (Blueprint $table) {
                $table->renameColumn('facebook_account', 'facebook_profile_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return;
        }

        if (Schema::hasColumn('kk_survey_responses', 'facebook_profile_url')
            && ! Schema::hasColumn('kk_survey_responses', 'facebook_account')) {
            Schema::table('kk_survey_responses', function (Blueprint $table) {
                $table->renameColumn('facebook_profile_url', 'facebook_account');
            });
        }
    }
};
