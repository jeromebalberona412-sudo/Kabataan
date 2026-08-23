<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Notifications\KabataanSetPasswordEmail;
use App\Support\MailUrl;
use App\Rules\FacebookProfileUrl;
use App\Services\BarangayZoneService;
use App\Services\DuplicateKabataanRegistrationService;
use App\Services\KkProfilingDirectSubmitService;
use App\Services\KkRegistrationDraftService;
use App\Services\TurnstileService;
use App\Services\PhilippineIdDetectionService;
use App\Services\PhilippineIdPipelineService;
use App\Services\RegistrationEvaluationService;
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
        protected KkProfilingDirectSubmitService $directSubmitService,
    ) {}

    public function saveStep1(Request $request, string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $validated = $this->validateStep1($request, (int) $barangayRecord->id, true);

        if (trim((string) ($validated['email'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'email' => ['Enter a Gmail address to continue to the next steps, or leave it blank and use Submit KK Profiling.'],
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

    public function submitWithoutEmail(Request $request, string $barangay): JsonResponse
    {
        $this->assertTurnstilePassed($request);

        $barangayRecord = $this->resolveBarangay($barangay);

        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email !== '') {
            throw ValidationException::withMessages([
                'email' => ['Leave the email blank to submit without an account, or use Save & Continue if you entered an email.'],
            ]);
        }

        $request->merge(['email' => null]);
        $validated = $this->validateStep1($request, (int) $barangayRecord->id, false);
        $payload = $this->normalizeStep1Payload($request, $validated);
        $payload['email'] = null;

        $this->directSubmitService->commit($barangayRecord, $payload);

        return response()->json([
            'success' => true,
            'submitted_without_email' => true,
            'message' => 'KK Profiling submitted successfully.',
            'redirect' => route('kkprofiling.signup', ['clear' => 1]),
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

        $validationRules = [
            'skip_documents' => ['sometimes', 'boolean'],
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
                $wizard = $this->draftService->saveStep2($wizard, (string) $documentType, $sides);

                if ($this->philippineIdDetection->isSupportedDocumentType((string) $documentType)) {
                    try {
                        $frontRealPath = $sides['front']?->getRealPath();
                        $backRealPath = $sides['back']?->getRealPath();
                        $selfieRealPath = $request->file('selfie')?->getRealPath();

                        if (
                            config('ocr.philippine_pipeline_enabled', true)
                            && $this->philippineIdPipeline->isConfigured()
                            && is_string($frontRealPath)
                            && is_string($backRealPath)
                        ) {
                            $pipelineResult = $this->philippineIdPipeline->validate(
                                (int) $barangayRecord->id,
                                is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [],
                                $frontRealPath,
                                $backRealPath,
                                (string) $documentType,
                                is_string($selfieRealPath) ? $selfieRealPath : null,
                            );

                            if (is_array($pipelineResult)) {
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
                                $wizard = $this->draftService->storeIdVerification($wizard, $ocrPayload);
                                $formSuggestions = $ocrPayload['form_suggestions'] ?? null;
                            }
                        }

                        if (! is_array($ocrPayload)) {
                            $ocrPayload = $this->philippineIdDetection->detectUploadedPair(
                                $sides['front'],
                                $sides['back'],
                                (string) $documentType,
                            );

                            $verification = $this->philippineIdDetection->buildVerificationRecord(
                                $ocrPayload,
                                (string) $documentType,
                                is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [],
                            );
                            $wizard = $this->draftService->storeIdVerification($wizard, $verification);
                            $formSuggestions = $verification['form_suggestions'] ?? null;
                        }

                        if (is_array($ocrPayload) && ($ocrPayload['validation_error'] ?? false) === true) {
                            Log::info('KK wizard Step 2 OCR validation warning', [
                                'document_type' => $documentType,
                                'detected_id_type' => $ocrPayload['id_type'] ?? null,
                                'message' => $ocrPayload['message'] ?? null,
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        Log::warning('KK wizard Step 2 OCR failed', [
                            'document_type' => $documentType,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
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
            throw $e;
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
            'message' => $hasAnyUpload
                ? 'Supporting documents saved. Continue to email verification.'
                : 'Continue to email verification. You may upload an ID later if needed.',
            'documents_uploaded' => $hasAnyUpload,
            'verification_sent' => $verificationSent,
            'email_error' => $emailError,
            'ocr' => isset($ocrPayload) && is_array($ocrPayload) ? $ocrPayload : null,
            'form_suggestions' => isset($formSuggestions) && is_array($formSuggestions) ? $formSuggestions : null,
        ]);
    }

    public function sendVerification(Request $request, string $barangay)
    {
        $this->assertTurnstilePassed($request);

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
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('KK wizard set-password email failed on send-verification', [
                'email' => $email,
                'barangay_id' => $barangayRecord->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Unable to send set password email right now. Please tap Resend set password link to try again.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'email' => $email,
            'verification_sent' => true,
            'message' => 'Set password link sent. Please check your inbox.',
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

        $wizard = $this->draftService->resolveWizard();

        if ($wizard && (int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            $wizard = null;
        }

        if ($wizard) {
            $email = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

            if ($email !== '' && $this->draftService->isEmailRegistrationComplete($email, (int) $barangayRecord->id)) {
                $registration = KabataanRegistration::query()
                    ->where('barangay_id', $barangayRecord->id)
                    ->where('email', $email)
                    ->whereIn('status', ['password_set', 'active'])
                    ->latest('id')
                    ->first();

                $this->draftService->markRegistrationComplete($email, (int) $barangayRecord->id, $registration);
                $completed = $this->draftService->resolveCompletedRegistration((int) $barangayRecord->id);

                return response()->json([
                    'draft' => null,
                    'registration_completed' => true,
                    'email' => $email,
                    'auto_approved' => (bool) ($completed['auto_approved'] ?? false),
                    'evaluation_status' => $completed['evaluation_status'] ?? null,
                ]);
            }
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

        if ($targetStep === 3 && empty($wizard['verification_sent_at'])) {
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

        return response()->json([
            'success' => true,
            'message' => 'Registration draft cleared.',
        ]);
    }

    public function detectId(Request $request, string $barangay)
    {
        $barangayRecord = $this->resolveBarangay($barangay);
        $wizard = $this->requireWizard();

        if ((int) ($wizard['barangay_id'] ?? 0) !== (int) $barangayRecord->id) {
            throw ValidationException::withMessages([
                'draft' => ['Your registration session does not match this barangay.'],
            ]);
        }

        $request->validate([
            'document_type' => ['required', Rule::in(['national_id', 'philhealth_id', 'voters_id'])],
            'front' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'back' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $documentType = (string) $request->input('document_type');
        $registrationFields = is_array($wizard['step1_data'] ?? null) ? $wizard['step1_data'] : [];
        $frontPath = $request->file('front')?->getRealPath();
        $backPath = $request->file('back')?->getRealPath();
        $selfiePath = $request->file('selfie')?->getRealPath();

        if (
            config('ocr.philippine_pipeline_enabled', true)
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

                if (is_array($pipelineResult)) {
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
                        'message' => $pipelineResult['message'] ?? null,
                        'ocr' => $pipelineResult['ocr'] ?? [],
                    ];

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
                        'validation_error' => (bool) ($pipelineResult['validation_error'] ?? false),
                        'face_match' => (bool) ($pipelineResult['face_match'] ?? false),
                        'requires_selfie' => ! ($pipelineResult['face_match'] ?? false)
                            && empty($pipelineResult['face_verification']['available']),
                    ], ($pipelineResult['validation_error'] ?? false) ? 422 : 200);
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

        return response()->json([
            'success' => (bool) ($payload['success'] ?? false),
            'ocr' => $payload,
            'form_suggestions' => $formSuggestions,
            'message' => $payload['message'] ?? null,
            'validation_error' => (bool) ($payload['validation_error'] ?? false),
            'face_match' => (bool) ($payload['face_match'] ?? false),
        ], ($payload['validation_error'] ?? false) ? 422 : 200);
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

        Notification::route('mail', $email)
            ->notify(new KabataanSetPasswordEmail($setPasswordUrl));

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

    private function validateStep1(Request $request, int $barangayId, bool $emailRequired = true): array
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $email === '' ? null : $email]);

        $emailRules = $emailRequired
            ? ['required', 'email', 'max:254', 'regex:/^[A-Za-z0-9._%+-]{6,30}@gmail\.com$/i']
            : ['nullable', 'email', 'max:254', 'regex:/^[A-Za-z0-9._%+-]{6,30}@gmail\.com$/i'];

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z.\-]{3,50}$/'],
            'first_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'max:50', 'regex:/^$|^[A-Za-z.\-]{3,50}$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules($barangayId),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => $emailRules,
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
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
            'facebook_profile_url' => [
                'nullable',
                Rule::requiredIf(fn () => in_array((string) $request->input('group_chat'), ['Yes', 'No'], true)),
                'string',
                'min:3',
                'max:50',
                new FacebookProfileUrl,
            ],
            'group_chat' => [
                'nullable',
                Rule::requiredIf(fn () => trim((string) $request->input('facebook_profile_url', '')) !== ''),
                'string',
                Rule::in(['Yes', 'No']),
            ],
            'signature' => 'required|string',
            'data_agreement' => 'accepted',
        ]);

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
        $validated['facebook_profile_url'] = trim((string) $request->input('facebook_profile_url', '')) ?: null;
        $validated['group_chat'] = $request->input('group_chat');
        $validated['signature_name'] = $request->input('signature_name');

        return $validated;
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
