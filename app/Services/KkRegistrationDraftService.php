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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        protected CloudinaryService $cloudinary
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

        $wizard['step2_data'] = [
            'documents' => [
                $documentType => [
                    'type' => $documentType,
                    'sides' => $storedSides,
                ],
            ],
        ];
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 2);
        $wizard['expires_at'] = now()->addDays(7)->toIso8601String();

        return $this->persist($wizard);
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

    public function markVerificationSent(array $wizard): array
    {
        $wizard['verification_sent_at'] = now()->toIso8601String();
        $wizard['current_step'] = max((int) ($wizard['current_step'] ?? 1), 3);

        return $this->persist($wizard);
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

        $existingUser = User::where('email', $email)
            ->whereIn('status', ['ACTIVE', 'PENDING_APPROVAL', 'INACTIVE'])
            ->exists();

        $existingRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayId)
            ->whereIn('status', ['email_verified', 'password_set', 'active', 'pending_verification'])
            ->exists();

        if ($existingUser || $existingRegistration) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please use a different Gmail address.'],
            ]);
        }
    }

    public function commitWizard(array $wizard, string $password): KabataanRegistration
    {
        if (empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'step' => ['Registration data is incomplete. Please restart the wizard.'],
            ]);
        }

        if (empty($wizard['email_verified_at'])) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before completing registration.'],
            ]);
        }

        $barangay = Barangay::find($wizard['barangay_id'] ?? 0);

        if (! $barangay) {
            throw ValidationException::withMessages([
                'barangay' => ['Barangay not found. Please restart registration.'],
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
            $formData['supporting_documents'] = $this->promoteDocuments($wizard);

            $registration = KabataanRegistration::create([
                'tenant_id' => $barangay->tenant_id,
                'barangay_id' => $barangay->id,
                'last_name' => $step1['last_name'],
                'first_name' => $step1['first_name'],
                'middle_name' => $step1['middle_name'] ?? null,
                'suffix' => $this->resolvedSuffix($step1),
                'email' => $email,
                'contact_number' => $step1['contact_number'] ?? null,
                'profile_photo_path' => null,
                'form_data' => $formData,
                'status' => 'password_set',
                'email_verified_at' => $wizard['email_verified_at'] ?? now(),
                'submitted_at' => now(),
            ]);

            $user = User::create([
                'name' => $registration->full_name,
                'email' => $email,
                'password' => bcrypt($password),
                'email_verified_at' => now(),
                'tenant_id' => $registration->tenant_id,
                'barangay_id' => $registration->barangay_id,
                'role' => 'kabataan',
                'status' => 'PENDING_APPROVAL',
                'profile_image_url' => null,
                'profile_image_uploaded_at' => null,
            ]);

            $registration->markPasswordSet();
            $registration->linkUser($user->id);

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

            $registration = $registration->fresh();
            $this->rememberCompletedWizardToken($wizard['token'], $registration);
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
