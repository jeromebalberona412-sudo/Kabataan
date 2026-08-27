<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kabataan_registrations')) {
            Schema::table('kabataan_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('kabataan_registrations', 'previous_application_id')) {
                    $table->foreignId('previous_application_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('kabataan_registrations')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('kabataan_registrations', 'rejection_reason')) {
                    $table->string('rejection_reason', 255)->nullable()->after('review_notes');
                }

                if (! Schema::hasColumn('kabataan_registrations', 'rejection_remarks')) {
                    $table->text('rejection_remarks')->nullable()->after('rejection_reason');
                }

                if (! Schema::hasColumn('kabataan_registrations', 'profiling_year')) {
                    $table->unsignedSmallInteger('profiling_year')->nullable()->after('rejection_remarks');
                }
            });
        }

        if (Schema::hasTable('rejected_kk_profiling')) {
            Schema::table('rejected_kk_profiling', function (Blueprint $table) {
                if (! Schema::hasColumn('rejected_kk_profiling', 'rejection_remarks')) {
                    $table->text('rejection_remarks')->nullable()->after('rejection_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rejected_kk_profiling')) {
            Schema::table('rejected_kk_profiling', function (Blueprint $table) {
                if (Schema::hasColumn('rejected_kk_profiling', 'rejection_remarks')) {
                    $table->dropColumn('rejection_remarks');
                }
            });
        }

        if (Schema::hasTable('kabataan_registrations')) {
            Schema::table('kabataan_registrations', function (Blueprint $table) {
                if (Schema::hasColumn('kabataan_registrations', 'previous_application_id')) {
                    $table->dropForeign(['previous_application_id']);
                    $table->dropColumn('previous_application_id');
                }
                if (Schema::hasColumn('kabataan_registrations', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
                if (Schema::hasColumn('kabataan_registrations', 'rejection_remarks')) {
                    $table->dropColumn('rejection_remarks');
                }
                if (Schema::hasColumn('kabataan_registrations', 'profiling_year')) {
                    $table->dropColumn('profiling_year');
                }
            });
        }
    }
};
