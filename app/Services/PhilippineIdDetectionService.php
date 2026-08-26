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
    ): array {
        return $this->ocrService->detectIdPair($frontPath, $backPath, $documentType);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectUploadedPair(
        UploadedFile $front,
        UploadedFile $back,
        ?string $documentType = null,
    ): array {
        return $this->ocrService->detectIdPairFromUploads($front, $back, $documentType);
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
            'sex' => $payload['sex'] ?? null,
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
        $form = $this->nameMatcher->formComponentsFromFields($registrationFields);

        $nameMatch = $this->resolveNameMatch($form, $detectedName, $rawText, $registrationFields);
        $birthdateMatch = $this->datesMatch(
            (string) ($registrationFields['birthday'] ?? ''),
            (string) ($payload['birthdate'] ?? ''),
        );

        if (! $birthdateMatch && $rawText !== '' && $this->formAssistedMatcher->birthdateVisibleInOcr($rawText, $registrationFields)) {
            $birthdateMatch = true;
        }

        $success = (bool) ($payload['success'] ?? false);
        $needsReview = (bool) ($payload['needs_review'] ?? false);
        $confidence = (float) ($payload['confidence'] ?? 0);
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.45);
        $message = $payload['message'] ?? null;

        $hasFormName = ($form['first'] ?? '') !== '' && ($form['last'] ?? '') !== '';
        $requireNameSignal = (bool) config('ocr.require_name_signal_for_success', true);

        $validationError = (bool) ($payload['validation_error'] ?? false);
        $documentDetected = strtolower((string) ($payload['document_detected'] ?? ''));
        if ($validationError || $documentDetected === 'no') {
            $success = false;
            $needsReview = false;
            $validationError = true;
        } elseif ($hasFormName && $requireNameSignal) {
            $acceptFloor = max(0.40, $minConfidence - 0.05);
            $hasIdText = $rawText !== '' && $this->ocrService->looksLikeSupportingIdText($rawText);

            if ($nameMatch && $hasIdText && $confidence >= $acceptFloor) {
                // Name evidence can support review/success only when OCR already looks like an ID.
                if ($nameMatch && $birthdateMatch) {
                    $confidence = max($confidence, 0.72);
                    $success = true;
                    $needsReview = $confidence < 0.8;
                    $message = 'Document identity matched your profiling details.';
                } elseif ($confidence >= $minConfidence) {
                    $success = true;
                    $needsReview = false;
                    $message = $message ?: 'Document text was read and your name matched. Please review for accuracy.';
                } else {
                    $success = false;
                    $needsReview = true;
                    $message = 'Document text was read and your name matched, but image quality is low. Please review carefully or retake clearer photos.';
                }
            } elseif ($success && $confidence < 0.75) {
                // Keyword-only "success" without name evidence is often inaccurate.
                $success = false;
                $needsReview = true;
                $message = 'Document text was read, but your name could not be confidently matched. Please upload a clearer photo or submit for administrator review.';
            }
        }

        return [
            'success' => $success,
            'source' => 'philippine_id_ocr_v1',
            'document_type' => $documentType,
            'id_type' => $payload['id_type'] ?? 'Unknown',
            'confidence' => $confidence,
            'confidence_band' => $payload['confidence_band'] ?? null,
            'detected_name' => $payload['full_name'] ?? null,
            'detected_address' => $payload['address'] ?? null,
            'detected_birthdate' => $payload['birthdate'] ?? null,
            'detected_sex' => $payload['sex'] ?? null,
            'id_number' => $payload['id_number'] ?? null,
            'name_match' => $nameMatch,
            'birthdate_match' => $birthdateMatch,
            'form_suggestions' => $formSuggestions,
            'needs_review' => $validationError ? false : ($needsReview || ! $success),
            'document_detected' => $payload['document_detected'] ?? null,
            'ocr_status' => $payload['ocr_status'] ?? null,
            'raw_text' => $rawText !== '' ? $rawText : null,
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

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     * @param  array<string, mixed>  $registrationFields
     */
    private function resolveNameMatch(array $form, string $detectedName, string $rawText, array $registrationFields): bool
    {
        if (($form['first'] ?? '') === '' || ($form['last'] ?? '') === '') {
            return false;
        }

        if ($rawText !== '') {
            if ($this->nameMatcher->matchesFormToOcrText($form, $rawText, false)) {
                return true;
            }
            if ($this->formAssistedMatcher->nameVisibleInOcr($rawText, $registrationFields)) {
                return true;
            }
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

        return $this->fieldsMatchLegacy(
            trim(implode(' ', array_filter([
                $registrationFields['first_name'] ?? null,
                $registrationFields['middle_name'] ?? null,
                $registrationFields['last_name'] ?? null,
            ]))),
            $detectedName,
        );
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
