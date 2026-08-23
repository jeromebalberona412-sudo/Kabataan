<?php

namespace App\Services;

use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use Carbon\Carbon;

class KkSurveyResponseService
{
    public function syncFromRegistration(KabataanRegistration $registration, string $status = 'pending'): KkSurveyResponse
    {
        $payload = $this->mapFromRegistration($registration, $status);

        $response = KkSurveyResponse::query()->firstOrNew([
            'kabataan_registration_id' => $registration->id,
        ]);
        $response->fill($payload);
        $response->save();

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFromRegistration(KabataanRegistration $registration, string $status): array
    {
        $registration->loadMissing('barangay');
        $formData = $registration->form_data ?? [];

        $value = function (string $key) use ($formData) {
            $raw = $formData[$key] ?? null;

            if (is_array($raw)) {
                return $raw[0] ?? null;
            }

            return $raw;
        };

        $yesNo = fn (?string $input) => $this->triStateYesNo($input);

        $birthday = $value('birthday');
        $birthdate = null;
        if ($birthday) {
            try {
                $birthdate = Carbon::parse($birthday)->toDateString();
            } catch (\Throwable) {
                $birthdate = null;
            }
        }

        $barangay = $registration->barangay;

        return [
            'tenant_id' => $registration->tenant_id,
            'barangay_id' => $registration->barangay_id,
            'kabataan_registration_id' => $registration->id,
            'respondent_number' => $registration->respondent_number,
            'survey_date' => ($registration->submitted_at ?? now())->toDateString(),
            'last_name' => $registration->last_name,
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'suffix' => $registration->suffix,
            'region' => $barangay?->region ?? 'Region IV-A (CALABARZON)',
            'province' => $barangay?->province ?? 'Laguna',
            'municipality' => $barangay?->municipality ?? 'Santa Cruz',
            'barangay' => $barangay?->name,
            'purok_zone' => $value('purok_zone'),
            'sex_assigned_at_birth' => $value('sex'),
            'age' => is_numeric($value('age')) ? (int) $value('age') : null,
            'birthdate' => $birthdate,
            'email' => $registration->email,
            'contact_number' => $registration->contact_number,
            'civil_status' => $value('civil_status'),
            'youth_age_group' => $value('youth_age_group'),
            'educational_background' => $value('education'),
            'youth_classification' => $value('youth_classification'),
            'work_status' => $value('work_status'),
            'registered_sk_voter' => $yesNo($value('sk_voter')),
            'registered_national_voter' => $yesNo($value('national_voter')),
            'attended_kk_assembly' => $yesNo($value('kk_assembly')),
            'voted_last_sk' => $yesNo($value('sk_voted')),
            'kk_assembly_attendance_count' => $value('kk_times'),
            'kk_assembly_non_attendance_reason' => $value('kk_reason'),
            'facebook_profile_url' => $value('facebook_profile_url') ?: $value('facebook'),
            'willing_to_join_group_chat' => $yesNo($value('group_chat')),
            'participant_signature' => $value('signature'),
            'supporting_documents' => $this->resolveSupportingDocuments($formData),
            'consent_given' => true,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveSupportingDocuments(array $formData): ?array
    {
        $documents = $formData['supporting_documents'] ?? null;

        if (! is_array($documents) || $documents === []) {
            return null;
        }

        return array_values($documents);
    }

    private function triStateYesNo(?string $input): ?bool
    {
        $value = strtolower(trim((string) $input));

        if ($value === '' || $value === '—') {
            return null;
        }

        if (in_array($value, ['yes', 'true', '1'], true)) {
            return true;
        }

        if (in_array($value, ['no', 'false', '0'], true)) {
            return false;
        }

        return null;
    }
}
