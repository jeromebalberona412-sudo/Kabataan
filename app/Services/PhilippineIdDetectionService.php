<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class PhilippineIdDetectionService
{
    public function __construct(
        private readonly OCRService $ocrService,
        private readonly KabataanFullNameMatcher $nameMatcher,
        private readonly FormAssistedIdOcrMatcher $formAssistedMatcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detectSingle(string $absoluteImagePath, ?string $documentType = null): array
    {
        return $this->ocrService->detectId($absoluteImagePath, $documentType);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectPair(
        string $frontPath,
        string $backPath,
        ?string $documentType = null,
        array $registrationFields = [],
    ): array {
        return $this->ocrService->detectIdPair($frontPath, $backPath, $documentType, $registrationFields);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectUploadedPair(
        UploadedFile $front,
        UploadedFile $back,
        ?string $documentType = null,
        array $registrationFields = [],
    ): array {
        return $this->ocrService->detectIdPairFromUploads($front, $back, $documentType, $registrationFields);
    }

    /**
     * Map OCR payload to KK Profiling Step 1 form fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mapToFormFields(array $payload): array
    {
        $birthdate = $payload['birthdate'] ?? null;
        $age = null;

        if (is_string($birthdate) && $birthdate !== '') {
            try {
                $age = Carbon::parse($birthdate)->age;
            } catch (\Throwable) {
                $age = null;
            }
        }

        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $parsed = $fullName !== ''
            ? $this->nameMatcher->parseOcrName($fullName)
            : null;

        return array_filter([
            'first_name' => $payload['given_name'] ?? ($parsed['first'] ?? null),
            'middle_name' => $payload['middle_name'] ?? ($parsed['middle'] ?? null),
            'last_name' => $payload['surname'] ?? ($parsed['last'] ?? null),
            // Sex/gender is never suggested from ID reads.
            'birthday' => $birthdate,
            'age' => $age,
            'purok_zone' => $payload['address'] ?? null,
            'detected_full_name' => $payload['full_name'] ?? null,
            'detected_address' => $payload['address'] ?? null,
            'id_type' => $payload['id_type'] ?? null,
            'id_number' => $payload['id_number'] ?? null,
            'confidence' => $payload['confidence'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    public function buildVerificationRecord(
        array $payload,
        string $documentType,
        array $registrationFields = [],
    ): array {
        $formSuggestions = $this->mapToFormFields($payload);
        $rawText = trim((string) ($payload['raw_text'] ?? ''));
        $detectedName = trim((string) ($payload['full_name'] ?? ''));
        if ($detectedName === '') {
            $detectedName = trim(implode(' ', array_filter([
                $payload['given_name'] ?? null,
                $payload['middle_name'] ?? null,
                $payload['surname'] ?? null,
            ])));
        }
        $detectedName = $this->sanitizeDetectedPersonName($detectedName) ?? '';

        // Keep raw structured AI parts in the haystack even when full_name was sanitized away.
        $nameHaystack = trim(implode(' ', array_filter([
            $detectedName,
            $this->sanitizeDetectedPersonName((string) ($payload['given_name'] ?? '')) ?? null,
            $this->sanitizeDetectedPersonName((string) ($payload['middle_name'] ?? '')) ?? null,
            $this->sanitizeDetectedPersonName((string) ($payload['surname'] ?? '')) ?? null,
            $this->sanitizeDetectedPersonName((string) ($payload['full_name'] ?? '')) ?? null,
        ])));

        $form = $this->nameMatcher->formComponentsFromFields($registrationFields);
        $formFullName = $this->nameMatcher->formatFormFullNameForDisplay($registrationFields);

        $nameMatch = $this->resolveNameMatch(
            $form,
            $nameHaystack !== '' ? $nameHaystack : $detectedName,
            $rawText,
            $registrationFields,
        );
        $birthdateMatch = $this->datesMatch(
            (string) ($registrationFields['birthday'] ?? ''),
            (string) ($payload['birthdate'] ?? ''),
        );

        if (! $birthdateMatch && $rawText !== '' && $this->formAssistedMatcher->birthdateVisibleInOcr($rawText, $registrationFields)) {
            $birthdateMatch = true;
        }

        $addressMatch = $this->resolveAddressMatch(
            $registrationFields,
            (string) ($payload['address'] ?? ''),
            $rawText,
        );

        $detectedSex = $this->normalizeSexValue($payload['sex'] ?? ($payload['detected_sex'] ?? null));
        $formSex = $this->normalizeSexValue($registrationFields['sex'] ?? null);
        $sexMatch = null;
        if ($detectedSex !== null && $formSex !== null) {
            $sexMatch = $detectedSex === $formSex;
        }

        $success = (bool) ($payload['success'] ?? false);
        $needsReview = (bool) ($payload['needs_review'] ?? false);
        $confidence = (float) ($payload['confidence'] ?? 0);
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.45);
        $message = $payload['message'] ?? null;

        $hasFormName = ($form['first'] ?? '') !== '' && ($form['last'] ?? '') !== '';
        $hasFormBirthday = trim((string) ($registrationFields['birthday'] ?? '')) !== '';
        $hasDetectedBirthday = trim((string) ($payload['birthdate'] ?? '')) !== '';
        $requireNameSignal = (bool) config('ocr.require_name_signal_for_success', true);

        $validationError = (bool) ($payload['validation_error'] ?? false);
        $documentDetectedRaw = $payload['document_detected'] ?? null;
        $documentDetectedNormalized = $this->normalizeDocumentDetectedValue($documentDetectedRaw);
        $verificationStatus = strtolower((string) ($payload['verification_status'] ?? ''));

        if ($verificationStatus === '' && $validationError && $documentDetectedNormalized === null) {
            $verificationStatus = 'unavailable';
        } elseif ($verificationStatus === '' && $documentDetectedNormalized === false) {
            $verificationStatus = 'success';
        } elseif ($verificationStatus === '' && ! $validationError) {
            $verificationStatus = 'success';
        }

        if (in_array($verificationStatus, ['unavailable', 'error', 'invalid_response', 'invalid_image'], true)) {
            $success = false;
            $needsReview = false;
            $validationError = true;
            $documentDetectedNormalized = null;
        } elseif ($validationError || $documentDetectedNormalized === false) {
            $success = false;
            $needsReview = false;
            $validationError = true;
            if ($documentDetectedNormalized === false && trim((string) $message) === '') {
                $message = 'This does not appear to be a valid ID. Please upload a clear front and back photo of your selected ID.';
            }
            // Keep AI/OCR mismatch messages (wrong type) when already provided.
            if (
                trim((string) $message) === ''
                && is_string($documentType)
                && $documentType !== ''
                && $documentType !== 'other_id'
            ) {
                $detectedType = trim((string) ($payload['detected_id_type'] ?? $payload['id_type'] ?? ''));
                if (
                    $detectedType !== ''
                    && strcasecmp($detectedType, 'Unknown') !== 0
                    && $detectedType !== $documentType
                ) {
                    $message = 'You selected a different ID type than the one in the photos. Please upload the correct ID or change the document type.';
                }
            }
        } elseif ($documentDetectedNormalized === true && $hasFormName && $requireNameSignal) {
            // Compare overlapping Step 1 ↔ ID fields only when both sides have data.
            if ($detectedName === '') {
                $success = false;
                $needsReview = false;
                $validationError = true;
                $message = 'We could not read the name on this ID. Please upload a clearer front photo of your own ID that matches your Step 1 name ("'.$formFullName.'").';
            } elseif (! $nameMatch) {
                $success = false;
                $needsReview = false;
                $validationError = true;
                $idLabel = $detectedName !== '' ? $detectedName : 'the name on the ID';
                $message = 'Name mismatch. Your Step 1 profile is "'.$formFullName.'" but the ID shows "'.$idLabel.'". Please upload your own ID or correct your profiling details.';
            } elseif ($hasFormBirthday && $hasDetectedBirthday && ! $birthdateMatch) {
                $success = false;
                $needsReview = false;
                $validationError = true;
                $message = 'Birthday on the ID does not match your Step 1 birthday. Please upload your own ID or correct your profiling details.';
            } elseif ($sexMatch === false) {
                $success = false;
                $needsReview = false;
                $validationError = true;
                $message = 'Sex on the ID does not match your Step 1 sex. Please upload your own ID or correct your profiling details.';
            } elseif ($addressMatch === false) {
                $success = false;
                $needsReview = false;
                $validationError = true;
                $message = 'Address on the ID does not match Santa Cruz, Laguna (or your Step 1 barangay/purok). Please upload your own local ID or correct your profiling details.';
            } else {
                $acceptFloor = max(0.40, $minConfidence - 0.05);
                $hasIdText = $rawText !== '' && $this->ocrService->looksLikeSupportingIdText($rawText);
                $identityOk = $nameMatch && ($hasIdText || $confidence >= $acceptFloor || $detectedName !== '');

                if ($identityOk) {
                    $confidence = max($confidence, ($birthdateMatch || $sexMatch === true || $addressMatch === true) ? 0.78 : 0.72);
                    $success = true;
                    $needsReview = $confidence < 0.8;
                    // Keep success quiet in the UI — mismatches carry the messages.
                    $message = null;
                } else {
                    $success = false;
                    $needsReview = false;
                    $validationError = true;
                    $message = 'We could not confidently match your Step 1 name ("'.$formFullName.'") to this ID. Please upload a clearer front and back photo of your own ID.';
                }
            }
        } elseif (
            $documentDetectedNormalized === true
            && is_string($documentType)
            && $documentType !== ''
            && $documentType !== 'other_id'
            && ! $validationError
        ) {
            // Type mismatch messages from OCR/AI must stay visible even when name gate is skipped.
            $detectedType = trim((string) ($payload['detected_id_type'] ?? $payload['id_type'] ?? ''));
            if (
                $detectedType !== ''
                && strcasecmp($detectedType, 'Unknown') !== 0
                && $detectedType !== $documentType
                && in_array($detectedType, ['national_id', 'philhealth_id', 'voters_id', 'school_id'], true)
            ) {
                $success = false;
                $needsReview = false;
                $validationError = true;
                if (trim((string) $message) === '') {
                    $message = 'You selected a different ID type than the one in the photos. Please upload the correct ID or change the document type.';
                }
            }
        }

        $documentDetectedOut = $documentDetectedNormalized === true
            ? 'yes'
            : ($documentDetectedNormalized === false ? 'no' : null);

        return [
            'success' => $success,
            'source' => 'philippine_id_ocr_v1',
            'document_type' => $documentType,
            'id_type' => $payload['id_type'] ?? 'Unknown',
            'confidence' => $confidence,
            'confidence_band' => $payload['confidence_band'] ?? null,
            'detected_name' => $detectedName !== '' ? $detectedName : null,
            'detected_address' => $payload['address'] ?? null,
            'detected_birthdate' => $payload['birthdate'] ?? null,
            'detected_sex' => $detectedSex,
            'id_number' => $payload['id_number'] ?? null,
            'name_match' => $nameMatch,
            'birthdate_match' => $birthdateMatch,
            'sex_match' => $sexMatch,
            'address_match' => $addressMatch,
            'form_suggestions' => $formSuggestions,
            'needs_review' => $validationError ? false : ($needsReview || ! $success),
            'verification_status' => $verificationStatus !== '' ? $verificationStatus : 'success',
            'document_detected' => $documentDetectedOut,
            'ocr_status' => $payload['ocr_status'] ?? null,
            'raw_text' => $rawText !== '' ? $rawText : null,
            'pair_hash' => $payload['pair_hash'] ?? null,
            'from_cache' => (bool) ($payload['from_cache'] ?? false),
            'is_philippine_id' => $payload['is_philippine_id'] ?? null,
            'image_quality' => $payload['image_quality'] ?? null,
            'expiry_date' => $payload['expiry_date'] ?? null,
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : null,
            'ocr' => [
                'front' => $this->sanitizeOcrSide($payload['front'] ?? null),
                'back' => $this->sanitizeOcrSide($payload['back'] ?? null),
                'text_length' => (int) ($payload['text_length'] ?? mb_strlen($rawText)),
            ],
            'message' => $message,
            'validation_error' => $validationError,
            'processed_at' => now()->toIso8601String(),
        ];
    }

    private function normalizeDocumentDetectedValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((float) $value) > 0;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '' || $raw === 'null' || $raw === 'unknown') {
            return null;
        }

        if (in_array($raw, ['yes', 'true', '1', 'y'], true)) {
            return true;
        }

        if (in_array($raw, ['no', 'false', '0', 'n'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Reject institution/header OCR junk that is not a person name.
     */
    private function sanitizeDetectedPersonName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $upper = strtoupper($name);
        foreach ([
            'REPUBLIC OF THE PHILIPPINES',
            'REPUBLIC OF THE',
            'PROVINCIAL GOVERNMENT',
            'LOCAL GOVERNMENT',
            'UNIVERSITY',
            'COLLEGE OF',
            'DEPARTMENT OF',
            'COMMISSION ON',
            'PHILSYS',
            'PHILHEALTH',
            'NATIONAL ID',
        ] as $fragment) {
            if (str_contains($upper, $fragment)) {
                return null;
            }
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $name) ?? '';
        if (strlen($letters) < 5) {
            return null;
        }

        $tokens = preg_split('/[\s,]+/', $name) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));
        if (count($tokens) < 2) {
            return null;
        }

        return $name;
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     * @param  array<string, mixed>  $registrationFields
     */
    private function resolveNameMatch(array $form, string $detectedName, string $rawText, array $registrationFields): bool
    {
        if (($form['first'] ?? '') === '' || ($form['last'] ?? '') === '') {
            return false;
        }

        // Combine every available ID name signal — printed order/format does not matter.
        $haystacks = array_values(array_filter([
            $detectedName,
            $rawText,
        ], static fn ($t) => trim((string) $t) !== ''));

        if ($haystacks !== [] && $this->nameMatcher->formIdentityVisibleInText($form, ...$haystacks)) {
            return true;
        }

        if ($detectedName !== '') {
            if ($this->nameMatcher->matchesFormToOcrText($form, $detectedName, false)) {
                return true;
            }

            $parsed = $this->nameMatcher->parseOcrName($detectedName, $form);
            if (is_array($parsed) && $this->nameMatcher->matches($form, $parsed, false)) {
                return true;
            }
        }

        if ($rawText !== '') {
            if ($this->nameMatcher->matchesFormToOcrText($form, $rawText, false)) {
                return true;
            }
            if ($this->formAssistedMatcher->nameVisibleInOcr($rawText, $registrationFields)) {
                return $this->nameMatcher->formIdentityVisibleInText($form, $rawText, $detectedName)
                    || ($form['middle'] ?? '') === '';
            }
        }

        return $this->fieldsMatchLegacy(
            trim(implode(' ', array_filter([
                $registrationFields['first_name'] ?? null,
                $registrationFields['last_name'] ?? null,
            ]))),
            $detectedName !== '' ? $detectedName : $rawText,
        );
    }

    /**
     * Soft address match when the ID shows locality/purok text.
     * Expected locality for Kabataan: barangay + Santa Cruz, Laguna (Region IV-A).
     * Returns null when comparison should be skipped (no address signal on ID).
     */
    private function resolveAddressMatch(array $registrationFields, string $detectedAddress, string $rawText): ?bool
    {
        $haystack = strtoupper(trim($detectedAddress.' '.$rawText));
        if ($haystack === '') {
            return null;
        }

        $normalizedHay = preg_replace('/[^A-Z0-9\s]/', ' ', $haystack) ?? $haystack;
        $normalizedHay = preg_replace('/\s+/', ' ', trim($normalizedHay)) ?? '';
        if ($normalizedHay === '') {
            return null;
        }

        $hasAddressSignal = (bool) preg_match(
            '/\b(PUROK|ZONE|SITIO|BARANGAY|BRGY|STREET|CITY|MUNICIPALITY|PROVINCE|REGION|LAGUNA|CALABARZON)\b/',
            $normalizedHay
        );

        if (! $hasAddressSignal && strlen($normalizedHay) < 24) {
            return null;
        }

        // Locality expected for this portal (Santa Cruz, Laguna).
        $barangay = strtoupper(trim((string) (
            $registrationFields['_barangay_name']
            ?? $registrationFields['barangay']
            ?? $registrationFields['barangay_name']
            ?? ''
        )));
        $municipality = strtoupper(trim((string) ($registrationFields['_municipality'] ?? 'SANTA CRUZ')));
        $province = strtoupper(trim((string) ($registrationFields['_province'] ?? 'LAGUNA')));

        $localityHits = 0;
        $localityChecks = 0;

        if ($province !== '') {
            $localityChecks++;
            if (
                str_contains($normalizedHay, $province)
                || str_contains($normalizedHay, 'LAGUNA')
            ) {
                $localityHits++;
            }
        }

        if ($municipality !== '') {
            $localityChecks++;
            $muniOk = str_contains($normalizedHay, $municipality)
                || str_contains($normalizedHay, 'STA CRUZ')
                || str_contains($normalizedHay, 'STA  CRUZ')
                || str_contains($normalizedHay, 'SANTA CRUZ');
            if ($muniOk) {
                $localityHits++;
            }
        }

        if ($barangay !== '' && strlen($barangay) >= 3) {
            $localityChecks++;
            $barangayNorm = preg_replace('/[^A-Z0-9\s]/', ' ', $barangay) ?? $barangay;
            $barangayNorm = preg_replace('/\s+/', ' ', trim($barangayNorm)) ?? '';
            if ($barangayNorm !== '' && str_contains($normalizedHay, $barangayNorm)) {
                $localityHits++;
            }
        }

        // Soft region check — never hard-fail alone.
        $regionOk = str_contains($normalizedHay, 'REGION IV')
            || str_contains($normalizedHay, 'REGION 4')
            || str_contains($normalizedHay, 'CALABARZON')
            || str_contains($normalizedHay, 'IVA');

        // Hard fail: ID clearly shows a different province/city outside Santa Cruz, Laguna.
        $otherProvince = (bool) preg_match(
            '/\b(CAVITE|BATANGAS|RIZAL|QUEZON|MANILA|MAKATI|QUEZON CITY|BULACAN|PAMPANGA|CEBU|DAVAO)\b/',
            $normalizedHay
        );
        $hasLaguna = str_contains($normalizedHay, 'LAGUNA');
        $hasSantaCruz = str_contains($normalizedHay, 'SANTA CRUZ')
            || str_contains($normalizedHay, 'STA CRUZ');

        if ($hasAddressSignal && $otherProvince && ! $hasLaguna) {
            return false;
        }

        if ($hasAddressSignal && $hasLaguna && ! $hasSantaCruz) {
            // Laguna but different municipality (e.g. Calamba, San Pablo) → fail when that city appears.
            if (preg_match('/\b(CALAMBA|SAN PABLO|BINAN|BI[NÑ]AN|CABUYAO|LOS BANOS|LOS BA[NÑ]OS|STA ROSA|SANTA ROSA)\b/', $normalizedHay)) {
                return false;
            }
        }

        if ($localityChecks > 0 && $localityHits === $localityChecks) {
            return true;
        }

        if ($hasLaguna && $hasSantaCruz) {
            // Municipality+province match is enough when barangay text is missing/unreadable.
            return true;
        }

        if ($regionOk && $hasLaguna && $localityHits >= 1) {
            return true;
        }

        // Purok / zone tokens from Step 1.
        $formPurok = strtoupper(trim((string) ($registrationFields['purok_zone'] ?? '')));
        if ($formPurok !== '' && strlen($formPurok) >= 3) {
            $normalizedForm = preg_replace('/[^A-Z0-9\s]/', ' ', $formPurok) ?? $formPurok;
            $normalizedForm = preg_replace('/\s+/', ' ', trim($normalizedForm)) ?? '';

            if ($normalizedForm !== '' && str_contains($normalizedHay, $normalizedForm)) {
                return true;
            }

            $tokens = array_values(array_filter(
                explode(' ', $normalizedForm),
                static fn (string $token) => strlen($token) >= 3
                    && ! in_array($token, ['PUROK', 'ZONE', 'SITIO', 'BRGY', 'BARANGAY', 'STREET', 'ST', 'THE'], true)
            ));

            if ($tokens !== []) {
                $hits = 0;
                foreach ($tokens as $token) {
                    if (str_contains($normalizedHay, $token)) {
                        $hits++;
                    }
                }

                if (($hits / count($tokens)) >= 0.6) {
                    return true;
                }

                if ($hasAddressSignal && $hits === 0 && $localityHits === 0) {
                    return false;
                }
            }
        }

        // Address present but no locality/purok signal matched — fail when Step 1 expects Santa Cruz locality.
        if ($hasAddressSignal && $localityHits === 0 && ! $hasLaguna && ! $hasSantaCruz) {
            return false;
        }

        return $localityHits > 0 ? true : null;
    }

    private function normalizeSexValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = strtoupper(trim((string) $value));
        if ($raw === '' || $raw === 'NULL' || $raw === 'UNKNOWN' || $raw === 'N/A') {
            return null;
        }

        if (in_array($raw, ['M', 'MALE', 'LALAKE', 'LALAKI'], true)) {
            return 'Male';
        }

        if (in_array($raw, ['F', 'FEMALE', 'BABAE'], true)) {
            return 'Female';
        }

        return null;
    }

    /**
     * @param  mixed  $side
     * @return array<string, mixed>|null
     */
    private function sanitizeOcrSide(mixed $side): ?array
    {
        if (! is_array($side)) {
            return null;
        }

        return [
            'success' => (bool) ($side['success'] ?? false),
            'ocr_status' => $side['ocr_status'] ?? null,
            'engine' => $side['engine'] ?? null,
            'text_length' => (int) ($side['text_length'] ?? 0),
            'processing_ms' => $side['processing_ms'] ?? null,
            'image' => is_array($side['image'] ?? null) ? [
                'bytes' => $side['image']['bytes'] ?? null,
                'mime' => $side['image']['mime'] ?? null,
                'width' => $side['image']['width'] ?? null,
                'height' => $side['image']['height'] ?? null,
            ] : null,
        ];
    }

    private function fieldsMatchLegacy(string $registered, string $detected): bool
    {
        $registered = strtolower(preg_replace('/\s+/', ' ', trim($registered)) ?? '');
        $detected = strtolower(preg_replace('/\s+/', ' ', trim($detected)) ?? '');

        if ($registered === '' || $detected === '') {
            return false;
        }

        similar_text($registered, $detected, $percent);

        return $percent >= 82.0 || str_contains($detected, $registered) || str_contains($registered, $detected);
    }

    private function datesMatch(string $registered, string $detected): bool
    {
        if ($registered === '' || $detected === '') {
            return false;
        }

        try {
            return Carbon::parse($registered)->isSameDay(Carbon::parse($detected));
        } catch (\Throwable) {
            return false;
        }
    }

    public function isSupportedDocumentType(string $documentType): bool
    {
        return in_array($documentType, config('ocr.supported_philippine_ids', []), true);
    }
}
