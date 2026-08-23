<?php

namespace App\Services;

/**
 * When Step 1 form data appears in OCR text, treat identity fields as verified.
 */
class FormAssistedIdOcrMatcher
{
    /**
     * @param  array<string, mixed>  $registrationFields
     */
    public function identityVisibleInOcr(string $text, array $registrationFields, ?string $barangayName): bool
    {
        $text = strtoupper($text);
        if (trim($text) === '') {
            return false;
        }

        if (! $this->nameVisibleInOcr($text, $registrationFields)) {
            return false;
        }

        if (! $this->birthdateVisibleInOcr($text, $registrationFields)) {
            return false;
        }

        return $this->barangayVisibleInOcr($text, $barangayName);
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    public function nameVisibleInOcr(string $text, array $registrationFields): bool
    {
        $lastName = strtoupper(trim((string) ($registrationFields['last_name'] ?? '')));
        $firstName = strtoupper(trim((string) ($registrationFields['first_name'] ?? '')));

        if ($lastName === '' || $firstName === '') {
            return false;
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', $text) ?? '';
        $lastKey = preg_replace('/[^A-Z0-9]/', '', $lastName) ?? '';

        if ($lastKey === '' || ! str_contains($compact, $lastKey)) {
            return false;
        }

        $firstKey = preg_replace('/[^A-Z0-9]/', '', $firstName) ?? '';
        if ($firstKey !== '' && str_contains($compact, $firstKey)) {
            return true;
        }

        foreach (preg_split('/\s+/', $firstName) as $token) {
            $tokenKey = preg_replace('/[^A-Z0-9]/', '', $token) ?? '';
            if ($tokenKey !== '' && str_contains($compact, $tokenKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    public function birthdateVisibleInOcr(string $text, array $registrationFields): bool
    {
        $birthday = (string) ($registrationFields['birthday'] ?? '');
        if (trim($birthday) === '') {
            return false;
        }

        try {
            $formDate = \Carbon\Carbon::parse($birthday)->toDateString();
        } catch (\Throwable) {
            $formDate = $birthday;
        }

        $year = null;
        if (preg_match('/\b(19\d{2}|20[0-1]\d)\b/', $formDate, $match)) {
            $year = $match[1];
        }

        if ($year !== null && str_contains($text, $year)) {
            return true;
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text)) ?? '';
        $isoCompact = str_replace('-', '', $formDate);

        return $isoCompact !== '' && str_contains($compact, $isoCompact);
    }

    public function barangayVisibleInOcr(string $text, ?string $barangayName): bool
    {
        $barangayName = trim((string) $barangayName);
        if ($barangayName === '') {
            return false;
        }

        return stripos($text, $barangayName) !== false;
    }
}
