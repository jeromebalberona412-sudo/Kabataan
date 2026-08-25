<?php

namespace App\Services;

use App\Models\SupportingDocumentVerification;
use Illuminate\Support\Facades\Log;

class DocumentDuplicateDetectionService
{
    public function __construct(
        protected DocumentFingerprintService $fingerprintService,
        protected PerceptualHashService $perceptualHashService,
    ) {}

    /**
     * @param  array<string, mixed>  $ocrSignals
     * @return array{
     *     duplicate_status: string,
     *     needs_review: bool,
     *     matched_verification_id: int|null,
     *     reasons: list<string>
     * }
     */
    public function evaluate(
        ?string $fingerprint,
        ?string $perceptualHash,
        array $ocrSignals = [],
        ?string $excludeWizardToken = null,
    ): array {
        $reasons = [];
        $matchedId = null;
        $status = 'none';

        if ($fingerprint) {
            $exact = SupportingDocumentVerification::query()
                ->where('verification_fingerprint', $fingerprint)
                ->when($excludeWizardToken, fn ($q) => $q->where('wizard_token', '!=', $excludeWizardToken))
                ->orderByDesc('id')
                ->first();

            if ($exact) {
                return [
                    'duplicate_status' => 'high',
                    'needs_review' => true,
                    'matched_verification_id' => (int) $exact->id,
                    'reasons' => ['fingerprint_match'],
                ];
            }
        }

        if ($perceptualHash) {
            $threshold = (int) config('documents.duplicate.phash_hamming_threshold', 8);
            $possibleThreshold = (int) config('documents.duplicate.possible_phash_threshold', 12);

            $candidates = SupportingDocumentVerification::query()
                ->whereNotNull('perceptual_hash')
                ->when($excludeWizardToken, fn ($q) => $q->where('wizard_token', '!=', $excludeWizardToken))
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'perceptual_hash']);

            foreach ($candidates as $candidate) {
                $distance = $this->perceptualHashService->hammingDistance(
                    $perceptualHash,
                    (string) $candidate->perceptual_hash
                );

                if ($distance === null) {
                    continue;
                }

                if ($distance <= $threshold) {
                    $status = 'high';
                    $matchedId = (int) $candidate->id;
                    $reasons[] = 'perceptual_hash_close';
                    break;
                }

                if ($distance <= $possibleThreshold && $status === 'none') {
                    $status = 'possible';
                    $matchedId = (int) $candidate->id;
                    $reasons[] = 'perceptual_hash_similar';
                }
            }
        }

        $normalizedName = $this->fingerprintService->normalize([
            'full_name' => $ocrSignals['full_name'] ?? null,
            'document_number' => $ocrSignals['document_number'] ?? null,
        ]);

        if ($normalizedName !== '' && isset($ocrSignals['document_number']) && is_string($ocrSignals['document_number'])) {
            // Soft signal only — never store raw OCR text on the duplicate result payload for clients.
            Log::info('Supporting document duplicate OCR signal evaluated', [
                'has_document_number' => true,
                'has_name' => ! empty($ocrSignals['full_name']),
            ]);
        }

        return [
            'duplicate_status' => $status,
            'needs_review' => $status !== 'none',
            'matched_verification_id' => $matchedId,
            'reasons' => $reasons,
        ];
    }
}
