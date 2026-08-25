<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class PhilippineIdDetectionService
{
    public function __construct(
        private readonly OCRService $ocrService,
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

        return array_filter([
            'first_name' => $payload['given_name'] ?? null,
            'middle_name' => $payload['middle_name'] ?? null,
            'last_name' => $payload['surname'] ?? null,
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
        $nameMatch = $this->fieldsMatch(
            trim(implode(' ', array_filter([
                $registrationFields['first_name'] ?? null,
                $registrationFields['middle_name'] ?? null,
                $registrationFields['last_name'] ?? null,
            ]))),
            (string) ($payload['full_name'] ?? ''),
        );
        $birthdateMatch = $this->datesMatch(
            (string) ($registrationFields['birthday'] ?? ''),
            (string) ($payload['birthdate'] ?? ''),
        );

        return [
            'success' => (bool) ($payload['success'] ?? false),
            'source' => 'philippine_id_ocr_v1',
            'document_type' => $documentType,
            'id_type' => $payload['id_type'] ?? 'Unknown',
            'confidence' => $payload['confidence'] ?? 0,
            'detected_name' => $payload['full_name'] ?? null,
            'detected_address' => $payload['address'] ?? null,
            'detected_birthdate' => $payload['birthdate'] ?? null,
            'detected_sex' => $payload['sex'] ?? null,
            'id_number' => $payload['id_number'] ?? null,
            'name_match' => $nameMatch,
            'birthdate_match' => $birthdateMatch,
            'form_suggestions' => $formSuggestions,
            'needs_review' => (bool) ($payload['needs_review'] ?? false),
            'document_detected' => $payload['document_detected'] ?? null,
            'ocr_status' => $payload['ocr_status'] ?? null,
            'ocr' => [
                'front' => $this->sanitizeOcrSide($payload['front'] ?? null),
                'back' => $this->sanitizeOcrSide($payload['back'] ?? null),
                'text_length' => (int) ($payload['text_length'] ?? 0),
            ],
            'message' => $payload['message'] ?? null,
            'validation_error' => (bool) ($payload['validation_error'] ?? false),
            'processed_at' => now()->toIso8601String(),
        ];
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

    private function fieldsMatch(string $registered, string $detected): bool
    {
        $registered = strtolower(preg_replace('/\s+/', ' ', trim($registered)) ?? '');
        $detected = strtolower(preg_replace('/\s+/', ' ', trim($detected)) ?? '');

        if ($registered === '' || $detected === '') {
            return false;
        }

        similar_text($registered, $detected, $percent);

        return $percent >= 75.0 || str_contains($detected, $registered) || str_contains($registered, $detected);
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
