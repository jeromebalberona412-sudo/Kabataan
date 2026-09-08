<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Notifications\KabataanSetPasswordEmail;
use App\Rules\PhilippineMobileNumber;
use App\Rules\ParticipantSignatureImage;
use App\Rules\ValidEmailAddress;
use App\Services\BarangayZoneService;
use App\Services\DuplicateKabataanRegistrationService;
use App\Services\IdImageQualityService;
use App\Services\IdVerificationAiService;
use App\Services\KkProfilingIdentityValidator;
use App\Services\KkRegistrationDraftService;
use App\Services\OCRService;
use App\Services\PhilippineIdDetectionService;
use App\Services\PhilippineIdPipelineService;
use App\Services\PhoneNumberService;
use App\Services\RegistrationEvaluationService;
use App\Services\SupportingDocumentVerificationRecorder;
use App\Services\InvalidEmailService;
use App\Services\TurnstileAttemptGuard;
use App\Services\TurnstileService;
use App\Support\MailUrl;
use App\Support\SupportingDocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KKProfilingWizardController extends Controller
{
    public function __construct(
        protected KkRegistrationDraftService $draftService,
        protected BarangayZoneService $barangayZoneService,
        protected DuplicateKabataanRegistrationService $duplicateChecker,
        protected PhilippineIdDetectionService $philippineIdDetection,
        protected PhilippineIdPipelineService $philippineIdPipeline,
        protected TurnstileService $turnstileService,
        protected TurnstileAttemptGuard $turnstileGuard,
        protected InvalidEmailService $invalidEmails,
        protected SupportingDocumentVerificationRecorder $documentVerificationRecorder,
        protected KkProfilingIdentityValidator $identityValidator,
        protected IdImageQualityService $idImageQuality,
        protected OCRService $ocrService,
        protected IdVerificationAiService $idVerificationAi,
    ) {}

    public function saveStep1(Request $request, string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $validated = $this->validateStep1($request, (int) $barangayRecord->id);

        if (trim((string) ($validated['email'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'email' => ['Enter an email address to continue to the next steps, or leave it blank and use Submit KK Profiling.'],
            ]);
        }

        $payload = $this->normalizeStep1Payload($request, $validated);

        $this->draftService->assertEmailAvailable(
            strtolower(trim((string) $validated['email'])),
            (int) $barangayRecord->id
        );

        $wizard = $this->draftService->createOrUpdateStep1(
            $barangayRecord,
            $payload,
            $request->input('respondent_number')
        );

        return response()->json([
            'success' => true,
            'token' => $wizard['token'],
            'step' => 2,
            'message' => 'Step 1 saved. Continue to supporting documents.',
            'email_verification_recommended' => true,
        ]);
    }

    /**
     * Autosave unfinished Step 1 fields for refresh / back-navigation recovery.
     * Does not advance the wizard and does not run full Step 1 validation.
     */
    public function saveStep1Draft(Request $request, string $barangay): JsonResponse
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $partial = $this->extractStep1DraftPayload($request);

        if ($partial === []) {
            return response()->json([
                'success' => true,
                'saved' => false,
                'message' => 'Nothing to save.',
            ]);
        }

        $wizard = $this->draftService->saveStep1Partial(
            $barangayRecord,
            $partial,
            $request->input('respondent_number')
        );

        return response()->json([
            'success' => true,
            'saved' => true,
            'token' => $wizard['token'],
            'draft' => $this->draftService->wizardStatusPayload($wizard),
        ]);
    }

    public function saveStep2(Request $request, string $barangay)
    {
        set_time_limit((int) config('ocr.timeout', 120) + 60);

        $this->assertTurnstilePassed($request);

        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        if (empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'document_type' => ['Please complete Step 1 (KK Profiling Form) before continuing.'],
            ]);
        }

        $skipDocuments = $request->boolean('skip_documents');
        $fileRule = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'];
        $ocrPayload = null;
        $formSuggestions = null;
        $privacyVerification = null;

        $validationRules = [
            'skip_documents' => ['sometimes', 'boolean'],
            'use_stored_documents' => ['sometimes', 'boolean'],
            'document_type' => [
                $skipDocuments ? 'nullable' : 'sometimes',
                'nullable',
                Rule::in(SupportingDocumentTypes::allowed()),
            ],
        ];

        foreach (SupportingDocumentTypes::allowed() as $type) {
            $validationRules[$type.'_front'] = $fileRule;
            $validationRules[$type.'_back'] = $fileRule;
        }

        $validationRules['selfie'] = $fileRule;

        $request->validate($validationRules);

        $documentType = $request->input('document_type');

        $uploadsByType = [];
        foreach (SupportingDocumentTypes::allowed() as $type) {
            $front = $request->file($type.'_front');
            $back = $request->file($type.'_back');
            if ($front || $back) {
                $uploadsByType[$type] = [
                    'front' => $front,
                    'back' => $back,
                ];
            }
        }

        $typesWithUpload = array_keys($uploadsByType);
        $hasAnyUpload = $typesWithUpload !== [];

        if ($skipDocuments && $hasAnyUpload) {
            throw ValidationException::withMessages([
                'document_type' => ['Remove uploaded files or upload both sides instead of skipping this step.'],
            ]);
        }

        if (! $skipDocuments && $hasAnyUpload) {
            if (count($typesWithUpload) > 1) {
                throw ValidationException::withMessages([
                    'document_type' => ['You can only upload one ID type at a time.'],
                ]);
            }

            $uploadedType = $typesWithUpload[0] ?? null;

            if (! $documentType) {
                throw ValidationException::withMessages([
                    'document_type' => ['Please select a document type.'],
                ]);
            }

            if ($uploadedType !== null && $documentType !== $uploadedType) {
                throw ValidationException::withMessages([
                    'document_type' => [
                        'Selected '.SupportingDocumentTypes::label((string) $documentType)
                        .' but files were uploaded for a different document type.',
                    ],
                ]);
            }

            $sides = $uploadsByType[$documentType] ?? [
                'front' => null,
                'back' => null,
            ];

            $uploadedSides = collect($sides)->filter(fn ($file) => $file instanceof UploadedFile);

            if ($uploadedSides->count() > 0 && $uploadedSides->count() < 2) {
                throw ValidationException::withMessages([
                    'document_type' => ['Please upload both front and back images of your ID.'],
                ]);
            }

            if ($uploadedSides->count() === 2) {
                if (config('documents.quality.enabled', true)) {
                    foreach (['front', 'back'] as $side) {
                        $sideFile = $sides[$side] ?? null;
                        if (! ($sideFile instanceof UploadedFile)) {
                            continue;
                        }

                        $quality = $this->idImageQuality->validateUpload($sideFile, $side);
                        if (! ($quality['ok'] ?? false)) {
                            throw ValidationException::withMessages([
                                $documentType.'_'.$side => [
                                    (string) ($quality['message'] ?? 'Please retake or upload a clearer ID photo.'),
                                ],
                            ]);
                        }
                    }
                }
                $frontUpload = $sides['front'] ?? null;
                $backUpload = $sides['back'] ?? null;

                if ($frontUpload instanceof UploadedFile && $backUpload instanceof UploadedFile) {
                    $sameSideError = $this->identicalFrontBackError($frontUpload, $backUpload);
                    if ($sameSideError !== null) {
                        throw ValidationException::withMessages([
                            'document_type' => [$sameSideError],
                        ]);
                    }
                }

                $wizard = $this->draftService->saveStep2($wizard, (string) $documentType, $sides);

                $selfieEnabled = (bool) config('documents.selfie_verification_enabled', false);
                $selfieRealPath = $selfieEnabled ? $request->file('selfie')?->getRealPath() : null;
                $registrationFields = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
                $registrationFields['_barangay_name'] = (string) $barangayRecord->name;
                $registrationFields['_municipality'] = 'Santa Cruz';
                $registrationFields['_province'] = 'Laguna';
                $registrationFields['_region'] = 'Region IV-A';

                try {
                    [$ocrPayload, $formSuggestions] = $this->runStep2DocumentValidation(
                        (string) $documentType,
                        $sides,
                        (int) $barangayRecord->id,
                        $registrationFields,
                        is_string($selfieRealPath) ? $selfieRealPath : null,
                    );
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (\Throwable $exception) {
                    report($exception);
                    Log::warning('KK wizard Step 2 document validation failed', [
                        'document_type' => $documentType,
                        'error' => $exception->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'document_type' => ['We couldn\'t process this document. Please try uploading a clearer photo of your ID.'],
                    ]);
                }

                $wizard = $this->draftService->storeIdVerification($wizard, is_array($ocrPayload) ? $ocrPayload : []);

                $this->assertStep2DocumentAllowedToProceed((string) $documentType, is_array($ocrPayload) ? $ocrPayload : []);

                try {
                    $privacyVerification = $this->documentVerificationRecorder->record(
                        $wizard,
                        (int) $barangayRecord->id,
                        (string) $documentType,
                        $sides,
                        is_array($ocrPayload) ? $ocrPayload : null,
                    );

                    $wizard = $this->draftService->storeIdVerification(
                        $wizard,
                        array_merge(
                            is_array($wizard['step2_data']['id_verification'] ?? null)
                                ? $wizard['step2_data']['id_verification']
                                : [],
                            [
                                'privacy_verification' => [
                                    'verification_status' => $privacyVerification['verification_status'] ?? null,
                                    'duplicate_status' => $privacyVerification['duplicate_status'] ?? null,
                                    'needs_review' => $privacyVerification['needs_review'] ?? false,
                                    'user_message' => $privacyVerification['user_message'] ?? null,
                                ],
                            ]
                        )
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                    Log::warning('KK wizard Step 2 privacy verification failed', [
                        'document_type' => $documentType,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } elseif (
            ! $skipDocuments
            && ! $hasAnyUpload
            && $request->boolean('use_stored_documents')
        ) {
            if (! is_string($documentType) || $documentType === '') {
                throw ValidationException::withMessages([
                    'document_type' => ['Please select a document type.'],
                ]);
            }

            if (! $this->draftService->hasStoredDocumentPair($wizard, (string) $documentType)) {
                throw ValidationException::withMessages([
                    'document_type' => ['Please upload both front and back images of your ID.'],
                ]);
            }

            $hasAnyUpload = true;
            $existingVerification = is_array($wizard['step2_data']['id_verification'] ?? null)
                ? $wizard['step2_data']['id_verification']
                : null;
            $ocrPayload = $existingVerification;
            $formSuggestions = is_array($existingVerification['form_suggestions'] ?? null)
                ? $existingVerification['form_suggestions']
                : null;

            if (is_array($existingVerification)) {
                $this->assertStep2DocumentAllowedToProceed((string) $documentType, $existingVerification);
            }
        }

        $wizard = $this->draftService->advanceToStep3($wizard);

        $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));
        $verificationSent = false;
        $emailError = null;

        try {
            $wizard = $this->dispatchWizardSetPasswordEmail($wizard, $barangayRecord);
            $verificationSent = true;
        } catch (ValidationException $e) {
            $emailError = $e->errors()['email'][0] ?? 'This email address is invalid and cannot receive mail.';
            Log::warning('KK wizard set-password email rejected', [
                'email' => $email,
                'barangay_id' => $barangayRecord->id,
                'error' => $emailError,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $emailError = 'Unable to send set password email. Please tap Resend set password link.';
            Log::warning('KK wizard set-password email failed after step 2', [
                'email' => $email,
                'barangay_id' => $barangayRecord->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'token' => $wizard['token'],
            'step' => 3,
            'email' => $email,
            'message' => $privacyVerification['user_message']
                ?? ($hasAnyUpload
                    ? 'Supporting documents saved. Continue to email verification.'
                    : 'Continue to email verification. You may upload an ID later if needed.'),
            'documents_uploaded' => $hasAnyUpload,
            'verification_sent' => $verificationSent,
            'email_error' => $emailError,
            'ocr' => isset($ocrPayload) && is_array($ocrPayload) ? $ocrPayload : null,
            'form_suggestions' => isset($formSuggestions) && is_array($formSuggestions) ? $formSuggestions : null,
            'document_verification' => is_array($privacyVerification) ? [
                'verification_status' => $privacyVerification['verification_status'] ?? null,
                'duplicate_status' => $privacyVerification['duplicate_status'] ?? null,
                'needs_review' => (bool) ($privacyVerification['needs_review'] ?? false),
                'user_message' => $privacyVerification['user_message'] ?? null,
            ] : null,
        ]);
    }

    public function sendVerification(Request $request, string $barangay)
    {
        if ($fail = $this->turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request)) {
            return response()->json([
                'success' => false,
                'message' => $fail,
                'errors' => [
                    'cf-turnstile-response' => [$fail],
                ],
                'turnstile_required' => true,
            ], 422);
        }

        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->draftService->resolveWizard();

        if (! $wizard || empty($wizard['step1_data'])) {
            if ($completed = $this->completedRegistrationResponse($barangayRecord)) {
                return $completed;
            }

            throw ValidationException::withMessages([
                'draft' => ['Your registration session expired. Please start again from Step 1.'],
            ]);
        }

        if ((int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            throw ValidationException::withMessages([
                'draft' => ['Your registration session does not match this barangay. Please restart registration.'],
            ]);
        }

        if (empty($wizard['email_verified_at'])) {
            $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

            if ($email === '' || ! $this->draftService->isEmailRegistrationComplete($email, (int) $barangayRecord->id)) {
                $this->draftService->clearCompletedRegistration();
            }
        }

        $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['Email address is missing from your registration session.'],
            ]);
        }

        try {
            $wizard = $this->dispatchWizardSetPasswordEmail($wizard, $barangayRecord);
        } catch (ValidationException $e) {
            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

            if ($attempt['threshold_reached']) {
                return response()->json([
                    'success' => false,
                    'message' => $attempt['message'] ?? 'Too many unsuccessful attempts. Please complete the security verification before trying again.',
                    'errors' => [
                        'email' => [$attempt['message'] ?? 'Too many unsuccessful attempts. Please complete the security verification before trying again.'],
                    ],
                    'turnstile_required' => true,
                ], 422);
            }

            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('KK wizard set-password email failed on send-verification', [
                'email' => $email,
                'barangay_id' => $barangayRecord->id,
                'error' => $e->getMessage(),
            ]);

            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);
            $message = $attempt['message']
                ?? 'Unable to send set password email right now. Please tap Resend set password link to try again.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [
                    'email' => [$message],
                ],
                'turnstile_required' => (bool) $attempt['turnstile_required'],
            ], 422);
        }

        $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

        return response()->json([
            'success' => true,
            'email' => $email,
            'verification_sent' => true,
            'message' => 'Set password link sent. Please check your inbox.',
            'turnstile_required' => (bool) $attempt['turnstile_required'],
        ]);
    }

    public function resendVerification(Request $request, string $barangay)
    {
        return $this->sendVerification($request, $barangay);
    }

    public function openSetPasswordFromEmail(Request $request, string $token, string $hash)
    {
        $wizard = $this->draftService->loadByToken($token);

        if (! $wizard) {
            $completed = $this->draftService->resolveCompletedByWizardToken($token);
            $emailFromCache = strtolower(trim($completed['email'] ?? ''));

            if ($completed && $emailFromCache !== '' && hash_equals($hash, sha1($emailFromCache))) {
                $barangayRecord = Barangay::find((int) ($completed['barangay_id'] ?? 0));

                if ($barangayRecord) {
                    $registration = KabataanRegistration::query()
                        ->where('barangay_id', $barangayRecord->id)
                        ->where('email', $emailFromCache)
                        ->whereIn('status', ['password_set', 'active'])
                        ->latest('id')
                        ->first();

                    return $this->renderRegistrationCompleteView($barangayRecord, $emailFromCache, $registration);
                }
            }

            return redirect()->route('sign-in')->with('success', 'Your registration has already been submitted. Please wait for SK officials to verify your account before logging in.');
        }

        $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

        if ($email === '' || ! hash_equals($hash, sha1($email))) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'verification' => 'The verification link is invalid.',
            ]);
        }

        $barangayRecord = Barangay::find((int) $wizard['barangay_id']);

        if (! $barangayRecord) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'verification' => 'Barangay not found for this registration.',
            ]);
        }

        if ($this->draftService->isEmailRegistrationComplete($email, (int) $barangayRecord->id)) {
            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayRecord->id)
                ->where('email', $email)
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();

            return $this->renderRegistrationCompleteView($barangayRecord, $email, $registration);
        }

        if ($this->draftService->isExpiredWizard($wizard)) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'verification' => 'This registration link has expired. Please start again.',
            ]);
        }

        try {
            $this->draftService->assertEmailAvailable($email, (int) $wizard['barangay_id']);
        } catch (ValidationException $e) {
            $slug = $this->barangaySlugFromId((int) $wizard['barangay_id']);

            return redirect()
                ->route('kkprofiling', ['barangay' => $slug])
                ->withErrors($e->errors())
                ->with('kk_wizard_email_conflict', true);
        }

        $wizard = $this->draftService->markEmailVerified($wizard);
        $this->draftService->syncWizardSession($wizard);

        return view('kkprofiling::set_password', [
            'wizardToken' => $token,
            'barangay' => $barangayRecord->name,
            'slug' => $this->barangaySlugFromId((int) $barangayRecord->id),
            'email' => $email,
            'emailVerified' => true,
            'barangayLogoUrl' => KKProfilingController::getBarangayLogoUrl($barangayRecord->id),
        ]);
    }

    public function finalizeByToken(Request $request, string $token)
    {
        set_time_limit((int) config('kkprofiling.finalize_time_limit', 180));

        $this->assertTurnstilePassed($request);

        $wizard = $this->draftService->loadByToken($token);

        if (! $wizard) {
            $completed = $this->draftService->resolveCompletedByWizardToken($token);

            if (is_array($completed) && ! empty($completed['email'])) {
                $registration = KabataanRegistration::query()
                    ->where('barangay_id', (int) ($completed['barangay_id'] ?? 0))
                    ->where('email', strtolower(trim((string) $completed['email'])))
                    ->whereIn('status', ['password_set', 'active'])
                    ->latest('id')
                    ->first();

                if ($registration) {
                    return $this->finalizeRegistrationResponse($registration);
                }
            }

            throw ValidationException::withMessages([
                'draft' => ['This registration link has expired or was already completed.'],
            ]);
        }

        $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, number, and special character.',
        ]);

        if (empty($wizard['email_verified_at'])) {
            $wizard = $this->draftService->markEmailVerified($wizard);
        }

        $registration = $this->draftService->commitWizard($wizard, $request->password);

        return $this->finalizeRegistrationResponse($registration);
    }

    public function checkRegistrationComplete(Request $request, string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($request->input('email', '')));

        $completed = KabataanRegistration::query()
            ->where('barangay_id', $barangayRecord->id)
            ->where('email', $email)
            ->whereIn('status', ['password_set', 'active'])
            ->first();

        if ($completed) {
            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayRecord->id)
                ->where('email', $email)
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();

            $this->draftService->markRegistrationComplete($email, (int) $barangayRecord->id, $registration);
        }

        return response()->json([
            'completed' => $completed !== null,
            'auto_approved' => $completed
                ? RegistrationEvaluationService::isAutoApprovedStatus($completed->evaluation_status)
                : false,
            'evaluation_status' => $completed?->evaluation_status,
        ]);
    }

    public function finalize(Request $request, string $barangay)
    {
        set_time_limit((int) config('kkprofiling.finalize_time_limit', 180));

        $this->assertTurnstilePassed($request);

        $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, number, and special character.',
        ]);

        if (empty($wizard['email_verified_at'])) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before completing registration.'],
            ]);
        }

        $registration = $this->draftService->commitWizard($wizard, $request->password);

        $response = $this->finalizeRegistrationResponse($registration);
        $payload = $response->getData(true);
        $payload['redirect'] = '/youth/login';

        return response()->json($payload);
    }

    public function status(string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);

        $wizard = $this->draftService->resolveWizard();

        if ($wizard && (int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            $wizard = null;
        }

        $hasActiveUnfinishedDraft = is_array($wizard) && ! empty($wizard['step1_data']);

        // Prefer restoring an unfinished draft over a leftover success session.
        if ($hasActiveUnfinishedDraft) {
            $this->draftService->clearCompletedRegistration();

            return response()->json([
                'draft' => $this->draftService->wizardStatusPayload($wizard),
            ]);
        }

        if ($completed = $this->draftService->resolveCompletedRegistration((int) $barangayRecord->id)) {
            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayRecord->id)
                ->where('email', strtolower(trim($completed['email'] ?? '')))
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();

            $autoApproved = $registration
                ? RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)
                : (bool) ($completed['auto_approved'] ?? false);

            if ($registration) {
                $this->draftService->markRegistrationComplete(
                    (string) $completed['email'],
                    (int) $barangayRecord->id,
                    $registration,
                );
            }

            return response()->json([
                'draft' => null,
                'registration_completed' => true,
                'email' => $completed['email'],
                'auto_approved' => $autoApproved,
                'evaluation_status' => $registration?->evaluation_status ?? ($completed['evaluation_status'] ?? null),
            ]);
        }

        return response()->json([
            'draft' => $this->draftService->wizardStatusPayload($wizard),
        ]);
    }

    public function setStep(Request $request, string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        if ((int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            throw ValidationException::withMessages([
                'draft' => ['Your registration session does not match this barangay.'],
            ]);
        }

        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $targetStep = (int) $validated['step'];

        if ($targetStep === 3 && empty($wizard['step1_data'])) {
            throw ValidationException::withMessages([
                'step' => ['Please complete Step 1 before continuing.'],
            ]);
        }

        // Allow restoring Step 3 UI after mobile/desktop toggles even if the
        // verification email has not been resent yet in this browser session.
        if (
            $targetStep === 3
            && empty($wizard['verification_sent_at'])
            && empty($wizard['step2_data'])
            && empty($wizard['email'])
        ) {
            throw ValidationException::withMessages([
                'step' => ['Please complete Step 2 before opening email verification.'],
            ]);
        }

        $wizard = $this->draftService->setWizardStep($wizard, $targetStep);

        return response()->json([
            'success' => true,
            'step' => $targetStep,
            'draft' => $this->draftService->wizardStatusPayload($wizard),
        ]);
    }

    public function clearDraft(string $barangay)
    {
        $this->resolveBarangay($barangay);
        $this->draftService->clearSessionDraft();
        $this->draftService->clearCompletedRegistration();

        return response()->json([
            'success' => true,
            'message' => 'Registration draft cleared.',
        ]);
    }

    public function detectId(Request $request, string $barangay)
    {
        // Keep PHP under a tight budget so stuck Gemini/network calls fail instead of hanging.
        if (function_exists('set_time_limit')) {
            $geminiTimeout = max(12, min(25, (int) config('ocr.gemini.timeout', 18)));
            @set_time_limit($geminiTimeout + 20);
        }

        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        if ((int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            throw ValidationException::withMessages([
                'draft' => ['Your registration session does not match this barangay.'],
            ]);
        }

        $request->validate([
            'document_type' => ['required', Rule::in(['national_id', 'philhealth_id', 'voters_id', 'school_id', 'other_id'])],
            'front' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'back' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $frontFile = $request->file('front');
        $backFile = $request->file('back');

        if ($frontFile instanceof UploadedFile && $backFile instanceof UploadedFile) {
            $sameSideError = $this->identicalFrontBackError($frontFile, $backFile);
            if ($sameSideError !== null) {
                return response()->json([
                    'success' => false,
                    'validation_error' => true,
                    'message' => $sameSideError,
                    'ocr' => [
                        'success' => false,
                        'validation_error' => true,
                        'needs_review' => false,
                        'verification_status' => 'invalid_image',
                        'document_detected' => null,
                        'id_type' => 'Unknown',
                        'confidence' => 0,
                        'ocr_status' => 'invalid_upload',
                        'message' => $sameSideError,
                    ],
                    'form_suggestions' => [],
                ], 422);
            }
        }

        if (config('documents.quality.enabled', true)) {
            foreach ([['file' => $frontFile, 'side' => 'front'], ['file' => $backFile, 'side' => 'back']] as $item) {
                if (! ($item['file'] instanceof UploadedFile)) {
                    continue;
                }

                $quality = $this->idImageQuality->validateUpload($item['file'], $item['side']);
                if (! ($quality['ok'] ?? false)) {
                    $message = (string) ($quality['message'] ?? 'Please retake or upload a clearer ID photo.');

                    return response()->json([
                        'success' => false,
                        'validation_error' => true,
                        'quality_error' => true,
                        'quality_code' => $quality['code'] ?? null,
                        'message' => $message,
                        'ocr' => [
                            'success' => false,
                            'validation_error' => true,
                            'quality_error' => true,
                            'quality_code' => $quality['code'] ?? null,
                            'needs_review' => false,
                            'verification_status' => 'invalid_image',
                            'document_detected' => null,
                            'id_type' => 'Unknown',
                            'confidence' => 0,
                            'ocr_status' => 'invalid_image',
                            'message' => $message,
                        ],
                        'form_suggestions' => [],
                    ], 422);
                }
            }
        }

        $documentType = (string) $request->input('document_type');
        $registrationFields = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
        $registrationFields['_barangay_name'] = (string) $barangayRecord->name;
        $registrationFields['_municipality'] = 'Santa Cruz';
        $registrationFields['_province'] = 'Laguna';
        $registrationFields['_region'] = 'Region IV-A';
        $frontPath = $frontFile?->getRealPath();
        $backPath = $backFile?->getRealPath();
        $selfiePath = config('documents.selfie_verification_enabled')
            ? $request->file('selfie')?->getRealPath()
            : null;

        // Persist uploads immediately so refresh (mobile / desktop site) keeps the images.
        if ($frontFile instanceof UploadedFile && $backFile instanceof UploadedFile) {
            $wizard = $this->draftService->saveStep2($wizard, $documentType, [
                'front' => $frontFile,
                'back' => $backFile,
            ]);
        }

        // Reuse AI extraction for the same images, but ALWAYS re-check against current Step 1 identity.
        if (is_string($frontPath) && is_string($backPath)) {
            $pairHash = $this->idVerificationAi->pairHash($frontPath, $backPath, $documentType);
            $existing = is_array($wizard['step2_data']['id_verification'] ?? null)
                ? $wizard['step2_data']['id_verification']
                : null;

            if (
                is_array($existing)
                && is_string($existing['pair_hash'] ?? null)
                && hash_equals((string) $existing['pair_hash'], $pairHash)
                && $this->isReusableStoredVerification($existing)
            ) {
                $payload = [
                    'success' => (bool) ($existing['success'] ?? false),
                    'validation_error' => false,
                    'needs_review' => (bool) ($existing['needs_review'] ?? false),
                    'verification_status' => $existing['verification_status'] ?? 'success',
                    'document_detected' => $existing['document_detected'] ?? null,
                    'id_type' => $existing['id_type'] ?? 'Unknown',
                    'detected_id_type' => $existing['detected_id_type'] ?? ($existing['id_type'] ?? null),
                    'expected_id_type' => $documentType,
                    'confidence' => $existing['confidence'] ?? 0,
                    'confidence_band' => $existing['confidence_band'] ?? null,
                    'full_name' => $existing['detected_name'] ?? null,
                    'given_name' => $existing['form_suggestions']['first_name'] ?? null,
                    'middle_name' => $existing['form_suggestions']['middle_name'] ?? null,
                    'surname' => $existing['form_suggestions']['last_name'] ?? null,
                    'birthdate' => $existing['detected_birthdate'] ?? null,
                    'sex' => $existing['detected_sex'] ?? null,
                    'address' => $existing['detected_address'] ?? null,
                    'id_number' => $existing['id_number'] ?? null,
                    'ocr_status' => $existing['ocr_status'] ?? null,
                    'message' => null,
                    'raw_text' => $existing['raw_text'] ?? null,
                    'pair_hash' => $pairHash,
                    'from_cache' => true,
                    'source' => $existing['source'] ?? 'wizard_cache',
                    'is_philippine_id' => $existing['is_philippine_id'] ?? null,
                    'image_quality' => $existing['image_quality'] ?? null,
                    'expiry_date' => $existing['expiry_date'] ?? null,
                    'data' => is_array($existing['data'] ?? null) ? $existing['data'] : null,
                ];

                // Re-apply Step 1 name/birthday/address gates with the latest profiling fields.
                $verification = $this->philippineIdDetection->buildVerificationRecord(
                    $payload,
                    $documentType,
                    $registrationFields,
                );
                $this->draftService->storeIdVerification($wizard, $verification);

                $formSuggestions = is_array($verification['form_suggestions'] ?? null)
                    ? $verification['form_suggestions']
                    : $this->philippineIdDetection->mapToFormFields($payload);

                $publicOcr = [
                    'success' => (bool) ($verification['success'] ?? false),
                    'validation_error' => (bool) ($verification['validation_error'] ?? false),
                    'needs_review' => (bool) ($verification['needs_review'] ?? false),
                    'verification_status' => $verification['verification_status'] ?? 'success',
                    'document_detected' => $verification['document_detected'] ?? null,
                    'id_type' => $verification['id_type'] ?? 'Unknown',
                    'detected_id_type' => $verification['detected_id_type'] ?? ($payload['detected_id_type'] ?? null),
                    'expected_id_type' => $documentType,
                    'confidence' => $verification['confidence'] ?? 0,
                    'full_name' => $verification['detected_name'] ?? null,
                    'birthdate' => $verification['detected_birthdate'] ?? null,
                    'sex' => $verification['detected_sex'] ?? null,
                    'address' => $verification['detected_address'] ?? null,
                    'id_number' => $verification['id_number'] ?? null,
                    'message' => $this->sanitizePublicVerificationMessage($verification['message'] ?? null),
                    'name_match' => $verification['name_match'] ?? null,
                    'birthdate_match' => $verification['birthdate_match'] ?? null,
                    'sex_match' => $verification['sex_match'] ?? null,
                    'address_match' => $verification['address_match'] ?? null,
                    'pair_hash' => $pairHash,
                    'from_cache' => true,
                    'source' => $verification['source'] ?? 'wizard_cache',
                ];

                Log::info('ID detect-id reused AI extraction; rechecked Step 1 identity', [
                    'pair_hash' => $pairHash,
                    'name_match' => $verification['name_match'] ?? null,
                    'validation_error' => $verification['validation_error'] ?? null,
                ]);

                return response()->json([
                    'success' => (bool) ($verification['success'] ?? false),
                    'ocr' => $publicOcr,
                    'form_suggestions' => $formSuggestions,
                    'message' => $publicOcr['message'],
                    'validation_error' => (bool) ($verification['validation_error'] ?? false),
                    'needs_review' => (bool) ($verification['needs_review'] ?? false),
                    'verification_status' => $verification['verification_status'] ?? null,
                    'from_cache' => true,
                ], ($verification['validation_error'] ?? false) ? 422 : 200);
            }
        }

        if (
            $this->philippineIdDetection->isSupportedDocumentType($documentType)
            && config('ocr.philippine_pipeline_enabled', true)
            && $this->philippineIdPipeline->isConfigured()
            && is_string($frontPath)
            && is_string($backPath)
        ) {
            try {
                $pipelineResult = $this->philippineIdPipeline->validate(
                    (int) $barangayRecord->id,
                    $registrationFields,
                    $frontPath,
                    $backPath,
                    $documentType,
                    is_string($selfiePath) ? $selfiePath : null,
                );

                if (
                    is_array($pipelineResult)
                    && $this->pipelineResultHasReadableSignal($pipelineResult)
                    && $this->pipelineResultMatchesSelectedType($pipelineResult, $documentType)
                ) {
                    $pipelineRaw = trim((string) (
                        $pipelineResult['raw_text']
                        ?? data_get($pipelineResult, 'ocr.raw_text')
                        ?? data_get($pipelineResult, 'ocr.full_text')
                        ?? ''
                    ));

                    $payload = [
                        'success' => (bool) ($pipelineResult['success'] ?? false),
                        'id_type' => $pipelineResult['id_type'] ?? 'Unknown',
                        'detected_id_type' => $pipelineResult['detected_id_type'] ?? null,
                        'expected_id_type' => $pipelineResult['expected_id_type'] ?? null,
                        'confidence' => $pipelineResult['confidence'] ?? 0,
                        'full_name' => $pipelineResult['detected_name'] ?? null,
                        'birthdate' => $pipelineResult['detected_birthdate'] ?? null,
                        'sex' => $pipelineResult['detected_sex'] ?? null,
                        'address' => $pipelineResult['detected_address'] ?? null,
                        'id_number' => $pipelineResult['id_number'] ?? null,
                        'face_match' => $pipelineResult['face_match'] ?? false,
                        'face_verification' => $pipelineResult['face_verification'] ?? null,
                        'validation_error' => (bool) ($pipelineResult['validation_error'] ?? false),
                        'needs_review' => (bool) ($pipelineResult['needs_review'] ?? false),
                        'document_detected' => $pipelineResult['document_detected'] ?? 'yes',
                        'message' => $pipelineResult['message'] ?? null,
                        'ocr' => $pipelineResult['ocr'] ?? [],
                        'raw_text' => $pipelineRaw,
                    ];

                    if (($payload['document_detected'] ?? '') === 'no' && ! ($payload['success'] ?? false)) {
                        $payload['validation_error'] = true;
                        $payload['message'] = $payload['message']
                            ?: 'This does not appear to be a valid ID. Please scan or upload a clear front and back photo of your selected ID.';
                    }

                    $formSuggestions = $this->philippineIdDetection->mapToFormFields($payload);
                    $verification = array_merge($pipelineResult, [
                        'form_suggestions' => $formSuggestions,
                    ]);
                    $this->draftService->storeIdVerification($wizard, $verification);

                    return response()->json([
                        'success' => (bool) ($pipelineResult['success'] ?? false),
                        'ocr' => $payload,
                        'form_suggestions' => $formSuggestions,
                        'message' => $pipelineResult['message'] ?? null,
                        'validation_error' => (bool) ($payload['validation_error'] ?? false),
                        'face_match' => (bool) ($pipelineResult['face_match'] ?? false),
                        'requires_selfie' => ! ($pipelineResult['face_match'] ?? false)
                            && empty($pipelineResult['face_verification']['available']),
                    ], ($payload['validation_error'] ?? false) ? 422 : 200);
                }
            } catch (\Throwable $exception) {
                report($exception);
                Log::warning('Philippine ID pipeline failed in detect-id', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $payload = $this->philippineIdDetection->detectUploadedPair(
                $request->file('front'),
                $request->file('back'),
                $documentType,
                $registrationFields,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'validation_error' => true,
                'message' => 'Unable to scan your ID right now. Please try again with clearer front and back photos.',
                'ocr' => [
                    'success' => false,
                    'validation_error' => true,
                    'message' => 'Unable to scan your ID right now. Please try again with clearer front and back photos.',
                ],
                'form_suggestions' => [],
            ], 422);
        }

        $formSuggestions = $this->philippineIdDetection->mapToFormFields($payload);
        $verification = $this->philippineIdDetection->buildVerificationRecord(
            $payload,
            $documentType,
            $registrationFields,
        );
        $this->draftService->storeIdVerification($wizard, $verification);

        $publicOcr = [
            'success' => (bool) ($verification['success'] ?? false),
            'validation_error' => (bool) ($verification['validation_error'] ?? false),
            'needs_review' => (bool) ($verification['needs_review'] ?? false),
            'verification_status' => $verification['verification_status'] ?? ($payload['verification_status'] ?? null),
            'document_detected' => array_key_exists('document_detected', $verification)
                ? $verification['document_detected']
                : (array_key_exists('document_detected', $payload) ? $payload['document_detected'] : null),
            'id_type' => $verification['id_type'] ?? ($payload['id_type'] ?? 'Unknown'),
            'detected_id_type' => $payload['detected_id_type'] ?? null,
            'expected_id_type' => $payload['expected_id_type'] ?? null,
            'confidence' => $verification['confidence'] ?? ($payload['confidence'] ?? 0),
            'confidence_band' => $verification['confidence_band'] ?? ($payload['confidence_band'] ?? null),
            'full_name' => $verification['detected_name'] ?? ($payload['full_name'] ?? null),
            'birthdate' => $verification['detected_birthdate'] ?? ($payload['birthdate'] ?? null),
            'sex' => $verification['detected_sex'] ?? ($payload['sex'] ?? null),
            'address' => $verification['detected_address'] ?? ($payload['address'] ?? null),
            'id_number' => $verification['id_number'] ?? ($payload['id_number'] ?? null),
            'ocr_status' => $verification['ocr_status'] ?? ($payload['ocr_status'] ?? null),
            'message' => $this->sanitizePublicVerificationMessage($verification['message'] ?? ($payload['message'] ?? null)),
            'name_match' => $verification['name_match'] ?? null,
            'birthdate_match' => $verification['birthdate_match'] ?? null,
            'sex_match' => $verification['sex_match'] ?? null,
            'address_match' => $verification['address_match'] ?? null,
            'face_match' => $payload['face_match'] ?? false,
            'face_verification' => $payload['face_verification'] ?? null,
            'text_length' => (int) ($payload['text_length'] ?? 0),
            'source' => $payload['source'] ?? null,
            'auto_detected' => (bool) ($payload['auto_detected'] ?? false),
            'auto_corrected' => (bool) ($payload['auto_corrected'] ?? false),
            'pair_hash' => $verification['pair_hash'] ?? ($payload['pair_hash'] ?? null),
            'from_cache' => (bool) ($verification['from_cache'] ?? ($payload['from_cache'] ?? false)),
            'is_philippine_id' => $verification['is_philippine_id'] ?? ($payload['is_philippine_id'] ?? null),
            'image_quality' => $verification['image_quality'] ?? ($payload['image_quality'] ?? null),
            'expiry_date' => $verification['expiry_date'] ?? ($payload['expiry_date'] ?? null),
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : null,
        ];

        return response()->json([
            'success' => (bool) ($verification['success'] ?? false),
            'ocr' => $publicOcr,
            'form_suggestions' => $formSuggestions,
            'message' => $this->sanitizePublicVerificationMessage($verification['message'] ?? ($payload['message'] ?? null)),
            'validation_error' => (bool) ($verification['validation_error'] ?? false),
            'needs_review' => (bool) ($verification['needs_review'] ?? false),
            'verification_status' => $verification['verification_status'] ?? ($payload['verification_status'] ?? null),
            'face_match' => (bool) ($payload['face_match'] ?? false),
            'from_cache' => (bool) ($publicOcr['from_cache'] ?? false),
        ], ($verification['validation_error'] ?? false) ? 422 : 200);
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function isReusableStoredVerification(array $verification): bool
    {
        $status = strtolower((string) ($verification['verification_status'] ?? ''));
        if (in_array($status, ['unavailable', 'error', 'invalid_response'], true)) {
            return false;
        }

        $detected = $verification['document_detected'] ?? null;
        if ($detected === null || $detected === '') {
            return $status === 'invalid_image';
        }

        return in_array($detected, [true, false, 'yes', 'no', 1, 0, '1', '0'], true)
            || $status === 'success';
    }

    public function documentPreview(string $barangay, string $type, ?string $side = 'front')
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        if ((int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            abort(404);
        }

        if (! in_array($side, SupportingDocumentTypes::sides(), true)) {
            abort(404);
        }

        $document = $wizard['step2_data']['documents'][$type] ?? null;

        if (! is_array($document)) {
            abort(404);
        }

        $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];
        $meta = is_array($sides[$side] ?? null)
            ? $sides[$side]
            : ($side === 'front' && ! empty($document['path']) ? $document : null);

        if (! is_array($meta) || empty($meta['path'])) {
            abort(404);
        }

        $disk = Storage::disk(KkRegistrationDraftService::TEMP_DISK);

        if (! $disk->exists($meta['path'])) {
            abort(404);
        }

        return response()->file($disk->path($meta['path']), [
            'Content-Type' => $meta['mime'] ?? 'image/jpeg',
        ]);
    }

    private function completedRegistrationResponse(Barangay $barangayRecord): ?JsonResponse
    {
        $completed = $this->draftService->resolveCompletedRegistration((int) $barangayRecord->id);

        if (! $completed) {
            return null;
        }

        $autoApproved = (bool) ($completed['auto_approved'] ?? false);
        $registration = KabataanRegistration::query()
            ->where('barangay_id', $barangayRecord->id)
            ->where('email', strtolower(trim($completed['email'] ?? '')))
            ->whereIn('status', ['password_set', 'active'])
            ->latest('id')
            ->first();

        if ($registration) {
            $autoApproved = RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status);
        }

        return response()->json([
            'success' => true,
            'registration_completed' => true,
            'email' => $completed['email'],
            'auto_approved' => $autoApproved,
            'evaluation_status' => $registration?->evaluation_status ?? ($completed['evaluation_status'] ?? null),
            'message' => 'Your registration has been submitted. Please wait for SK officials to verify your account.',
        ]);
    }

    private function requireWizard(): array
    {
        $wizard = $this->draftService->resolveWizard();

        if (! $wizard) {
            throw ValidationException::withMessages([
                'draft' => ['Your registration session expired. Please start again from Step 1.'],
            ]);
        }

        return $wizard;
    }

    /**
     * Detect/validate uploaded supporting ID images for Step 2.
     *
     * @param  array<string, UploadedFile|null>  $sides
     * @param  array<string, mixed>  $registrationFields
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function runStep2DocumentValidation(
        string $documentType,
        array $sides,
        int $barangayId,
        array $registrationFields,
        ?string $selfieRealPath = null,
    ): array {
        $front = $sides['front'] ?? null;
        $back = $sides['back'] ?? null;
        $frontPath = $front instanceof UploadedFile ? $front->getRealPath() : null;
        $backPath = $back instanceof UploadedFile ? $back->getRealPath() : null;

        if (! is_string($frontPath) || ! is_string($backPath)) {
            throw ValidationException::withMessages([
                'document_type' => ['Please upload both front and back images of your ID.'],
            ]);
        }

        if ($documentType === SupportingDocumentTypes::SCHOOL_ID) {
            // Prefer Gemini vision path (shared with other ID types) when enabled.
            if (
                in_array(strtolower((string) config('ocr.provider', 'gemini')), ['gemini', 'groq'], true)
                && $this->idVerificationAi->isEnabled()
                && $front instanceof UploadedFile
                && $back instanceof UploadedFile
            ) {
                $detected = $this->philippineIdDetection->detectUploadedPair($front, $back, $documentType, $registrationFields);
                $ocrPayload = $this->philippineIdDetection->buildVerificationRecord(
                    $detected,
                    $documentType,
                    $registrationFields,
                );

                return [$ocrPayload, is_array($ocrPayload['form_suggestions'] ?? null) ? $ocrPayload['form_suggestions'] : null];
            }

            $result = $this->identityValidator->validateUploadedId(
                $barangayId,
                $registrationFields,
                $frontPath,
                $backPath,
                $documentType,
            );

            $payload = array_merge($result, [
                'validation_error' => ! $this->step2DocumentLooksValid($documentType, $result),
                'success' => (bool) ($result['success'] ?? false),
                'id_type' => $result['id_type'] ?? 'School ID',
                'confidence' => $result['overall_confidence'] ?? $result['confidence'] ?? 0,
                'detected_name' => $result['detected_full_name'] ?? $result['detected_name'] ?? null,
                'form_suggestions' => $result['form_suggestions'] ?? null,
            ]);

            return [$payload, is_array($payload['form_suggestions'] ?? null) ? $payload['form_suggestions'] : null];
        }

        if ($this->philippineIdDetection->isSupportedDocumentType($documentType)) {
            $ocrPayload = null;

            if (
                config('ocr.philippine_pipeline_enabled', true)
                && $this->philippineIdPipeline->isConfigured()
            ) {
                try {
                    $pipelineResult = $this->philippineIdPipeline->validate(
                        $barangayId,
                        $registrationFields,
                        $frontPath,
                        $backPath,
                        $documentType,
                        $selfieRealPath,
                    );

                    if (
                        is_array($pipelineResult)
                        && $this->pipelineResultHasReadableSignal($pipelineResult)
                        && $this->pipelineResultMatchesSelectedType($pipelineResult, $documentType)
                    ) {
                        $ocrPayload = array_merge($pipelineResult, [
                            'form_suggestions' => $this->philippineIdDetection->mapToFormFields([
                                'success' => $pipelineResult['success'] ?? false,
                                'id_type' => $pipelineResult['id_type'] ?? 'Unknown',
                                'full_name' => $pipelineResult['detected_name'] ?? null,
                                'birthdate' => $pipelineResult['detected_birthdate'] ?? null,
                                'sex' => $pipelineResult['detected_sex'] ?? null,
                                'address' => $pipelineResult['detected_address'] ?? null,
                                'id_number' => $pipelineResult['id_number'] ?? null,
                                'confidence' => $pipelineResult['confidence'] ?? 0,
                            ]),
                        ]);
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                    Log::warning('Philippine ID pipeline failed in step-2 validation', [
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if (! is_array($ocrPayload)) {
                if (! $front instanceof UploadedFile || ! $back instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'document_type' => ['Please upload both front and back images of your ID.'],
                    ]);
                }

                $detected = $this->philippineIdDetection->detectUploadedPair($front, $back, $documentType, $registrationFields);
                $ocrPayload = $this->philippineIdDetection->buildVerificationRecord(
                    $detected,
                    $documentType,
                    $registrationFields,
                );
            }

            return [$ocrPayload, is_array($ocrPayload['form_suggestions'] ?? null) ? $ocrPayload['form_suggestions'] : null];
        }

        // other_id — require the upload to look like a readable supporting document
        if (! $front instanceof UploadedFile || ! $back instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document_type' => ['Please upload both front and back images of your ID.'],
            ]);
        }

        $detected = $this->philippineIdDetection->detectUploadedPair($front, $back, $documentType, $registrationFields);
        $ocrPayload = $this->philippineIdDetection->buildVerificationRecord(
            $detected,
            $documentType,
            $registrationFields,
        );

        return [$ocrPayload, is_array($ocrPayload['form_suggestions'] ?? null) ? $ocrPayload['form_suggestions'] : null];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertStep2DocumentAllowedToProceed(string $documentType, array $payload): void
    {
        // Supporting ID upload is optional for continuing to Step 3.
        // Invalid / weak detections are stored for SK review but must not block Next.
        if ($this->step2DocumentLooksValid($documentType, $payload)) {
            return;
        }

        Log::info('KK wizard Step 2 ID verification soft-fail; allowing continue', [
            'document_type' => $documentType,
            'verification_status' => $payload['verification_status'] ?? null,
            'document_detected' => $payload['document_detected'] ?? null,
            'success' => $payload['success'] ?? null,
        ]);
    }

    /**
     * Reject when front and back are the same photo (exact or near-duplicate).
     */
    private function identicalFrontBackError(UploadedFile $front, UploadedFile $back): ?string
    {
        $frontPath = $front->getRealPath();
        $backPath = $back->getRealPath();

        if (! is_string($frontPath) || ! is_string($backPath) || ! is_file($frontPath) || ! is_file($backPath)) {
            return null;
        }

        if (! $this->ocrService->frontAndBackAreSameImage($frontPath, $backPath)) {
            return null;
        }

        return 'Front and back must be different photos. You uploaded the same image for both sides. Please upload the real front and the real back of your ID.';
    }

    /**
     * Prefer local OCR when the Python pipeline returns an empty/unknown/non-ID result.
     *
     * @param  array<string, mixed>  $pipelineResult
     */
    private function pipelineResultHasReadableSignal(array $pipelineResult): bool
    {
        if (($pipelineResult['validation_error'] ?? false) === true) {
            return false;
        }

        $rawText = trim((string) (
            $pipelineResult['raw_text']
            ?? data_get($pipelineResult, 'ocr.raw_text')
            ?? data_get($pipelineResult, 'ocr.full_text')
            ?? ''
        ));
        $message = strtolower((string) ($pipelineResult['message'] ?? ''));

        if (
            str_contains($message, 'couldn\'t read any text')
            || str_contains($message, 'no text')
            || str_contains($message, 'unavailable')
            || str_contains($message, 'timed out')
        ) {
            return false;
        }

        // Name-only / type-only hits without ID-like OCR text are not trustworthy.
        if ($rawText === '' || ! $this->ocrService->looksLikeSupportingIdText($rawText)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $pipelineResult
     */
    private function pipelineResultMatchesSelectedType(array $pipelineResult, string $documentType): bool
    {
        $rawText = trim((string) (
            $pipelineResult['raw_text']
            ?? data_get($pipelineResult, 'ocr.raw_text')
            ?? data_get($pipelineResult, 'ocr.full_text')
            ?? ''
        ));

        if ($rawText === '') {
            return false;
        }

        $scores = is_array($pipelineResult['scores'] ?? null) ? $pipelineResult['scores'] : [];

        return $this->ocrService->supportsSelectedIdType($rawText, $documentType, $scores);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function step2DocumentLooksValid(string $documentType, array $payload): bool
    {
        if (($payload['validation_error'] ?? false) === true) {
            return false;
        }

        $detected = strtolower((string) ($payload['document_detected'] ?? ''));
        if ($detected === 'no' || $detected === '') {
            return false;
        }

        $confidence = (float) ($payload['confidence'] ?? $payload['overall_confidence'] ?? 0);
        $minConfidence = (float) config('ocr.min_detect_confidence', 0.45);

        if (($payload['success'] ?? false) === true && $confidence >= $minConfidence) {
            return true;
        }

        // Review path: require ID detected + confidence at least the detect floor
        // (do not let weak/random OCR through as "needs review").
        if (($payload['needs_review'] ?? false) === true) {
            $idType = trim((string) ($payload['id_type'] ?? $payload['detected_id_type'] ?? ''));
            if (
                $documentType !== SupportingDocumentTypes::OTHER_ID
                && $idType !== ''
                && strcasecmp($idType, 'Unknown') !== 0
                && strcasecmp($idType, $documentType) !== 0
            ) {
                return false;
            }

            return $detected === 'yes' && $confidence >= $minConfidence;
        }

        $decision = strtoupper(trim((string) ($payload['decision'] ?? '')));
        if ($documentType === SupportingDocumentTypes::SCHOOL_ID && $decision === 'MANUAL_REVIEW') {
            return $detected === 'yes' && $confidence >= $minConfidence;
        }

        $errorCode = (string) ($payload['error_code'] ?? '');
        if (in_array($errorCode, ['ocr_failed', 'pipeline_reject', 'duplicate'], true)) {
            return false;
        }

        $idType = trim((string) ($payload['id_type'] ?? $payload['detected_id_type'] ?? ''));

        if (
            $documentType === SupportingDocumentTypes::OTHER_ID
            && $idType !== ''
            && strcasecmp($idType, 'Unknown') !== 0
            && $confidence >= $minConfidence
            && $detected === 'yes'
        ) {
            return true;
        }

        return false;
    }

    private function sanitizePublicVerificationMessage(mixed $message): ?string
    {
        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        $sanitized = preg_replace('/\bOCR\b/i', 'AI', $message) ?? $message;
        $sanitized = preg_replace('/\bTesseract\b/i', 'AI', $sanitized) ?? $sanitized;
        $sanitized = str_ireplace('ID text detected', 'ID details detected', $sanitized);
        $sanitized = str_ireplace('Text detected from', 'Details detected from', $sanitized);
        $sanitized = str_ireplace("couldn't read any text from this ID", "couldn't verify this ID", $sanitized);
        $sanitized = str_ireplace('could not read any text from this ID', 'could not verify this ID', $sanitized);
        $sanitized = str_ireplace('No useful text detected', 'No usable ID details detected', $sanitized);

        // Quiet success copy — mismatches still surface their error messages.
        if (preg_match('/^ID verified\b/i', trim($sanitized))) {
            return null;
        }

        return $sanitized;
    }

    private function finalizeRegistrationResponse(KabataanRegistration $registration): JsonResponse
    {
        $registration = $registration->fresh();
        $autoApproved = RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status);

        if ($autoApproved && $registration->user_id) {
            User::query()
                ->where('id', $registration->user_id)
                ->where('status', User::STATUS_PENDING_APPROVAL)
                ->update(['status' => User::STATUS_ACTIVE]);
        }

        return response()->json([
            'success' => true,
            'auto_approved' => $autoApproved,
            'message' => 'Registration completed! Please wait for verification/approval by SK Officials before logging in.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchWizardSetPasswordEmail(array $wizard, Barangay $barangayRecord): array
    {
        $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['Email address is missing from your registration session.'],
            ]);
        }

        $this->draftService->assertEmailAvailable($email, (int) $barangayRecord->id);

        $setPasswordUrl = MailUrl::route('kkprofiling.wizard.set-password', [
            'token' => $wizard['token'],
            'hash' => sha1($email),
        ]);

        Log::info('Sending KK wizard set-password email', [
            'email' => $email,
            'barangay_id' => $barangayRecord->id,
            'token' => $wizard['token'] ?? null,
        ]);

        $this->invalidEmails->attemptMailDelivery($email, function () use ($email, $setPasswordUrl) {
            Notification::route('mail', $email)
                ->notify(new KabataanSetPasswordEmail($setPasswordUrl));
        }, 'email');

        return $this->draftService->markVerificationSent($wizard);
    }

    private function assertTurnstilePassed(Request $request): void
    {
        if (! $this->turnstileService->isEnabled()) {
            return;
        }

        $token = (string) $request->input('cf-turnstile-response', '');

        if ($token === '' || ! $this->turnstileService->verify($token, $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => ['Please complete the security verification and try again.'],
            ]);
        }
    }

    private function validateStep1(Request $request, int $barangayId): array
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $request->merge([
            'email' => $email === '' ? null : $email,
            'last_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('last_name', ''))) ?: '',
            'first_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('first_name', ''))) ?: '',
            'middle_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('middle_name', ''))) ?: null,
        ]);

        if ($request->input('middle_name') === '') {
            $request->merge(['middle_name' => null]);
        }

        $namePattern = '/^[A-Za-z.\-\s]+$/';

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:'.$namePattern],
            'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:'.$namePattern],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:'.$namePattern],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules($barangayId),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => ValidEmailAddress::profilingRules(),
            'contact_number' => ['required', 'string', 'max:30', new PhilippineMobileNumber],
            'civil_status' => 'required|string',
            'youth_classification' => 'required|string',
            'youth_age_group' => 'required|string',
            'work_status' => 'required|string',
            'education' => 'required|string',
            'sk_voter' => 'required|string',
            'national_voter' => 'required|string',
            'sk_voted' => 'required|string',
            'kk_assembly' => 'required|string|in:Yes,No',
            'kk_times' => 'required_if:kk_assembly,Yes|nullable|string',
            'kk_reason' => 'required_if:kk_assembly,No|nullable|string',
            'signature_name' => [
                'required',
                'string',
                'min:'.(int) config('signature.name_min', 1),
                'max:'.(int) config('signature.name_max', 255),
            ],
            'signature' => ['required', 'string', new ParticipantSignatureImage],
        ], [
            'contact_number.required' => PhoneNumberService::MSG_REQUIRED,
            'last_name.min' => 'Minimum 2 characters required.',
            'first_name.min' => 'Minimum 2 characters required.',
            'middle_name.min' => 'Minimum 2 characters required.',
            'last_name.max' => '150 maximum characters only.',
            'first_name.max' => '150 maximum characters only.',
            'middle_name.max' => '150 maximum characters only.',
            'signature_name.required' => config('signature.messages.name_required'),
            'signature_name.min' => config('signature.messages.name_required'),
            'signature_name.max' => config('signature.messages.name_max'),
            'signature.required' => config('signature.messages.required'),
        ] + ValidEmailAddress::profilingMessages());

        if (($validated['suffix'] ?? null) === 'Others') {
            $customSuffix = trim((string) ($validated['custom_suffix'] ?? ''));
            $compact = strtoupper(str_replace(' ', '', $customSuffix));
            $validRoman = in_array($compact, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);
            $validText = (bool) preg_match('/^[A-Za-z.]+$/', str_replace(' ', '', $customSuffix));

            if (! $validRoman && ! $validText) {
                throw ValidationException::withMessages([
                    'custom_suffix' => ['Only text and valid Roman numeral suffixes are allowed.'],
                ]);
            }

            if ($validRoman && (strlen($compact) < 1 || strlen($compact) > 5)) {
                throw ValidationException::withMessages([
                    'custom_suffix' => ['Suffix must not exceed 5 characters.'],
                ]);
            }

            if (! $validRoman && strlen(str_replace(' ', '', $customSuffix)) > 5) {
                throw ValidationException::withMessages([
                    'custom_suffix' => ['Suffix must not exceed 5 characters.'],
                ]);
            }

            $validated['suffix'] = $customSuffix;
        }

        try {
            $derivedAge = Carbon::parse($validated['birthday'])->age;
            if ($derivedAge < 15 || $derivedAge > 30 || (int) $validated['age'] !== (int) $derivedAge) {
                throw ValidationException::withMessages([
                    'birthday' => ['Birthday and age must match and be within 15 to 30 years old.'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'birthday' => ['Invalid birthday value.'],
            ]);
        }

        $canonicalContact = app(PhoneNumberService::class)->normalize($validated['contact_number'] ?? null);
        if ($canonicalContact === null) {
            throw ValidationException::withMessages([
                'contact_number' => [PhoneNumberService::MSG_INVALID],
            ]);
        }
        $validated['contact_number'] = $canonicalContact;

        return $validated;
    }

    private function normalizeStep1Payload(Request $request, array $validated): array
    {
        $validated['civil_status'] = $request->input('civil_status', []);
        $validated['youth_classification'] = $request->input('youth_classification', []);
        $validated['youth_age_group'] = $request->input('youth_age_group', []);
        $validated['work_status'] = $request->input('work_status', []);
        $validated['education'] = $request->input('education', []);
        $validated['sk_voter'] = $request->input('sk_voter');
        $validated['national_voter'] = $request->input('national_voter');
        $validated['sk_voted'] = $request->input('sk_voted');
        $validated['kk_assembly'] = $request->input('kk_assembly');
        $validated['kk_times'] = $request->input('kk_assembly') === 'Yes'
            ? ($request->input('kk_times') ?: $request->input('kk_timesChk'))
            : null;
        $validated['kk_reason'] = $request->input('kk_assembly') === 'No'
            ? ($request->input('kk_reason') ?: $request->input('kk_reasonChk'))
            : null;
        $validated['group_chat'] = null;
        $validated['signature_name'] = $request->input('signature_name');

        return $validated;
    }

    /**
     * Soft-extract Step 1 fields for draft autosave. Unknown keys are ignored.
     *
     * @return array<string, mixed>
     */
    private function extractStep1DraftPayload(Request $request): array
    {
        $allowed = [
            'last_name',
            'first_name',
            'middle_name',
            'suffix',
            'custom_suffix',
            'purok_zone',
            'sex',
            'age',
            'birthday',
            'email',
            'contact_number',
            'civil_status',
            'youth_classification',
            'youth_age_group',
            'work_status',
            'education',
            'sk_voter',
            'national_voter',
            'sk_voted',
            'kk_assembly',
            'kk_times',
            'kk_reason',
            'group_chat',
            'signature',
            'signature_name',
            'data_agreement',
        ];

        $partial = [];

        foreach ($allowed as $key) {
            if (! $request->exists($key)) {
                continue;
            }

            $value = $request->input($key);

            if (is_string($value)) {
                $value = trim($value);
            }

            if (is_array($value) && $value === []) {
                $partial[$key] = null;
                continue;
            }

            // Persist clears: empty string/null must overwrite prior draft values
            if ($value === null || $value === '') {
                $partial[$key] = null;
                continue;
            }

            $partial[$key] = $value;
        }

        if (isset($partial['email'])) {
            $partial['email'] = strtolower(trim((string) $partial['email']));
        }

        $kkAssembly = $request->input('kk_assembly') ?: $request->input('kk_assemblyChk');
        if (is_string($kkAssembly) && $kkAssembly !== '') {
            $partial['kk_assembly'] = $kkAssembly;
        }

        if (($partial['kk_assembly'] ?? null) === 'Yes') {
            $times = $request->input('kk_times') ?: $request->input('kk_timesChk');
            if (is_string($times) && $times !== '') {
                $partial['kk_times'] = $times;
            }
        }

        if (($partial['kk_assembly'] ?? null) === 'No') {
            $reason = $request->input('kk_reason') ?: $request->input('kk_reasonChk');
            if (is_string($reason) && $reason !== '') {
                $partial['kk_reason'] = $reason;
            }
        }

        if ($request->boolean('data_agreement')) {
            $partial['data_agreement'] = '1';
        }

        return $partial;
    }

    private function resolveBarangay(string $barangay): Barangay
    {
        $slug = $this->normalizeSlug($barangay);
        $name = $this->getBarangayName($slug);

        if (! $name) {
            abort(404);
        }

        $record = Barangay::where('name', $name)->first();

        if (! $record) {
            abort(404);
        }

        return $record;
    }

    private function barangaySlugFromId(int $barangayId): string
    {
        $name = Barangay::whereKey($barangayId)->value('name');

        return $this->getBarangaySlug($name ?? '');
    }

    private function normalizeSlug(string $barangay): string
    {
        $slug = strtolower(trim($barangay));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    private function getBarangayName(string $slug): ?string
    {
        $barangayMap = [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'poblacion-i' => 'Poblacion I',
            'poblacion-ii' => 'Poblacion II',
            'poblacion-iii' => 'Poblacion III',
            'poblacion-iv' => 'Poblacion IV',
            'poblacion-v' => 'Poblacion V',
            'bubukal' => 'Bubukal',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];

        return $barangayMap[$slug] ?? null;
    }

    private function getBarangaySlug(string $name): string
    {
        $slugMap = [
            'Alipit' => 'alipit',
            'Bagumbayan' => 'bagumbayan',
            'Poblacion I' => 'poblacion-i',
            'Poblacion II' => 'poblacion-ii',
            'Poblacion III' => 'poblacion-iii',
            'Poblacion IV' => 'poblacion-iv',
            'Poblacion V' => 'poblacion-v',
            'Bubukal' => 'bubukal',
            'Calios' => 'calios',
            'Duhat' => 'duhat',
            'Gatid' => 'gatid',
            'Jasaan' => 'jasaan',
            'Labuin' => 'labuin',
            'Malinao' => 'malinao',
            'Oogong' => 'oogong',
            'Pagsawitan' => 'pagsawitan',
            'Palasan' => 'palasan',
            'Patimbao' => 'patimbao',
            'San Jose' => 'san-jose',
            'San Juan' => 'san-juan',
            'San Pablo Norte' => 'san-pablo-norte',
            'San Pablo Sur' => 'san-pablo-sur',
            'Santisima Cruz' => 'santisima-cruz',
            'Santo Angel Central' => 'santo-angel-central',
            'Santo Angel Norte' => 'santo-angel-norte',
            'Santo Angel Sur' => 'santo-angel-sur',
        ];

        return $slugMap[$name] ?? strtolower(str_replace(' ', '-', $name));
    }

    private function renderRegistrationCompleteView(
        Barangay $barangayRecord,
        string $email,
        ?KabataanRegistration $registration = null,
    ): View {
        $this->draftService->markRegistrationComplete($email, (int) $barangayRecord->id, $registration);

        $autoApproved = $registration
            ? RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)
            : false;

        return view('kkprofiling::set_password', [
            'barangay' => $barangayRecord->name,
            'slug' => $this->barangaySlugFromId((int) $barangayRecord->id),
            'email' => $email,
            'registrationAlreadyComplete' => true,
            'registrationAutoApproved' => $autoApproved,
            'barangayLogoUrl' => KKProfilingController::getBarangayLogoUrl($barangayRecord->id),
        ]);
    }
}
