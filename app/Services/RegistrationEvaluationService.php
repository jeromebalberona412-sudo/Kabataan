<?php

namespace App\Services;

use App\Models\KabataanRegistration;

class RegistrationEvaluationService
{
    /**
     * Historical evaluation values. New submissions are never auto-approved.
     *
     * @return list<string>
     */
    public static function autoApprovedStatuses(): array
    {
        return ['Auto Approved', 'ID Verified'];
    }

    public static function isAutoApprovedStatus(?string $status): bool
    {
        return in_array($status, self::autoApprovedStatuses(), true);
    }

    /**
     * Mark the registration for manual SK Officials review.
     * Duplicate identities are flagged; nothing is auto-approved from Previous Kabataan.
     *
     * @param  array<string, mixed>|null  $idVerification
     */
    public function evaluate(KabataanRegistration $registration, ?array $idVerification = null): bool
    {
        unset($idVerification);

        if ($this->rejectDuplicateIfNeeded($registration)) {
            return false;
        }

        $formData = $registration->form_data ?? [];
        $documents = is_array($formData['supporting_documents'] ?? null)
            ? $formData['supporting_documents']
            : [];

        $message = $documents !== []
            ? 'Supporting documents uploaded. Awaiting SK Officials review.'
            : 'Awaiting SK Officials review.';

        $registration->update([
            'evaluation_status' => 'Not Profiled',
            'evaluation_notes' => ['message' => $message],
            'status' => 'password_set',
        ]);

        return false;
    }

    private function rejectDuplicateIfNeeded(KabataanRegistration $registration): bool
    {
        $duplicateChecker = app(DuplicateKabataanRegistrationService::class);
        $fields = array_merge($registration->form_data ?? [], [
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'last_name' => $registration->last_name,
            'suffix' => $registration->suffix,
        ]);

        if (! $duplicateChecker->hasApprovedDuplicate((int) $registration->barangay_id, $fields, $registration->id)) {
            return false;
        }

        $this->markDuplicate($registration, KkProfilingValidationMessages::DUPLICATE_IDENTITY);

        return true;
    }

    private function markDuplicate(KabataanRegistration $registration, string $message): void
    {
        $registration->update([
            'evaluation_status' => 'Duplicate',
            'evaluation_notes' => ['message' => $message],
        ]);
    }
}
