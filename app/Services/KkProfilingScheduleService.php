<?php

namespace App\Services;

use App\Models\KabataanProfilingHistory;
use App\Models\KabataanRegistration;
use App\Models\KkProfilingUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KkProfilingScheduleService
{
    private const CACHE_TTL = 30;

    public function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Manila');
    }

    public function expectedProfilingYear(): int
    {
        return (int) now($this->timezone())->format('Y');
    }

    /**
     * Ongoing barangay schedule that forces existing kabataan accounts to update.
     */
    public function activeUpdateSchedule(int $barangayId): ?object
    {
        if (! Schema::hasTable('kk_profiling_schedules')) {
            return null;
        }

        $today = now($this->timezone())->toDateString();

        return Cache::remember("kk_profiling_schedule.{$barangayId}.{$today}", self::CACHE_TTL, function () use ($barangayId, $today) {
            $query = DB::table('kk_profiling_schedules')
                ->where('barangay_id', $barangayId)
                ->where('status', 'Ongoing')
                // Existing-account updates: allow Ongoing schedules even before date_start
                // (e.g. profiling_year 2027 with a Jan 2027 window) so Kabataan can update now.
                // Still require date_expiry >= today so completed/expired windows stay closed.
                ->where('date_expiry', '>=', $today);

            if (Schema::hasColumn('kk_profiling_schedules', 'allow_existing_update')) {
                $query->whereRaw('allow_existing_update IS TRUE');
            }

            return $query
                ->orderByDesc('profiling_year')
                ->orderByDesc('date_start')
                ->first();
        });
    }

    public function hasActiveProfilingSchedule(int $barangayId): bool
    {
        return $this->activeUpdateSchedule($barangayId) !== null;
    }

    public function lastCompletedProfilingYear(KabataanRegistration $registration): int
    {
        $years = [];

        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        if (! empty($formData['profile_updated_year'])) {
            $years[] = (int) $formData['profile_updated_year'];
        }

        if (Schema::hasTable('kk_profiling_updates')) {
            $updateYear = KkProfilingUpdate::query()
                ->where('kabataan_id', $registration->id)
                ->where('status', KkProfilingUpdate::STATUS_COMPLETED)
                ->max('year');
            if ($updateYear) {
                $years[] = (int) $updateYear;
            }
        }

        if (Schema::hasTable('kabataan_profiling_history')) {
            $historyYear = Cache::remember("kk_profiling_history.max_year.{$registration->id}", self::CACHE_TTL, function () use ($registration) {
                return KabataanProfilingHistory::query()
                    ->where('kabataan_registration_id', $registration->id)
                    ->max('profiling_year');
            });

            if ($historyYear) {
                $years[] = (int) $historyYear;
            }
        }

        return $years === [] ? 0 : max($years);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function formDataCompletedYear(array $formData, int $year): bool
    {
        return ! empty($formData['profile_updated_year'])
            && (int) $formData['profile_updated_year'] >= $year;
    }

    public function hasCompletedProfilingForYear(KabataanRegistration $registration, int $year): bool
    {
        if (Schema::hasTable('kk_profiling_updates')) {
            $completed = Cache::remember(
                "kk_profiling_updates.completed.{$registration->id}.{$year}",
                self::CACHE_TTL,
                function () use ($registration, $year) {
                    return KkProfilingUpdate::query()
                        ->where('kabataan_id', $registration->id)
                        ->where('year', $year)
                        ->where('status', KkProfilingUpdate::STATUS_COMPLETED)
                        ->exists();
                }
            );

            if ($completed) {
                return true;
            }
        }

        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        if ($this->formDataCompletedYear($formData, $year)) {
            return true;
        }

        if (! Schema::hasTable('kabataan_profiling_history')) {
            return false;
        }

        return Cache::remember("kk_profiling_history.completed.{$registration->id}.{$year}", self::CACHE_TTL, function () use ($registration, $year) {
            return KabataanProfilingHistory::query()
                ->where('kabataan_registration_id', $registration->id)
                ->where('profiling_year', $year)
                ->exists();
        });
    }

    public function forgetCompletionCache(int $registrationId, int $year): void
    {
        Cache::forget("kk_profiling_history.completed.{$registrationId}.{$year}");
        Cache::forget("kk_profiling_history.max_year.{$registrationId}");
        Cache::forget("kk_profiling_updates.completed.{$registrationId}.{$year}");
    }

    public function forgetRegistrationCaches(KabataanRegistration $registration, ?int $year = null): void
    {
        if ($year !== null) {
            $this->forgetCompletionCache((int) $registration->id, $year);
        } else {
            Cache::forget("kk_profiling_history.max_year.{$registration->id}");
        }

        if ($registration->user_id) {
            Cache::forget("kabataan_registration.latest.{$registration->user_id}");
        }

        if ($registration->barangay_id) {
            $today = now($this->timezone())->toDateString();
            Cache::forget("kk_profiling_schedule.{$registration->barangay_id}.{$today}");
        }
    }

    public function scheduleProfilingYear(?object $schedule): int
    {
        if ($schedule === null) {
            return $this->expectedProfilingYear();
        }

        return (int) ($schedule->profiling_year ?? $this->expectedProfilingYear());
    }

    public function needsKkProfilingUpdate(?KabataanRegistration $registration): bool
    {
        return $this->requiresProfilingUpdate($registration);
    }

    public function requiresProfilingUpdate(?KabataanRegistration $registration): bool
    {
        if ($registration === null || ! $registration->user_id) {
            return false;
        }

        if (! in_array((string) $registration->status, ['active', 'email_verified', 'password_set'], true)) {
            return false;
        }

        $schedule = $this->activeUpdateSchedule((int) $registration->barangay_id);
        if ($schedule === null) {
            return false;
        }

        if ($registration->submitted_at === null) {
            return false;
        }

        $targetYear = $this->scheduleProfilingYear($schedule);

        $submittedYear = (int) Carbon::parse($registration->submitted_at)
            ->timezone($this->timezone())
            ->format('Y');

        if ($submittedYear >= $targetYear) {
            return false;
        }

        return ! $this->hasCompletedProfilingForYear($registration, $targetYear);
    }

    public function targetProfilingYearForRegistration(KabataanRegistration $registration): ?int
    {
        $schedule = $this->activeUpdateSchedule((int) $registration->barangay_id);

        return $schedule ? $this->scheduleProfilingYear($schedule) : null;
    }

    public function startAnnualUpdate(KabataanRegistration $registration, ?int $year = null): ?KkProfilingUpdate
    {
        if (! Schema::hasTable('kk_profiling_updates')) {
            return null;
        }

        $year = $year ?? $this->targetProfilingYearForRegistration($registration) ?? $this->expectedProfilingYear();

        return DB::transaction(function () use ($registration, $year) {
            $existing = KkProfilingUpdate::query()
                ->where('kabataan_id', $registration->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === KkProfilingUpdate::STATUS_COMPLETED) {
                    return $existing;
                }

                if ($existing->started_at === null) {
                    $existing->started_at = now();
                    $existing->save();
                }

                return $existing;
            }

            return KkProfilingUpdate::query()->create([
                'kabataan_id' => $registration->id,
                'year' => $year,
                'status' => KkProfilingUpdate::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
        });
    }

    public function markAnnualUpdateCompleted(KabataanRegistration $registration, int $year): KkProfilingUpdate
    {
        $now = now();

        $row = DB::transaction(function () use ($registration, $year, $now) {
            $row = KkProfilingUpdate::query()->firstOrCreate(
                [
                    'kabataan_id' => $registration->id,
                    'year' => $year,
                ],
                [
                    'status' => KkProfilingUpdate::STATUS_IN_PROGRESS,
                    'started_at' => $now,
                ]
            );

            $row->status = KkProfilingUpdate::STATUS_COMPLETED;
            $row->submitted_at = $now;
            $row->completed_at = $now;
            if ($row->started_at === null) {
                $row->started_at = $now;
            }
            $row->save();

            return $row;
        });

        $this->forgetCompletionCache((int) $registration->id, $year);

        return $row;
    }
}
