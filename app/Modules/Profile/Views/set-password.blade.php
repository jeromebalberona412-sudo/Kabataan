<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Password - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Profile/assets/css/change-password.css',
        'app/Modules/Profile/assets/js/set-password.js',
    ])
</head>
<body class="youth-login-page set-password-page">

    <div class="youth-bg-wrapper">
        <div class="youth-bg-image"></div>
        <div class="youth-gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <main class="youth-login-container">
        <div class="youth-branding-section">
            <div class="branding-content">
                <div class="logo-wrapper">
                    <img src="/images/skoneportal_logo.webp" alt="SK OnePortal Logo" class="youth-logo">
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
            </div>
        </div>

        <div class="youth-login-section">
            <div class="youth-login-card">
                <div class="card-header">
                    <h2 class="card-title">Set New Password 🔐</h2>
                    <p class="card-subtitle">Your new email <strong>{{ $user->pending_email }}</strong> will be activated after you set a password.</p>
                </div>

                @if (session('status'))
                    <div class="youth-alert youth-alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="youth-alert youth-alert-error">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('change-email.set-password.update', ['id' => $user->id, 'token' => $token]) }}" method="POST" class="youth-login-form" id="setPasswordForm" novalidate>
                    @csrf

                    <div class="youth-form-group">
                        <label for="password" class="youth-label">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="youth-input password-input" placeholder="Enter new password" autocomplete="new-password" minlength="8" maxlength="64" required>
                            <button type="button" class="pw-toggle-btn" data-target="password" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <path d="M1 1l22 22"/>
                                </svg>
                            </button>
                        </div>
                        <ul class="password-rules" id="passwordRules" aria-live="polite">
                            <li id="rule-length">At least 8 characters</li>
                            <li id="rule-lowercase">At least one lowercase letter</li>
                            <li id="rule-uppercase">At least one uppercase letter</li>
                            <li id="rule-number">At least one number</li>
                            <li id="rule-special">At least one special character</li>
                        </ul>
                    </div>

                    <div class="youth-form-group">
                        <label for="password_confirmation" class="youth-label">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="youth-input password-input" placeholder="Re-enter new password" autocomplete="new-password" minlength="8" maxlength="64" required>
                            <button type="button" class="pw-toggle-btn" data-target="password_confirmation" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <path d="M1 1l22 22"/>
                                </svg>
                            </button>
                        </div>
                        <span class="inline-error" id="confirmPasswordError" style="display: none;"></span>
                    </div>

                    <button type="submit" class="youth-submit-btn" id="spSubmitBtn">
                        <span id="spBtnText">Set Password</span>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <div id="spSuccessOverlay" class="sp-success-overlay" hidden>
        <div class="sp-success-card">
            <div class="sp-success-icon">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h3>Password Set!</h3>
            <p>Redirecting you to sign in…</p>
        </div>
    </div>

</body>
</html>
