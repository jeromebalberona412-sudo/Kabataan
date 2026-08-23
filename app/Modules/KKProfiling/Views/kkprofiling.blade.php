<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KK Profiling - {{ $barangay }} - SK OnePortal</title>
    @vite([
        'app/Modules/Homepage/assets/css/homepage.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-wizard.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-optional-email.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/KKProfiling/assets/js/kkprofiling.js',
        'app/Modules/KKProfiling/assets/js/kkprofiling-wizard.js',
        'app/Modules/KKProfiling/assets/js/kkprofiling-optional-email.js',
    ])
    @inject('turnstileService', 'App\Services\TurnstileService')
</head>
<body class="homepage-body kkp-form-page kkp-wizard-mode @if($registrationComplete ?? false) kkp-wizard-registration-complete kkp-wizard-success-modal-open @endif">

    @include('authentication::partials.turnstile-gate', [
        'turnstileService' => $turnstileService,
        'turnstileSubtitle' => 'Complete the security check to continue.',
    ])

    <main class="kkp-main">
        <div class="kkp-page-wrap">
            <a href="{{ route('kkprofiling.signup', ['clear' => 1]) }}" class="kkp-back-link" id="kkpTopBackLink" aria-label="Back to KK Profiling signup">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L8.414 9H17a1 1 0 110 2H8.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Back
            </a>

            @if (session('success'))
                <div class="kkp-alert kkp-alert-success">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="kkp-alert kkp-alert-error" id="kkpPageErrors">
                    {{ $errors->first() }}
                </div>
            @endif

            @php
                $kkpInitialStep = (int) ($wizardInitialStep ?? 1);
            @endphp

            <div
                id="kkpRegistrationWizard"
                class="kkp-wizard-root"
                data-barangay-slug="{{ $slug }}"
                data-barangay-name="{{ $barangay }}"
                data-respondent-number="{{ $respondentNumber ?? '' }}"
                data-initial-step="{{ $kkpInitialStep }}"
                data-verification-sent="{{ ($verificationSent ?? false) ? '1' : '0' }}"
                data-registration-complete="{{ ($registrationComplete ?? false) ? '1' : '0' }}"
                data-auto-approved="{{ ($registrationAutoApproved ?? false) ? '1' : '0' }}"
                data-turnstile-enabled="{{ !empty($turnstileEnabled) ? '1' : '0' }}"
                data-turnstile-sitekey="{{ $turnstileSiteKey ?? '' }}"
                @if(!empty($completedEmail)) data-completed-email="{{ $completedEmail }}" @endif
                @if(!empty($wizardDraftEmail)) data-draft-email="{{ $wizardDraftEmail }}" @endif
                @if($errors->has('email')) data-email-error="{{ $errors->first('email') }}" @endif
            >
                @include('kkprofiling::partials.wizard.progress-header')

                <div class="kkp-paper" id="kkpFormCard">
                    <div class="kkp-responsive-container">

                        {{-- STEP 1: KK Profiling Form --}}
                        <section class="kkp-wizard-panel" id="kkpWizardStep1" data-wizard-step="1" @if($kkpInitialStep !== 1) hidden @endif>
                            <form
                                method="POST"
                                action="{{ route('kkprofiling.submit', ['barangay' => $slug]) }}"
                                id="kkProfilingForm"
                                data-barangay-slug="{{ $slug }}"
                                data-wizard-mode="1"
                                onsubmit="handleFormSubmit(event); return false;"
                            >
                                @csrf

                                @include('kkprofiling::partials.kk-profiling-form-fields', [
                                    'barangay' => $barangay,
                                    'respondentNumber' => $respondentNumber ?? '',
                                    'respondentDisplay' => $respondentDisplay ?? '01',
                                    'submitLabel' => 'Submit KK Profiling',
                                    'barangayLogoUrl' => $barangayLogoUrl ?? null,
                                    'barangayZones' => $barangayZones ?? collect(),
                                ])
                            </form>
                        </section>

                        {{-- STEP 2: Supporting Documents (optional) --}}
                        @include('kkprofiling::partials.wizard.step-documents', ['kkpInitialStep' => $kkpInitialStep])

                        {{-- STEP 3: Email Verification + Account Setup --}}
                        @include('kkprofiling::partials.wizard.step-account', [
                            'kkpInitialStep' => $kkpInitialStep,
                            'wizardDraftEmail' => $wizardDraftEmail ?? null,
                        ])

                    </div>
                </div>

                <div class="kkp-wizard-nav" id="kkpWizardNav">
                    <button type="button" class="kkp-wizard-btn kkp-wizard-btn-secondary" id="kkpWizardBackBtn" hidden>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Back
                    </button>
                    <div class="kkp-wizard-nav-actions">
                        <button type="button" class="kkp-wizard-btn kkp-wizard-btn-primary" id="kkpWizardNextBtn">
                            <span id="kkpWizardNextLabel">Submit KK Profiling</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    @include('kkprofiling::partials.kk-profiling-signature-modals')
    @include('kkprofiling::partials.kk-profiling-no-email-modal')

    <div class="kkp-reg-success-overlay" id="kkpRegSuccessModal" @if(empty($registrationComplete ?? false)) hidden @endif aria-hidden="{{ ($registrationComplete ?? false) ? 'false' : 'true' }}">
        <div class="kkp-reg-success-modal" role="dialog" aria-labelledby="kkpRegSuccessTitle" aria-modal="true">
            <div class="kkp-reg-success-modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2 class="kkp-reg-success-modal-title" id="kkpRegSuccessTitle">
                {{ ($registrationComplete ?? false) && ($registrationAutoApproved ?? false) ? 'Registration Verified!' : 'Registration Submitted Successfully' }}
            </h2>
            <p class="kkp-reg-success-message" id="kkpRegSuccessMessage">
                @if(($registrationComplete ?? false) && ($registrationAutoApproved ?? false))
                    Your ID address matches your registered barangay. Your account is approved — you can log in now.
                @else
                    Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.
                @endif
            </p>
            <div class="kkp-reg-success-actions">
                <a href="{{ route('sign-in') }}" class="kkp-reg-success-modal-btn" id="kkpRegSuccessLoginBtn">Go to Sign in</a>
            </div>
        </div>
    </div>

</body>
</html>
