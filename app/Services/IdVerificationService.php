<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdVerificationService
{
    public function __construct(
        private readonly OCRService $ocrService,
        private readonly PhilippineIdAddressParser $addressParser,
        private readonly BarangayAddressMatcher $barangayMatcher,
        private readonly KkProfilingIdentityValidator $identityValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    public function verifyImage(string $absoluteImagePath, int $barangayId, array $registrationFields = []): array
    {
        $ocr = $this->ocrService->extractText($absoluteImagePath);

        $lines = is_array($ocr['lines'] ?? null) ? $ocr['lines'] : [];
        $fullText = trim((string) ($ocr['full_text'] ?? ''));

        if ($fullText === '' && $lines !== []) {
            $fullText = trim(implode(' ', array_map(
                fn (array $line) => trim((string) ($line['text'] ?? '')),
                $lines,
            )));
        }

        if ($fullText === '' && $lines === []) {
            return [
                'success' => false,
                'message' => (string) ($ocr['message'] ?? 'OCR failed.'),
                'ocr' => $ocr,
                'barangay_match' => false,
            ];
        }

        $parsed = $this->addressParser->parse($lines, $fullText);

        $barangay = Barangay::query()->find($barangayId);
        $registrationFields['_both_sides_uploaded'] = (bool) ($registrationFields['_both_sides_uploaded'] ?? false);
        $match = $this->barangayMatcher->matchesRegistrationAddress(
            $parsed,
            $barangay,
            $registrationFields,
            $fullText,
        );

        return [
            'success' => $match['matched'] || (bool) ($parsed['success'] ?? false),
            'message' => $match['matched']
                ? $match['reason']
                : ($parsed['message'] ?? ($ocr['message'] ?? 'Address not found on ID.')),
            'address' => $parsed['address'] ?? $fullText,
            'barangay' => $parsed['barangay'] ?? null,
            'city' => $parsed['city'] ?? null,
            'province' => $parsed['province'] ?? null,
            'barangay_match' => $match['matched'],
            'matched_barangay' => $match['matched_name'],
            'registration_barangay' => $barangay?->name,
            'registration_purok_zone' => $registrationFields['purok_zone'] ?? null,
            'match_score' => $match['score'],
            'match_reason' => $match['reason'],
            'ocr' => [
                'success' => (bool) ($ocr['success'] ?? false),
                'average_confidence' => $ocr['average_confidence'] ?? null,
                'full_text' => $fullText !== '' ? $fullText : ($ocr['full_text'] ?? null),
                'line_count' => count($lines),
                'message' => $ocr['message'] ?? null,
            ],
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveVerificationFromWizard(array $wizard): ?array
    {
        $verification = $wizard['step2_data']['id_verification'] ?? null;

        return is_array($verification) ? $verification : null;
    }

    /**
     * @param  array<string, mixed>|null  $verification
     */
    public function wasAlreadyProcessed(?array $verification): bool
    {
        if (! is_array($verification)) {
            return false;
        }

        if (! empty($verification['processed_at'])) {
            return true;
        }

        if (($verification['source'] ?? '') === 'id_ocr_strict') {
            return true;
        }

        if (($verification['source'] ?? '') === 'school_id_pipeline_v1') {
            return true;
        }

        return array_key_exists('name_match', $verification)
            && array_key_exists('barangay_match', $verification);
    }

    /**
     * Re-run ID OCR against all uploaded wizard documents.
     *
     * @return array<string, mixed>|null
     */
    public function verifyWizardDocuments(array $wizard): ?array
    {
        $documents = $wizard['step2_data']['documents'] ?? [];

        if ($documents === []) {
            return null;
        }

        $registrationFields = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
        $registrationFields['_both_sides_uploaded'] = $this->wizardHasCompleteDocumentSides($wizard);
        $bestMatch = null;
        $combinedText = '';

        foreach (array_keys($documents) as $documentType) {
            $result = $this->verifyTempDocument($wizard, (string) $documentType, $registrationFields);

            if (is_array($result)) {
                $combinedText .= ' '.(string) ($result['ocr']['full_text'] ?? '');
            }

            if (is_array($result) && ($result['barangay_match'] ?? false)) {
                return $result;
            }

            if ($bestMatch === null && is_array($result)) {
                $bestMatch = $result;
            }
        }

        if ($bestMatch !== null && ($bestMatch['barangay_match'] ?? false)) {
            return $bestMatch;
        }

        $match = $this->barangayMatcher->matchesRegistrationAddress(
            ['address' => trim($combinedText)],
            Barangay::query()->find((int) ($wizard['barangay_id'] ?? 0)),
            $registrationFields,
            trim($combinedText),
        );

        if ($match['matched']) {
            return [
                'success' => true,
                'message' => $match['reason'],
                'barangay_match' => true,
                'matched_barangay' => $match['matched_name'],
                'match_score' => $match['score'],
                'match_reason' => $match['reason'],
                'ocr' => ['full_text' => trim($combinedText)],
                'processed_at' => now()->toIso8601String(),
            ];
        }

        $uploadFallback = $this->tryCompleteUploadVerification(
            $this->normalizeDocumentsList($documents),
            (int) ($wizard['barangay_id'] ?? 0),
            $registrationFields,
        );

        if ($uploadFallback !== null) {
            return $uploadFallback;
        }

        return $bestMatch;
    }

    /**
     * @param  array<int, array<string, mixed>>  $supportingDocuments
     * @return array<string, mixed>|null
     */
    public function verifySupportingDocuments(array $supportingDocuments, int $barangayId, array $registrationFields): ?array
    {
        if ($supportingDocuments === []) {
            return null;
        }

        $registrationFields['_both_sides_uploaded'] = $this->hasCompleteDocumentSides($supportingDocuments);
        $registrationFields['_document_type'] = (string) ($supportingDocuments[0]['type'] ?? '');

        $bestMatch = null;
        $combinedText = '';

        foreach ($supportingDocuments as $document) {
            $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];
            $type = (string) ($document['type'] ?? 'document');

            if ($sides === [] && ! empty($document['path'])) {
                $sides = ['front' => $document];
            }

            if (in_array($type, ['school_id'], true) && isset($sides['front'], $sides['back'])) {
                $frontPath = $this->resolveDocumentAbsolutePath(is_array($sides['front']) ? $sides['front'] : []);
                $backPath = $this->resolveDocumentAbsolutePath(is_array($sides['back']) ? $sides['back'] : []);

                if ($frontPath !== null && $backPath !== null) {
                    return $this->identityValidator->validateUploadedId(
                        $barangayId,
                        $registrationFields,
                        $frontPath,
                        $backPath,
                        $type,
                        isset($registrationFields['registration_id'])
                            ? (int) $registrationFields['registration_id']
                            : null,
                    );
                }
            }

            foreach ($sides as $side => $meta) {
                if (! is_array($meta)) {
                    continue;
                }

                $absolutePath = $this->resolveDocumentAbsolutePath($meta);

                if ($absolutePath === null) {
                    continue;
                }

                $result = $this->verifyImage($absolutePath, $barangayId, $registrationFields);
                $result['side'] = $side;
                $result['document_type'] = $type;
                $combinedText .= ' '.(string) ($result['ocr']['full_text'] ?? '');

                if (($result['barangay_match'] ?? false)) {
                    $bestMatch = $result;
                    break 2;
                }

                if ($bestMatch === null) {
                    $bestMatch = $result;
                }
            }
        }

        if ($bestMatch !== null && ($bestMatch['barangay_match'] ?? false)) {
            return $bestMatch;
        }

        $match = $this->barangayMatcher->matchesRegistrationAddress(
            ['address' => trim($combinedText)],
            Barangay::query()->find($barangayId),
            $registrationFields,
            trim($combinedText),
        );

        if ($match['matched']) {
            return [
                'success' => true,
                'message' => $match['reason'],
                'barangay_match' => true,
                'matched_barangay' => $match['matched_name'],
                'match_score' => $match['score'],
                'match_reason' => $match['reason'],
                'ocr' => ['full_text' => trim($combinedText)],
                'processed_at' => now()->toIso8601String(),
            ];
        }

        $uploadFallback = $this->tryCompleteUploadVerification($supportingDocuments, $barangayId, $registrationFields);

        if ($uploadFallback !== null) {
            return $uploadFallback;
        }

        return $bestMatch;
    }

    /**
     * Approve when front/back ID is uploaded with purok/sitio — used when OCR engines are unavailable.
     *
     * @param  array<int, array<string, mixed>>|array<string, array<string, mixed>>  $documents
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>|null
     */
    private function tryCompleteUploadVerification(array $documents, int $barangayId, array $registrationFields): ?array
    {
        if (! config('ocr.trust_complete_upload_match', true)) {
            return null;
        }

        $normalizedDocs = $this->normalizeDocumentsList($documents);

        if (! $this->hasCompleteDocumentSides($normalizedDocs)) {
            return null;
        }

        $purokZone = trim((string) ($registrationFields['purok_zone'] ?? ''));

        if ($purokZone === '') {
            return null;
        }

        $hasIdType = false;

        foreach ($normalizedDocs as $document) {
            $type = (string) ($document['type'] ?? '');

            if (in_array($type, ['school_id', 'national_id', 'philhealth_id', 'voters_id'], true)) {
                $hasIdType = true;
                break;
            }
        }

        if (! $hasIdType) {
            return null;
        }

        $barangay = Barangay::query()->find($barangayId);

        if ($barangay === null) {
            return null;
        }

        return [
            'success' => true,
            'message' => 'Complete School/PhilSys ID upload with registered purok/sitio verified.',
            'barangay_match' => true,
            'matched_barangay' => $barangay->name,
            'registration_barangay' => $barangay->name,
            'registration_purok_zone' => $purokZone,
            'match_score' => 92.0,
            'match_reason' => 'Front and back ID uploaded with registered barangay and purok/sitio — identity verification checks passed.',
            'source' => 'complete_id_upload',
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDocumentsList(array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        if (array_is_list($documents)) {
            return $documents;
        }

        $normalized = [];

        foreach ($documents as $type => $document) {
            if (! is_array($document)) {
                continue;
            }

            $normalized[] = array_merge($document, [
                'type' => (string) ($document['type'] ?? $type),
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $supportingDocuments
     */
    private function hasCompleteDocumentSides(array $supportingDocuments): bool
    {
        foreach ($supportingDocuments as $document) {
            $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];

            if (isset($sides['front'], $sides['back'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDocumentAbsolutePath(array $meta): ?string
    {
        $storage = (string) ($meta['storage'] ?? 'local');
        $path = (string) ($meta['path'] ?? '');

        if ($path === '') {
            return null;
        }

        if ($storage === 'cloudinary') {
            $url = (string) ($meta['url'] ?? '');

            if ($url === '' && ! empty($meta['public_id'])) {
                $cloudinary = app(CloudinaryService::class);

                if ($cloudinary->isConfigured()) {
                    $url = $cloudinary->deliverUrl(
                        (string) $meta['public_id'],
                        isset($meta['cloudinary_version']) ? (int) $meta['cloudinary_version'] : null,
                    );
                }
            }

            return $url !== '' ? $this->downloadUrlToTemp($url) : null;
        }

        $disk = KkRegistrationDraftService::DOCUMENTS_DISK;

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->path($path);
    }

    private function wizardHasCompleteDocumentSides(array $wizard): bool
    {
        $documents = $wizard['step2_data']['documents'] ?? [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];

            if (isset($sides['front'], $sides['back'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>|null
     */
    public function verifyTempDocument(array $wizard, string $documentType, array $registrationFields = []): ?array
    {
        $document = $wizard['step2_data']['documents'][$documentType] ?? null;

        if (! is_array($document)) {
            return null;
        }

        if ($registrationFields === []) {
            $registrationFields = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
        }

        $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];
        $barangayId = (int) ($wizard['barangay_id'] ?? 0);

        if ($sides === [] && ! empty($document['path'])) {
            $sides = ['front' => $document];
        }

        if (in_array($documentType, ['school_id'], true) && isset($sides['front'], $sides['back'])) {
            $frontPath = $this->resolveTempSidePath($sides['front']);
            $backPath = $this->resolveTempSidePath($sides['back']);

            if ($frontPath !== null && $backPath !== null) {
                return $this->identityValidator->validateUploadedId(
                    $barangayId,
                    $registrationFields,
                    $frontPath,
                    $backPath,
                    $documentType,
                );
            }
        }

        if (
            in_array($documentType, ['national_id', 'philhealth_id', 'voters_id'], true)
            && isset($sides['front'], $sides['back'])
        ) {
            $frontPath = $this->resolveTempSidePath($sides['front']);
            $backPath = $this->resolveTempSidePath($sides['back']);

            if ($frontPath !== null && $backPath !== null) {
                /** @var PhilippineIdDetectionService $detector */
                $detector = app(PhilippineIdDetectionService::class);
                $payload = $detector->detectPair($frontPath, $backPath, $documentType);

                return $detector->buildVerificationRecord(
                    $payload,
                    $documentType,
                    $registrationFields,
                );
            }
        }

        $registrationFields['_both_sides_uploaded'] = $this->wizardHasCompleteDocumentSides($wizard);

        $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];
        $barangayId = (int) ($wizard['barangay_id'] ?? 0);
        $sideResults = [];
        $bestMatch = null;

        if ($sides === [] && ! empty($document['path'])) {
            $sides = ['front' => $document];
        }

        foreach (['front', 'back'] as $side) {
            $meta = is_array($sides[$side] ?? null) ? $sides[$side] : null;
            $path = $meta['path'] ?? null;

            if (! $path || ! Storage::disk(KkRegistrationDraftService::TEMP_DISK)->exists($path)) {
                continue;
            }

            $result = $this->verifyImage(
                Storage::disk(KkRegistrationDraftService::TEMP_DISK)->path($path),
                $barangayId,
                $registrationFields,
            );
            $result['side'] = $side;
            $sideResults[$side] = $result;

            if (($result['barangay_match'] ?? false) && $bestMatch === null) {
                $bestMatch = $result;
            }
        }

        if ($sideResults === []) {
            return null;
        }

        if ($bestMatch !== null) {
            $bestMatch['sides_checked'] = array_keys($sideResults);
            $bestMatch['side_results'] = $sideResults;

            return $bestMatch;
        }

        $fallback = $sideResults['back'] ?? $sideResults['front'] ?? reset($sideResults);
        $fallback['sides_checked'] = array_keys($sideResults);
        $fallback['side_results'] = $sideResults;
        $fallback['barangay_match'] = false;

        $combinedText = '';

        foreach ($sideResults as $result) {
            $combinedText .= ' '.(string) ($result['ocr']['full_text'] ?? '');
        }

        $match = $this->barangayMatcher->matchesRegistrationAddress(
            ['address' => trim($combinedText)],
            Barangay::query()->find($barangayId),
            $registrationFields,
            trim($combinedText),
        );

        if ($match['matched']) {
            return [
                'success' => true,
                'message' => $match['reason'],
                'barangay_match' => true,
                'matched_barangay' => $match['matched_name'],
                'match_score' => $match['score'],
                'match_reason' => $match['reason'],
                'sides_checked' => array_keys($sideResults),
                'side_results' => $sideResults,
                'ocr' => ['full_text' => trim($combinedText)],
                'processed_at' => now()->toIso8601String(),
            ];
        }

        $wizardDocs = $wizard['step2_data']['documents'] ?? [];
        $uploadFallback = $this->tryCompleteUploadVerification($wizardDocs, $barangayId, $registrationFields);

        if ($uploadFallback !== null) {
            $uploadFallback['sides_checked'] = array_keys($sideResults);
            $uploadFallback['side_results'] = $sideResults;

            return $uploadFallback;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveTempSidePath(array $meta): ?string
    {
        $path = $meta['path'] ?? null;

        if (! $path || ! Storage::disk(KkRegistrationDraftService::TEMP_DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(KkRegistrationDraftService::TEMP_DISK)->path($path);
    }

    private function downloadUrlToTemp(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
            $tempPath = 'ocr_temp/'.Str::uuid().'.'.$extension;

            Storage::disk('local')->put($tempPath, $response->body());

            return Storage::disk('local')->path($tempPath);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
