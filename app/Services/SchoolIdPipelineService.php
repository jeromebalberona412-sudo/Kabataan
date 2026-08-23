<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SchoolIdPipelineService
{
    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>|null
     */
    public function validate(
        int $barangayId,
        array $registrationFields,
        string $frontImagePath,
        string $backImagePath,
        ?string $selfieImagePath = null,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $frontImagePath = $this->normalizeImagePath($frontImagePath);
        $backImagePath = $this->normalizeImagePath($backImagePath);

        if ($frontImagePath === null || $backImagePath === null) {
            Log::warning('School ID pipeline missing image files', [
                'front' => $frontImagePath,
                'back' => $backImagePath,
            ]);

            return null;
        }

        $barangay = Barangay::query()->find($barangayId);
        $stagedFront = $this->stageImageCopy($frontImagePath);
        $stagedBack = $this->stageImageCopy($backImagePath);

        if ($stagedFront === null || $stagedBack === null) {
            Log::warning('School ID pipeline could not stage image copies');

            return null;
        }

        $payload = [
            'front_image' => $stagedFront,
            'back_image' => $stagedBack,
            'selfie_image' => $selfieImagePath,
            'form' => $this->buildFormPayload($registrationFields, $barangay?->name),
        ];

        $tempPayload = tempnam(sys_get_temp_dir(), 'sk_school_id_');
        if ($tempPayload === false) {
            @unlink($stagedFront);
            @unlink($stagedBack);

            return null;
        }

        $payloadPath = $tempPayload.'.json';
        rename($tempPayload, $payloadPath);
        file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $pythonDir = dirname(str_replace('\\', '/', (string) config('ocr.pipeline_script')));

        try {
            $result = Process::timeout((int) config('ocr.pipeline_timeout', 600))
                ->path($pythonDir)
                ->run([
                    (string) config('ocr.python'),
                    (string) config('ocr.pipeline_script'),
                    '--payload',
                    $payloadPath,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('School ID pipeline process failed', ['error' => $exception->getMessage()]);

            @unlink($payloadPath);
            @unlink($stagedFront);
            @unlink($stagedBack);

            return null;
        }

        @unlink($payloadPath);
        @unlink($stagedFront);
        @unlink($stagedBack);

        $output = trim($result->output());
        $stderr = trim($result->errorOutput());

        if ($output === '' && $stderr !== '') {
            Log::warning('School ID pipeline returned empty stdout', [
                'exit_code' => $result->exitCode(),
                'stderr' => substr($stderr, 0, 1000),
            ]);

            return null;
        }

        if (! $result->successful()) {
            Log::warning('School ID pipeline exited with error', [
                'exit_code' => $result->exitCode(),
                'stderr' => substr($stderr, 0, 1000),
                'stdout' => substr($output, 0, 500),
            ]);
        }

        if ($output === '') {
            Log::warning('School ID pipeline returned empty output', [
                'stderr' => substr(trim($result->errorOutput()), 0, 500),
            ]);

            return null;
        }

        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::warning('School ID pipeline returned invalid JSON', [
                'output' => substr($output, 0, 500),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $frontText = trim((string) ($decoded['ocr']['front']['full_text'] ?? ''));
        $backText = trim((string) ($decoded['ocr']['back']['full_text'] ?? ''));
        if ($frontText === '' && $backText === '') {
            Log::warning('School ID pipeline returned empty OCR text', [
                'front_image' => $frontImagePath,
                'back_image' => $backImagePath,
                'quality_warnings' => $decoded['quality_warnings'] ?? [],
            ]);
        }

        return $this->mapToVerificationResult($decoded, $registrationFields, $barangay);
    }

    public function isConfigured(): bool
    {
        $python = (string) config('ocr.python');
        $script = (string) config('ocr.pipeline_script');

        return is_file($python) && is_file($script);
    }

    private function normalizeImagePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $realPath = realpath($normalized);

        if ($realPath === false || ! is_file($realPath)) {
            Log::warning('School ID image path not readable', ['path' => $path]);

            return null;
        }

        return $realPath;
    }

    private function stageImageCopy(string $absolutePath): ?string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $extension = 'jpg';
        }

        $staged = tempnam(sys_get_temp_dir(), 'sk_id_img_');
        if ($staged === false) {
            return null;
        }

        $target = $staged.'.'.$extension;
        @unlink($staged);

        if (! @copy($absolutePath, $target)) {
            Log::warning('School ID image staging copy failed', ['source' => $absolutePath]);

            return null;
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function buildFormPayload(array $registrationFields, ?string $barangayName): array
    {
        $suffix = (string) ($registrationFields['suffix'] ?? '');
        if (strcasecmp($suffix, 'Others') === 0) {
            $suffix = (string) ($registrationFields['custom_suffix'] ?? '');
        }
        if (strcasecmp($suffix, 'None') === 0) {
            $suffix = '';
        }

        $birthday = (string) ($registrationFields['birthday'] ?? '');
        if ($birthday !== '' && ! str_contains($birthday, '-')) {
            try {
                $birthday = \Carbon\Carbon::parse($birthday)->toDateString();
            } catch (\Throwable) {
                // keep original
            }
        }

        return [
            'first_name' => (string) ($registrationFields['first_name'] ?? ''),
            'middle_name' => (string) ($registrationFields['middle_name'] ?? ''),
            'last_name' => (string) ($registrationFields['last_name'] ?? ''),
            'suffix' => $suffix,
            'birthday' => $birthday,
            'purok_zone' => (string) ($registrationFields['purok_zone'] ?? ''),
            'barangay' => $barangayName ?? (string) ($registrationFields['barangay'] ?? ($registrationFields['registration_barangay'] ?? '')),
            'municipality' => 'Santa Cruz',
            'province' => 'Laguna',
        ];
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function mapToVerificationResult(array $pipeline, array $registrationFields, ?Barangay $barangay): array
    {
        $decision = $this->normalizeDecision((string) ($pipeline['decision'] ?? 'MANUAL REVIEW'));
        $validationBlock = is_array($pipeline['validation'] ?? null) ? $pipeline['validation'] : [];
        $nameValidation = is_array($pipeline['validations']['name'] ?? null) ? $pipeline['validations']['name'] : [];
        $birthdateValidation = is_array($pipeline['validations']['birthdate'] ?? null) ? $pipeline['validations']['birthdate'] : [];
        $barangayValidation = is_array($pipeline['validations']['barangay'] ?? null)
            ? $pipeline['validations']['barangay']
            : (is_array($pipeline['validations']['address'] ?? null) ? $pipeline['validations']['address'] : []);

        $nameMatch = ($validationBlock['name'] ?? null) === 'PASS' || (bool) ($nameValidation['match'] ?? false);
        $birthdateMatch = ($validationBlock['birthdate'] ?? null) === 'PASS' || (bool) ($birthdateValidation['match'] ?? false);
        $barangayMatch = ($validationBlock['barangay'] ?? null) === 'PASS' || (bool) ($barangayValidation['match'] ?? false);

        $formBarangayName = (string) ($barangay?->name ?? ($registrationFields['registration_barangay'] ?? ''));
        if (! $barangayMatch && $formBarangayName !== '') {
            $backText = (string) ($pipeline['ocr']['back']['full_text'] ?? '');
            $address = (string) ($pipeline['address'] ?? '');
            if (stripos($backText, $formBarangayName) !== false || stripos($address, $formBarangayName) !== false) {
                $barangayMatch = true;
            }
        }

        if ($nameMatch && $birthdateMatch && $barangayMatch) {
            $decision = 'AUTO_APPROVE';
        }

        $formFullName = app(KabataanFullNameMatcher::class)->formatFormFullNameForDisplay($registrationFields);
        $detectedFullName = trim((string) ($pipeline['full_name'] ?? $pipeline['raw_name'] ?? ''));
        if ($detectedFullName === '') {
            $lastname = trim((string) ($pipeline['lastname'] ?? ''));
            $firstname = trim((string) ($pipeline['firstname'] ?? ''));
            $middleInitial = trim((string) ($pipeline['middle_initial'] ?? ''));
            if ($lastname !== '' && $firstname !== '') {
                $detectedFullName = $lastname.', '.$firstname;
                if ($middleInitial !== '') {
                    $detectedFullName .= ' '.$middleInitial.'.';
                }
            }
        }
        if ($detectedFullName === '') {
            $detectedFullName = trim((string) ($pipeline['ocr']['front']['raw_name'] ?? ''));
        }

        $formAddress = KkProfilingValidationMessages::formAddressLabel($registrationFields, $barangay?->name);
        $detectedAddress = (string) ($pipeline['address'] ?? '');
        $detectedBarangay = (string) ($pipeline['barangay'] ?? '');

        $formBirthday = (string) ($registrationFields['birthday'] ?? '');
        if ($formBirthday !== '' && ! str_contains($formBirthday, '-')) {
            try {
                $formBirthday = \Carbon\Carbon::parse($formBirthday)->toDateString();
            } catch (\Throwable) {
                // keep original
            }
        }

        $success = $decision === 'AUTO_APPROVE';
        $message = $success
            ? 'School ID verified successfully.'
            : $this->resolveFailureMessage($pipeline, $formFullName, $detectedFullName, $formAddress, $detectedAddress);

        return [
            'success' => $success,
            'message' => $message,
            'name_match' => $nameMatch,
            'barangay_match' => $barangayMatch,
            'birthdate_match' => $birthdateMatch,
            'duplicate_detected' => false,
            'error_code' => $success ? null : 'pipeline_reject',
            'decision' => $decision,
            'overall_confidence' => $this->overallConfidence($nameValidation, $birthdateValidation, $barangayValidation),
            'auto_approve_eligible' => $decision === 'AUTO_APPROVE',
            'pipeline' => $pipeline,
            'form_full_name' => $formFullName,
            'detected_full_name' => $detectedFullName !== '' ? $detectedFullName : null,
            'form_birthday' => $formBirthday !== '' ? $formBirthday : null,
            'form_barangay' => $barangay?->name ?? ($registrationFields['registration_barangay'] ?? null),
            'form_address' => $formAddress,
            'detected_address' => $detectedAddress !== '' ? $detectedAddress : null,
            'address' => $detectedAddress,
            'barangay' => $detectedBarangay !== '' ? $detectedBarangay : ($pipeline['barangay'] ?? null),
            'city' => null,
            'province' => null,
            'matched_barangay' => $barangayMatch ? ($barangay?->name ?? $detectedBarangay) : null,
            'registration_barangay' => $barangay?->name,
            'registration_purok_zone' => $registrationFields['purok_zone'] ?? null,
            'match_score' => $nameMatch && $birthdateMatch && $barangayMatch ? 1.0 : 0.0,
            'match_reason' => implode(' ', $pipeline['reasons'] ?? []),
            'source' => 'school_id_pipeline_v3',
            'ocr' => $pipeline['ocr'] ?? [],
            'processed_at' => now()->toIso8601String(),
        ];
    }

    private function normalizeDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));

        return match ($decision) {
            'AUTO PASS', 'AUTO_PASS', 'AUTO APPROVE' => 'AUTO_APPROVE',
            'MANUAL REVIEW', 'MANUAL_REVIEW' => 'MANUAL_REVIEW',
            default => $decision,
        };
    }

    /**
     * @param  array<string, mixed>  $nameValidation
     * @param  array<string, mixed>  $birthdateValidation
     * @param  array<string, mixed>  $barangayValidation
     */
    private function overallConfidence(array $nameValidation, array $birthdateValidation, array $barangayValidation): float
    {
        $scores = [
            (float) ($nameValidation['score'] ?? 0),
            (float) ($birthdateValidation['score'] ?? 0),
            (float) ($barangayValidation['score'] ?? 0),
        ];

        return round(array_sum($scores) / max(count($scores), 1), 2);
    }

    /**
     * @param  array<string, mixed>  $pipeline
     */
    private function resolveFailureMessage(
        array $pipeline,
        string $formFullName,
        string $detectedFullName,
        string $formAddress,
        string $detectedAddress,
    ): string {
        $reasons = $pipeline['reasons'] ?? [];
        if (is_array($reasons) && $reasons !== []) {
            return implode(' ', $reasons);
        }

        $birthdateValidation = is_array($pipeline['validations']['birthdate'] ?? null)
            ? $pipeline['validations']['birthdate']
            : [];

        if (($birthdateValidation['match'] ?? true) === false) {
            return KkProfilingValidationMessages::birthdateMismatch(
                (string) ($birthdateValidation['form'] ?? ''),
                (string) ($birthdateValidation['extracted'] ?? ($pipeline['birthdate'] ?? ($pipeline['birthdate_raw'] ?? ''))),
            );
        }

        if (($pipeline['validations']['name']['match'] ?? false) === false) {
            return KkProfilingValidationMessages::nameMismatch($formFullName, $detectedFullName);
        }

        if (($pipeline['validations']['barangay']['match'] ?? ($pipeline['validations']['address']['match'] ?? false)) === false) {
            return KkProfilingValidationMessages::addressMismatch($formAddress, $detectedAddress);
        }

        return KkProfilingValidationMessages::uploadProcessingFailed();
    }
}
