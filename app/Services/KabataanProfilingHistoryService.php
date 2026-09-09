<?php

namespace App\Services;

use App\Models\KabataanProfilingHistory;
use App\Models\KabataanRegistration;
use Illuminate\Support\Facades\Schema;

class KabataanProfilingHistoryService
{
    public function saveSnapshot(
        KabataanRegistration $registration,
        int $profilingYear,
        ?int $scheduleId = null,
    ): KabataanProfilingHistory {
        return KabataanProfilingHistory::query()->updateOrCreate(
            [
                'kabataan_registration_id' => $registration->id,
                'profiling_year' => $profilingYear,
            ],
            [
                'kk_profiling_schedule_id' => $scheduleId,
                'form_data' => $registration->form_data ?? [],
                'last_name' => (string) $registration->last_name,
                'first_name' => (string) $registration->first_name,
                'middle_name' => $registration->middle_name,
                'suffix' => $registration->suffix,
                'email' => (string) $registration->email,
                'contact_number' => $registration->contact_number,
                'submitted_at' => $registration->submitted_at ?? now(),
            ],
        );
    }

    /**
     * Personal/basic fields only for yearly KK update (Section I).
     * Current-year demographic/questionnaire answers must be re-entered blank.
     *
     * Prefill: last_name, first_name, middle_name, suffix, custom_suffix,
     * purok_zone, sex, age, birthday, email, contact_number, respondent_number.
     *
     * Intentionally excluded (blank on update form):
     * civil_status, youth_classification, youth_age_group, education, work_status,
     * sk_voter, national_voter, sk_voted, kk_assembly, kk_times, kk_reason,
     * signature_name, signature.
     *
     * @return array<string, mixed>
     */
    public function formDataForUpdate(KabataanRegistration $registration): array
    {
        $formData = is_array($registration->form_data) ? $registration->form_data : [];

        $profileKeys = [
            'purok_zone',
            'sex',
            'age',
            'birthday',
            'custom_suffix',
            'respondent_number',
        ];

        $prefill = [];
        foreach ($profileKeys as $key) {
            if (array_key_exists($key, $formData) && $formData[$key] !== null && $formData[$key] !== '') {
                $prefill[$key] = $formData[$key];
            }
        }

        $suffix = trim((string) ($registration->suffix ?? ''));
        if ($suffix === '' || strcasecmp($suffix, 'none') === 0) {
            $suffix = 'None';
        }

        // Explicitly do not carry prior-year questionnaire answers into UPDATE mode.
        return [
            'last_name' => $registration->last_name,
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'suffix' => $suffix,
            'email' => $registration->email,
            'contact_number' => $registration->contact_number,
            'respondent_number' => $formData['respondent_number'] ?? $registration->respondent_number,
            'purok_zone' => $prefill['purok_zone'] ?? null,
            'sex' => $prefill['sex'] ?? null,
            'age' => $prefill['age'] ?? null,
            'birthday' => $prefill['birthday'] ?? null,
            'custom_suffix' => $prefill['custom_suffix'] ?? null,
        ];
    }

    /**
     * @return list<int>
     */
    public function availableYears(?int $barangayId = null): array
    {
        if (! Schema::hasTable('kabataan_profiling_history')) {
            return [];
        }

        $query = KabataanProfilingHistory::query()
            ->select('profiling_year')
            ->distinct()
            ->orderByDesc('profiling_year');

        if ($barangayId) {
            $query->whereHas('registration', fn ($builder) => $builder->where('barangay_id', $barangayId));
        }

        return $query->pluck('profiling_year')->map(fn ($year) => (int) $year)->all();
    }
}
