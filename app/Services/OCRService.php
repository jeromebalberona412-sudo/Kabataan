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
        private readonly KabataanFullNameMatcher $nameMatcher,
        private readonly PerceptualHashService $perceptualHash,
    ) {}

    /**
     * Public gate for ID-like OCR text (used by wizard detect-id / step-2).
     */
    public function looksLikeSupportingIdText(string $text): bool
    {
        return $this->textHasSupportingIdSignal($text);
    }

    /**
     * Public gate: OCR text must support the user-selected document type.
     *
     * @param  array<string, float|int>  $scores
     */
    public function supportsSelectedIdType(string $text, string $documentType, array $scores = []): bool
    {
        return $this->textSupportsSelectedIdType($text, $documentType, $scores);
    }

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
                'message' => 'We couldn\'t read any text from this ID. Please make sure the ID is clear, properly aligned, and well lit.',
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
            $gated = $this->enforceDetectionAccuracy($apiResult, $documentType);

            if (! ($gated['validation_error'] ?? false)) {
                return $gated;
            }

            // API accepted weak/non-ID text — try local OCR before returning the reject.
            $local = $this->detectIdViaLocalOcr($imagePath, null, $documentType);
            if (! ($local['validation_error'] ?? false)) {
                return $local;
            }

            return $gated;
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

        if ($this->frontAndBackAreSameImage($frontPath, $backPath)) {
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

        $apiResult = $this->detectIdPairViaApi($frontPath, $backPath, $documentType);

        if ($this->isUsableDetectionResult($apiResult)) {
            $gated = $this->enforceDetectionAccuracy($apiResult, $documentType);

            if (! ($gated['validation_error'] ?? false)) {
                return $gated;
            }

            $local = $this->detectIdViaLocalOcr($frontPath, $backPath, $documentType);
            if (! ($local['validation_error'] ?? false)) {
                return $local;
            }

            return $gated;
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

        if ($this->frontAndBackAreSameImage($frontPath, $backPath)) {
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

    /**
     * Exact bytes or near-duplicate (re-saved / resized) front+back photos.
     */
    public function frontAndBackAreSameImage(string $pathA, string $pathB): bool
    {
        if (! is_file($pathA) || ! is_file($pathB)) {
            return false;
        }

        $sizeA = (int) filesize($pathA);
        $sizeB = (int) filesize($pathB);

        if ($sizeA <= 0 || $sizeB <= 0) {
            return false;
        }

        if ($sizeA === $sizeB) {
            $hashA = @hash_file('sha256', $pathA);
            $hashB = @hash_file('sha256', $pathB);

            if (is_string($hashA) && is_string($hashB) && $hashA !== '' && hash_equals($hashA, $hashB)) {
                return true;
            }
        }

        $phashA = $this->perceptualHash->hashFromFile($pathA);
        $phashB = $this->perceptualHash->hashFromFile($pathB);

        if (! is_string($phashA) || ! is_string($phashB) || $phashA === '' || $phashB === '') {
            // Last-resort without decoder: same dimensions + nearly same size + matching head/tail samples.
            return $this->frontAndBackLookStructurallyIdentical($pathA, $pathB, $sizeA, $sizeB);
        }

        $distance = $this->perceptualHash->hammingDistance($phashA, $phashB);
        if ($distance === null) {
            return $this->frontAndBackLookStructurallyIdentical($pathA, $pathB, $sizeA, $sizeB);
        }

        $threshold = (int) config('documents.front_back.phash_hamming_threshold', 5);

        return $distance <= max(0, $threshold);
    }

    private function frontAndBackLookStructurallyIdentical(string $pathA, string $pathB, int $sizeA, int $sizeB): bool
    {
        $infoA = @getimagesize($pathA);
        $infoB = @getimagesize($pathB);

        if (! is_array($infoA) || ! is_array($infoB)) {
            return false;
        }

        if ((int) $infoA[0] !== (int) $infoB[0] || (int) $infoA[1] !== (int) $infoB[1]) {
            return false;
        }

        $larger = max($sizeA, $sizeB);
        $smaller = min($sizeA, $sizeB);
        if ($larger <= 0 || ($smaller / $larger) < 0.92) {
            return false;
        }

        $sample = static function (string $path, int $size): string {
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                return '';
            }
            $head = (string) fread($handle, 2048);
            $tail = '';
            if ($size > 2048) {
                fseek($handle, max(0, $size - 2048));
                $tail = (string) fread($handle, 2048);
            }
            fclose($handle);

            return hash('sha256', $head.'|'.$tail);
        };

        $sampleA = $sample($pathA, $sizeA);
        $sampleB = $sample($pathB, $sizeB);

        return $sampleA !== '' && $sampleB !== '' && hash_equals($sampleA, $sampleB);
    }

    /**
     * True when front/back OCR text is nearly the same (same ID side uploaded twice).
     */
    public function frontAndBackOcrTextTooSimilar(string $frontText, string $backText): bool
    {
        $front = trim(preg_replace('/\s+/', ' ', $frontText) ?? '');
        $back = trim(preg_replace('/\s+/', ' ', $backText) ?? '');

        if ($front === '' || $back === '') {
            return false;
        }

        if (mb_strlen($front) < 24 || mb_strlen($back) < 24) {
            return false;
        }

        if (strcasecmp($front, $back) === 0) {
            return true;
        }

        similar_text(mb_strtolower($front), mb_strtolower($back), $percent);
        $threshold = (float) config('documents.front_back.ocr_text_similarity_percent', 90);

        return $percent >= $threshold;
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
                ->timeout(min(8, (int) config('ocr.timeout', 120)))
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
                ->timeout(min(8, (int) config('ocr.timeout', 120)))
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

        if (
            is_string($backPath)
            && $frontText !== ''
            && $backText !== ''
            && $this->frontAndBackOcrTextTooSimilar($frontText, $backText)
        ) {
            Log::info('ID detection rejected identical front/back OCR text', [
                'document_type' => $documentType,
                'front_length' => mb_strlen($frontText),
                'back_length' => mb_strlen($backText),
            ]);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => 'Unknown',
                'detected_id_type' => 'Unknown',
                'confidence' => 0.0,
                'confidence_band' => 'low',
                'ocr_status' => 'invalid_upload',
                'message' => 'Front and back look like the same ID side. Please upload two different photos — the real front and the real back.',
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
            ];
        }

        if ($combined === '') {
            $message = match ($ocrStatus) {
                'tesseract_unavailable' => 'Document processing is temporarily unavailable. Please try again.',
                'ocr_failed' => 'Document processing is temporarily unavailable. Please try again.',
                'invalid_image' => 'One of the uploaded files could not be read as an image. Please upload JPG or PNG photos.',
                default => 'This does not appear to be a readable ID. Please scan or upload a clear front and back photo of your selected ID — not a random image.',
            };

            Log::info('ID detection local OCR empty', [
                'ocr_status' => $ocrStatus,
                'text_length' => 0,
            ]);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => 'Unknown',
                'detected_id_type' => 'Unknown',
                'confidence' => 0.0,
                'confidence_band' => 'low',
                'ocr_status' => $ocrStatus === 'ocr_empty' ? 'ocr_empty' : $ocrStatus,
                'message' => $message,
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
            ];
        }

        if (! $this->textHasSupportingIdSignal($combined)) {
            Log::info('ID detection rejected non-ID image text', [
                'text_length' => $textLength,
                'document_type' => $documentType,
            ]);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => 'Unknown',
                'detected_id_type' => 'Unknown',
                'confidence' => 0.0,
                'confidence_band' => 'low',
                'ocr_status' => 'ocr_empty',
                'message' => 'This does not appear to be a valid ID. Please scan or upload a clear photo of your selected ID card (front and back) — random photos are not accepted.',
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
            ];
        }

        $classified = $this->classifyDocumentFromText($combined, $documentType);
        $scores = is_array($classified['scores'] ?? null) ? $classified['scores'] : [];
        $confidence = (float) ($classified['confidence'] ?? 0);
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.45);
        $autoCorrectMin = (float) config('ocr.auto_correct_min_confidence', 0.55);
        $detectedType = (string) ($classified['id_type'] ?? 'Unknown');
        $selectedScore = (is_string($documentType) && isset($scores[$documentType]))
            ? (float) $scores[$documentType]
            : 0.0;

        // Selected ID type must be supported by OCR evidence (stop accepting "whatever").
        if (is_string($documentType) && $documentType !== '' && ! $this->textSupportsSelectedIdType($combined, $documentType, $scores)) {
            $expectedLabel = $this->documentTypeLabel($documentType);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => $detectedType !== 'Unknown' ? $detectedType : 'Unknown',
                'detected_id_type' => $detectedType !== 'Unknown' ? $detectedType : null,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => 'low',
                'ocr_status' => 'ocr_low_confidence',
                'message' => "The uploaded images do not look like a valid {$expectedLabel}. Please upload a clearer front and back of your selected ID — not a random photo.",
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
                'scores' => $scores,
            ];
        }

        $matchesSelected = $this->detectedTypeMatchesSelection($detectedType, $documentType);

        // Do not auto-switch the user's selected type — mismatch must be a hard error.
        // Keep selected type only when its own score is competitive with the top label.
        if (
            ! $matchesSelected
            && is_string($documentType)
            && $documentType !== ''
            && $documentType !== 'other_id'
            && $selectedScore >= 0.40
            && ($selectedScore + 0.08) >= $confidence
            && $confidence < $autoCorrectMin
        ) {
            $detectedType = $documentType;
            $confidence = max($confidence, $selectedScore);
            $matchesSelected = true;
        }

        // Reject weak classifications instead of marking them as reviewable "IDs".
        $acceptFloor = max(0.40, $minConfidence - 0.05);
        if ($confidence < $acceptFloor && $selectedScore < $acceptFloor) {
            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => 'Unknown',
                'detected_id_type' => null,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => 'low',
                'ocr_status' => 'ocr_low_confidence',
                'message' => 'We could not confidently identify this as your selected ID. Please retake clearer front and back photos.',
                'raw_text' => '',
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
                'scores' => $scores,
            ];
        }

        $needsReview = $confidence < $minConfidence || $ocrStatus === 'ocr_low_confidence';
        $success = $confidence >= $minConfidence && ($matchesSelected || $documentType === 'other_id');
        $validationError = false;
        $autoCorrectedType = false;

        // Type mismatch → hard error (never soft-pass as needs_review).
        if (
            is_string($documentType)
            && $documentType !== ''
            && $documentType !== 'other_id'
            && ! $matchesSelected
            && in_array($detectedType, ['national_id', 'philhealth_id', 'voters_id', 'school_id'], true)
        ) {
            $expectedLabel = $this->documentTypeLabel($documentType);
            $detectedLabel = $this->documentTypeLabel($detectedType);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'yes',
                'id_type' => $detectedType,
                'detected_id_type' => $detectedType,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => $this->confidenceBand($confidence, $minConfidence),
                'ocr_status' => $ocrStatus,
                'message' => "You selected {$expectedLabel}, but the images look like {$detectedLabel}. Please upload the correct ID or change the document type.",
                'raw_text' => $combined,
                'front' => $this->publicOcrSummary($frontOcr),
                'back' => $this->publicOcrSummary($backOcr),
                'source' => 'local_ocr_fallback',
                'scores' => $scores,
            ];
        }

        // Unknown classification but text supports the selected type → bind to selection.
        if (
            ! $matchesSelected
            && is_string($documentType)
            && $documentType !== ''
            && ($detectedType === '' || strcasecmp($detectedType, 'Unknown') === 0)
        ) {
            $detectedType = $documentType;
            $matchesSelected = true;
            $success = $confidence >= $minConfidence;
            $needsReview = ! $success || $ocrStatus === 'ocr_low_confidence';
        }

        $idLabel = $this->documentTypeLabel($detectedType);
        $confidenceBand = $this->confidenceBand($confidence, $minConfidence);

        Log::info('ID detection local OCR classified', [
            'ocr_status' => $ocrStatus,
            'text_length' => $textLength,
            'id_type' => $detectedType,
            'confidence' => $confidence,
            'selected_score' => $selectedScore,
            'confidence_band' => $confidenceBand,
            'needs_review' => $needsReview,
            'success' => $success,
            'auto_corrected' => $autoCorrectedType,
        ]);

        $message = match ($confidenceBand) {
            'high' => "ID text detected. Information was extracted from your {$idLabel}. Please review for accuracy.",
            'medium' => "Text detected from your {$idLabel}, but please review the information carefully.",
            default => "We could not confidently read the ID. Please retake the photo or upload a clearer image.",
        };

        $result = [
            'success' => $success,
            'validation_error' => $validationError,
            'needs_review' => $needsReview || ! $success,
            'document_detected' => 'yes',
            'id_type' => $detectedType,
            'detected_id_type' => $detectedType,
            'expected_id_type' => $documentType,
            'confidence' => $confidence,
            'confidence_band' => $confidenceBand,
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
            'scores' => $scores,
        ];

        return $this->enforceDetectionAccuracy($result, $documentType);
    }

    private function confidenceBand(float $confidence, float $minConfidence): string
    {
        if ($confidence >= max(0.7, $minConfidence + 0.15)) {
            return 'high';
        }

        if ($confidence >= $minConfidence) {
            return 'medium';
        }

        return 'low';
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
     * True when OCR text contains supporting-ID signals (not a random photo/animal/meme).
     */
    private function textHasSupportingIdSignal(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '' || mb_strlen($trimmed) < 18) {
            return false;
        }

        $alphaDigits = preg_replace('/[^A-Za-z0-9]+/', '', $trimmed) ?? '';
        if (mb_strlen($alphaDigits) < 16) {
            return false;
        }

        // Strong issuer / ID-type keywords (required for most accept paths).
        $hasIssuerKeyword = preg_match(
            '/\b(PHILSYS|PHIL\s*ID|PHILIPPINE\s+IDENTIFICATION|PAMBANSANG\s+IDENTIDAD|ePhilID|PCN|PHILHEALTH|PHIL\s*HEALTH|PHIC|COMELEC|COMMISSION\s+ON\s+ELECTIONS|VOTER\'?S?\s+ID|VOTER\s+CERTIFICATION|STUDENT\s+ID|SCHOOL\s+ID|DRIVER\'?S?\s+LICENSE|PASSPORT|PWD\s+ID|SENIOR\s+CITIZEN|BARANGAY\s+ID|POSTAL\s+ID|COMPANY\s+ID|REPUBLIC\s+OF\s+THE\s+PHILIPPINES|IDENTIFICATION\s+CARD|NATIONAL\s+ID)\b/i',
            $trimmed
        ) === 1;

        $labelHits = 0;
        foreach ([
            '/\b(GIVEN\s+NAMES?|FIRST\s+NAME|MIDDLE\s+NAME|LAST\s+NAME|SURNAME|APELYIDO)\b/i',
            '/\b(DATE\s+OF\s+BIRTH|BIRTHDAY|BIRTH\s*DATE|DOB|PETSA\s+NG\s+KAPANGANAKAN)\b/i',
            '/\b(ADDRESS|TIRAHAN)\b/i',
            '/\b(SEX|KASARIAN)\b/i',
            '/\b(ID\s*NO\.?|ID\s+NUMBER|MEMBERSHIP|STUDENT\s+NO|STUDENT\s+NUMBER|LRN|VIN|PCN)\b/i',
        ] as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                $labelHits++;
            }
        }

        // PhilHealth / National-style number formats.
        $hasIdNumberFormat = preg_match('/\b\d{2}-\d{9}-\d\b/', $trimmed) === 1
            || preg_match('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', $trimmed) === 1;

        // Avoid weak words like bare "MEMBER"/"PIN" (too common in non-ID text).
        $hasSecondaryEvidence = $labelHits >= 1
            || $hasIdNumberFormat
            || $this->textLooksLikePersonalIdCard($trimmed, 2)
            || preg_match('/\b(PRECINCT|VIN|REGISTERED\s+VOTER|STUDENT\s+NO|STUDENT\s+NUMBER|GIVEN\s+NAMES?)\b/i', $trimmed) === 1
            || (
                preg_match('/\b(PHILHEALTH|PHIL\s*HEALTH|PHIC)\b/i', $trimmed) === 1
                && preg_match('/\b(MEMBER|PIN)\b/i', $trimmed) === 1
            );

        if ($hasIssuerKeyword && $hasSecondaryEvidence) {
            return true;
        }

        // No issuer keyword: require multiple real field labels (not random capitalized words).
        if ($labelHits >= 3) {
            return true;
        }

        if ($hasIdNumberFormat && $labelHits >= 2) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, float|int>  $scores
     */
    private function textSupportsSelectedIdType(string $text, string $documentType, array $scores): bool
    {
        $score = (float) ($scores[$documentType] ?? 0);
        $hasPhilsys = preg_match('/\b(PHILSYS|PHIL\s*ID|PHILIPPINE\s+IDENTIFICATION|PAMBANSANG\s+IDENTIDAD|ePhilID|PCN|NATIONAL\s+ID)\b/i', $text) === 1;
        $hasPhilhealth = preg_match('/\b(PHILHEALTH|PHIL\s*HEALTH|PHIC)\b/i', $text) === 1
            || preg_match('/\b\d{2}-\d{9}-\d\b/', $text) === 1;
        $hasVoters = preg_match('/\b(COMELEC|COMMISSION\s+ON\s+ELECTIONS|VOTER\'?S?\s+ID|VOTER\s+CERTIFICATION|PRECINCT|VIN)\b/i', $text) === 1;
        $hasSchool = preg_match('/\b(STUDENT\s+ID|SCHOOL\s+ID|STUDENT\s+NO|STUDENT\s+NUMBER|LRN)\b/i', $text) === 1
            || (
                preg_match('/\b(UNIVERSITY|COLLEGE|ACADEMY|INSTITUTE|SCHOOL)\b/i', $text) === 1
                && preg_match('/\b(STUDENT|GRADE|SECTION|COURSE|ID\s*NO)\b/i', $text) === 1
            );
        $hasPhilSysLayout = preg_match('/\b(GIVEN\s+NAMES?|LAST\s+NAME|DATE\s+OF\s+BIRTH)\b/i', $text) === 1
            && preg_match('/\b(SEX|ADDRESS|MARITAL|BLOOD|PCN)\b/i', $text) === 1;

        return match ($documentType) {
            'national_id' => ($score >= 0.40 || $hasPhilsys || (
                preg_match('/\bREPUBLIC\s+OF\s+THE\s+PHILIPPINES\b/i', $text) === 1
                && $hasPhilSysLayout
            ) || $hasPhilSysLayout)
                && ! (($hasPhilhealth || $hasVoters || $hasSchool) && ! $hasPhilsys && $score < 0.50),
            'philhealth_id' => ($score >= 0.40 || $hasPhilhealth)
                && ! (($hasPhilsys || $hasVoters || $hasSchool) && ! $hasPhilhealth && $score < 0.50),
            'voters_id' => ($score >= 0.40 || $hasVoters)
                && ! (($hasPhilsys || $hasPhilhealth || $hasSchool) && ! $hasVoters && $score < 0.50),
            'school_id' => ($score >= 0.40 || $hasSchool)
                && ! (($hasPhilsys || $hasPhilhealth || $hasVoters) && ! $hasSchool && $score < 0.50),
            'other_id' => $score >= 0.40
                || preg_match('/\b(DRIVER\'?S?\s+LICENSE|PASSPORT|PWD\s+ID|SENIOR\s+CITIZEN|BARANGAY\s+ID|POSTAL\s+ID|COMPANY\s+ID|IDENTIFICATION\s+CARD)\b/i', $text) === 1
                || $this->textLooksLikePersonalIdCard($text, 3),
            default => $this->textHasSupportingIdSignal($text),
        };
    }

    /**
     * @param  array<string, mixed>  $frontMeta
     * @param  array<string, mixed>|null  $backMeta
     */
    private function imageLooksLikeIdDocument(array $frontMeta, ?array $backMeta): bool
    {
        // Dimension heuristics alone are not enough to accept random photos.
        // Kept for diagnostics / optional soft checks only.
        $width = (int) ($frontMeta['width'] ?? 0);
        $height = (int) ($frontMeta['height'] ?? 0);
        $bytes = (int) ($frontMeta['bytes'] ?? 0);

        if ($bytes < 8_000) {
            return false;
        }

        if ($width > 0 && $height > 0) {
            $ratio = $width / max(1, $height);

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
     * Final accuracy gate for API + local OCR payloads.
     * Rejects non-ID text, wrong selected type, and weak classifications.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enforceDetectionAccuracy(array $payload, ?string $documentType): array
    {
        if (($payload['validation_error'] ?? false) === true
            && strtolower((string) ($payload['document_detected'] ?? '')) === 'no') {
            $payload['success'] = false;
            $payload['needs_review'] = false;

            return $payload;
        }

        $rawText = trim((string) (
            $payload['raw_text']
            ?? $payload['full_text']
            ?? data_get($payload, 'ocr.raw_text')
            ?? data_get($payload, 'ocr.full_text')
            ?? ''
        ));

        $minConfidence = (float) config('ocr.min_detect_confidence', 0.50);
        $acceptFloor = max(0.40, $minConfidence - 0.05);
        $confidence = (float) ($payload['confidence'] ?? 0);
        $detectedType = trim((string) ($payload['id_type'] ?? $payload['detected_id_type'] ?? 'Unknown'));
        $scores = is_array($payload['scores'] ?? null) ? $payload['scores'] : [];
        $source = (string) ($payload['source'] ?? 'unknown');

        $reject = function (string $message, array $extra = []) use ($payload, $documentType, $detectedType, $confidence, $source): array {
            return array_merge($payload, [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'no',
                'id_type' => $extra['id_type'] ?? (($detectedType !== '' && strcasecmp($detectedType, 'Unknown') !== 0) ? $detectedType : 'Unknown'),
                'detected_id_type' => $extra['detected_id_type'] ?? ($detectedType !== '' ? $detectedType : null),
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => 'low',
                'ocr_status' => $extra['ocr_status'] ?? 'ocr_low_confidence',
                'message' => $message,
                'raw_text' => '',
                'source' => $source,
            ], $extra);
        };

        if ($rawText === '' || ! $this->textHasSupportingIdSignal($rawText)) {
            return $reject(
                'This does not appear to be a valid ID. Please scan or upload a clear photo of your selected ID card (front and back) — random photos are not accepted.',
                ['ocr_status' => 'ocr_empty', 'id_type' => 'Unknown', 'detected_id_type' => null]
            );
        }

        if (is_string($documentType) && $documentType !== ''
            && ! $this->textSupportsSelectedIdType($rawText, $documentType, $scores)) {
            $expectedLabel = $this->documentTypeLabel($documentType);

            return $reject(
                "The uploaded images do not look like a valid {$expectedLabel}. Please upload a clearer front and back of your selected ID — not a random photo."
            );
        }

        $matchesSelected = $this->detectedTypeMatchesSelection($detectedType, $documentType);
        $autoCorrected = false;

        // Unknown detection but text supports selected type → bind to selection.
        if (
            ! $matchesSelected
            && is_string($documentType)
            && $documentType !== ''
            && ($detectedType === '' || strcasecmp($detectedType, 'Unknown') === 0)
        ) {
            $detectedType = $documentType;
            $payload['id_type'] = $documentType;
            $payload['detected_id_type'] = $documentType;
            $matchesSelected = true;
        }

        if (
            is_string($documentType)
            && $documentType !== ''
            && $documentType !== 'other_id'
            && ! $matchesSelected
            && in_array($detectedType, ['national_id', 'philhealth_id', 'voters_id', 'school_id'], true)
        ) {
            $expectedLabel = $this->documentTypeLabel($documentType);
            $detectedLabel = $this->documentTypeLabel($detectedType);

            return array_merge($payload, [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'document_detected' => 'yes',
                'id_type' => $detectedType,
                'detected_id_type' => $detectedType,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => $this->confidenceBand($confidence, $minConfidence),
                'message' => "You selected {$expectedLabel}, but the images look like {$detectedLabel}. Please upload the correct ID or change the document type.",
                'raw_text' => $rawText,
                'auto_corrected' => false,
            ]);
        }

        $selectedScore = (is_string($documentType) && isset($scores[$documentType]))
            ? (float) $scores[$documentType]
            : 0.0;

        if ($confidence < $acceptFloor && $selectedScore < $acceptFloor) {
            return $reject(
                'We could not confidently identify this as your selected ID. Please retake clearer front and back photos.'
            );
        }

        // Passed gates — never leave document_detected empty/no on a soft pass.
        $payload['raw_text'] = $rawText;
        $payload['document_detected'] = 'yes';
        $payload['validation_error'] = false;
        $payload['auto_corrected'] = false;
        $payload['confidence_band'] = $this->confidenceBand($confidence, $minConfidence);

        if ($confidence < $minConfidence) {
            $payload['success'] = false;
            $payload['needs_review'] = true;
            if (empty($payload['message'])) {
                $payload['message'] = 'We could not confidently read the ID. Please retake the photo or upload a clearer image.';
            }
        } elseif (is_string($documentType) && $documentType !== '' && $documentType !== 'other_id'
            && ! $matchesSelected && ! $autoCorrected) {
            // Remaining mismatches (e.g. other_id detected vs selected) — hard reject.
            $expectedLabel = $this->documentTypeLabel($documentType);

            return $reject(
                "The uploaded images do not look like a valid {$expectedLabel}. Please upload a clearer front and back of your selected ID — not a random photo."
            );
        } else {
            $payload['success'] = (bool) ($payload['success'] ?? false) || ($confidence >= $minConfidence && $matchesSelected);
            if ($payload['success']) {
                $payload['needs_review'] = false;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isUsableDetectionResult(array $payload): bool
    {
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
            || str_contains($message, 'invalid ocr api response')
        ) {
            return false;
        }

        $idType = trim((string) ($payload['id_type'] ?? $payload['detected_id_type'] ?? ''));
        $confidence = (float) ($payload['confidence'] ?? 0);
        $rawText = trim((string) (
            $payload['raw_text']
            ?? $payload['full_text']
            ?? data_get($payload, 'ocr.raw_text')
            ?? data_get($payload, 'ocr.full_text')
            ?? ''
        ));

        // Transport / empty failures should fall through to local OCR.
        if (($idType === '' || strcasecmp($idType, 'Unknown') === 0) && $confidence <= 0 && $rawText === ''
            && ! array_key_exists('validation_error', $payload)) {
            return false;
        }

        // Structured API payload (including hard rejects) can be evaluated by accuracy gates.
        return array_key_exists('id_type', $payload)
            || array_key_exists('validation_error', $payload)
            || array_key_exists('document_detected', $payload)
            || $rawText !== ''
            || (($payload['success'] ?? false) === true);
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
            'other_id' => 0.0,
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

        if (preg_match('/\b(STUDENT\s+ID|SCHOOL\s+ID)\b/i', $text)) {
            $scores['school_id'] += 0.65;
        }
        if (preg_match('/\b(UNIVERSITY|COLLEGE|ACADEMY|INSTITUTE)\b/i', $text)
            && preg_match('/\b(STUDENT|GRADE|SECTION|COURSE|CAMPUS)\b/i', $text)) {
            $scores['school_id'] += 0.35;
        }
        if (preg_match('/\b(STUDENT\s+NO|STUDENT\s+NUMBER|LRN)\b/i', $text)) {
            $scores['school_id'] += 0.25;
        }

        if (preg_match('/\b(DRIVER\'?S?\s+LICENSE|PASSPORT|PWD\s+ID|SENIOR\s+CITIZEN|BARANGAY\s+ID|POSTAL\s+ID|COMPANY\s+ID)\b/i', $text)) {
            $scores['other_id'] += 0.55;
        }

        if (preg_match('/\b(GIVEN\s+NAMES?|LAST\s+NAME|DATE\s+OF\s+BIRTH|ADDRESS|SEX)\b/i', $text)) {
            foreach (['national_id', 'philhealth_id', 'voters_id', 'school_id'] as $key) {
                if ($scores[$key] > 0) {
                    $scores[$key] += 0.05;
                }
            }
        }

        // Personal ID card layout without strong rival keywords → likely PhilSys/National ID.
        // Require PhilSys keyword (or Republic + strong field labels) so random OCR is not national_id.
        if ($this->textLooksLikePersonalIdCard($text, 3)) {
            $rival = max($scores['philhealth_id'], $scores['voters_id'], $scores['school_id'], $scores['other_id']);
            $hasPhilsysKeyword = preg_match('/\b(PHILSYS|PHIL\s*ID|PHILIPPINE\s+IDENTIFICATION|PCN|ePhilID|NATIONAL\s+ID)\b/i', $text) === 1;
            if ($rival < 0.45 && $hasPhilsysKeyword) {
                $scores['national_id'] = max($scores['national_id'], 0.72);
            } elseif (
                $rival < 0.35
                && $hasPhilsysKeyword === false
                && preg_match('/\bREPUBLIC\s+OF\s+THE\s+PHILIPPINES\b/i', $text)
                && $this->textLooksLikePersonalIdCard($text, 4)
            ) {
                $scores['national_id'] = max($scores['national_id'], 0.48);
            }
        }

        arsort($scores);
        $idType = (string) array_key_first($scores);
        $confidence = round((float) $scores[$idType], 2);
        $textLength = mb_strlen(trim($text));
        $alpha = preg_match_all('/[A-Za-z]/', $text) ?: 0;

        // Low-confidence / other_id: prefer selected type only with real score evidence.
        if ($confidence < 0.35 || $idType === 'other_id') {
            if (is_string($expectedType) && isset($scores[$expectedType]) && $expectedType !== 'other_id') {
                $expectedScore = (float) $scores[$expectedType];
                $rival = 0.0;
                foreach ($scores as $key => $score) {
                    if ($key === $expectedType) {
                        continue;
                    }
                    $rival = max($rival, (float) $score);
                }

                if ($expectedScore >= 0.35 && $expectedScore + 0.08 >= $rival) {
                    $idType = $expectedType;
                    $confidence = max($expectedScore, min(0.58, $confidence));
                }
            } elseif ($this->textLooksLikePersonalIdCard($text, 3)) {
                $rival = max($scores['philhealth_id'], $scores['voters_id'], $scores['school_id']);
                if ($rival >= 0.45) {
                    foreach (['voters_id', 'philhealth_id', 'school_id'] as $key) {
                        if ($scores[$key] >= $rival) {
                            $idType = $key;
                            $confidence = max(0.55, (float) $scores[$key]);
                            break;
                        }
                    }
                } elseif (preg_match('/\b(PHILSYS|PHIL\s*ID|PCN|ePhilID|NATIONAL\s+ID)\b/i', $text)) {
                    $idType = 'national_id';
                    $confidence = max(0.58, (float) $scores['national_id'], $confidence);
                } else {
                    $idType = 'Unknown';
                    $confidence = max($confidence, 0.2);
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

        // Keep selected type only when its own score is competitive with the top label
        // (do not force selected type on weak OCR — that accepts wrong / random uploads).
        if (
            is_string($expectedType)
            && isset($scores[$expectedType])
            && $expectedType !== 'other_id'
            && $scores[$expectedType] >= 0.40
            && (($scores[$expectedType] + 0.12) >= $confidence)
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

    private function textLooksLikePersonalIdCard(string $text, int $minSignals = 2): bool
    {
        $signals = 0;

        if (preg_match('/\b(DATE\s+OF\s+BIRTH|BIRTHDAY|BIRTH\s*DATE|DOB)\b/i', $text)) {
            $signals++;
        } elseif (preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+\d{1,2},?\s+\d{4}\b/i', $text)) {
            $signals++;
        }

        if (preg_match('/\b(SEX|KASARIAN)\b/i', $text)
            || (preg_match('/\b(MALE|FEMALE|LALAKE|BABAE)\b/i', $text) && preg_match('/\b(NAME|BIRTH|ADDRESS|ID)\b/i', $text))) {
            $signals++;
        }

        if (preg_match('/\b(ADDRESS|TIRAHAN)\b/i', $text)
            || preg_match('/\b(BRGY\.?|BARANGAY)\b/i', $text)) {
            $signals++;
        }

        // Count only explicit name field labels — not random ALL-CAPS words.
        if (preg_match('/\b(GIVEN\s+NAMES?|MIDDLE\s+NAME|LAST\s+NAME|FIRST\s+NAME|SURNAME|APELYIDO)\b/i', $text)) {
            $signals++;
        }

        if (preg_match('/\b(PHILSYS|PHIL\s*ID|REPUBLIC\s+OF\s+THE\s+PHILIPPINES|PCN|NATIONAL\s+ID)\b/i', $text)) {
            $signals++;
        }

        return $signals >= $minSignals;
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

        // PhilSys / National ID often lists LAST NAME then GIVEN NAMES (+ MIDDLE NAME).
        if (
            preg_match('/\b(?:LAST\s+NAME|SURNAME|APELYIDO)\s*[:\-]?\s*([A-Z][A-Za-z.\-]+)/i', $flat, $last)
            && preg_match(
                '/\b(?:GIVEN\s+NAMES?|FIRST\s+NAME|MGA\s+PANGALAN)\s*[:\-]?\s*([A-Z][A-Za-z.\-]+(?:\s+[A-Z][A-Za-z.\-]+){0,3})(?=\s+(?:MIDDLE\s+NAME|DATE\s+OF\s+BIRTH|SEX|MALE|FEMALE|ADDRESS|BLOOD|$))/i',
                $flat,
                $given,
            )
        ) {
            $middle = '';
            if (preg_match(
                '/\bMIDDLE\s+NAME\s*[:\-]?\s*([A-Z][A-Za-z.\-]+)(?=\s+(?:DATE\s+OF\s+BIRTH|SEX|MALE|FEMALE|ADDRESS|BLOOD|$))/i',
                $flat,
                $mid,
            )) {
                $middle = ' '.trim($mid[1]);
            }

            return trim($given[1].$middle.' '.$last[1]);
        }

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
            if (! preg_match('/\b(DATE|BIRTH|ADDRESS|SEX|MALE|FEMALE|REPUBLIC|PHILIPPINES)\b/i', $candidate)) {
                return $candidate;
            }
        }

        // Structured OCR name parsing (handles noisy multi-line IDs better than a single regex).
        $bestLine = $this->nameMatcher->extractBestNameLine($text);
        if (is_string($bestLine) && trim($bestLine) !== '') {
            return trim($bestLine);
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
