<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Password - {{ $barangay }} - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
        'app/Modules/KKProfiling/assets/js/kkprofiling.js',
    ])
</head>
<body class="youth-signin-page kkp-setpw-page" @if(!empty($registrationAlreadyComplete)) data-registration-already-complete="1" data-auto-approved="{{ !empty($registrationAutoApproved) ? '1' : '0' }}" @endif>
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to activate your account.',
    ])

    <div class="youth-bg-wrapper">
        <div class="youth-bg-image"></div>
        <div class="youth-gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <main class="youth-signin-container">
        <div class="youth-branding-section">
            <div class="branding-content">
                <div class="logo-wrapper">
                    <img
                        src="{{ asset('images/SK_OnePortal.png') }}"
                        alt="SK OnePortal Logo"
                        class="youth-logo kkp-setpw-logo"
                    >
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
                <p class="kkp-setpw-branding-sub">KK Profiling · {{ $barangay }}</p>
            </div>
        </div>

        <div class="youth-signin-section">
            <div class="youth-signin-card kkp-setpw-card">
                <div class="card-header">
                    <h2 class="card-title">Set Your Password</h2>
                    <p class="card-subtitle">
                        @if(!empty($emailVerified))
                            Your email has been verified. Create a secure password for your <strong>{{ $barangay }}</strong> account.
                        @else
                            Almost done! Create a password for your <strong>{{ $barangay }}</strong> KK account.
                        @endif
                    </p>
                </div>

                @if(!empty($email))
                    <p class="kkp-setpw-email-badge">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                        {{ $email }}
                    </p>
                @endif

                @if(session('success'))
                    <div class="kkp-setpw-alert kkp-setpw-alert-success">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="kkp-setpw-alert kkp-setpw-alert-error">{{ $errors->first() }}</div>
                @endif

                @if(empty($registrationAlreadyComplete))
                <form
                    id="setPasswordForm"
                    class="kkp-setpw-form"
                    method="POST"
                    action="{{ !empty($wizardToken) ? '#' : route('kkprofiling.store-password', ['barangay' => request()->route('barangay') ?? ($slug ?? '')]) }}"
                    novalidate
                    @if(!empty($wizardToken))
                        data-wizard-token="{{ $wizardToken }}"
                        data-finalize-url="{{ route('kkprofiling.wizard.finalize-token', ['token' => $wizardToken]) }}"
                    @endif
                >
                    @csrf

                    <div class="youth-form-group kkp-setpw-field">
                        <label for="password" class="youth-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="youth-input" placeholder="Enter your password" autocomplete="new-password">
                            <button type="button" class="pw-toggle-btn kkp-setpw-toggle" data-target="password" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                    <path d="M1 1l22 22"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="pw-rules" id="pwRules" hidden>
                            <div class="pw-rule" data-rule="len">At least 8 characters</div>
                            <div class="pw-rule" data-rule="lower">At least one lowercase letter</div>
                            <div class="pw-rule" data-rule="upper">At least one uppercase letter</div>
                            <div class="pw-rule" data-rule="num">At least one number</div>
                            <div class="pw-rule" data-rule="special">At least one special character</div>
                        </div>
                        <p class="kkp-setpw-field-error" id="passwordError" hidden></p>
                    </div>

                    <div class="youth-form-group kkp-setpw-field kkp-setpw-field--confirm">
                        <label for="password_confirmation" class="youth-label">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="youth-input" placeholder="Re-enter your password" autocomplete="new-password">
                            <button type="button" class="pw-toggle-btn kkp-setpw-toggle" data-target="password_confirmation" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                    <path d="M1 1l22 22"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="kkp-setpw-field-error" id="confirmPasswordError" hidden></p>
                    </div>

                    <button type="submit" class="youth-submit-btn" id="setpwSubmitBtn">
                        <span class="setpw-btn-text">Complete Registration</span>
                    </button>
                </form>
                @else
                    <p class="kkp-setpw-alert kkp-setpw-alert-success">Your password is already set. You can proceed to login.</p>
                @endif
            </div>
        </div>
    </main>

    <div class="kkp-reg-success-overlay" id="kkpRegSuccessModal" hidden aria-hidden="true">
        <div class="kkp-reg-success-modal" role="dialog" aria-labelledby="kkpRegSuccessTitle" aria-modal="true">
            <div class="kkp-reg-success-modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2 class="kkp-reg-success-modal-title" id="kkpRegSuccessTitle">Registration Submitted Successfully</h2>
            <p class="kkp-reg-success-message" id="kkpRegSuccessMessage">
                Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.
            </p>
            <div class="kkp-reg-success-actions">
                <a href="{{ route('sign-in') }}" class="kkp-reg-success-modal-btn">Go to Sign in</a>
            </div>
        </div>
    </div>

</body>
</html>
