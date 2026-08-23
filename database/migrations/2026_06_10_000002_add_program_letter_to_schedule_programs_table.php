<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_programs')) {
            return;
        }

        if (! Schema::hasColumn('schedule_programs', 'program_letter')) {
            Schema::table('schedule_programs', function (Blueprint $table) {
                $table->string('program_letter', 1)->nullable()->after('program_name');
                $table->index(['barangay_id', 'program_letter']);
            });
        }

        DB::table('schedule_programs')
            ->whereNull('program_letter')
            ->whereRaw('LOWER(committee) LIKE ?', ['%sport%'])
            ->update(['program_letter' => 'I']);

        DB::table('schedule_programs')
            ->whereNull('program_letter')
            ->update(['program_letter' => 'A']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_programs') || ! Schema::hasColumn('schedule_programs', 'program_letter')) {
            return;
        }

        Schema::table('schedule_programs', function (Blueprint $table) {
            $table->dropIndex(['barangay_id', 'program_letter']);
            $table->dropColumn('program_letter');
        });
    }
};
