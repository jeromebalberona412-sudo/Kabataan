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
     * Profile-only fields for yearly KK update (Section I).
     * Demographic characteristics, footer fields, and signature are left blank.
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

        return array_merge($prefill, [
            'last_name' => $registration->last_name,
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'suffix' => $suffix,
            'email' => $registration->email,
            'contact_number' => $registration->contact_number,
            'respondent_number' => $formData['respondent_number'] ?? $registration->respondent_number,
        ]);
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
