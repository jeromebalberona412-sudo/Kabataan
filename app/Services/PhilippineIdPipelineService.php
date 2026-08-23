<?php

namespace App\Services;

use App\Models\Barangay;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PhilippineIdPipelineService
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
        string $documentType,
        ?string $selfieImagePath = null,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $frontImagePath = $this->normalizeImagePath($frontImagePath);
        $backImagePath = $this->normalizeImagePath($backImagePath);

        if ($frontImagePath === null || $backImagePath === null) {
            Log::warning('Philippine ID pipeline missing image files', [
                'front' => $frontImagePath,
                'back' => $backImagePath,
            ]);

            return null;
        }

        $barangay = Barangay::query()->find($barangayId);
        $stagedFront = $this->stageImageCopy($frontImagePath);
        $stagedBack = $this->stageImageCopy($backImagePath);
        $stagedSelfie = $selfieImagePath ? $this->stageImageCopy($this->normalizeImagePath($selfieImagePath) ?? '') : null;

        if ($stagedFront === null || $stagedBack === null) {
            return null;
        }

        $payload = [
            'front_image' => $stagedFront,
            'back_image' => $stagedBack,
            'selfie_image' => $stagedSelfie,
            'document_type' => $documentType,
            'form' => $this->buildFormPayload($registrationFields, $barangay?->name),
        ];

        $tempPayload = tempnam(sys_get_temp_dir(), 'sk_phil_id_');
        if ($tempPayload === false) {
            $this->cleanup([$stagedFront, $stagedBack, $stagedSelfie]);

            return null;
        }

        $payloadPath = $tempPayload.'.json';
        rename($tempPayload, $payloadPath);
        file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $pythonDir = dirname(str_replace('\\', '/', (string) config('ocr.philippine_pipeline_script')));

        try {
            $result = Process::timeout((int) config('ocr.pipeline_timeout', 600))
                ->path($pythonDir)
                ->run([
                    (string) config('ocr.python'),
                    (string) config('ocr.philippine_pipeline_script'),
                    '--payload',
                    $payloadPath,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Philippine ID pipeline process failed', ['error' => $exception->getMessage()]);
            $this->cleanup([$payloadPath, $stagedFront, $stagedBack, $stagedSelfie]);

            return null;
        }

        $this->cleanup([$payloadPath, $stagedFront, $stagedBack, $stagedSelfie]);

        $output = trim($result->output());
        if ($output === '') {
            Log::warning('Philippine ID pipeline returned empty output', [
                'stderr' => substr(trim($result->errorOutput()), 0, 500),
            ]);

            return null;
        }

        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::warning('Philippine ID pipeline returned invalid JSON', [
                'output' => substr($output, 0, 500),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return $this->mapToVerificationResult($decoded, $documentType, $registrationFields, $barangay);
    }

    public function isConfigured(): bool
    {
        $python = (string) config('ocr.python');
        $script = (string) config('ocr.philippine_pipeline_script');

        return is_file($python) && is_file($script);
    }

    private function normalizeImagePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $realPath = realpath(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

        return ($realPath !== false && is_file($realPath)) ? $realPath : null;
    }

    private function stageImageCopy(string $absolutePath): ?string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $extension = 'jpg';
        }

        $staged = tempnam(sys_get_temp_dir(), 'sk_phil_id_img_');
        if ($staged === false) {
            return null;
        }

        $target = $staged.'.'.$extension;
        @unlink($staged);

        if (! @copy($absolutePath, $target)) {
            return null;
        }

        return $target;
    }

    /**
     * @param  list<string|null>  $paths
     */
    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
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
            'barangay' => $barangayName ?? (string) ($registrationFields['registration_barangay'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @param  array<string, mixed>  $registrationFields
     * @return array<string, mixed>
     */
    private function mapToVerificationResult(
        array $pipeline,
        string $documentType,
        array $registrationFields,
        ?Barangay $barangay,
    ): array {
        $success = (bool) ($pipeline['success'] ?? false);
        $validationError = (bool) ($pipeline['validation_error'] ?? false);
        $formFullName = app(KabataanFullNameMatcher::class)->formatFormFullNameForDisplay($registrationFields);
        $detectedFullName = (string) ($pipeline['full_name'] ?? '');

        return [
            'success' => $success,
            'message' => $pipeline['message'] ?? ($success ? 'ID verified successfully.' : 'ID verification failed.'),
            'validation_error' => $validationError,
            'name_match' => (bool) ($pipeline['name_match'] ?? false),
            'birthdate_match' => (bool) ($pipeline['birthdate_match'] ?? false),
            'face_match' => (bool) ($pipeline['face_match'] ?? false),
            'id_number_valid' => (bool) ($pipeline['id_number_valid'] ?? false),
            'id_type' => $pipeline['id_type'] ?? 'Unknown',
            'expected_id_type' => $pipeline['expected_id_type'] ?? null,
            'detected_id_type' => $pipeline['detected_id_type'] ?? null,
            'id_number' => $pipeline['id_number'] ?? null,
            'confidence' => $pipeline['confidence'] ?? 0,
            'detected_name' => $detectedFullName !== '' ? $detectedFullName : null,
            'detected_birthdate' => $pipeline['birthdate'] ?? null,
            'detected_address' => $pipeline['address'] ?? null,
            'detected_sex' => $pipeline['sex'] ?? null,
            'face_verification' => $pipeline['face_verification'] ?? null,
            'form_full_name' => $formFullName,
            'form_barangay' => $barangay?->name,
            'pipeline' => $pipeline,
            'ocr' => $pipeline['ocr'] ?? [],
            'source' => 'philippine_id_pipeline_v1',
            'document_type' => $documentType,
            'processed_at' => now()->toIso8601String(),
        ];
    }
}
