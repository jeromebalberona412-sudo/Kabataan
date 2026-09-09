<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Annual KK Profiling completion tracker.
 *
 * Live profile table is `kabataan_registrations` (no `kabataan` table).
 * `kabataan_id` stores kabataan_registrations.id — one profile, many yearly update rows.
 *
 * Recreated after 2026_09_09_080000_drop_kk_profiling_updates_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return;
        }

        if (! Schema::hasTable('kk_profiling_updates')) {
            Schema::create('kk_profiling_updates', function (Blueprint $table) {
                $table->id();

                $table->foreignId('kabataan_id')
                    ->constrained('kabataan_registrations')
                    ->cascadeOnDelete();

                $table->unsignedInteger('year');

                $table->string('status', 20)->default('in_progress');

                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('submitted_at')->nullable();
                $table->timestampTz('completed_at')->nullable();

                $table->timestamps();

                $table->unique(
                    ['kabataan_id', 'year'],
                    'unique_kabataan_profiling_year'
                );

                $table->index(
                    ['kabataan_id', 'year'],
                    'idx_kabataan_profiling_year'
                );

                $table->index(
                    ['status', 'year'],
                    'idx_kk_profiling_updates_status_year'
                );
            });
        }

        $this->backfillCompletedRows();
    }

    public function down(): void
    {
        Schema::dropIfExists('kk_profiling_updates');
    }

    private function backfillCompletedRows(): void
    {
        if (! Schema::hasTable('kk_profiling_updates')) {
            return;
        }

        if (Schema::hasTable('kabataan_profiling_history')) {
            $historyRows = DB::table('kabataan_profiling_history')
                ->select('kabataan_registration_id', 'profiling_year', 'submitted_at', 'created_at')
                ->orderBy('id')
                ->get();

            foreach ($historyRows as $row) {
                $year = (int) $row->profiling_year;
                $kabataanId = (int) $row->kabataan_registration_id;
                if ($year < 2000 || $kabataanId < 1) {
                    continue;
                }

                $completedAt = $row->submitted_at ?? $row->created_at ?? now();

                DB::table('kk_profiling_updates')->updateOrInsert(
                    [
                        'kabataan_id' => $kabataanId,
                        'year' => $year,
                    ],
                    [
                        'status' => 'completed',
                        'started_at' => $completedAt,
                        'submitted_at' => $completedAt,
                        'completed_at' => $completedAt,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $registrations = DB::table('kabataan_registrations')
            ->select('id', 'form_data', 'submitted_at', 'updated_at')
            ->whereNotNull('form_data')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $formData = is_string($registration->form_data)
                ? json_decode($registration->form_data, true)
                : (is_array($registration->form_data) ? $registration->form_data : null);

            if (! is_array($formData) || empty($formData['profile_updated_year'])) {
                continue;
            }

            $year = (int) $formData['profile_updated_year'];
            if ($year < 2000) {
                continue;
            }

            $completedAt = $formData['profile_updated_at']
                ?? $registration->submitted_at
                ?? $registration->updated_at
                ?? now();

            $exists = DB::table('kk_profiling_updates')
                ->where('kabataan_id', (int) $registration->id)
                ->where('year', $year)
                ->where('status', 'completed')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('kk_profiling_updates')->updateOrInsert(
                [
                    'kabataan_id' => (int) $registration->id,
                    'year' => $year,
                ],
                [
                    'status' => 'completed',
                    'started_at' => $completedAt,
                    'submitted_at' => $completedAt,
                    'completed_at' => $completedAt,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
};
