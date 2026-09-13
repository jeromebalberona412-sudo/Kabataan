<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Support\SupportingDocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Session + temp-file wizard storage. No database writes until Step 4 finalize.
 */
class KkRegistrationDraftService
{
    public const SESSION_KEY = 'kk_wizard';

    public const COMPLETE_SESSION_KEY = 'kk_wizard_registration_complete';

    public const COMPLETED_TOKEN_CACHE_PREFIX = 'kk_wizard_completed_token:';

    public const PENDING_DISK = 'local';

    public const PENDING_ROOT = 'kk_wizard_pending';

    public const TEMP_DISK = 'local';

    public const TEMP_ROOT = 'kk_wizard_pending';

    public const DOCUMENTS_DISK = 'public';

    public const DOCUMENTS_DIRECTORY = 'kabataan_documents';

    public const DRAFT_COOKIE_NAME = 'kk_wizard_draft_token';

    public function __construct(
        protected CloudinaryService $cloudinary,
        protected InvalidEmailService $invalidEmails,
    ) {}

    public function resolveWizard(): ?array
    {
        $wizard = session(self::SESSION_KEY);

        if (is_array($wizard) && ! empty($wizard['token'])) {
            if ($this->isExpired($wizard)) {
                $this->clearSessionDraft();

                return null;
            }

            return $this->normalizeWizardSteps($wizard);
        }

        $token = request()->cookie(self::DRAFT_COOKIE_NAME);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $wizard = $this->loadByToken($token);

        if (! $wizard) {
            $this->forgetDraftCookie();

            return null;
        }

        $this->persist($this->denormalizeWizardForStorage($wizard));

        return $wizard;
    }

    public function loadByToken(string $token): ?array
    {
        $path = $this->pendingFilePath($token);

        if (! Storage::disk(self::PENDING_DISK)->exists($path)) {
            return null;
        }

        $wizard = json_decode(Storage::disk(self::PENDING_DISK)->get($path), true);

        if (! is_array($wizard) || empty($wizard['token'])) {
            return null;
        }

        if ($this->isExpired($wizard)) {
            $this->deletePendingFiles($token);

            return null;
        }

        return $this->normalizeWizardSteps($wizard);
    }

    public function clearSessionDraft(): void
    {
        $wizard = session(self::SESSION_KEY);
        $token = is_array($wizard) ? ($wizard['token'] ?? null) : null;

        if ($token) {
            $this->deletePendingFiles($token);
        }

        session()->forget([
            self::SESSION_KEY,
            'kk_wizard_step',
            'kk_wizard_email_verified',
            'kk_wizard_draft_id',
        ]);

        $this->forgetDraftCookie();
    }

    public function syncWizardSession(array $wizard): void
    {
        $this->persist($wizard);
    }

    public function createOrUpdateStep1(Barangay $barangay, array $validated, ?string $respondentNumber = null): array
    {
        $wizard = $this->resolveWizard();

        if (! $wizard || (int) ($wizard['barangay_id'] ?? 0) !== (int) $barangay->id) {
            if ($wizard && ! empty($wizard['token'])) {
                $this->deletePendingFiles($wizard['token']);
            }

            $this->clearCompletedRegistration();
            $wizard = $this->blankWizard($barangay->id);
        }

        $wizard['respondent_number'] = $respondentNumber ?: ($wizard['respondent_number'] ?? null);
        $wizard['step1_data'] = $validated;
        $wizard['email'] = strtolower(trim($validated['email'] ?? ''));
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 2);
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        // New unfinished progress should dismiss a leftover success modal session.
        $this->clearCompletedRegistration();

        return $this->persist($wizard);
    }

    /**
     * Persist unfinished Step 1 fields without advancing the wizard.
     * Used for refresh recovery — does not require full validation.
     *
     * @param  array<string, mixed>  $partial
     */
    public function saveStep1Partial(Barangay $barangay, array $partial, ?string $respondentNumber = null): array
    {
        $wizard = $this->resolveWizard();

        if (! $wizard || (int) ($wizard['barangay_id'] ?? 0) !== (int) $barangay->id) {
            if ($wizard && ! empty($wizard['token'])) {
                $this->deletePendingFiles($wizard['token']);
            }

            $this->clearCompletedRegistration();
            $wizard = $this->blankWizard($barangay->id);
        }

        $existing = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
        $merged = array_merge($existing, $partial);

        // Cleared fields (null/empty) must drop from draft so refresh does not restore them
        foreach ($partial as $key => $value) {
            if ($value === null || $value === '') {
                unset($merged[$key]);
            }
        }

        $wizard['respondent_number'] = $respondentNumber ?: ($wizard['respondent_number'] ?? null);
        $wizard['step1_data'] = $merged;

        $email = strtolower(trim((string) ($merged['email'] ?? '')));
        $wizard['email'] = $email !== '' ? $email : ($wizard['email'] ?? null);

        $wizard['current_step'] = max(1, (int) ($wizard['current_step'] ?? 1));
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        $this->clearCompletedRegistration();

        return $this->persist($wizard);
    }

    /**
     * @param  array<string, UploadedFile|null>  $sides  Keys: front, back
     */
    public function saveStep2(array $wizard, string $documentType, array $sides): array
    {
        if (empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'step' => ['Please complete Step 1 before uploading documents.'],
            ]);
        }

        $dir = $this->wizardDirectory($wizard['token']).'/documents';
        $storedSides = [];

        foreach (SupportingDocumentTypes::sides() as $side) {
            $file = $sides[$side] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $filename = Str::slug($documentType.'_'.$side, '_').'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6))
                .'.'.$file->getClientOriginalExtension();

            $path = $file->storeAs($dir, $filename, self::TEMP_DISK);

            $storedSides[$side] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $existingStep2 = is_array($wizard['step2_data'] ?? null) ? $wizard['step2_data'] : [];
        $wizard['step2_data'] = [
            'documents' => [
                $documentType => [
                    'type' => $documentType,
                    'sides' => $storedSides,
                ],
            ],
        ];
        // Keep prior ID verification until a fresh scan overwrites it.
        if (is_array($existingStep2['id_verification'] ?? null)) {
            $wizard['step2_data']['id_verification'] = $existingStep2['id_verification'];
        }
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 2);
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        return $this->persist($wizard);
    }

    /**
     * Whether the draft already has both front and back files for a document type.
     */
    public function hasStoredDocumentPair(array $wizard, string $documentType): bool
    {
        $sides = $wizard['step2_data']['documents'][$documentType]['sides'] ?? null;
        if (! is_array($sides)) {
            return false;
        }

        return ! empty($sides['front']['path']) && ! empty($sides['back']['path']);
    }

    public function skipStep2(array $wizard): array
    {
        if (empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'step' => ['Please complete Step 1 before continuing.'],
            ]);
        }

        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 2);
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        return $this->persist($wizard);
    }

    public function setWizardStep(array $wizard, int $step): array
    {
        $wizard['current_step'] = max(1, min(3, $step));
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        return $this->persist($wizard);
    }

    public function advanceToStep3(array $wizard): array
    {
        $wizard['current_step'] = 3;
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        return $this->persist($wizard);
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    public function storeIdVerification(array $wizard, array $verification): array
    {
        $step2 = is_array($wizard['step2_data'] ?? null) ? $wizard['step2_data'] : [];
        $step2['id_verification'] = $verification;
        $wizard['step2_data'] = $step2;

        return $this->persist($wizard);
    }

    /**
     * Enforce a 60-second gap between set-password emails (send/resend).
     * No hard attempt cap — users may resend indefinitely after each cooldown.
     *
     * @param  array<string, mixed>  $wizard
     * @return array{remaining_seconds:int}
     */
    public function setPasswordEmailCooldown(array $wizard): array
    {
        $sentAt = $wizard['verification_sent_at'] ?? null;

        if (! is_string($sentAt) || trim($sentAt) === '') {
            return ['remaining_seconds' => 0];
        }

        try {
            $availableAt = Carbon::parse($sentAt)->addSeconds(60);
        } catch (\Throwable) {
            return ['remaining_seconds' => 0];
        }

        $remaining = (int) max(0, $availableAt->getTimestamp() - now()->getTimestamp());

        return ['remaining_seconds' => $remaining];
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    public function assertSetPasswordEmailCooldown(array $wizard): void
    {
        $remaining = (int) ($this->setPasswordEmailCooldown($wizard)['remaining_seconds'] ?? 0);

        if ($remaining <= 0) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ["Please wait {$remaining} second".($remaining === 1 ? '' : 's').' before resending the set-password link.'],
        ]);
    }

    /**
     * Persist a freshly issued set-password link token and clear prior email verification.
     * Call only after the email was sent successfully so a failed send does not kill the previous link.
     *
     * @param  array<string, mixed>  $wizard
     * @return array<string, mixed>
     */
    public function applySetPasswordLink(array $wizard, string $linkToken): array
    {
        $linkToken = strtolower(trim($linkToken));

        if ($linkToken === '' || strlen($linkToken) !== 40 || ! ctype_xdigit($linkToken)) {
            throw ValidationException::withMessages([
                'email' => ['Unable to create a set-password link. Please try again.'],
            ]);
        }

        $wizard['set_password_link_token'] = $linkToken;
        $wizard['email_verified_at'] = null;
        $wizard['verification_sent_at'] = now()->toIso8601String();
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 3);

        return $this->persist($wizard);
    }

    /**
     * @deprecated Prefer applySetPasswordLink() after a successful send.
     *
     * @param  array<string, mixed>  $wizard
     * @return array<string, mixed>
     */
    public function rotateSetPasswordLink(array $wizard): array
    {
        return $this->applySetPasswordLink($wizard, bin2hex(random_bytes(20)));
    }

    public function markVerificationSent(array $wizard): array
    {
        if (empty($wizard['set_password_link_token'])) {
            return $this->applySetPasswordLink($wizard, bin2hex(random_bytes(20)));
        }

        $wizard['verification_sent_at'] = now()->toIso8601String();
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 3);

        return $this->persist($wizard);
    }

    /**
     * Whether the email link hash matches the current (non-superseded) set-password token.
     *
     * @param  array<string, mixed>  $wizard
     */
    public function matchesSetPasswordLink(array $wizard, string $hash): bool
    {
        $hash = strtolower(trim($hash));

        if ($hash === '' || strlen($hash) !== 40 || ! ctype_xdigit($hash)) {
            return false;
        }

        $current = strtolower(trim((string) ($wizard['set_password_link_token'] ?? '')));

        if ($current !== '' && hash_equals($current, $hash)) {
            return true;
        }

        // Legacy drafts emailed with sha1(email) before rotating tokens existed.
        if ($current === '') {
            $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

            return $email !== '' && hash_equals(sha1($email), $hash);
        }

        return false;
    }

    public function markEmailVerified(array $wizard): array
    {
        $wizard['email_verified_at'] = now()->toIso8601String();
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 3);

        return $this->persist($wizard);
    }

    public function assertEmailAvailable(string $email, int $barangayId): void
    {
        $email = strtolower(trim($email));

        $formatValidator = \Illuminate\Support\Facades\Validator::make(
            ['email' => $email],
            ['email' => \App\Rules\ValidEmailAddress::profilingRules()],
            \App\Rules\ValidEmailAddress::profilingMessages()
        );

        if ($formatValidator->fails()) {
            throw ValidationException::withMessages([
                'email' => $formatValidator->errors()->get('email'),
            ]);
        }

        $invalidCheck = $this->invalidEmails->checkBeforeSending($email);
        if (! $invalidCheck['allowed']) {
            throw ValidationException::withMessages([
                'email' => [$invalidCheck['message'] ?? 'This email address cannot be used.'],
            ]);
        }

        $activePendingRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayId)
            ->whereIn('status', ['pending_verification', 'email_verified', 'password_set', 'pending'])
            ->whereNull('deleted_at')
            ->first();

        if ($activePendingRegistration) {
            throw ValidationException::withMessages([
                'registration' => ['You already have a KK Profiling application under review. Please wait for the SK Official\'s review.'],
            ]);
        }

        $approvedRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayId)
            ->whereIn('status', ['active', 'approved'])
            ->whereNull('deleted_at')
            ->first();

        if ($approvedRegistration) {
            throw ValidationException::withMessages([
                'email' => ['This email is already taken. Please use another email.'],
            ]);
        }
    }

    public function commitWizard(array $wizard, string $password): KabataanRegistration
    {
        if (empty($wizard['email_verified_at'])) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before completing registration.'],
            ]);
        }

        if (empty($wizard['verification_sent_at'])) {
            throw ValidationException::withMessages([
                'email' => ['Please request the set-password email before completing registration.'],
            ]);
        }

        if (empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'step' => ['Registration data is incomplete. Please restart the wizard.'],
            ]);
        }

        $barangay = Barangay::find($wizard['barangay_id'] ?? 0);

        if (! $barangay) {
            throw ValidationException::withMessages([
                'barangay' => ['Barangay not found. Please restart registration.'],
            ]);
        }

        if (empty($barangay->tenant_id)) {
            throw ValidationException::withMessages([
                'barangay' => ['This barangay is not configured for registration. Please contact SK Officials.'],
            ]);
        }

        $step1 = $wizard['step1_data'];
        $email = strtolower(trim($step1['email'] ?? $wizard['email'] ?? ''));

        $this->assertEmailAvailable($email, $barangay->id);

        if (app(DuplicateKabataanRegistrationService::class)->hasApprovedDuplicate((int) $barangay->id, $step1)) {
            throw ValidationException::withMessages([
                'registration' => [KkProfilingValidationMessages::DUPLICATE_IDENTITY],
            ]);
        }

        return DB::transaction(function () use ($wizard, $barangay, $step1, $email, $password) {
            $formData = $this->buildFormData($step1, $wizard);
            try {
                $formData['supporting_documents'] = $this->promoteDocuments($wizard);
            } catch (Throwable $e) {
                report($e);
                Log::error('KK wizard document promote failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $formData['supporting_documents'] = $wizard['step2_data']['documents'] ?? [];
            }

            $previousRejected = KabataanRegistration::where('email', $email)
                ->where('barangay_id', $barangay->id)
                ->where('status', 'rejected')
                ->latest('id')
                ->first();

            $contactNumber = app(PhoneNumberService::class)->toLocalMobile((string) ($step1['contact_number'] ?? ''))
                ?: mb_substr(preg_replace('/\D+/', '', (string) ($step1['contact_number'] ?? '')) ?: '', 0, 15);

            $registrationPayload = [
                'tenant_id' => $barangay->tenant_id,
                'barangay_id' => $barangay->id,
                'last_name' => mb_substr((string) $step1['last_name'], 0, 100),
                'first_name' => mb_substr((string) $step1['first_name'], 0, 100),
                'middle_name' => ($step1['middle_name'] ?? null)
                    ? mb_substr((string) $step1['middle_name'], 0, 100)
                    : null,
                'suffix' => mb_substr((string) ($this->resolvedSuffix($step1) ?? 'None'), 0, 10),
                'email' => $email,
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'profile_photo_path' => null,
                'form_data' => $formData,
                'status' => 'password_set',
                'profiling_year' => now()->year,
                'email_verified_at' => $wizard['email_verified_at'] ?? now(),
                'submitted_at' => now(),
            ];

            if (Schema::hasColumn('kabataan_registrations', 'previous_application_id')) {
                $registrationPayload['previous_application_id'] = $previousRejected?->id;
            }

            $registrationPayload = array_filter(
                $registrationPayload,
                static fn (string $column): bool => Schema::hasColumn('kabataan_registrations', $column),
                ARRAY_FILTER_USE_KEY
            );

            $registration = KabataanRegistration::create($registrationPayload);

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $userPayload = $this->wizardUserPayload($registration, $email, $password);

            if ($user) {
                $user->update($userPayload);
            } else {
                $user = User::create($userPayload);
            }

            $registration->markPasswordSet();
            $registration->linkUser($user->id);

            try {
                (new RegistrationEvaluationService)->evaluate($registration->fresh());
            } catch (Throwable $e) {
                report($e);
            }

            try {
                (new SkOfficialsNotificationDispatcher)->notifyKkProfilingSubmission(
                    (int) $barangay->id,
                    $registration->full_name,
                );
            } catch (Throwable $e) {
                report($e);
            }

            try {
                (new KkSurveyResponseService)->syncFromRegistration(
                    $registration->fresh(),
                    'pending'
                );
            } catch (Throwable $e) {
                report($e);
            }

            $registration = $registration->fresh();
            try {
                $this->rememberCompletedWizardToken($wizard['token'], $registration);
            } catch (Throwable $e) {
                report($e);
            }
            $this->deletePendingFiles($wizard['token']);
            $this->clearSessionDraft();
            $this->markRegistrationComplete($email, (int) $barangay->id, $registration);

            return $registration;
        });
    }

    public function markRegistrationComplete(string $email, int $barangayId, ?KabataanRegistration $registration = null): void
    {
        $email = strtolower(trim($email));

        if (! $registration) {
            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayId)
                ->where('email', $email)
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();
        }

        session([
            self::COMPLETE_SESSION_KEY => [
                'email' => $email,
                'barangay_id' => $barangayId,
                'completed_at' => now()->toIso8601String(),
                'auto_approved' => $registration
                    ? RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)
                    : false,
                'evaluation_status' => $registration?->evaluation_status,
            ],
        ]);

        $this->forgetDraftCookie();
    }

    public function rememberCompletedWizardToken(string $token, KabataanRegistration $registration): void
    {
        try {
            Cache::put(
                self::COMPLETED_TOKEN_CACHE_PREFIX.$token,
                [
                    'registration_id' => $registration->id,
                    'email' => strtolower(trim($registration->email)),
                    'barangay_id' => (int) $registration->barangay_id,
                    'auto_approved' => RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status),
                    'evaluation_status' => $registration->evaluation_status,
                ],
                now()->addHours(24),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveCompletedByWizardToken(string $token): ?array
    {
        $data = Cache::get(self::COMPLETED_TOKEN_CACHE_PREFIX.$token);

        return is_array($data) ? $data : null;
    }

    public function resolveCompletedRegistration(?int $barangayId = null): ?array
    {
        $data = session(self::COMPLETE_SESSION_KEY);

        if (! is_array($data) || empty($data['email'])) {
            return null;
        }

        if ($barangayId !== null && (int) ($data['barangay_id'] ?? 0) !== $barangayId) {
            return null;
        }

        return $data;
    }

    public function clearCompletedRegistration(): void
    {
        session()->forget(self::COMPLETE_SESSION_KEY);
    }

    public function isEmailRegistrationComplete(string $email, int $barangayId): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        return KabataanRegistration::query()
            ->where('barangay_id', $barangayId)
            ->where('email', $email)
            ->whereIn('status', ['password_set', 'active'])
            ->exists();
    }

    public function wizardStatusPayload(?array $wizard): ?array
    {
        if (! $wizard) {
            return null;
        }

        return [
            'token' => $wizard['token'],
            'current_step' => $this->mapCurrentStepForClient((int) ($wizard['current_step'] ?? 1)),
            'email' => $wizard['email'] ?? null,
            'email_verified' => ! empty($wizard['email_verified_at']),
            'verification_sent' => ! empty($wizard['verification_sent_at']),
            'has_step1' => ! empty($wizard['step1_data']),
            'has_documents' => ! empty($wizard['step2_data']['documents']),
            'step1' => $wizard['step1_data'] ?? null,
            'step2' => $this->step2StatusPayload($wizard),
            'respondent_number' => $wizard['respondent_number'] ?? null,
        ];
    }

    private function step2StatusPayload(array $wizard): ?array
    {
        $documents = $wizard['step2_data']['documents'] ?? [];

        if ($documents === []) {
            return null;
        }

        $documentType = array_key_first($documents);
        $meta = is_array($documents[$documentType] ?? null) ? $documents[$documentType] : [];
        $sides = is_array($meta['sides'] ?? null) ? $meta['sides'] : [];

        $sidePayload = [];

        foreach (SupportingDocumentTypes::sides() as $side) {
            if (! empty($sides[$side]['path'])) {
                $sidePayload[$side] = [
                    'original_name' => $sides[$side]['original_name'] ?? '',
                ];
            }
        }

        return [
            'document_type' => $documentType,
            'sides' => $sidePayload,
            'id_verification' => is_array($wizard['step2_data']['id_verification'] ?? null)
                ? $wizard['step2_data']['id_verification']
                : null,
        ];
    }

    private function blankWizard(int $barangayId): array
    {
        return [
            'token' => (string) Str::uuid(),
            'barangay_id' => $barangayId,
            'respondent_number' => null,
            'step1_data' => null,
            'step2_data' => null,
            'email' => null,
            'email_verified_at' => null,
            'verification_sent_at' => null,
            'set_password_link_token' => null,
            'current_step' => 1,
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ];
    }

    private function persist(array $wizard): array
    {
        session([self::SESSION_KEY => $wizard]);

        if (! empty($wizard['token'])) {
            Storage::disk(self::PENDING_DISK)->put(
                $this->pendingFilePath($wizard['token']),
                json_encode($wizard)
            );

            $this->queueDraftCookie((string) $wizard['token']);
        }

        session([
            'kk_wizard_step' => max(1, min(3, $this->mapCurrentStepForClient((int) ($wizard['current_step'] ?? 1)))),
            'kk_wizard_email_verified' => ! empty($wizard['email_verified_at']),
        ]);

        return $wizard;
    }

    /**
     * Reverse client step mapping before writing storage that tracks raw progress.
     *
     * @param  array<string, mixed>  $wizard
     * @return array<string, mixed>
     */
    private function denormalizeWizardForStorage(array $wizard): array
    {
        $step = (int) ($wizard['current_step'] ?? 1);

        if ($step === 3 && ! empty($wizard['verification_sent_at'])) {
            $wizard['current_step'] = 3;
        }

        return $wizard;
    }

    private function queueDraftCookie(string $token): void
    {
        cookie()->queue(cookie(
            self::DRAFT_COOKIE_NAME,
            $token,
            60 * 24 * 7,
            '/',
            null,
            (bool) config('session.secure'),
            true,
            false,
            'Lax'
        ));
    }

    private function forgetDraftCookie(): void
    {
        cookie()->queue(cookie()->forget(self::DRAFT_COOKIE_NAME));
    }

    public function isExpiredWizard(array $wizard): bool
    {
        return $this->isExpired($wizard);
    }

    private function isExpired(array $wizard): bool
    {
        if (empty($wizard['expires_at'])) {
            return false;
        }

        try {
            return now()->greaterThan(Carbon::parse($wizard['expires_at']));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function deletePendingFiles(string $token): void
    {
        $dir = self::TEMP_ROOT.'/'.$token;

        if (Storage::disk(self::TEMP_DISK)->exists($dir)) {
            Storage::disk(self::TEMP_DISK)->deleteDirectory($dir);
        }

        $file = $this->pendingFilePath($token);

        if (Storage::disk(self::PENDING_DISK)->exists($file)) {
            Storage::disk(self::PENDING_DISK)->delete($file);
        }
    }

    private function pendingFilePath(string $token): string
    {
        return self::PENDING_ROOT.'/'.$token.'.json';
    }

    private function wizardDirectory(string $token): string
    {
        return self::TEMP_ROOT.'/'.$token;
    }

    private function normalizeWizardSteps(array $wizard): array
    {
        if (! empty($wizard['step3_data']['documents']) && empty($wizard['step2_data']['documents'])) {
            $wizard['step2_data'] = $wizard['step3_data'];
        }

        if (! empty($wizard['step2_data']['selfie_path'])) {
            unset($wizard['step2_data']);
        }

        unset($wizard['step3_data']);

        if ((int) ($wizard['current_step'] ?? 1) >= 3 && empty($wizard['verification_sent_at'])) {
            $wizard['current_step'] = 2;
        }

        $step2Verification = $wizard['step2_data']['id_verification'] ?? null;
        if (is_array($step2Verification)
            && ! ($step2Verification['success'] ?? false)
            && empty($wizard['verification_sent_at'])) {
            $wizard['current_step'] = min((int) ($wizard['current_step'] ?? 1), 2);
        }

        $wizard['current_step'] = $this->mapStoredStepToClient((int) ($wizard['current_step'] ?? 1));

        return $wizard;
    }

    private function mapStoredStepToClient(int $step): int
    {
        if ($step >= 4) {
            return 3;
        }

        return max(1, min(3, $step));
    }

    private function mapCurrentStepForClient(int $step): int
    {
        return $this->mapStoredStepToClient($step);
    }

    private function buildFormData(array $step1, array $wizard): array
    {
        $data = $step1;

        if (! empty($wizard['respondent_number'])) {
            $data['respondent_number'] = $wizard['respondent_number'];
        }

        if (($data['suffix'] ?? null) === 'Others' && ! empty($data['custom_suffix'])) {
            $data['suffix'] = trim($data['custom_suffix']);
        }

        unset($data['custom_suffix'], $data['data_agreement']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardUserPayload(KabataanRegistration $registration, string $email, string $password): array
    {
        $payload = [
            'name' => $registration->full_name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'tenant_id' => $registration->tenant_id,
            'barangay_id' => $registration->barangay_id,
            'role' => 'kabataan',
            'status' => User::STATUS_PENDING_APPROVAL,
            'profile_image_url' => null,
            'profile_image_uploaded_at' => null,
        ];

        return array_filter(
            $payload,
            static fn (string $column): bool => Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function resolvedSuffix(array $step1): ?string
    {
        $suffix = $step1['suffix'] ?? null;

        if ($suffix === 'Others') {
            return trim($step1['custom_suffix'] ?? '') ?: null;
        }

        if ($suffix === null || $suffix === '') {
            return 'None';
        }

        if (strcasecmp((string) $suffix, 'none') === 0) {
            return 'None';
        }

        return $suffix;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function promoteDocuments(array $wizard): array
    {
        $documents = $wizard['step2_data']['documents'] ?? [];

        if ($documents === []) {
            return [];
        }

        if (! Storage::disk(self::DOCUMENTS_DISK)->exists(self::DOCUMENTS_DIRECTORY)) {
            Storage::disk(self::DOCUMENTS_DISK)->makeDirectory(self::DOCUMENTS_DIRECTORY);
        }

        $promoted = [];
        $token = $wizard['token'] ?? Str::random(8);
        $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? 'user'));
        $emailSlug = Str::slug($email, '_') ?: 'user';

        foreach ($documents as $key => $meta) {
            $sides = is_array($meta['sides'] ?? null) ? $meta['sides'] : null;

            if ($sides !== null && $sides !== []) {
                $promotedSides = [];

                foreach ($sides as $side => $sideMeta) {
                    $promotedSide = $this->promoteSingleDocumentFile(
                        $sideMeta,
                        $token,
                        $emailSlug,
                        $key.'_'.$side,
                    );

                    if ($promotedSide !== null) {
                        $promotedSides[$side] = $promotedSide;
                    }
                }

                if ($promotedSides !== []) {
                    $promoted[] = [
                        'type' => $key,
                        'sides' => $promotedSides,
                    ];
                }

                continue;
            }

            $promotedFile = $this->promoteSingleDocumentFile($meta, $token, $emailSlug, (string) $key);

            if ($promotedFile !== null) {
                $promoted[] = array_merge(['type' => $key], $promotedFile, [
                    'ocr' => $wizard['step2_data']['id_verification'] ?? null,
                ]);
            }
        }

        return $promoted;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    private function promoteSingleDocumentFile(array $meta, string $token, string $emailSlug, string $key): ?array
    {
        $tempPath = $meta['path'] ?? null;

        if (! $tempPath || ! Storage::disk(self::TEMP_DISK)->exists($tempPath)) {
            return null;
        }

        $originalName = $meta['original_name'] ?? basename($tempPath);
        $displayName = pathinfo($originalName, PATHINFO_FILENAME);
        $publicId = $emailSlug.'_'.Str::slug($key, '_').'_'.now()->format('YmdHis');

        if ($this->cloudinary->isConfigured()) {
            try {
                $absolutePath = Storage::disk(self::TEMP_DISK)->path($tempPath);
                $uploaded = $this->cloudinary->uploadSupportingDocument($absolutePath, $publicId, $displayName);

                return [
                    'path' => $uploaded['public_id'],
                    'url' => $uploaded['url'],
                    'public_id' => $uploaded['public_id'],
                    'cloudinary_version' => $uploaded['version'],
                    'original_name' => $originalName,
                    'display_name' => $displayName,
                    'storage' => 'cloudinary',
                ];
            } catch (Throwable $e) {
                report($e);
                Log::error('KK wizard Cloudinary document upload failed', [
                    'path' => $tempPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! Storage::disk(self::DOCUMENTS_DISK)->exists(self::DOCUMENTS_DIRECTORY)) {
            Storage::disk(self::DOCUMENTS_DISK)->makeDirectory(self::DOCUMENTS_DIRECTORY);
        }

        $basename = basename($tempPath);
        $dest = self::DOCUMENTS_DIRECTORY.'/'.$token.'_'.$basename;

        Storage::disk(self::DOCUMENTS_DISK)->put(
            $dest,
            Storage::disk(self::TEMP_DISK)->get($tempPath)
        );

        return [
            'path' => $dest,
            'url' => Storage::disk(self::DOCUMENTS_DISK)->url($dest),
            'original_name' => $originalName,
            'display_name' => $displayName,
            'storage' => 'local',
        ];
    }
}
