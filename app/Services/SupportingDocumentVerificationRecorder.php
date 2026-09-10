<?php

namespace App\Services;

use App\Models\SupportingDocumentAuditLog;
use App\Models\SupportingDocumentVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SupportingDocumentVerificationRecorder
{
    public function __construct(
        protected DocumentFingerprintService $fingerprintService,
        protected PerceptualHashService $perceptualHashService,
        protected DocumentDuplicateDetectionService $duplicateDetection,
    ) {}

    /**
     * Record privacy-preserving verification metadata after Step 2 upload/OCR.
     * Does not store raw OCR text or expose fingerprints to the client.
     *
     * @param  array<string, mixed>  $wizard
     * @param  array<string, UploadedFile|null>  $sides
     * @param  array<string, mixed>|null  $ocrPayload
     * @return array<string, mixed>
     */
    public function record(
        array $wizard,
        int $barangayId,
        string $documentType,
        array $sides,
        ?array $ocrPayload = null,
    ): array {
        if (! Schema::hasTable('supporting_document_verifications')) {
            return [
                'verification_status' => 'pending',
                'duplicate_status' => 'none',
                'needs_review' => false,
                'user_message' => 'Document received successfully.',
            ];
        }

        $detectedType = (string) ($ocrPayload['id_type']
            ?? $ocrPayload['detected_id_type']
            ?? $documentType);
        $confidence = isset($ocrPayload['confidence']) ? (float) $ocrPayload['confidence'] : null;

        $signals = [
            'document_type' => $documentType,
            'document_number' => $ocrPayload['id_number'] ?? null,
            'full_name' => $ocrPayload['full_name'] ?? $ocrPayload['detected_name'] ?? null,
            'birthdate' => $ocrPayload['birthdate'] ?? $ocrPayload['detected_birthdate'] ?? null,
            'issuing_organization' => $ocrPayload['issuing_organization'] ?? null,
        ];

        $fingerprint = $this->fingerprintService->fingerprint($signals);

        $frontPath = $sides['front'] instanceof UploadedFile
            ? ($sides['front']->getRealPath() ?: $sides['front']->getPathname())
            : null;
        if (! is_string($frontPath) || $frontPath === '' || ! is_file($frontPath)) {
            $frontPath = null;
        }
        $perceptualHash = is_string($frontPath) ? $this->perceptualHashService->hashFromFile($frontPath) : null;

        $duplicate = $this->duplicateDetection->evaluate(
            $fingerprint,
            $perceptualHash,
            $signals,
            is_string($wizard['token'] ?? null) ? (string) $wizard['token'] : null,
        );

        $needsReview = (bool) ($duplicate['needs_review'] ?? false);
        $reviewReason = null;

        if ($confidence !== null && $confidence < (float) config('documents.detection_confidence_review_below', 0.55)) {
            $needsReview = true;
            $reviewReason = 'low_confidence';
        }

        if (! empty($ocrPayload['validation_error'])) {
            $needsReview = true;
            $reviewReason = $reviewReason ?: 'detection_uncertain';
        }

        if (($duplicate['duplicate_status'] ?? 'none') !== 'none') {
            $reviewReason = 'possible_duplicate';
        }

        $status = match ($duplicate['duplicate_status'] ?? 'none') {
            'high' => 'duplicate',
            'possible' => 'needs_review',
            default => $needsReview ? 'needs_review' : 'verified',
        };

        $retentionDays = (int) config('documents.retention_days', 0);
        $retainUntil = $retentionDays > 0 ? now()->addDays($retentionDays) : now();

        $tempPath = null;
        if ($sides['front'] instanceof UploadedFile && $retentionDays > 0) {
            $tempPath = $this->storeTemporaryCopy($sides['front'], (string) ($wizard['token'] ?? 'draft'));
        }

        $record = SupportingDocumentVerification::query()->create([
            'wizard_token' => $wizard['token'] ?? null,
            'barangay_id' => $barangayId,
            'document_type' => $documentType,
            'detected_document_type' => $detectedType !== '' ? $detectedType : null,
            'verification_fingerprint' => $fingerprint,
            'perceptual_hash' => $perceptualHash,
            'verification_status' => $status,
            'detection_confidence' => $confidence,
            'duplicate_status' => $duplicate['duplicate_status'] ?? 'none',
            'needs_review' => $needsReview || $status === 'needs_review' || $status === 'duplicate',
            'review_reason' => $reviewReason,
            'temp_storage_path' => $tempPath,
            'retain_until' => $retainUntil,
        ]);

        SupportingDocumentAuditLog::query()->create([
            'supporting_document_verification_id' => $record->id,
            'actor_id' => null,
            'action' => 'auto_processed',
            'reason' => $reviewReason,
        ]);

        if ($retentionDays <= 0) {
            $this->deleteTemporaryPath($tempPath);
        }

        Log::info('Supporting document verification recorded', [
            'verification_id' => $record->id,
            'document_type' => $documentType,
            'verification_status' => $status,
            'duplicate_status' => $duplicate['duplicate_status'] ?? 'none',
            'needs_review' => $record->needs_review,
        ]);

        return [
            'verification_id' => $record->id,
            'verification_status' => $status,
            'duplicate_status' => $duplicate['duplicate_status'] ?? 'none',
            'needs_review' => (bool) $record->needs_review,
            'detection_confidence' => $confidence,
            'detected_document_type' => $detectedType,
            'user_message' => $this->userMessage($status, (bool) $record->needs_review),
        ];
    }

    private function storeTemporaryCopy(UploadedFile $file, string $token): ?string
    {
        $disk = (string) config('documents.temp_disk', 'local');
        $root = trim((string) config('documents.temp_root', 'kk_document_temp'), '/');
        $directory = $root.'/'.preg_replace('/[^a-zA-Z0-9\\-_]/', '', $token);

        try {
            return $file->storeAs(
                $directory,
                'front_'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension(),
                $disk
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to store temporary supporting document copy', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function deleteTemporaryPath(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = (string) config('documents.temp_disk', 'local');

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete temporary supporting document', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function userMessage(string $status, bool $needsReview): string
    {
        if ($status === 'duplicate') {
            return 'We found a possible matching submission. Your document will be reviewed.';
        }

        if ($needsReview || $status === 'needs_review') {
            return 'Your submission requires administrator review.';
        }

        return 'Document received successfully.';
    }
}
