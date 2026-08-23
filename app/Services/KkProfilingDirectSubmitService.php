<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\KabataanRegistration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KkProfilingDirectSubmitService
{
    public function __construct(
        private readonly DuplicateKabataanRegistrationService $duplicateChecker,
        private readonly KkRegistrationDraftService $draftService,
    ) {}

    /**
     * Persist Step 1 as a KK Profiling record without creating a user account.
     *
     * @param  array<string, mixed>  $payload
     */
    public function commit(Barangay $barangay, array $payload): KabataanRegistration
    {
        $payload['email'] = null;
        unset($payload['data_agreement']);

        if (($payload['suffix'] ?? null) === 'Others' && ! empty($payload['custom_suffix'])) {
            $payload['suffix'] = trim((string) $payload['custom_suffix']);
        }

        $lockKey = $this->submissionLockKey((int) $barangay->id, $payload);

        if (! Cache::add($lockKey, 1, 20)) {
            throw ValidationException::withMessages([
                'form' => ['Your KK Profiling is already being submitted. Please wait a moment.'],
            ]);
        }

        $existing = $this->duplicateChecker->findExistingIdentity((int) $barangay->id, $payload);

        if ($existing) {
            throw ValidationException::withMessages([
                'registration' => [KkProfilingValidationMessages::DUPLICATE_IDENTITY],
            ]);
        }

        return DB::transaction(function () use ($barangay, $payload) {
                $registration = KabataanRegistration::create([
                    'tenant_id' => $barangay->tenant_id,
                    'barangay_id' => $barangay->id,
                    'last_name' => $payload['last_name'],
                    'first_name' => $payload['first_name'],
                    'middle_name' => $payload['middle_name'] ?? null,
                    'suffix' => $payload['suffix'] ?? 'None',
                    'email' => null,
                    'contact_number' => $payload['contact_number'] ?? null,
                    'profile_photo_path' => null,
                    'form_data' => $payload,
                    'status' => 'password_set',
                    'submitted_at' => now(),
                ]);

                $evaluator = new RegistrationEvaluationService;
                $evaluator->evaluate($registration->fresh());

                try {
                    (new SkOfficialsNotificationDispatcher)->notifyKkProfilingSubmission(
                        (int) $barangay->id,
                        $registration->full_name,
                    );
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    (new KkSurveyResponseService)->syncFromRegistration(
                        $registration->fresh(),
                        'pending'
                    );
                } catch (\Throwable $e) {
                    report($e);
                }

                $this->draftService->clearSessionDraft();

                return $registration->fresh();
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submissionLockKey(int $barangayId, array $payload): string
    {
        $fingerprint = $this->duplicateChecker->identityFingerprint($barangayId, $payload);
        $identity = is_array($fingerprint)
            ? implode('|', $fingerprint)
            : strtolower(trim(($payload['last_name'] ?? '').'|'.($payload['first_name'] ?? '').'|'.($payload['birthday'] ?? '')));

        return 'kk_no_email_submit:'.sha1($identity);
    }
}
