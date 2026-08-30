<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini vision ID verification for KK Profiling Step 2.
 *
 * Primary provider when OCR_PROVIDER=gemini (Gemini 2.5 Flash).
 *
 * verification_status and document_detected are separate:
 * - document_detected true/"yes"  = analyzed and is an ID
 * - document_detected false/"no"  = analyzed and is NOT an ID
 * - document_detected null        = could not determine (API/service/quality failure)
 */
class IdVerificationAiService
{
    public function isEnabled(): bool
    {
        if (! (bool) config('ocr.gemini.enabled', true)) {
            return false;
        }

        return trim((string) config('ocr.gemini.api_key', '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeIdPair(
        string $frontPath,
        string $backPath,
        ?string $documentType = null,
        array $registrationFields = [],
    ): array {
        unset($registrationFields); // Never send profile/PII into the Gemini prompt.

        if (! $this->isEnabled()) {
            return $this->undeterminedResult(
                'unavailable',
                'ID verification is temporarily unavailable. Please try again.',
                'ocr_failed',
            );
        }

        if (! is_file($frontPath) || ! is_file($backPath)) {
            return $this->undeterminedResult(
                'invalid_image',
                'Front or back image file not found.',
                'invalid_image',
            );
        }

        $started = microtime(true);
        $pairHash = $this->pairHash($frontPath, $backPath, $documentType);
        $analyzeBack = (bool) config('ocr.gemini.analyze_back', false);
        $provider = 'gemini';
        $model = (string) config('ocr.gemini.model', 'gemini-3.6-flash');

        try {
            $parts = [
                ['text' => $this->buildMinimalPrompt($documentType, $analyzeBack)],
                $this->inlineImagePart($frontPath),
            ];

            if ($analyzeBack) {
                $parts[] = $this->inlineImagePart($backPath);
            }

            $maxTokens = max(180, min(1024, (int) config('ocr.gemini.max_output_tokens', 512)));
            $thinkingLevel = strtoupper(trim((string) config('ocr.gemini.thinking_level', 'MINIMAL')));
            if (! in_array($thinkingLevel, ['MINIMAL', 'LOW', 'MEDIUM', 'HIGH'], true)) {
                $thinkingLevel = 'MINIMAL';
            }

            $generationConfig = [
                'maxOutputTokens' => $maxTokens,
                'responseMimeType' => 'application/json',
                'thinkingConfig' => [
                    'thinkingLevel' => $thinkingLevel,
                ],
            ];

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => $generationConfig,
            ];

            $response = $this->requestGemini($payload, $model);

            if ($response === null || ! $response->successful()) {
                $category = $this->classifyHttpFailure($response);
                $status = $category === 'auth_failure' ? 'error' : 'unavailable';

                Log::warning('ID verification AI HTTP failure', [
                    'provider' => $provider,
                    'model' => $model,
                    'error_category' => $category,
                    'status' => $response?->status(),
                    'pair_hash' => $pairHash,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                ]);

                return $this->undeterminedResult(
                    $status,
                    'ID verification is temporarily unavailable. Please try again.',
                    'ocr_failed',
                    [
                        'error_category' => $category,
                        'pair_hash' => $pairHash,
                    ],
                );
            }

            $rawContent = $this->extractGeminiText($response);
            $parsed = $this->decodeJsonObject($rawContent);

            if ($parsed === null) {
                Log::warning('ID verification AI invalid response', [
                    'provider' => $provider,
                    'model' => $model,
                    'error_category' => 'invalid_response',
                    'content_length' => mb_strlen($rawContent),
                    'pair_hash' => $pairHash,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                ]);

                return $this->undeterminedResult(
                    'invalid_response',
                    'Unable to analyze the document. Please try again.',
                    'ocr_failed',
                    ['pair_hash' => $pairHash],
                );
            }

            $result = $this->normalizeResult($parsed, $documentType, $analyzeBack);
            $result['pair_hash'] = $pairHash;
            $result['analyze_back'] = $analyzeBack;
            $result['is_philippine_id'] = $parsed['is_philippine_id'] ?? null;
            $result['image_quality'] = $this->normalizeImageQuality($parsed['image_quality'] ?? null);
            $result['expiry_date'] = $this->nullableString(
                $parsed['expiry_date'] ?? ($parsed['expiration_date'] ?? null)
            );

            Log::info('ID verification AI completed', [
                'provider' => $provider,
                'model' => $model,
                'verification_status' => $result['verification_status'] ?? null,
                'document_detected' => $result['document_detected'] ?? null,
                'image_quality' => $result['image_quality'] ?? null,
                'pair_hash' => $pairHash,
                'analyze_back' => $analyzeBack,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            return $result;
        } catch (ConnectionException $e) {
            Log::warning('ID verification AI connection failure', [
                'provider' => $provider,
                'error_category' => 'timeout_or_connection',
                'pair_hash' => $pairHash,
            ]);

            return $this->undeterminedResult(
                'unavailable',
                'ID verification is temporarily unavailable. Please try again.',
                'ocr_failed',
                [
                    'error_category' => 'timeout_or_connection',
                    'pair_hash' => $pairHash,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('ID verification AI exception', [
                'provider' => $provider,
                'error_category' => 'exception',
                'error' => $e->getMessage(),
                'pair_hash' => $pairHash,
            ]);

            return $this->undeterminedResult(
                'error',
                'ID verification is temporarily unavailable. Please try again.',
                'ocr_failed',
                [
                    'error_category' => 'exception',
                    'pair_hash' => $pairHash,
                ],
            );
        }
    }

    private function buildMinimalPrompt(?string $documentType, bool $analyzeBack): string
    {
        $expected = $this->documentTypeLabel($documentType);
        $sides = $analyzeBack ? 'FRONT then BACK images' : 'FRONT image only';

        return 'Philippine ID checker. Analyze the '.$sides.'. User selected: '.$expected.'. '
            .'FAST REJECT: if not a clear physical ID card, return '
            .'{"document_detected":false,"is_philippine_id":false,"id_type":"Unknown","full_name":null,'
            .'"given_name":null,"middle_name":null,"surname":null,"date_of_birth":null,"sex":null,"id_number":null,'
            .'"expiry_date":null,"address":null,"image_quality":"unreadable","confidence":0,'
            .'"front_is_id":false,"back_is_id":false,"unreadable":true}. '
            .'If it IS an ID, return JSON only: '
            .'{"document_detected":true,"is_philippine_id":true,'
            .'"id_type":"national_id|philhealth_id|voters_id|school_id|drivers_license|passport|umid|sss_id|postal_id|prc_id|tin_id|senior_citizen_id|other_id|Unknown",'
            .'"full_name":null,"given_name":null,"middle_name":null,"surname":null,'
            .'"date_of_birth":null,"sex":null,"id_number":null,"expiry_date":null,"address":null,'
            .'"image_quality":"good|acceptable|poor|unreadable","confidence":0,'
            .'"front_is_id":true,"back_is_id":true,"unreadable":false}. '
            .'Name = cardholder only (any print order). Never use university/agency headers as names. '
            .'If an address is readable, put the full printed address in address (include barangay/municipality/province/region when shown); else null. '
            .'If sex/gender is printed (M/F/Male/Female), put Male or Female in sex; else null. '
            .'DOB YYYY-MM-DD or null. confidence 0-100.';
    }

    public function pairHash(string $frontPath, string $backPath, ?string $documentType = null): string
    {
        // Fast content fingerprint only — do not re-encode images just for the cache key.
        $frontHash = is_file($frontPath) ? ((string) (@hash_file('sha256', $frontPath) ?: '')) : '';
        $backHash = is_file($backPath) ? ((string) (@hash_file('sha256', $backPath) ?: '')) : '';

        return hash('sha256', $frontHash.'|'.$backHash.'|'.(string) $documentType);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function undeterminedResult(
        string $verificationStatus,
        string $message,
        string $ocrStatus,
        array $extra = [],
    ): array {
        return array_merge([
            'success' => false,
            'validation_error' => true,
            'needs_review' => false,
            'verification_status' => $verificationStatus,
            'document_detected' => null,
            'is_philippine_id' => null,
            'id_type' => 'Unknown',
            'detected_id_type' => null,
            'confidence' => 0,
            'confidence_band' => null,
            'ocr_status' => $ocrStatus,
            'message' => $message,
            'source' => 'gemini',
            'raw_text' => '',
            'data' => null,
        ], $extra);
    }

    private function classifyHttpFailure(?Response $response): string
    {
        if ($response === null) {
            return 'no_response';
        }

        $status = $response->status();
        $payload = $response->json();
        $code = strtolower((string) (
            data_get($payload, 'error.status')
            ?: data_get($payload, 'error.code')
            ?: ''
        ));
        $message = strtolower((string) data_get($payload, 'error.message', ''));

        if (
            $status === 429
            || str_contains($code, 'resource_exhausted')
            || str_contains($message, 'quota')
            || str_contains($message, 'rate')
        ) {
            return 'rate_limit_or_quota';
        }

        if (
            in_array($status, [401, 403], true)
            || str_contains($code, 'permission')
            || str_contains($message, 'api key')
            || str_contains($message, 'api_key')
        ) {
            return 'auth_failure';
        }

        if (in_array($status, [408, 504], true) || str_contains($message, 'timeout')) {
            return 'timeout';
        }

        if (in_array($status, [500, 502, 503], true)) {
            return 'provider_error';
        }

        if ($status === 404 || str_contains($message, 'not found')) {
            return 'model_unavailable';
        }

        return 'http_'.$status;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestGemini(array $payload, string $model): ?Response
    {
        $timeout = max(8, min(25, (int) config('ocr.gemini.timeout', 18)));
        $maxRateRetries = max(1, min(2, (int) config('ocr.gemini.rate_limit_max_retries', 1)));
        $apiKey = (string) config('ocr.gemini.api_key');
        $baseUrl = rtrim((string) config('ocr.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $models = [$model];
        // Fallbacks only when primary model is unavailable — chaining on every failure makes wrong-image checks very slow.
        $fallbacks = config('ocr.gemini.fallback_models', []);
        if (is_array($fallbacks)) {
            foreach ($fallbacks as $fallback) {
                $fallback = trim((string) $fallback);
                if ($fallback !== '' && ! in_array($fallback, $models, true)) {
                    $models[] = $fallback;
                }
            }
        }

        $lastResponse = null;

        foreach ($models as $candidate) {
            if (! preg_match('/^[a-zA-Z0-9._-]+$/', $candidate)) {
                continue;
            }

            $url = $baseUrl.'/models/'.$candidate.':generateContent';
            $rateAttempt = 0;
            // One primary attempt; only retry briefly on 429.
            $maxAttempts = $maxRateRetries;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $response = Http::withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                        ->acceptJson()
                        ->timeout($timeout)
                        ->connectTimeout(4)
                        ->post($url, $payload);
                } catch (ConnectionException $e) {
                    Log::warning('ID verification AI connection attempt failed', [
                        'provider' => 'gemini',
                        'model' => $candidate,
                        'attempt' => $attempt,
                    ]);
                    if ($attempt < $maxAttempts) {
                        usleep(200_000);
                        continue;
                    }
                    throw $e;
                }

                $lastResponse = $response;

                if ($response->successful()) {
                    if ($candidate !== $model) {
                        Log::info('ID verification AI used fallback model', [
                            'provider' => 'gemini',
                            'requested_model' => $model,
                            'model' => $candidate,
                        ]);
                    }

                    return $response;
                }

                $category = $this->classifyHttpFailure($response);

                Log::warning('ID verification AI model attempt failed', [
                    'provider' => 'gemini',
                    'model' => $candidate,
                    'status' => $response->status(),
                    'error_category' => $category,
                    'attempt' => $attempt,
                    'rate_attempt' => $rateAttempt,
                    'api_message' => substr((string) data_get($response->json(), 'error.message', ''), 0, 220),
                ]);

                if ($category === 'model_unavailable') {
                    break; // next fallback model only
                }

                if ($category === 'rate_limit_or_quota' && $attempt < $maxAttempts) {
                    $rateAttempt++;
                    usleep(400_000);
                    continue;
                }

                // Do not cascade through fallbacks on timeout/auth/provider errors — fail fast.
                return $response;
            }
        }

        return $lastResponse;
    }

    private function extractGeminiText(Response $response): string
    {
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            $text = trim((string) ($part['text'] ?? ''));
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return trim(implode("\n", $chunks));
    }

    /**
     * @return array{inline_data: array{mime_type: string, data: string}}
     */
    private function inlineImagePart(string $path): array
    {
        $maxEdge = max(420, min(900, (int) config('ocr.gemini.image_max_edge', 640)));
        $quality = max(45, min(70, (int) config('ocr.gemini.image_jpeg_quality', 55)));
        $bytes = $this->shrinkJpeg($path, $maxEdge, $quality);

        if ($bytes === null) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new \RuntimeException('Unable to read image for ID verification.');
            }
            $bytes = $raw;
        }

        return [
            'inline_data' => [
                'mime_type' => 'image/jpeg',
                'data' => base64_encode($bytes),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeResult(array $parsed, ?string $documentType, bool $analyzeBack = true): array
    {
        // Accept Gemini schema aliases.
        if (! isset($parsed['birthdate']) && isset($parsed['date_of_birth'])) {
            $parsed['birthdate'] = $parsed['date_of_birth'];
        }

        $documentDetected = $this->normalizeDocumentDetectedFlag($parsed['document_detected'] ?? null);
        $isPhilippineId = $this->normalizeDocumentDetectedFlag($parsed['is_philippine_id'] ?? null);
        $imageQuality = $this->normalizeImageQuality($parsed['image_quality'] ?? null);
        $unreadable = (bool) ($parsed['unreadable'] ?? false)
            || $imageQuality === 'unreadable';

        $isValid = array_key_exists('is_valid_id', $parsed)
            ? (bool) $parsed['is_valid_id']
            : ($documentDetected === true);

        if ($documentDetected === true && $isPhilippineId === false) {
            // Still an ID document, but not PH — treat as non-matching for this flow.
            $isValid = false;
        }

        $confidenceRaw = $parsed['confidence'] ?? 0;
        $confidence = is_numeric($confidenceRaw) ? (float) $confidenceRaw : 0.0;
        if ($confidence > 1.0) {
            $confidence = min(1.0, $confidence / 100);
        }
        $confidence = max(0, min(1, $confidence));

        $idType = $this->normalizeIdType((string) ($parsed['id_type'] ?? 'Unknown'));
        $rawText = trim((string) ($parsed['raw_text'] ?? ''));
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.50);

        $frontIsId = (bool) ($parsed['front_is_id'] ?? ($documentDetected === true));
        $backIsId = $analyzeBack
            ? (bool) ($parsed['back_is_id'] ?? ($documentDetected === true))
            : true;
        $sameDocument = (bool) ($parsed['same_document'] ?? true);

        if ($documentDetected === true && (! $frontIsId || ! $backIsId || ($analyzeBack && ! $sameDocument))) {
            $documentDetected = false;
            $isValid = false;
        }

        // Also merge readable address into raw_text so locality checks can use OCR haystack.
        $address = $this->nullableString($parsed['address'] ?? null);
        if ($address !== null && $address !== '' && $rawText === '') {
            $rawText = $address;
        } elseif ($address !== null && $address !== '' && ! str_contains(strtoupper($rawText), strtoupper($address))) {
            $rawText = trim($rawText."\n".$address);
        }

        $sex = $this->normalizeSex($parsed['sex'] ?? ($parsed['gender'] ?? null));

        $data = [
            'id_type' => $idType !== 'Unknown' ? $idType : null,
            'full_name' => $this->sanitizePersonName($parsed['full_name'] ?? null),
            'date_of_birth' => $this->nullableString($parsed['birthdate'] ?? null),
            'sex' => $sex,
            'id_number' => $this->nullableString($parsed['id_number'] ?? null),
            'expiry_date' => $this->nullableString($parsed['expiry_date'] ?? ($parsed['expiration_date'] ?? null)),
            'address' => $address,
            'image_quality' => $imageQuality,
            'confidence' => (int) round($confidence * 100),
        ];

        $parsed['full_name'] = $data['full_name'];
        $parsed['given_name'] = $this->sanitizePersonName($parsed['given_name'] ?? null);
        $parsed['middle_name'] = $this->sanitizePersonName($parsed['middle_name'] ?? null);
        $parsed['surname'] = $this->sanitizePersonName($parsed['surname'] ?? null);

        // If full_name was rejected as institutional text, rebuild from name parts when available.
        if ($data['full_name'] === null) {
            $joined = trim(implode(' ', array_filter([
                $parsed['given_name'] ?? null,
                $parsed['middle_name'] ?? null,
                $parsed['surname'] ?? null,
            ])));
            $data['full_name'] = $this->sanitizePersonName($joined !== '' ? $joined : null);
            $parsed['full_name'] = $data['full_name'];
        }

        if ($unreadable || $documentDetected === null || $imageQuality === 'unreadable') {
            return array_merge($this->undeterminedResult(
                'invalid_image',
                $this->nullableString($parsed['message'] ?? null)
                    ?: 'We couldn\'t reliably read this document. Please upload a clearer image.',
                'invalid_image',
            ), [
                'verification_status' => 'invalid_image',
                'id_type' => $idType,
                'detected_id_type' => $idType !== 'Unknown' ? $idType : null,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => $confidence >= 0.7 ? 'medium' : 'low',
                'full_name' => $data['full_name'],
                'birthdate' => $data['date_of_birth'],
                'sex' => $sex,
                'address' => $this->nullableString($parsed['address'] ?? null),
                'id_number' => $data['id_number'],
                'expiry_date' => $data['expiry_date'],
                'image_quality' => $imageQuality,
                'is_philippine_id' => $isPhilippineId,
                'raw_text' => $rawText,
                'data' => $data,
            ]);
        }

        if ($documentDetected === false || ! $isValid) {
            $expectedLabel = $this->documentTypeLabel($documentType);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'verification_status' => 'success',
                'document_detected' => 'no',
                'is_philippine_id' => $isPhilippineId === true ? true : false,
                'id_type' => $idType,
                'detected_id_type' => $idType !== 'Unknown' ? $idType : null,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => $confidence >= 0.7 ? 'medium' : 'low',
                'ocr_status' => 'ocr_low_confidence',
                'full_name' => $data['full_name'],
                'given_name' => $this->nullableString($parsed['given_name'] ?? null),
                'middle_name' => $this->nullableString($parsed['middle_name'] ?? null),
                'surname' => $this->nullableString($parsed['surname'] ?? null),
                'birthdate' => $data['date_of_birth'],
                'sex' => $sex,
                'address' => $this->nullableString($parsed['address'] ?? null),
                'id_number' => $data['id_number'],
                'expiry_date' => $data['expiry_date'],
                'image_quality' => $imageQuality,
                'raw_text' => $rawText,
                'message' => $this->nullableString($parsed['message'] ?? null)
                    ?: "We could not verify a clear {$expectedLabel}. Please retake front and back photos with better lighting.",
                'source' => 'gemini',
                'data' => $data,
            ];
        }

        if ($confidence < $minConfidence || in_array($imageQuality, ['poor'], true)) {
            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'verification_status' => 'invalid_image',
                'document_detected' => null,
                'is_philippine_id' => $isPhilippineId,
                'id_type' => $idType,
                'detected_id_type' => $idType !== 'Unknown' ? $idType : null,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => 'low',
                'ocr_status' => 'ocr_low_confidence',
                'full_name' => $data['full_name'],
                'birthdate' => $data['date_of_birth'],
                'sex' => $sex,
                'address' => $this->nullableString($parsed['address'] ?? null),
                'id_number' => $data['id_number'],
                'expiry_date' => $data['expiry_date'],
                'image_quality' => $imageQuality,
                'raw_text' => $rawText,
                'message' => $this->nullableString($parsed['message'] ?? null)
                    ?: 'We couldn\'t reliably read this document. Please upload a clearer image.',
                'source' => 'gemini',
                'data' => $data,
            ];
        }

        // Always keep the AI-detected type for mismatch checks against the user's selection.
        $finalType = $idType !== 'Unknown'
            ? $idType
            : ((is_string($documentType) && $documentType !== '') ? $documentType : 'Unknown');
        $autoCorrected = false;

        $success = $confidence >= max(0.62, $minConfidence);
        $needsReview = ! $success || $confidence < 0.8 || $imageQuality === 'acceptable';
        $data['id_type'] = $finalType;

        // Wrong selected type vs AI type → hard fail with clear message (do not soft-pass).
        if (
            is_string($documentType)
            && $documentType !== ''
            && $documentType !== 'other_id'
            && $idType !== 'Unknown'
            && $idType !== $documentType
        ) {
            $expectedLabel = $this->documentTypeLabel($documentType);
            $detectedLabel = $this->documentTypeLabel($idType);

            return [
                'success' => false,
                'validation_error' => true,
                'needs_review' => false,
                'verification_status' => 'success',
                'document_detected' => 'yes',
                'is_philippine_id' => $isPhilippineId !== false,
                'id_type' => $idType,
                'detected_id_type' => $idType,
                'expected_id_type' => $documentType,
                'confidence' => $confidence,
                'confidence_band' => $confidence >= 0.8 ? 'high' : ($confidence >= 0.6 ? 'medium' : 'low'),
                'ocr_status' => 'ocr_low_confidence',
                'full_name' => $data['full_name'],
                'given_name' => $this->nullableString($parsed['given_name'] ?? null),
                'middle_name' => $this->nullableString($parsed['middle_name'] ?? null),
                'surname' => $this->nullableString($parsed['surname'] ?? null),
                'birthdate' => $data['date_of_birth'],
                'sex' => $sex,
                'address' => $this->nullableString($parsed['address'] ?? null),
                'id_number' => $data['id_number'],
                'expiry_date' => $data['expiry_date'],
                'image_quality' => $imageQuality,
                'raw_text' => $rawText,
                'auto_corrected' => false,
                'message' => "You selected {$expectedLabel}, but the images look like {$detectedLabel}. Please upload the correct ID or change the document type.",
                'source' => 'gemini',
                'data' => $data,
                'ocr' => [
                    'engine' => 'gemini',
                    'model' => config('ocr.gemini.model'),
                    'raw_text' => $rawText,
                ],
            ];
        }

        return [
            'success' => $success,
            'validation_error' => false,
            'needs_review' => $needsReview,
            'verification_status' => 'success',
            'document_detected' => 'yes',
            'is_philippine_id' => $isPhilippineId !== false,
            'id_type' => $finalType,
            'detected_id_type' => $finalType,
            'expected_id_type' => $documentType,
            'confidence' => $confidence,
            'confidence_band' => $confidence >= 0.8 ? 'high' : ($confidence >= 0.6 ? 'medium' : 'low'),
            'ocr_status' => $success ? 'ocr_success' : 'ocr_low_confidence',
            'full_name' => $data['full_name'],
            'given_name' => $this->nullableString($parsed['given_name'] ?? null),
            'middle_name' => $this->nullableString($parsed['middle_name'] ?? null),
            'surname' => $this->nullableString($parsed['surname'] ?? null),
            'birthdate' => $data['date_of_birth'],
            'sex' => $sex,
            'address' => $this->nullableString($parsed['address'] ?? null),
            'id_number' => $data['id_number'],
            'expiry_date' => $data['expiry_date'],
            'image_quality' => $imageQuality,
            'raw_text' => $rawText,
            'auto_corrected' => $autoCorrected,
            'message' => $this->nullableString($parsed['message'] ?? null)
                ?: ($success
                    ? 'ID detected.'
                    : 'ID detected with medium confidence.'),
            'source' => 'gemini',
            'data' => $data,
            'ocr' => [
                'engine' => 'gemini',
                'model' => config('ocr.gemini.model'),
                'raw_text' => $rawText,
            ],
        ];
    }

    private function normalizeDocumentDetectedFlag(mixed $value): ?bool
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

    private function normalizeImageQuality(mixed $value): ?string
    {
        $raw = strtolower(trim((string) ($value ?? '')));
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            'good', 'acceptable', 'poor', 'unreadable' => $raw,
            'ok', 'clear', 'high' => 'good',
            'fair', 'medium' => 'acceptable',
            'bad', 'blurry', 'low' => 'poor',
            default => null,
        };
    }

    private function shrinkJpeg(string $path, int $max = 1024, int $quality = 70): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        $image = $this->applyExifOrientation($image, $path);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height, 1));
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newW, $newH);
        if ($resized === false) {
            imagedestroy($image);

            return null;
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($resized, null, max(50, min(85, $quality)));
        $out = ob_get_clean();
        imagedestroy($resized);

        return is_string($out) ? $out : null;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function applyExifOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $image;
        }

        $mime = @mime_content_type($path) ?: '';
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/tiff'], true) && ! preg_match('/\.(jpe?g|tiff?)$/i', $path)) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation <= 1) {
            return $image;
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $content = preg_replace('/```(?:json)?\s*([\s\S]*?)```/i', '$1', $content) ?? $content;
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeIdType(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(["'", '"'], '', $value);

        $map = [
            'national_id' => 'national_id',
            'philid' => 'national_id',
            'phil id' => 'national_id',
            'philsys' => 'national_id',
            'philippine national id' => 'national_id',
            'philippine identification' => 'national_id',
            'philhealth_id' => 'philhealth_id',
            'philhealth' => 'philhealth_id',
            'voters_id' => 'voters_id',
            'voter id' => 'voters_id',
            'voters id' => 'voters_id',
            'school_id' => 'school_id',
            'school id' => 'school_id',
            'drivers_license' => 'other_id',
            'driver license' => 'other_id',
            "driver's license" => 'other_id',
            'passport' => 'other_id',
            'umid' => 'other_id',
            'sss_id' => 'other_id',
            'sss' => 'other_id',
            'postal_id' => 'other_id',
            'postal' => 'other_id',
            'prc_id' => 'other_id',
            'prc' => 'other_id',
            'tin_id' => 'other_id',
            'tin' => 'other_id',
            'senior_citizen_id' => 'other_id',
            'other_id' => 'other_id',
            'other philippine government id' => 'other_id',
            'other' => 'other_id',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        return in_array($value, ['national_id', 'philhealth_id', 'voters_id', 'school_id', 'other_id'], true)
            ? $value
            : 'Unknown';
    }

    private function documentTypeLabel(?string $documentType): string
    {
        return match ($documentType) {
            'national_id' => 'PhilSys / National ID',
            'philhealth_id' => 'PhilHealth ID',
            'voters_id' => "Voter's ID",
            'school_id' => 'School ID',
            'other_id' => 'Supporting ID',
            default => 'government or school ID',
        };
    }

    private function normalizeSex(mixed $value): ?string
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Keep person names only — reject university/government header OCR junk.
     */
    private function sanitizePersonName(mixed $value): ?string
    {
        $name = $this->nullableString($value);
        if ($name === null) {
            return null;
        }

        $upper = strtoupper($name);
        $blockedFragments = [
            'REPUBLIC OF THE PHILIPPINES',
            'REPUBLIC OF THE',
            'PROVINCIAL GOVERNMENT',
            'LOCAL GOVERNMENT',
            'UNIVERSITY',
            'COLLEGE',
            'SCHOOL',
            'BARANGAY',
            'PHILSYS',
            'PHILHEALTH',
            'DRIVER',
            'PASSPORT',
            'DEPARTMENT OF',
            'COMMISSION ON',
            'NATIONAL ID',
            'STUDENT NO',
            'COURSE',
            'PROGRAM',
        ];

        foreach ($blockedFragments as $fragment) {
            if (str_contains($upper, $fragment)) {
                return null;
            }
        }

        // Too short / mostly non-letters → not a usable person name.
        $letters = preg_replace('/[^A-Za-z]/', '', $name) ?? '';
        if (strlen($letters) < 5) {
            return null;
        }

        // Require at least two name tokens (e.g. FIRST LAST or LAST, FIRST).
        $tokens = preg_split('/[\s,]+/', $name) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));
        if (count($tokens) < 2) {
            return null;
        }

        return $name;
    }
}
