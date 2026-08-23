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
    <title>Change Password - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Profile/assets/css/change-password.css',
        'app/Modules/Profile/assets/js/change-password.js',
    ])
</head>
<body class="youth-signin-page">

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
                        src="/images/skoneportal_logo.webp"
                        alt="SK OnePortal Logo"
                        class="youth-logo"
                    >
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
            </div>
        </div>

        <div class="youth-signin-section">
            <div class="youth-signin-card">
                <div class="card-header cp-card-header">
                    <h2 class="card-title">Change Password</h2>
                    <p class="card-helper-text">Create a new password for your account. We will email you a confirmation link before the change takes effect.</p>
                </div>

                @if ($errors->any())
                    <div class="youth-alert youth-alert-error">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <!-- Change Password Form -->
                <form class="youth-signin-form" method="POST" action="{{ route('change-password.post') }}" id="changePasswordForm" novalidate>
                    @csrf

                    <!-- New Password Field -->
                    <div class="youth-form-group">
                        <label for="password" class="youth-label">
                            <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            New Password
                        </label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="youth-input password-input"
                                required
                                maxlength="50"
                                placeholder="Minimum 8 characters"
                            >
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

                    <!-- Confirm Password Field -->
                    <div class="youth-form-group">
                        <label for="password_confirmation" class="youth-label">
                            <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Confirm Password
                        </label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="youth-input password-input"
                                required
                                maxlength="50"
                                placeholder="Re-enter your new password"
                            >
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
                        <span class="inline-error" id="confirmPasswordError" style="display: none; color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem;"></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="youth-submit-btn" id="cpSubmitBtn">
                        <span id="cpBtnText">Send Verification Link</span>
                    </button>
                </form>

                <!-- Back to Profile Link -->
                <div class="youth-register-section">
                    <p class="register-text">
                        <a href="{{ route('profile') }}" class="register-link">← Back to Profile</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
