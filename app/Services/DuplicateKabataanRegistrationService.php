<?php

namespace App\Services;

use App\Models\KabataanRegistration;
use Carbon\Carbon;

class DuplicateKabataanRegistrationService
{
    public function __construct(
        private readonly KabataanFullNameMatcher $nameMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    public function findApprovedDuplicate(int $barangayId, array $registrationFields, ?int $excludeRegistrationId = null): ?KabataanRegistration
    {
        return $this->findMatchingIdentity($barangayId, $registrationFields, $excludeRegistrationId, true);
    }

    /**
     * Any non-rejected KK Profiling record with the same identity (name + birthday + barangay).
     *
     * @param  array<string, mixed>  $registrationFields
     */
    public function findExistingIdentity(int $barangayId, array $registrationFields, ?int $excludeRegistrationId = null): ?KabataanRegistration
    {
        return $this->findMatchingIdentity($barangayId, $registrationFields, $excludeRegistrationId, false);
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    private function findMatchingIdentity(
        int $barangayId,
        array $registrationFields,
        ?int $excludeRegistrationId,
        bool $approvedOnly
    ): ?KabataanRegistration {
        $candidate = $this->identityFingerprint($barangayId, $registrationFields);

        if ($candidate === null) {
            return null;
        }

        $query = KabataanRegistration::query()
            ->where('barangay_id', $barangayId)
            ->whereNotIn('status', ['rejected']);

        if ($excludeRegistrationId !== null) {
            $query->where('id', '!=', $excludeRegistrationId);
        }

        foreach ($query->get() as $registration) {
            if ($approvedOnly && ! $this->isApprovedKabataan($registration)) {
                continue;
            }

            $existingFields = $this->registrationFields($registration);

            if ($this->identitiesMatch($barangayId, $registrationFields, $existingFields)) {
                return $registration;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    public function hasApprovedDuplicate(int $barangayId, array $registrationFields, ?int $excludeRegistrationId = null): bool
    {
        return $this->findApprovedDuplicate($barangayId, $registrationFields, $excludeRegistrationId) !== null;
    }

    /**
     * @param  array<string, mixed>  $candidateFields
     * @param  array<string, mixed>  $existingFields
     */
    public function identitiesMatch(int $barangayId, array $candidateFields, array $existingFields): bool
    {
        if ($barangayId <= 0) {
            return false;
        }

        $candidateBirth = $this->normalizeBirthdate($candidateFields['birthday'] ?? null);
        $existingBirth = $this->normalizeBirthdate($existingFields['birthday'] ?? null);

        if ($candidateBirth === '' || $existingBirth === '' || $candidateBirth !== $existingBirth) {
            return false;
        }

        $candidateName = $this->nameMatcher->formComponentsFromFields($candidateFields);
        $existingName = $this->nameMatcher->formComponentsFromFields($existingFields);

        return $this->nameMatcher->matches($candidateName, $existingName);
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array{name_key: string, birthdate: string, barangay_id: int}|null
     */
    public function identityFingerprint(int $barangayId, array $registrationFields): ?array
    {
        $nameKey = $this->nameMatcher->normalizedKeyFromFormFields($registrationFields);
        $birthdate = $this->normalizeBirthdate($registrationFields['birthday'] ?? null);

        if ($nameKey === '' || $birthdate === '' || $barangayId <= 0) {
            return null;
        }

        return [
            'name_key' => $nameKey,
            'birthdate' => $birthdate,
            'barangay_id' => $barangayId,
        ];
    }

    public function isApprovedKabataan(KabataanRegistration $registration): bool
    {
        if ($registration->status === 'rejected') {
            return false;
        }

        $evaluation = $registration->evaluation_status;

        if (in_array($evaluation, ['Not Profiled', 'Wrong Credentials', 'Duplicate'], true)) {
            return false;
        }

        return in_array($evaluation, ['active', 'Auto Approved', 'ID Verified'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationFields(KabataanRegistration $registration): array
    {
        $formData = is_array($registration->form_data) ? $registration->form_data : [];

        return array_merge($formData, [
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'last_name' => $registration->last_name,
            'suffix' => $registration->suffix,
            'birthday' => $formData['birthday'] ?? null,
        ]);
    }

    private function normalizeBirthdate(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }
}
