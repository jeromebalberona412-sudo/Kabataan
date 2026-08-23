<?php

namespace App\Services;

use App\Models\KabataanProfilingHistory;
use App\Models\KabataanRegistration;
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
                ->where(function ($window) use ($today) {
                    $window->whereNull('date_start')->orWhere('date_start', '<=', $today);
                })
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
     * True when this kabataan already finished the yearly update for $year.
     * Officials toggling the schedule on/off later must not ask them again.
     *
     * @param  array<string, mixed>  $formData
     */
    public function formDataCompletedYear(array $formData, int $year): bool
    {
        return ! empty($formData['profile_updated_year'])
            && (int) $formData['profile_updated_year'] >= $year;
    }

    public function hasCompletedProfilingForYear(KabataanRegistration $registration, int $year): bool
    {
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
    }

    public function scheduleProfilingYear(?object $schedule): int
    {
        if ($schedule === null) {
            return $this->expectedProfilingYear();
        }

        return (int) ($schedule->profiling_year ?? $this->expectedProfilingYear());
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

        // Accounts created/submitted in the same profiling year already
        // contain current data — no update needed regardless of schedule toggling.
        $submittedYear = (int) \Carbon\Carbon::parse($registration->submitted_at)
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
}
