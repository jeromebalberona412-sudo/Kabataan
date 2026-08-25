<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OCRService
{
    public function __construct(
        private readonly TesseractOcrService $tesseract,
        private readonly ImagePreprocessingService $preprocessing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractText(string $imagePath): array
    {
        $imagePath = $this->normalizeImagePath($imagePath);

        if (! is_file($imagePath)) {
            return [
                'success' => false,
                'ocr_status' => 'invalid_image',
                'message' => 'Image file not found.',
                'lines' => [],
                'full_text' => '',
                'text_length' => 0,
            ];
        }

        $stagedPath = $this->stageImageForOcr($imagePath);

        try {
            $tesseractResult = $this->normalizePayload($this->tesseract->extractText($stagedPath));

            if ($this->hasUsableText($tesseractResult) && ! $this->shouldTryPythonFallback($tesseractResult)) {
                return $tesseractResult;
            }

            $windowsResult = PHP_OS_FAMILY === 'Windows'
                ? $this->normalizePayload($this->extractWithWindows($stagedPath))
                : ['success' => false, 'lines' => [], 'full_text' => '', 'ocr_status' => 'skipped'];

            if ($this->hasUsableText($windowsResult) && ! $this->shouldTryPythonFallback($windowsResult)) {
                return $this->hasUsableText($tesseractResult)
                    ? $this->mergeOcrResults($tesseractResult, $windowsResult)
                    : $windowsResult;
            }

            $pythonResult = $this->normalizePayload($this->extractWithPython($stagedPath));

            $candidates = array_values(array_filter(
                [$tesseractResult, $windowsResult, $pythonResult],
                fn (array $payload) => $this->hasUsableText($payload),
            ));

            if (count($candidates) >= 2) {
                return $this->mergeOcrResults($candidates[0], $candidates[1]);
            }

            if ($candidates !== []) {
                return $candidates[0];
            }

            // Prefer a specific engine status for diagnostics.
            foreach ([$tesseractResult, $windowsResult, $pythonResult] as $failed) {
                if (! empty($failed['ocr_status']) || ! empty($failed['message'])) {
                    return $this->normalizePayload($failed);
                }
            }

            return [
                'success' => false,
                'ocr_status' => 'ocr_empty',
                'message' => 'No useful text detected.',
                'lines' => [],
                'full_text' => '',
                'text_length' => 0,
            ];
        } finally {
            $this->deleteStagedImage($stagedPath, $imagePath);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldTryPythonFallback(array $payload): bool
    {
        if (! $this->hasUsableText($payload)) {
            return true;
        }

        $text = strtolower((string) ($payload['full_text'] ?? ''));

        if ($this->textHasNameCandidate($text)) {
            return false;
        }

        return ! $this->textHasAddressCandidate($text);
    }

    private function textHasNameCandidate(string $text): bool
    {
        return (bool) preg_match(
            '/\b[A-Za-z]{2,}(?:\s+[A-Za-z]{2,}){1,4}\b/',
            $text,
        );
    }

    private function textHasAddressCandidate(string $text): bool
    {
        return (bool) preg_match(
            '/\b(sitio|purok|zone|brgy|barangay|address|sta\.?|santa\s*cruz|laguna|lag\.?)\b/i',
            $text,
        );
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    private function mergeOcrResults(array $primary, array $secondary): array
    {
        $lines = [];
        $seen = [];

        foreach ([$primary, $secondary] as $payload) {
            foreach ($payload['lines'] ?? [] as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $text = trim((string) ($line['text'] ?? ''));

                if ($text === '') {
                    continue;
                }

                $key = strtoupper(preg_replace('/\s+/', ' ', $text) ?? $text);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $lines[] = [
                    'text' => $text,
                    'confidence' => (float) ($line['confidence'] ?? 0.75),
                ];
            }
        }

        $confidences = array_map(
            fn (array $line) => (float) ($line['confidence'] ?? 0.75),
            $lines,
        );

        $fullText = trim(implode(' ', array_map(
            fn (array $line) => trim((string) ($line['text'] ?? '')),
            $lines,
        )));

        return $this->normalizePayload([
            'average_confidence' => $confidences !== []
                ? round(array_sum($confidences) / count($confidences), 3)
                : 0.0,
            'lines' => $lines,
            'full_text' => $fullText,
            'engine' => trim(((string) ($primary['engine'] ?? 'python')).'+'.((string) ($secondary['engine'] ?? 'windows')), '+'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasUsableText(array $payload): bool
    {
        if (! empty($payload['lines']) && is_array($payload['lines'])) {
            return true;
        }

        return trim((string) ($payload['full_text'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if ($this->hasUsableText($payload)) {
            $payload['success'] = true;
        }

        return $payload;
    }

    private function normalizeImagePath(string $imagePath): string
    {
        $resolved = realpath($imagePath);

        return $resolved !== false ? $resolved : $imagePath;
    }

    /**
     * Detect Philippine ID (PhilSys, PhilHealth, Voter's) via FastAPI, with local OCR fallback.
     *
     * @return array<string, mixed>
     */
    public function detectId(string $imagePath, ?string $documentType = null): array
    {
        $imagePath = $this->normalizeImagePath($imagePath);

        if (! is_file($imagePath)) {
            return [
                'success' => false,
                'validation_error' => true,
                'message' => 'Image file not found.',
            ];
        }

        $apiResult = $this->detectIdViaApi($imagePath, $documentType);

        if ($this->isUsableDetectionResult($apiResult)) {
            return $apiResult;
        }

        Log::info('Philippine ID OCR API unavailable — using local OCR fallback', [
            'document_type' => $documentType,
            'api_message' => $apiResult['message'] ?? null,
        ]);

        return $this->detectIdViaLocalOcr($imagePath, null, $documentType);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectIdPair(
        string $frontPath,
        string $backPath,
        ?string $documentType = null,
    ): array {
        $frontPath = $this->normalizeImagePath($frontPath);
        $backPath = $this->normalizeImagePath($backPath);

        if (! is_file($frontPath) || ! is_file($backPath)) {
            return [
                'success' => false,
                'validation_error' => true,
                'message' => 'Front or back image file not found.',
            ];
        }

        $apiResult = $this->detectIdPairViaApi($frontPath, $backPath, $documentType);

        if ($this->isUsableDetectionResult($apiResult)) {
            return $apiResult;
        }

        Log::info('Philippine ID OCR pair API unavailable — using local OCR fallback', [
            'document_type' => $documentType,
            'api_message' => $apiResult['message'] ?? null,
        ]);

        return $this->detectIdViaLocalOcr($frontPath, $backPath, $documentType);
    }

    /**
     * @return array<string, mixed>
     */
    public function detectIdPairFromUploads(
        UploadedFile $front,
        UploadedFile $back,
        ?string $documentType = null,
    ): array {
        $frontPath = $front->getRealPath();
        $backPath = $back->getRealPath();

        if (! is_string($frontPath) || ! is_string($backPath)) {
            return [
                'success' => false,
                'validation_error' => true,
                'message' => 'Unable to read uploaded images.',
            ];
        }

        if ($this->filesHaveIdenticalBytes($frontPath, $backPath)) {
            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => 'Unknown',
                'detected_id_type' => 'Unknown',
                'confidence' => 0,
                'ocr_status' => 'invalid_upload',
                'message' => 'Front and back must be different photos. You uploaded the same image for both sides. Please upload the real front and the real back of your ID.',
                'source' => 'upload_validation',
            ];
        }

        $stagedFront = $this->stageImageForOcr($frontPath, $front->getClientOriginalExtension() ?: null);
        $stagedBack = $this->stageImageForOcr($backPath, $back->getClientOriginalExtension() ?: null);

        try {
            return $this->detectIdPair($stagedFront, $stagedBack, $documentType);
        } finally {
            $this->deleteStagedImage($stagedFront, $frontPath);
            $this->deleteStagedImage($stagedBack, $backPath);
        }
    }

    private function filesHaveIdenticalBytes(string $pathA, string $pathB): bool
    {
        if (! is_file($pathA) || ! is_file($pathB)) {
            return false;
        }

        $sizeA = (int) filesize($pathA);
        $sizeB = (int) filesize($pathB);

        if ($sizeA <= 0 || $sizeA !== $sizeB) {
            return false;
        }

        $hashA = @hash_file('sha256', $pathA);
        $hashB = @hash_file('sha256', $pathB);

        return is_string($hashA) && is_string($hashB) && $hashA !== '' && hash_equals($hashA, $hashB);
    }

    /**
     * @return array<string, mixed>
     */
    private function detectIdViaApi(string $imagePath, ?string $documentType): array
    {
        if (! config('ocr.api_enabled', true)) {
            return [
                'success' => false,
                'message' => 'OCR API is disabled.',
            ];
        }

        $apiUrl = (string) config('ocr.api_url', '');

        if ($apiUrl === '') {
            return [
                'success' => false,
                'message' => 'OCR API URL is not configured.',
            ];
        }

        try {
            $request = Http::connectTimeout(1)
                ->timeout((int) config('ocr.timeout', 120))
                ->attach('image', file_get_contents($imagePath), basename($imagePath));

            $apiKey = config('ocr.api_key');

            if (is_string($apiKey) && $apiKey !== '') {
                $request = $request->withHeaders(['X-Api-Key' => $apiKey]);
            }

            $form = ($documentType !== null && $documentType !== '')
                ? ['document_type' => $documentType]
                : [];

            $response = $request->post($apiUrl.'/detect-id', $form);
        } catch (\Throwable $exception) {
            Log::warning('Philippine ID OCR API request failed', [
                'error' => $exception->getMessage(),
                'path' => $imagePath,
            ]);

            return [
                'success' => false,
                'message' => 'OCR service is unavailable.',
            ];
        }

        return $this->decodeApiResponse($response->status(), $response->json());
    }

    /**
     * @return array<string, mixed>
     */
    private function detectIdPairViaApi(string $frontPath, string $backPath, ?string $documentType): array
    {
        if (! config('ocr.api_enabled', true)) {
            return [
                'success' => false,
                'message' => 'OCR API is disabled.',
            ];
        }

        $apiUrl = (string) config('ocr.api_url', '');

        if ($apiUrl === '') {
            return [
                'success' => false,
                'message' => 'OCR API URL is not configured.',
            ];
        }

        try {
            $request = Http::connectTimeout(1)
                ->timeout((int) config('ocr.timeout', 120))
                ->attach('front', file_get_contents($frontPath), basename($frontPath))
                ->attach('back', file_get_contents($backPath), basename($backPath));

            $apiKey = config('ocr.api_key');

            if (is_string($apiKey) && $apiKey !== '') {
                $request = $request->withHeaders(['X-Api-Key' => $apiKey]);
            }

            $form = ($documentType !== null && $documentType !== '')
                ? ['document_type' => $documentType]
                : [];

            $response = $request->post($apiUrl.'/detect-id-pair', $form);
        } catch (\Throwable $exception) {
            Log::warning('Philippine ID OCR pair API request failed', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'OCR service is unavailable.',
            ];
        }

        return $this->decodeApiResponse($response->status(), $response->json());
    }

    /**
     * Local always-on detection using Windows/Python OCR text extraction.
     *
     * @return array<string, mixed>
     */
    private function detectIdViaLocalOcr(string $frontPath, ?string $backPath, ?string $documentType): array
    {
        $frontMeta = $this->preprocessing->inspectImage($frontPath);
        $backMeta = is_string($backPath) ? $this->preprocessing->inspectImage($backPath) : null;

        Log::info('ID detection local OCR started', [
            'document_type' => $documentType,
            'front_bytes' => $frontMeta['bytes'] ?? null,
            'front_mime' => $frontMeta['mime'] ?? null,
            'front_width' => $frontMeta['width'] ?? null,
            'front_height' => $frontMeta['height'] ?? null,
            'has_back' => is_string($backPath),
            'back_bytes' => $backMeta['bytes'] ?? null,
            'tesseract_available' => $this->tesseract->isAvailable(),
        ]);

        $frontOcr = $this->extractText($frontPath);
        $backOcr = is_string($backPath) ? $this->extractText($backPath) : [
            'success' => false,
            'lines' => [],
            'full_text' => '',
            'ocr_status' => 'skipped',
            'text_length' => 0,
        ];

        $frontText = trim((string) ($frontOcr['full_text'] ?? ''));
        $backText = trim((string) ($backOcr['full_text'] ?? ''));
        $combined = trim($frontText.($backText !== '' ? "\n".$backText : ''));
        $textLength = mb_strlen($combined);
        $ocrStatus = $this->resolveCombinedOcrStatus($frontOcr, $backOcr, $textLength);

        if ($combined === '') {
            $looksLikeId = $this->imageLooksLikeIdDocument($frontMeta, $backMeta);
            $message = match ($ocrStatus) {
                'tesseract_unavailable' => 'Document processing is temporarily unavailable. Please try again.',
                'ocr_failed' => 'Document processing is temporarily unavailable. Please try again.',
                'invalid_image' => 'One of the uploaded files could not be read as an image. Please upload JPG or PNG photos.',
                default => $looksLikeId
                    ? 'Text could not be confidently read. Please upload a clearer image or submit for administrator review.'
                    : 'We couldn\'t read useful text from the uploaded images. Please upload a clearer front and/or back photo of your ID.',
            };

            Log::info('ID detection local OCR empty', [
                'ocr_status' => $ocrStatus,
                'looks_like_id' => $looksLikeId,
                'text_length' => 0,
            ]);

            return [
                'success' => false,
                'validation_error' => ! $looksLikeId,
                'needs_review' => $looksLikeId,
                'document_detected' => $looksLikeId ? 'possible' : 'no',
                // Prefer the selected type over a vague "other" when the image looks like an ID.
                'id_type' => $looksLikeId
                    ? ((is_string($documentType) && $documentType !== '' && $documentType !== 'other_id')
                        ? $documentType
                        : 'national_id')
                    : 'Unknown',
                'detected_id_type' => $looksLikeId
                    ? ((is_string($documentType) && $documentType !== '' && $documentType !== 'other_id')
                        ? $documentType
                        : 'national_id')
                    : 'Unknown',
                'confidence' => $looksLikeId ? 0.42 : 0.0,
                'ocr_status' => $ocrStatus,
                'message' => $message,
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
            ];
        }

        $classified = $this->classifyDocumentFromText($combined, $documentType);
        $confidence = (float) ($classified['confidence'] ?? 0);
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.35);
        $detectedType = (string) ($classified['id_type'] ?? 'Unknown');

        if ($detectedType === 'Unknown' || $detectedType === '') {
            $detectedType = (is_string($documentType) && $documentType !== '' && $documentType !== 'other_id')
                ? $documentType
                : 'national_id';
            $confidence = max($confidence, 0.5);
        }

        $matchesSelected = $this->detectedTypeMatchesSelection($detectedType, $documentType);
        $autoCorrectedType = ! $matchesSelected
            && $confidence >= 0.45
            && in_array($detectedType, ['national_id', 'philhealth_id', 'voters_id', 'school_id'], true);

        // Auto-detect wins: do not hard-fail when OCR confidently found a different valid ID type.
        $needsReview = $confidence < $minConfidence || $ocrStatus === 'ocr_low_confidence';
        $success = $confidence >= $minConfidence;
        $validationError = false;

        $idLabel = $this->documentTypeLabel($detectedType);

        Log::info('ID detection local OCR classified', [
            'ocr_status' => $ocrStatus,
            'text_length' => $textLength,
            'id_type' => $detectedType,
            'confidence' => $confidence,
            'needs_review' => $needsReview,
            'success' => $success,
            'auto_corrected' => $autoCorrectedType,
        ]);

        $message = $success
            ? ($autoCorrectedType
                ? "Detected as {$idLabel}. The document type was updated to match your upload."
                : "Document appears to be a {$idLabel}. Administrator review may be required.")
            : "Text could not be confidently verified as {$idLabel}. You may upload a clearer photo or submit for administrator review.";

        return [
            'success' => $success,
            'validation_error' => $validationError,
            'needs_review' => $needsReview || ! $success,
            'document_detected' => 'yes',
            'id_type' => $detectedType,
            'detected_id_type' => $detectedType,
            'expected_id_type' => $documentType,
            'confidence' => $confidence,
            'full_name' => $classified['full_name'] ?? null,
            'birthdate' => $classified['birthdate'] ?? null,
            'sex' => $classified['sex'] ?? null,
            'address' => $classified['address'] ?? null,
            'id_number' => $classified['id_number'] ?? null,
            'ocr_status' => $ocrStatus,
            'message' => $message,
            'raw_text' => $combined,
            'front' => $this->publicOcrSummary($frontOcr),
            'back' => $this->publicOcrSummary($backOcr),
            'source' => 'local_ocr_fallback',
            'text_length' => $textLength,
            'auto_detected' => true,
            'auto_corrected' => $autoCorrectedType,
        ];
    }

    /**
     * @param  array<string, mixed>  $frontOcr
     * @param  array<string, mixed>  $backOcr
     */
    private function resolveCombinedOcrStatus(array $frontOcr, array $backOcr, int $textLength): string
    {
        if ($textLength >= 40) {
            return 'ocr_success';
        }

        if ($textLength > 0) {
            return 'ocr_low_confidence';
        }

        foreach ([$frontOcr, $backOcr] as $payload) {
            $status = (string) ($payload['ocr_status'] ?? '');
            if (in_array($status, ['tesseract_unavailable', 'ocr_failed', 'invalid_image'], true)) {
                return $status;
            }
        }

        return 'ocr_empty';
    }

    /**
     * @param  array<string, mixed>  $frontMeta
     * @param  array<string, mixed>|null  $backMeta
     */
    private function imageLooksLikeIdDocument(array $frontMeta, ?array $backMeta): bool
    {
        $width = (int) ($frontMeta['width'] ?? 0);
        $height = (int) ($frontMeta['height'] ?? 0);
        $bytes = (int) ($frontMeta['bytes'] ?? 0);

        if ($bytes < 8_000) {
            return false;
        }

        if ($width > 0 && $height > 0) {
            $ratio = $width / max(1, $height);

            // Card-like landscape, portrait phone screenshot, or roughly square scan.
            if (($ratio >= 1.2 && $ratio <= 2.2) || ($ratio >= 0.35 && $ratio <= 0.85) || ($ratio >= 0.85 && $ratio <= 1.2)) {
                return true;
            }
        }

        return $bytes >= 40_000;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function publicOcrSummary(array $payload): array
    {
        return [
            'success' => (bool) ($payload['success'] ?? false),
            'ocr_status' => $payload['ocr_status'] ?? null,
            'engine' => $payload['engine'] ?? null,
            'text_length' => (int) ($payload['text_length'] ?? mb_strlen((string) ($payload['full_text'] ?? ''))),
            'processing_ms' => $payload['processing_ms'] ?? null,
            'image' => $payload['image'] ?? null,
            // Keep full_text server-side only for classification; strip from UI-facing copies later.
            'full_text' => $payload['full_text'] ?? '',
            'lines' => $payload['lines'] ?? [],
            'message' => $payload['message'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isUsableDetectionResult(array $payload): bool
    {
        if (($payload['success'] ?? false) === true) {
            return true;
        }

        $message = strtolower((string) ($payload['message'] ?? ''));

        if (
            str_contains($message, 'unavailable')
            || str_contains($message, 'disabled')
            || str_contains($message, 'not configured')
            || str_contains($message, 'connection')
            || str_contains($message, 'timed out')
            || str_contains($message, 'couldn\'t read any text')
            || str_contains($message, 'invalid ocr api')
            || str_contains($message, 'ocr service error')
        ) {
            return false;
        }

        $idType = trim((string) ($payload['id_type'] ?? $payload['detected_id_type'] ?? ''));
        $confidence = (float) ($payload['confidence'] ?? 0);
        $rawText = trim((string) ($payload['raw_text'] ?? $payload['full_text'] ?? ''));

        // Empty / unknown API failures should fall through to local OCR.
        if (($idType === '' || strcasecmp($idType, 'Unknown') === 0) && $confidence <= 0 && $rawText === '') {
            return false;
        }

        if (array_key_exists('validation_error', $payload) || array_key_exists('id_type', $payload)) {
            return true;
        }

        return false;
    }

    /**
     * Copy uploads into a temp file with a real image extension and optional downscale.
     * PHP upload temps often have no extension, which breaks some OCR decoders.
     */
    private function stageImageForOcr(string $imagePath, ?string $hintExtension = null): string
    {
        $imagePath = $this->normalizeImagePath($imagePath);

        if (! is_file($imagePath)) {
            return $imagePath;
        }

        $mime = @mime_content_type($imagePath) ?: '';
        $extension = strtolower((string) $hintExtension);
        $extension = ltrim($extension, '.');

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp'], true)) {
            $extension = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'jpg',
                str_contains($mime, 'bmp') => 'jpg',
                default => 'jpg',
            };
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $staged = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_ocr_'.bin2hex(random_bytes(8)).'.'.$extension;

        if (! @copy($imagePath, $staged)) {
            return $imagePath;
        }

        // Keep a single PowerShell process per image (Windows OCR script already resizes).
        // Extra normalize PowerShell hosts cause intermittent 8009001d failures under artisan serve.
        return $staged;
    }

    private function deleteStagedImage(string $stagedPath, string $originalPath): void
    {
        $stagedPath = $this->normalizeImagePath($stagedPath);
        $originalPath = $this->normalizeImagePath($originalPath);

        if ($stagedPath !== $originalPath && is_file($stagedPath)) {
            @unlink($stagedPath);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function classifyDocumentFromText(string $text, ?string $expectedType = null): array
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', $text) ?? $text);

        $scores = [
            'national_id' => 0.0,
            'philhealth_id' => 0.0,
            'voters_id' => 0.0,
            'school_id' => 0.0,
            'other_id' => 0.05,
        ];

        // PhilSys / National ID signals (front + back layouts)
        if (preg_match('/\b(PHILSYS|PHIL\s*ID|PHILIPPINE\s+IDENTIFICATION|PAMBANSANG\s+IDENTIDAD)\b/i', $text)) {
            $scores['national_id'] += 0.7;
        }
        if (preg_match('/\bREPUBLIC\s+OF\s+THE\s+PHILIPPINES\b/i', $text)) {
            $scores['national_id'] += 0.25;
        }
        if (preg_match('/\b(GIVEN\s+NAMES?|MIDDLE\s+NAME|LAST\s+NAME|DATE\s+OF\s+BIRTH|PLACE\s+OF\s+BIRTH|MARITAL\s+STATUS|BLOOD\s+TYPE)\b/i', $text)) {
            $scores['national_id'] += 0.32;
        }
        if (preg_match('/\b(PCN|PhilSys|Digital\s+National\s+ID|ePhilID)\b/i', $text)) {
            $scores['national_id'] += 0.28;
        }
        if (preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{1,2},?\s+\d{4}\b/i', $text)) {
            $scores['national_id'] += 0.18;
        }
        if (preg_match('/\b(MALE|FEMALE)\b/i', $text) && preg_match('/\b(SINGLE|MARRIED|WIDOWED)\b/i', $text)) {
            $scores['national_id'] += 0.2;
        }
        if (preg_match('/\b(BRGY\.?|BARANGAY|SITIO|PUROK|LAGUNA|BULACAN|SINILOAN|MALOLOS)\b/i', $text)) {
            $scores['national_id'] += 0.12;
        }
        if (preg_match('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', $text)) {
            $scores['national_id'] += 0.15;
        }

        if (preg_match('/\b(PHILHEALTH|PHIL\s*HEALTH|PHIC)\b/i', $text)) {
            $scores['philhealth_id'] += 0.7;
        }
        if (preg_match('/\b(MEMBER|PIN)\b/i', $text) && $scores['philhealth_id'] > 0) {
            $scores['philhealth_id'] += 0.15;
        }
        if (preg_match('/\b\d{2}-\d{9}-\d\b/', $text)) {
            $scores['philhealth_id'] += 0.25;
        }

        if (preg_match('/\b(COMELEC|COMMISSION\s+ON\s+ELECTIONS)\b/i', $text)) {
            $scores['voters_id'] += 0.75;
        }
        if (preg_match('/\b(VOTER\'?S?\s+ID|VOTER\s+CERTIFICATION|VOTER\s+REGISTRATION|REGISTERED\s+VOTER)\b/i', $text)) {
            $scores['voters_id'] += 0.65;
        }
        if (preg_match('/\b(PRECINCT|VIN|VOTER\'?S?\s+IDENTIFICATION)\b/i', $text)) {
            $scores['voters_id'] += 0.35;
        }

        if (preg_match('/\b(SCHOOL|UNIVERSITY|COLLEGE|STUDENT\s+ID|ACADEMY|INSTITUTE|CAMPUS)\b/i', $text)) {
            $scores['school_id'] += 0.6;
        }
        if (preg_match('/\b(STUDENT\s+NO|STUDENT\s+NUMBER|LRN)\b/i', $text)) {
            $scores['school_id'] += 0.25;
        }

        if (preg_match('/\b(DRIVER\'?S?\s+LICENSE|PASSPORT|PWD\s+ID|SENIOR\s+CITIZEN|BARANGAY\s+ID|POSTAL\s+ID|COMPANY\s+ID)\b/i', $text)) {
            $scores['other_id'] += 0.55;
        }

        if (preg_match('/\b(NAME|BIRTH|ADDRESS|SEX)\b/i', $text)) {
            foreach (['national_id', 'philhealth_id', 'voters_id', 'school_id'] as $key) {
                if ($scores[$key] > 0) {
                    $scores[$key] += 0.05;
                }
            }
        }

        // Personal ID card layout without strong rival keywords → likely PhilSys/National ID.
        if ($this->textLooksLikePersonalIdCard($text)) {
            $rival = max($scores['philhealth_id'], $scores['voters_id'], $scores['school_id'], $scores['other_id']);
            if ($rival < 0.45) {
                $scores['national_id'] = max($scores['national_id'], 0.68);
            }
        }

        arsort($scores);
        $idType = (string) array_key_first($scores);
        $confidence = round((float) $scores[$idType], 2);
        $textLength = mb_strlen(trim($text));
        $alpha = preg_match_all('/[A-Za-z]/', $text) ?: 0;

        // Never leave a blank/Unknown label when OCR clearly extracted document text.
        if ($confidence < 0.35 || $idType === 'other_id') {
            if ($this->textLooksLikePersonalIdCard($text) || preg_match('/\b(REPUBLIC|PHILIPPINES|GIVEN|BIRTH|ADDRESS|MALE|FEMALE)\b/i', $text)) {
                $rival = max($scores['philhealth_id'], $scores['voters_id'], $scores['school_id']);
                if ($rival >= 0.45) {
                    foreach (['voters_id', 'philhealth_id', 'school_id'] as $key) {
                        if ($scores[$key] >= $rival) {
                            $idType = $key;
                            $confidence = max(0.6, (float) $scores[$key]);
                            break;
                        }
                    }
                } else {
                    $idType = 'national_id';
                    $confidence = max(0.62, (float) $scores['national_id'], $confidence);
                }
            } elseif ($scores['voters_id'] >= 0.3) {
                $idType = 'voters_id';
                $confidence = max(0.55, (float) $scores['voters_id']);
            } elseif ($scores['philhealth_id'] >= 0.3) {
                $idType = 'philhealth_id';
                $confidence = max(0.55, (float) $scores['philhealth_id']);
            } elseif ($scores['school_id'] >= 0.3) {
                $idType = 'school_id';
                $confidence = max(0.55, (float) $scores['school_id']);
            } elseif ($textLength >= 40 && $alpha >= 20) {
                // OCR worked but keywords were noisy — prefer selected type when available.
                if (is_string($expectedType) && isset($scores[$expectedType]) && $expectedType !== 'other_id') {
                    $idType = $expectedType;
                    $confidence = max(0.5, (float) $scores[$expectedType], 0.45);
                } else {
                    $idType = 'national_id';
                    $confidence = 0.5;
                }
            } elseif ($confidence < 0.35) {
                $idType = 'Unknown';
                $confidence = max($confidence, 0.2);
            }
        }

        // OCR volume boost: longer readable text increases confidence slightly.
        if ($idType !== 'Unknown' && $textLength >= 80 && $alpha >= 40) {
            $confidence = min(0.95, $confidence + 0.08);
        }

        // Only keep the selected type when its own score is genuinely competitive.
        if (
            is_string($expectedType)
            && isset($scores[$expectedType])
            && $scores[$expectedType] >= 0.45
            && ($scores[$expectedType] + 0.08) >= $confidence
        ) {
            $idType = $expectedType;
            $confidence = max($confidence, (float) $scores[$expectedType]);
        }

        return [
            'id_type' => $idType,
            'confidence' => min(0.95, round($confidence, 2)),
            'full_name' => $this->guessNameFromText($text),
            'birthdate' => $this->guessBirthdateFromText($text),
            'sex' => preg_match('/\b(MALE|LALAKE)\b/i', $normalized) ? 'Male'
                : (preg_match('/\b(FEMALE|BABAE)\b/i', $normalized) ? 'Female' : null),
            'address' => $this->guessAddressFromText($text),
            'id_number' => $this->guessIdNumberFromText($text, $idType),
            'scores' => $scores,
        ];
    }

    private function textLooksLikePersonalIdCard(string $text): bool
    {
        $signals = 0;

        if (preg_match('/\b(DATE\s+OF\s+BIRTH|BIRTHDAY|BIRTH\s*DATE|DOB)\b/i', $text)
            || preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{1,2},?\s+\d{4}\b/i', $text)
            || preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $text)) {
            $signals++;
        }

        if (preg_match('/\b(MALE|FEMALE|LALAKE|BABAE)\b/i', $text)) {
            $signals++;
        }

        if (preg_match('/\b(ADDRESS|BRGY\.?|BARANGAY|SITIO|PUROK|CITY|PROVINCE)\b/i', $text)) {
            $signals++;
        }

        if (preg_match('/\b(GIVEN\s+NAMES?|MIDDLE\s+NAME|LAST\s+NAME|PANGALAN|SURNAME)\b/i', $text)
            || preg_match('/\b[A-Z]{2,}(?:\s+[A-Z]{2,}){1,4}\b/', $text)) {
            $signals++;
        }

        return $signals >= 2;
    }

    private function detectedTypeMatchesSelection(string $detectedType, ?string $selectedType): bool
    {
        if ($selectedType === null || $selectedType === '' || $selectedType === 'other_id') {
            return $detectedType !== 'Unknown';
        }

        if ($detectedType === 'Unknown') {
            return false;
        }

        return $detectedType === $selectedType;
    }

    private function documentTypeLabel(?string $documentType): string
    {
        return match ($documentType) {
            'national_id' => 'PhilSys / National ID',
            'philhealth_id' => 'PhilHealth ID',
            'voters_id' => "Voter's ID",
            'school_id' => 'School ID',
            'other_id' => 'Other Supporting ID',
            default => 'ID',
        };
    }

    private function guessNameFromText(string $text): ?string
    {
        $flat = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (
            preg_match(
                '/\b(?:GIVEN\s+NAMES?|FIRST\s+NAME)\s*[:\-]?\s*([A-Z][A-Za-z.\-]+(?:\s+[A-Z][A-Za-z.\-]+){0,3})(?=\s+(?:MIDDLE\s+NAME|LAST\s+NAME|SURNAME|DATE\s+OF\s+BIRTH|SEX|MALE|FEMALE|$))/i',
                $flat,
                $given,
            )
            && preg_match('/\b(?:LAST\s+NAME|SURNAME)\s*[:\-]?\s*([A-Z][A-Za-z.\-]+)/i', $flat, $last)
        ) {
            $middle = '';
            if (preg_match(
                '/\bMIDDLE\s+NAME\s*[:\-]?\s*([A-Z][A-Za-z.\-]+)(?=\s+(?:LAST\s+NAME|SURNAME|DATE\s+OF\s+BIRTH|SEX|MALE|FEMALE|$))/i',
                $flat,
                $mid,
            )) {
                $middle = ' '.trim($mid[1]);
            }

            return trim($given[1].$middle.' '.$last[1]);
        }

        if (preg_match('/\b(?:NAME|PANGALAN)\s*[:\-]?\s*([A-Z][A-Za-z.\-]+(?:\s+[A-Z][A-Za-z.\-]+){1,4})/i', $flat, $match)) {
            $candidate = trim($match[1]);
            if (! preg_match('/\b(DATE|BIRTH|ADDRESS|SEX|MALE|FEMALE)\b/i', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function guessBirthdateFromText(string $text): ?string
    {
        if (preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{1,2}),?\s+(\d{4})\b/i', $text, $match)) {
            try {
                return \Carbon\Carbon::parse($match[0])->toDateString();
            } catch (\Throwable) {
                // continue
            }
        }

        if (preg_match('/\b(?:BIRTH(?:DAY|DATE)?|DATE OF BIRTH|DOB)\s*[:\-]?\s*([0-9]{4}[-\/.][0-9]{1,2}[-\/.][0-9]{1,2}|[0-9]{1,2}[-\/.][0-9]{1,2}[-\/.][0-9]{2,4})/i', $text, $match)) {
            try {
                return \Carbon\Carbon::parse($match[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function guessAddressFromText(string $text): ?string
    {
        if (preg_match('/\b(?:ADDRESS|TIRAHAN)\s*[:\-]?\s*(.+)$/im', $text, $match)) {
            $address = trim($match[1]);

            return $address !== '' ? mb_substr($address, 0, 180) : null;
        }

        if (preg_match('/\b((?:SITIO|PUROK|BRGY\.?|BARANGAY)\s+[A-Za-z0-9 .\-]+(?:,\s*[A-Za-z0-9 .\-]+){0,4})/i', $text, $match)) {
            return mb_substr(trim($match[1]), 0, 180);
        }

        return null;
    }

    private function guessIdNumberFromText(string $text, string $idType): ?string
    {
        if ($idType === 'philhealth_id' && preg_match('/\b(\d{2}-\d{9}-\d)\b/', $text, $match)) {
            return $match[1];
        }

        if (preg_match('/\b(\d{4}-\d{4}-\d{4}-\d{4})\b/', $text, $match)) {
            return $match[1];
        }

        if ($idType === 'national_id') {
            return null;
        }

        if (preg_match('/\b([A-Z0-9]{3,}(?:-[A-Z0-9]{2,}){1,4})\b/', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function decodeApiResponse(int $status, ?array $payload): array
    {
        if (! is_array($payload)) {
            return [
                'success' => false,
                'message' => 'Invalid OCR API response.',
            ];
        }

        if ($status >= 500) {
            return [
                'success' => false,
                'message' => (string) ($payload['detail'] ?? $payload['message'] ?? 'OCR service error.'),
            ];
        }

        if (! array_key_exists('success', $payload)) {
            $payload['success'] = ! ($payload['validation_error'] ?? false)
                && (($payload['id_type'] ?? 'Unknown') !== 'Unknown');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractWithPython(string $imagePath): array
    {
        $python = (string) config('ocr.python');
        $script = (string) config('ocr.script');
        $timeout = (int) config('ocr.timeout', 120);

        if (! is_file($python) || ! is_file($script)) {
            return [
                'success' => false,
                'message' => 'Python OCR is not configured.',
            ];
        }

        try {
            $result = Process::timeout($timeout)
                ->run([
                    $python,
                    $script,
                    $imagePath,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Python OCR process failed', ['error' => $exception->getMessage()]);

            return [
                'success' => false,
                'message' => 'OCR processing timed out or failed.',
            ];
        }

        return $this->decodeJsonOutput(trim($result->output()), trim($result->errorOutput()));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractWithWindows(string $imagePath): array
    {
        $script = (string) config('ocr.windows_script');
        $timeout = max(90, (int) config('ocr.timeout', 120));

        if (! is_file($script)) {
            return [
                'success' => false,
                'message' => 'Windows OCR script not found.',
            ];
        }

        $powershell = $this->windowsPowerShellPath();
        $attempts = 0;
        $lastPayload = [
            'success' => false,
            'message' => 'Windows OCR processing failed.',
            'lines' => [],
            'full_text' => '',
        ];

        while ($attempts < 2) {
            $attempts++;

            try {
                $result = Process::timeout($timeout)
                    ->run([
                        $powershell,
                        '-NoLogo',
                        '-NoProfile',
                        '-NonInteractive',
                        '-ExecutionPolicy',
                        'Bypass',
                        '-File',
                        $script,
                        '-ImagePath',
                        $imagePath,
                    ]);
            } catch (\Throwable $exception) {
                Log::warning('Windows OCR process failed', ['error' => $exception->getMessage()]);

                $lastPayload = [
                    'success' => false,
                    'message' => 'Windows OCR processing failed.',
                    'lines' => [],
                    'full_text' => '',
                ];

                usleep(250000);
                continue;
            }

            $output = $this->sanitizeProcessText($result->output());
            $errorOutput = $this->sanitizeProcessText($result->errorOutput());
            $payload = $this->decodeJsonOutput($output, $errorOutput);

            if ($this->hasUsableText($payload)) {
                return $payload;
            }

            $lastPayload = $payload;
            $combined = strtolower(($payload['message'] ?? '').' '.$errorOutput);

            // Retry once when the PowerShell host itself fails to load.
            if (
                $attempts < 2
                && (
                    str_contains($combined, '8009001d')
                    || str_contains($combined, 'loading managed windows powershell')
                    || str_contains($combined, 'internal windows powershell error')
                    || (int) $result->exitCode() === -65536
                )
            ) {
                Log::warning('Windows OCR host failed; retrying once', [
                    'exit' => $result->exitCode(),
                    'message' => $payload['message'] ?? null,
                ]);
                usleep(400000);
                continue;
            }

            Log::info('Windows OCR returned no usable text', [
                'message' => $payload['message'] ?? null,
                'exit' => $result->exitCode(),
            ]);

            break;
        }

        return $lastPayload;
    }

    private function windowsPowerShellPath(): string
    {
        $systemRoot = (string) (getenv('SystemRoot') ?: 'C:\\Windows');
        $candidate = $systemRoot.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        return is_file($candidate) ? $candidate : 'powershell.exe';
    }

    private function sanitizeProcessText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // PowerShell sometimes emits UTF-16LE on stderr ("A\0B\0...").
        if (str_contains($text, "\0")) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            } else {
                $text = str_replace("\0", '', $text);
            }
        }

        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOutput(string $output, string $errorOutput): array
    {
        $output = $this->sanitizeProcessText($output);
        $errorOutput = $this->sanitizeProcessText($errorOutput);

        if ($output === '') {
            return [
                'success' => false,
                'message' => $errorOutput !== '' ? $errorOutput : 'OCR returned no output.',
            ];
        }

        $payload = $this->tryDecodeJson($output);

        if ($payload === null && preg_match('/\{.*\}/s', $output, $match)) {
            $payload = $this->tryDecodeJson($match[0]);
        }

        if ($payload === null) {
            Log::warning('Invalid OCR JSON output', ['output' => substr($output, 0, 500)]);

            return [
                'success' => false,
                'message' => 'Invalid OCR response.',
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryDecodeJson(string $output): ?array
    {
        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            throw new RuntimeException('OCR response must be a JSON object.');
        }

        return $payload;
    }
}
