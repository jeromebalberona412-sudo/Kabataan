<?php

namespace App\Services;

final class KkProfilingValidationMessages
{
    public const DUPLICATE_IDENTITY = 'Duplicate registration detected. A Kabataan account with the same Full Name, Date of Birth, and Barangay already exists.';

    /** @deprecated Use DUPLICATE_IDENTITY */
    public const DUPLICATE_FULL_NAME = self::DUPLICATE_IDENTITY;

    public static function ocrFrontReadFailed(): string
    {
        return 'Could not read the name on the front of your School ID. Please re-upload a clear photo of the front side.';
    }

    public static function ocrBackReadFailed(): string
    {
        return 'Could not read the address on the back of your School ID. Please re-upload a clear photo of the back side showing your address.';
    }

    public static function uploadProcessingFailed(): string
    {
        return 'Your School ID files could not be processed. Please re-upload both the front and back images.';
    }

    /**
     * @param  array<string, mixed>  $verification
     * @return list<string>
     */
    public static function collectSchoolIdMismatches(array $verification): array
    {
        $verification = self::enrichVerificationForDisplay($verification);
        $messages = [];

        if (! ($verification['name_match'] ?? false)) {
            $messages[] = self::nameMismatch(
                (string) ($verification['form_full_name'] ?? ''),
                (string) ($verification['detected_full_name'] ?? ''),
            );
        }

        if (array_key_exists('birthdate_match', $verification) && ! ($verification['birthdate_match'] ?? false)) {
            $pipeline = is_array($verification['pipeline'] ?? null) ? $verification['pipeline'] : [];
            $birthdateValidation = is_array($pipeline['validations']['birthdate'] ?? null)
                ? $pipeline['validations']['birthdate']
                : [];

            $messages[] = self::birthdateMismatch(
                (string) ($birthdateValidation['form'] ?? ($verification['form_birthday'] ?? '')),
                (string) ($birthdateValidation['extracted'] ?? ($verification['_display_birth_extracted'] ?? ($pipeline['birthdate'] ?? ''))),
            );
        }

        if (! ($verification['barangay_match'] ?? false)) {
            $messages[] = self::barangayMismatch(
                (string) ($verification['form_barangay'] ?? ($verification['registration_barangay'] ?? '')),
                (string) ($verification['barangay'] ?? ($verification['detected_address'] ?? '')),
            );
        }

        return $messages;
    }

    public static function schoolIdValidationBlocked(array $verification): string
    {
        $verification = self::enrichVerificationForDisplay($verification);

        if (self::verificationOcrUnreadable($verification)) {
            return self::uploadProcessingFailed();
        }

        $messages = self::collectSchoolIdMismatches($verification);

        if ($messages === []) {
            return self::uploadProcessingFailed();
        }

        $allUnreadable = collect($messages)->every(
            fn (string $message) => str_contains($message, '(unreadable)')
        );

        if ($allUnreadable) {
            return self::uploadProcessingFailed();
        }

        return "Your KK Profiling Form does not match your uploaded School ID.\n\n"
            .implode("\n\n", $messages)
            ."\n\nPlease update Step 1 or upload a clear front and back photo of your School ID.";
    }

    public static function barangayMismatch(string $formBarangay, string $idBarangay): string
    {
        $formBarangay = trim($formBarangay) !== '' ? trim($formBarangay) : '(not provided)';
        $idBarangay = trim($idBarangay) !== '' ? trim($idBarangay) : '(unreadable)';

        return 'Barangay mismatch. KK Profiling Form: '.$formBarangay.'. School ID (back): '.$idBarangay.'.';
    }

    public static function nameMismatch(string $formFullName, string $idFullName): string
    {
        $formFullName = trim($formFullName) !== '' ? trim($formFullName) : '(not provided)';
        $idFullName = trim($idFullName) !== '' ? trim($idFullName) : '(unreadable)';

        return 'Full name mismatch. KK Profiling Form: '.$formFullName.'. School ID (front): '.$idFullName.'.';
    }

    public static function addressMismatch(string $formAddress, string $idAddress): string
    {
        $formAddress = trim($formAddress) !== '' ? trim($formAddress) : '(not provided)';
        $idAddress = trim($idAddress) !== '' ? trim($idAddress) : '(unreadable)';

        return 'Address mismatch. KK Profiling Form: '.$formAddress.'. School ID (back): '.$idAddress.'.';
    }

    public static function birthdateMismatch(string $formBirthday, string $idBirthday): string
    {
        $formYear = self::extractYear($formBirthday) ?? '(not provided)';
        $idYear = self::extractYear($idBirthday) ?? '(unreadable)';

        return 'Birth year mismatch. KK Profiling Form: '.$formYear.'. School ID (back): '.$idYear.'.';
    }

    private static function extractYear(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/\b(19\d{2}|20[0-1]\d)\b/', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function formAddressLabel(array $fields, ?string $barangayName = null): string
    {
        $parts = array_values(array_filter([
            trim((string) ($fields['purok_zone'] ?? '')),
            trim((string) ($barangayName ?? '')),
            'Santa Cruz',
            'Laguna',
        ], fn (string $part) => $part !== ''));

        return $parts !== [] ? implode(', ', $parts) : '(not provided)';
    }

    /**
     * @param  array<string, mixed>  $verification
     * @return array<string, mixed>
     */
    public static function enrichVerificationForDisplay(array $verification): array
    {
        $ocrFront = is_array($verification['ocr']['front'] ?? null) ? $verification['ocr']['front'] : [];
        $ocrBack = is_array($verification['ocr']['back'] ?? null) ? $verification['ocr']['back'] : [];
        $pipeline = is_array($verification['pipeline'] ?? null) ? $verification['pipeline'] : [];

        if (empty($verification['form_barangay'])) {
            $verification['form_barangay'] = (string) (
                $verification['registration_barangay'] ?? ''
            );
        }

        if (empty($verification['detected_full_name'])) {
            $verification['detected_full_name'] = $pipeline['full_name']
                ?? $pipeline['raw_name']
                ?? ($ocrFront['raw_name'] ?? null);
        }

        if (empty($verification['barangay'])) {
            $verification['barangay'] = $pipeline['barangay'] ?? null;
        }

        $birthExtracted = $pipeline['birthdate']
            ?? $pipeline['birthdate_raw']
            ?? ($pipeline['validations']['birthdate']['extracted'] ?? null);

        if (empty($birthExtracted) && ! empty($ocrBack['full_text'])) {
            if (preg_match('/\b(19\d{2}|20[0-1]\d)\b/', (string) $ocrBack['full_text'], $match)) {
                $birthExtracted = $match[1];
            }
        }

        if (! empty($birthExtracted)) {
            $verification['_display_birth_extracted'] = (string) $birthExtracted;
        }

        if (empty($verification['barangay']) && ! empty($ocrBack['full_text'])) {
            $formBarangay = (string) ($verification['form_barangay'] ?? ($verification['registration_barangay'] ?? ''));
            if ($formBarangay !== '' && stripos((string) $ocrBack['full_text'], $formBarangay) !== false) {
                $verification['barangay'] = $formBarangay;
            }
        }

        return $verification;
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    public static function verificationOcrUnreadable(array $verification): bool
    {
        if (trim((string) ($verification['detected_full_name'] ?? '')) !== '') {
            return false;
        }

        if (trim((string) ($verification['barangay'] ?? '')) !== '') {
            return false;
        }

        if (trim((string) ($verification['_display_birth_extracted'] ?? '')) !== '') {
            return false;
        }

        $frontText = trim((string) ($verification['ocr']['front']['full_text'] ?? ''));
        $backText = trim((string) ($verification['ocr']['back']['full_text'] ?? ''));

        return $frontText === '' && $backText === '';
    }
}
