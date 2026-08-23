<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_applications')) {
            return;
        }

        if (! Schema::hasColumn('program_applications', 'cancel_reason')) {
            Schema::table('program_applications', function (Blueprint $table) {
                $table->text('cancel_reason')->nullable()->after('status');
            });
        }

        $this->deduplicateApplications();

        $indexExists = DB::selectOne("
            SELECT 1
            FROM pg_indexes
            WHERE tablename = 'program_applications'
              AND indexname = 'program_applications_kabataan_program_unique'
        ");

        if ($indexExists === null) {
            Schema::table('program_applications', function (Blueprint $table) {
                $table->unique(['kabataan_id', 'program_id'], 'program_applications_kabataan_program_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_applications')) {
            return;
        }

        Schema::table('program_applications', function (Blueprint $table) {
            $table->dropUnique('program_applications_kabataan_program_unique');
        });

        if (Schema::hasColumn('program_applications', 'cancel_reason')) {
            Schema::table('program_applications', function (Blueprint $table) {
                $table->dropColumn('cancel_reason');
            });
        }
    }

    private function deduplicateApplications(): void
    {
        $duplicateGroups = DB::table('program_applications')
            ->select('kabataan_id', 'program_id')
            ->whereNotNull('kabataan_id')
            ->whereNotNull('program_id')
            ->groupBy('kabataan_id', 'program_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('program_applications')
                ->where('kabataan_id', $group->kabataan_id)
                ->where('program_id', $group->program_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id']);

            $keepId = $rows->first()?->id;
            if ($keepId === null) {
                continue;
            }

            DB::table('program_applications')
                ->where('kabataan_id', $group->kabataan_id)
                ->where('program_id', $group->program_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};
