{{-- Step 3: Check email for set password link — resend only --}}
<section class="kkp-wizard-panel kkp-wizard-step3-legacy" id="kkpWizardStep3" data-wizard-step="3" @if(($kkpInitialStep ?? 1) !== 3) hidden @endif>

    <div class="verify-card" id="emailVerifyCard">
        <div class="verify-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
        </div>
        <h1 class="verify-title">Check Your Email</h1>
        <p class="verify-message">We sent a set password link to:</p>
        <p class="verify-email" id="displayEmail">{{ $wizardDraftEmail ?? 'your-email@example.com' }}</p>
        <p class="kkp-wizard-email-recommend" id="kkpEmailRecommendNote">
            <strong>Email Verification Recommended.</strong>
            Your email address has been saved. We highly recommend verifying your email now so you can access your account faster and avoid delays when using SK programs and services.
        </p>
        <p class="verify-instruction">
            Open your inbox and click the <strong>Set Password</strong> link to continue your registration.
        </p>
        <p class="kkp-wizard-email-error" id="kkpWizardEmailError" hidden></p>
        <div class="verify-help">
            <p>Didn't receive the email?</p>
            <div class="verify-resend-wrap">
                <button type="button" class="verify-resend-btn" id="resendEmailBtn" disabled>
                    Resend set password link
                </button>
                <span class="verify-resend-timer" id="resendTimer" hidden></span>
            </div>
        </div>
    </div>

</section>
