<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\Log;

class KkProfilingIdentityValidator
{
    public function __construct(
        private readonly OCRService $ocrService,
        private readonly KabataanFullNameMatcher $nameMatcher,
        private readonly PhilippineIdAddressParser $addressParser,
        private readonly BarangayAddressMatcher $barangayMatcher,
        private readonly DuplicateKabataanRegistrationService $duplicateChecker,
        private readonly SchoolIdPipelineService $pipelineService,
    ) {}

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    public function validateUploadedId(
        int $barangayId,
        array $registrationFields,
        ?string $frontImagePath,
        ?string $backImagePath,
        string $documentType = 'school_id',
        ?int $excludeRegistrationId = null,
    ): array {
        if ($frontImagePath === null || $backImagePath === null) {
            return $this->mergeFormContext(
                $this->failureResult(
                    errorCode: 'ocr_failed',
                    message: KkProfilingValidationMessages::ocrFrontReadFailed(),
                ),
                $registrationFields,
                $barangayId,
            );
        }

        if (
            $documentType === 'school_id'
            && config('ocr.pipeline_enabled', true)
            && $this->pipelineService->isConfigured()
        ) {
            $pipelineResult = $this->pipelineService->validate(
                $barangayId,
                $registrationFields,
                $frontImagePath,
                $backImagePath,
            );

            if (is_array($pipelineResult)) {
                $pipelineResult = $this->ensureDetectedFieldsPopulated($pipelineResult, $registrationFields, $barangayId);

                if ($this->pipelineExtractionFailed($pipelineResult)) {
                    Log::info('School ID pipeline OCR empty — falling back to legacy OCR', [
                        'front' => $frontImagePath,
                        'back' => $backImagePath,
                    ]);
                } else {
                    if ($pipelineResult['success'] ?? false) {
                        $duplicate = $this->duplicateChecker->findApprovedDuplicate(
                            $barangayId,
                            $registrationFields,
                            $excludeRegistrationId,
                        );

                        if ($duplicate !== null) {
                            return $this->mergeFormContext(
                                $this->failureResult(
                                    errorCode: 'duplicate',
                                    message: KkProfilingValidationMessages::DUPLICATE_IDENTITY,
                                    nameMatch: (bool) ($pipelineResult['name_match'] ?? false),
                                    barangayMatch: (bool) ($pipelineResult['barangay_match'] ?? false),
                                    duplicateDetected: true,
                                    ocr: is_array($pipelineResult['ocr'] ?? null) ? $pipelineResult['ocr'] : [],
                                ),
                                $registrationFields,
                                $barangayId,
                            );
                        }
                    }

                    return $this->mergeFormContext($pipelineResult, $registrationFields, $barangayId);
                }
            }
        }

        return $this->mergeFormContext(
            $this->validateUploadedIdLegacy(
                $barangayId,
                $registrationFields,
                $frontImagePath,
                $backImagePath,
                $documentType,
                $excludeRegistrationId,
            ),
            $registrationFields,
            $barangayId,
        );
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function validateUploadedIdLegacy(
        int $barangayId,
        array $registrationFields,
        string $frontImagePath,
        string $backImagePath,
        string $documentType,
        ?int $excludeRegistrationId,
    ): array {
        $frontOcr = $this->ocrService->extractText($frontImagePath);
        $backOcr = $this->ocrService->extractText($backImagePath);

        $frontText = $this->ocrText($frontOcr);
        $backText = $this->ocrText($backOcr);

        if ($documentType === 'school_id') {
            if ($frontText === '') {
                return $this->failureResult(
                    errorCode: 'ocr_failed',
                    message: KkProfilingValidationMessages::ocrFrontReadFailed(),
                    ocr: [
                        'front' => $frontOcr,
                        'back' => $backOcr,
                    ],
                );
            }

            if ($backText === '') {
                return $this->failureResult(
                    errorCode: 'ocr_failed',
                    message: KkProfilingValidationMessages::ocrBackReadFailed(),
                    ocr: [
                        'front' => $frontOcr,
                        'back' => $backOcr,
                    ],
                );
            }
        }

        $combinedText = trim($frontText.' '.$backText);

        if ($combinedText === '') {
            return $this->failureResult(
                errorCode: 'ocr_failed',
                message: KkProfilingValidationMessages::ocrFrontReadFailed(),
                ocr: [
                    'front' => $frontOcr,
                    'back' => $backOcr,
                ],
            );
        }

        $formName = $this->nameMatcher->formComponentsFromFields($registrationFields);
        $formFullName = $this->nameMatcher->formatFormFullNameForDisplay($registrationFields);
        $barangay = Barangay::query()->find($barangayId);
        $nameText = $frontText !== '' ? $frontText : $combinedText;
        $ocrName = $this->nameMatcher->parseOcrName($nameText, $formName);
        $detectedFullName = $this->nameMatcher->extractBestNameLine($nameText, $formName)
            ?? $this->nameMatcher->formatComponentsForDisplay($ocrName);
        $nameMatch = $this->nameMatcher->matchesFormToOcrText($formName, $nameText, strictMiddle: true);

        if ($ocrName !== null) {
            $nameMatch = $nameMatch || $this->nameMatcher->matches($formName, $ocrName, strictMiddle: true);
        }

        $birthdateMatch = $this->birthYearMatchesForm($backText, $registrationFields);

        if (! $nameMatch) {
            return $this->failureResult(
                errorCode: 'invalid_full_name',
                message: KkProfilingValidationMessages::nameMismatch($formFullName, $detectedFullName),
                nameMatch: false,
                birthdateMatch: $birthdateMatch,
                ocr: [
                    'front' => $frontOcr,
                    'back' => $backOcr,
                    'full_text' => $combinedText,
                ],
                formName: $formName,
                detectedName: $ocrName,
                formFullName: $formFullName,
                detectedFullName: $detectedFullName,
                formBarangay: $barangay?->name,
            );
        }

        if (! $birthdateMatch) {
            return $this->failureResult(
                errorCode: 'invalid_birthdate',
                message: KkProfilingValidationMessages::birthdateMismatch(
                    (string) ($registrationFields['birthday'] ?? ''),
                    $this->extractBirthYearFromText($backText) ?? '',
                ),
                nameMatch: true,
                birthdateMatch: false,
                ocr: [
                    'front' => $frontOcr,
                    'back' => $backOcr,
                    'full_text' => $combinedText,
                ],
                formName: $formName,
                detectedName: $ocrName,
                formFullName: $formFullName,
                detectedFullName: $detectedFullName,
                formBarangay: $barangay?->name,
            );
        }

        $addressText = $documentType === 'school_id'
            ? trim($backText.' '.$frontText)
            : trim($backText !== '' ? $backText : $combinedText);
        $lines = array_merge(
            is_array($backOcr['lines'] ?? null) ? $backOcr['lines'] : [],
            is_array($frontOcr['lines'] ?? null) ? $frontOcr['lines'] : [],
        );
        $parsed = $this->addressParser->parse(is_array($lines) ? $lines : [], $addressText);

        $registrationFields['_both_sides_uploaded'] = true;
        $registrationFields['_document_type'] = $documentType;

        $formAddress = KkProfilingValidationMessages::formAddressLabel($registrationFields, $barangay?->name);
        $detectedAddress = $this->extractIdAddressSnippet($backText, $parsed, is_array($backOcr['lines'] ?? null) ? $backOcr['lines'] : []);
        $barangayMatch = $this->barangayMatcher->matchesRegistrationAddress(
            $parsed,
            $barangay,
            $registrationFields,
            $addressText,
        );

        if (! $barangayMatch['matched']) {
            return $this->failureResult(
                errorCode: 'invalid_barangay',
                message: KkProfilingValidationMessages::barangayMismatch(
                    (string) ($barangay?->name ?? ''),
                    (string) ($parsed['barangay'] ?? $detectedAddress),
                ),
                nameMatch: true,
                birthdateMatch: true,
                barangayMatch: false,
                ocr: [
                    'front' => $frontOcr,
                    'back' => $backOcr,
                    'full_text' => $combinedText,
                ],
                formName: $formName,
                detectedName: $ocrName,
                parsedAddress: $parsed,
                matchReason: $barangayMatch['reason'],
                formFullName: $formFullName,
                detectedFullName: $detectedFullName,
                formBarangay: $barangay?->name,
                detectedBarangay: (string) ($parsed['barangay'] ?? $detectedAddress),
            );
        }

        $duplicate = $this->duplicateChecker->findApprovedDuplicate(
            $barangayId,
            $registrationFields,
            $excludeRegistrationId,
        );

        if ($duplicate !== null) {
            return $this->failureResult(
                errorCode: 'duplicate',
                message: KkProfilingValidationMessages::DUPLICATE_IDENTITY,
                nameMatch: true,
                barangayMatch: true,
                duplicateDetected: true,
                ocr: [
                    'front' => $frontOcr,
                    'back' => $backOcr,
                    'full_text' => $combinedText,
                ],
                formName: $formName,
                detectedName: $ocrName,
                parsedAddress: $parsed,
                matchReason: $barangayMatch['reason'],
            );
        }

        return [
            'success' => true,
            'message' => 'School ID name and barangay match the KK Profiling form.',
            'name_match' => true,
            'birthdate_match' => true,
            'barangay_match' => true,
            'duplicate_detected' => false,
            'error_code' => null,
            'document_type' => $documentType,
            'form_name' => $formName,
            'detected_name' => $ocrName,
            'address' => $parsed['address'] ?? $addressText,
            'barangay' => $parsed['barangay'] ?? null,
            'city' => $parsed['city'] ?? null,
            'province' => $parsed['province'] ?? null,
            'matched_barangay' => $barangayMatch['matched_name'],
            'registration_barangay' => $barangay?->name,
            'registration_purok_zone' => $registrationFields['purok_zone'] ?? null,
            'match_score' => $barangayMatch['score'],
            'match_reason' => $barangayMatch['reason'],
            'source' => 'id_ocr_strict',
            'ocr' => [
                'front' => $frontOcr,
                'back' => $backOcr,
                'full_text' => $combinedText,
                'average_confidence' => $backOcr['average_confidence'] ?? $frontOcr['average_confidence'] ?? null,
            ],
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function ensureDetectedFieldsPopulated(array $result, array $registrationFields = [], int $barangayId = 0): array
    {
        $pipeline = is_array($result['pipeline'] ?? null) ? $result['pipeline'] : [];
        $ocrFront = is_array($result['ocr']['front'] ?? null) ? $result['ocr']['front'] : [];
        $ocrBack = is_array($result['ocr']['back'] ?? null) ? $result['ocr']['back'] : [];

        if (empty($result['form_full_name'])) {
            $result['form_full_name'] = app(KabataanFullNameMatcher::class)
                ->formatFormFullNameForDisplay($registrationFields);
        }

        if (empty($result['form_birthday']) && ! empty($registrationFields['birthday'])) {
            $result['form_birthday'] = (string) $registrationFields['birthday'];
        }

        if (empty($result['form_barangay'])) {
            $barangay = $barangayId > 0 ? Barangay::query()->find($barangayId) : null;
            $result['form_barangay'] = $barangay?->name
                ?? ($registrationFields['registration_barangay'] ?? null);
        }

        if (empty($result['detected_full_name'])) {
            $result['detected_full_name'] = $pipeline['full_name']
                ?? $pipeline['raw_name']
                ?? ($ocrFront['raw_name'] ?? null);
        }

        if (empty($result['barangay'])) {
            $result['barangay'] = $pipeline['barangay'] ?? null;
        }

        if (empty($result['detected_address']) && ! empty($pipeline['address'])) {
            $result['detected_address'] = $pipeline['address'];
        }

        if (! array_key_exists('birthdate_match', $result)) {
            $result['birthdate_match'] = (bool) ($pipeline['birthdate_match'] ?? false);
        }

        if (empty($result['barangay']) && ! empty($ocrBack['full_text'])) {
            $barangayName = (string) ($result['form_barangay'] ?? '');
            if ($barangayName !== '' && stripos((string) $ocrBack['full_text'], $barangayName) !== false) {
                $result['barangay'] = $barangayName;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function pipelineExtractionFailed(array $result): bool
    {
        $nameMethod = (string) ($result['pipeline']['validations']['name']['method'] ?? '');
        $hasName = trim((string) ($result['detected_full_name'] ?? '')) !== ''
            || trim((string) ($result['pipeline']['full_name'] ?? '')) !== '';
        $hasBirth = trim((string) ($result['pipeline']['birthdate'] ?? '')) !== ''
            || trim((string) ($result['pipeline']['birthdate_raw'] ?? '')) !== '';

        return $nameMethod === 'missing' || (! $hasName && ! $hasBirth);
    }

    /**
     * @param  array<string, mixed>  $ocr
     * @param  array<string, mixed>|null  $formName
     * @param  array<string, mixed>|null  $detectedName
     * @param  array<string, mixed>|null  $parsedAddress
     * @return array<string, mixed>
     */
    private function failureResult(
        string $errorCode,
        string $message,
        bool $nameMatch = false,
        bool $barangayMatch = false,
        bool $birthdateMatch = false,
        bool $duplicateDetected = false,
        array $ocr = [],
        ?array $formName = null,
        ?array $detectedName = null,
        ?array $parsedAddress = null,
        ?string $matchReason = null,
        ?string $formFullName = null,
        ?string $detectedFullName = null,
        ?string $formAddress = null,
        ?string $detectedAddress = null,
        ?string $formBarangay = null,
        ?string $detectedBarangay = null,
    ): array {
        return [
            'success' => false,
            'message' => $message,
            'name_match' => $nameMatch,
            'birthdate_match' => $birthdateMatch,
            'barangay_match' => $barangayMatch,
            'duplicate_detected' => $duplicateDetected,
            'error_code' => $errorCode,
            'form_name' => $formName,
            'detected_name' => $detectedName,
            'parsed_address' => $parsedAddress,
            'match_reason' => $matchReason,
            'form_full_name' => $formFullName,
            'detected_full_name' => $detectedFullName,
            'form_address' => $formAddress,
            'detected_address' => $detectedAddress,
            'form_barangay' => $formBarangay,
            'barangay' => $detectedBarangay,
            'ocr' => $ocr,
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     */
    private function birthYearMatchesForm(string $text, array $registrationFields): bool
    {
        $formYear = $this->extractBirthYearFromText((string) ($registrationFields['birthday'] ?? ''));
        $idYear = $this->extractBirthYearFromText($text);

        if ($formYear === null || $idYear === null) {
            return false;
        }

        return $formYear === $idYear;
    }

    private function extractBirthYearFromText(string $text): ?string
    {
        if (preg_match('/\b(19\d{2}|20[0-1]\d)\b/', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  list<array{text: string, confidence?: float}>  $backLines
     * @param  array<string, mixed>  $parsed
     */
    private function extractIdAddressSnippet(string $backText, array $parsed, array $backLines): string
    {
        if (trim((string) ($parsed['address'] ?? '')) !== '') {
            return trim((string) $parsed['address']);
        }

        if (preg_match('/address\s*[:\-]?\s*(.+?)(?:cell\s*no|contact|tel|phone|important|signature|parent|guardian|$)/i', $backText, $match)) {
            $snippet = trim($match[1]);

            if ($snippet !== '') {
                return preg_replace('/\s+/', ' ', $snippet) ?? $snippet;
            }
        }

        $addressParts = [];
        $capture = false;

        foreach ($backLines as $line) {
            $text = trim((string) ($line['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (preg_match('/^address\s*[:\-]?$/i', $text)) {
                $capture = true;

                continue;
            }

            if ($capture) {
                if (preg_match('/^(cell\s*no|contact|important|parent|guardian)/i', $text)) {
                    break;
                }

                $addressParts[] = $text;
            }
        }

        if ($addressParts !== []) {
            return preg_replace('/\s+/', ' ', implode(' ', $addressParts)) ?? implode(' ', $addressParts);
        }

        foreach ($backLines as $line) {
            $text = trim((string) ($line['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (preg_match('/\b(?:sitio|purok|brgy|barangay|sta\.?|santa\s*cruz|laguna|lag\.?)\b/i', $text)) {
                return $text;
            }
        }

        $compact = preg_replace('/\s+/', ' ', trim($backText)) ?? trim($backText);

        if (strlen($compact) > 140) {
            return substr($compact, 0, 140).'...';
        }

        return $compact;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function mergeFormContext(array $result, array $registrationFields, int $barangayId): array
    {
        if (empty($result['form_full_name'])) {
            $result['form_full_name'] = $this->nameMatcher->formatFormFullNameForDisplay($registrationFields);
        }

        if (empty($result['form_birthday']) && ! empty($registrationFields['birthday'])) {
            $result['form_birthday'] = (string) $registrationFields['birthday'];
        }

        if (empty($result['form_barangay'])) {
            $barangay = $barangayId > 0 ? Barangay::query()->find($barangayId) : null;
            $result['form_barangay'] = $barangay?->name
                ?? ($registrationFields['registration_barangay'] ?? null);
        }

        if (empty($result['registration_barangay'])) {
            $barangay = $barangayId > 0 ? Barangay::query()->find($barangayId) : null;
            $result['registration_barangay'] = $barangay?->name
                ?? ($registrationFields['registration_barangay'] ?? null);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $ocr
     */
    private function ocrText(array $ocr): string
    {
        $fullText = trim((string) ($ocr['full_text'] ?? ''));

        if ($fullText !== '') {
            return $fullText;
        }

        $lines = is_array($ocr['lines'] ?? null) ? $ocr['lines'] : [];

        return trim(implode(' ', array_map(
            fn (array $line) => trim((string) ($line['text'] ?? '')),
            $lines,
        )));
    }
}
