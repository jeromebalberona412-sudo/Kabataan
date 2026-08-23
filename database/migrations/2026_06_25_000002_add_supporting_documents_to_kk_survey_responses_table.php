<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return;
        }

        if (! Schema::hasColumn('kk_survey_responses', 'supporting_documents')) {
            Schema::table('kk_survey_responses', function (Blueprint $table) {
                $table->json('supporting_documents')->nullable()->after('participant_signature');
            });
        }

        $this->backfillFromRegistrations();
    }

    public function down(): void
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return;
        }

        if (Schema::hasColumn('kk_survey_responses', 'supporting_documents')) {
            Schema::table('kk_survey_responses', function (Blueprint $table) {
                $table->dropColumn('supporting_documents');
            });
        }
    }

    private function backfillFromRegistrations(): void
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return;
        }

        $rows = DB::table('kk_survey_responses as k')
            ->join('kabataan_registrations as r', 'r.id', '=', 'k.kabataan_registration_id')
            ->whereNull('k.supporting_documents')
            ->get(['k.id', 'r.form_data']);

        foreach ($rows as $row) {
            $formData = json_decode((string) $row->form_data, true);

            if (! is_array($formData)) {
                continue;
            }

            $documents = $formData['supporting_documents'] ?? null;

            if (! is_array($documents) || $documents === []) {
                continue;
            }

            DB::table('kk_survey_responses')
                ->where('id', $row->id)
                ->update([
                    'supporting_documents' => json_encode($documents),
                    'updated_at' => now(),
                ]);
        }
    }
};
