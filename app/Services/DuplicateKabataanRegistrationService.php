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
     * Any non-rejected KK Profiling record with the same exact identity.
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
     * A duplicate only when every identity field is the same:
     * last name, first name, middle name, suffix, barangay, purok/zone, sex, age, and birthday.
     * One difference means it is not a duplicate.
     *
     * @param  array<string, mixed>  $candidateFields
     * @param  array<string, mixed>  $existingFields
     */
    public function identitiesMatch(int $barangayId, array $candidateFields, array $existingFields): bool
    {
        $candidate = $this->exactIdentity($barangayId, $candidateFields);
        $existing = $this->exactIdentity($barangayId, $existingFields);

        return $candidate !== null && $existing !== null && $candidate === $existing;
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array{
     *     last: string,
     *     first: string,
     *     middle: string,
     *     suffix: string,
     *     barangay_id: int,
     *     purok: string,
     *     sex: string,
     *     age: int,
     *     birthday: string
     * }|null
     */
    public function identityFingerprint(int $barangayId, array $registrationFields): ?array
    {
        return $this->exactIdentity($barangayId, $registrationFields);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{
     *     last: string,
     *     first: string,
     *     middle: string,
     *     suffix: string,
     *     barangay_id: int,
     *     purok: string,
     *     sex: string,
     *     age: int,
     *     birthday: string
     * }|null
     */
    private function exactIdentity(int $barangayId, array $fields): ?array
    {
        if ($barangayId <= 0) {
            return null;
        }

        $name = $this->nameMatcher->formComponentsFromFields($fields);
        $birthday = $this->normalizeBirthdate($fields['birthday'] ?? null);
        $purok = $this->normalizeLabel($fields['purok_zone'] ?? '');
        $sex = $this->normalizeLabel($fields['sex'] ?? '');
        $age = $this->normalizeAge($fields['age'] ?? null);

        if ($name['last'] === '' || $name['first'] === '' || $birthday === '' || $purok === '' || $sex === '' || $age === null) {
            return null;
        }

        return [
            'last' => $name['last'],
            'first' => $name['first'],
            'middle' => $name['middle'],
            'suffix' => $name['suffix'],
            'barangay_id' => $barangayId,
            'purok' => $purok,
            'sex' => $sex,
            'age' => $age,
            'birthday' => $birthday,
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

        $suffix = trim((string) ($formData['suffix'] ?? ''));

        return array_merge($formData, [
            'first_name' => $registration->first_name,
            'middle_name' => $registration->middle_name,
            'last_name' => $registration->last_name,
            'suffix' => $suffix !== '' ? $suffix : $registration->suffix,
            'birthday' => $formData['birthday'] ?? null,
            'purok_zone' => $formData['purok_zone'] ?? null,
            'sex' => $formData['sex'] ?? null,
            'age' => $formData['age'] ?? null,
        ]);
    }

    private function normalizeLabel(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strtoupper($value);
    }

    private function normalizeAge(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
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
