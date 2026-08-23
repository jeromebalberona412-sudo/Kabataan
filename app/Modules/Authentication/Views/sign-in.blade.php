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
    <title>OnePortal Youth Officials</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/auth-legal.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/sign-in.js',
        'app/Modules/Authentication/assets/js/auth-legal.js',
    ])

    @inject('turnstileService', 'App\Services\TurnstileService')
</head>
<body class="youth-signin-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileService' => $turnstileService,
        'turnstileSubtitle' => 'Complete the security check to continue logging in.',
    ])

    <!-- Animated Background -->
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
        <!-- Left Side - Logo & Branding -->
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
                <p class="youth-tagline">Official Youth Portal &ndash; Santa Cruz, Laguna</p>
            </div>
        </div>

        <!-- Right Side - Sign In Card -->
        <div class="youth-signin-section">
            <div class="youth-signin-card">
                <div class="card-header">
                    <p class="card-subtitle">Sign in to your account</p>
                </div>

                @if (session('sign_in_error'))
                    <div class="youth-alert youth-alert-error" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('sign_in_error') }}</span>
                    </div>
                @endif

                @if (session('success'))
                    <div class="youth-alert youth-alert-success" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <!-- Sign In Form -->
                <form class="youth-signin-form" id="signInForm"
                      method="POST"
                      action="{{ route('sign-in') }}"
                      novalidate
                      @if($turnstileService->isEnabled())
                          data-turnstile-enabled="true"
                          data-turnstile-sitekey="{{ $turnstileService->getSiteKey() }}"
                      @endif>
                    @csrf

                    <!-- Email Field -->
                    <div class="youth-form-group">
                        <label for="email" class="youth-label">
                            <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                            Email Address
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="youth-input"
                            value="{{ old('email') }}"
                            autofocus
                            autocomplete="email"
                            placeholder="Enter example@gmail.com"
                            maxlength="150"
                        >
                        <div class="youth-field-error" id="email-error" hidden style="display: none !important;"></div>
                    </div>

                    <!-- Password Field -->
                    <div class="youth-form-group">
                        <label for="password" class="youth-label">
                            <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Password
                        </label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="youth-input password-input"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            >
                            <button type="button" class="pw-toggle-btn" id="pwToggleBtn" aria-label="Show password" tabindex="-1">
                                {{-- Eye open (password hidden) --}}
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                {{-- Eye closed (password visible) --}}
                                <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <path d="M1 1l22 22"/>
                                </svg>
                            </button>
                        </div>
                        <div class="youth-field-error" id="password-error" hidden style="display: none !important;"></div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="youth-form-options">
                        <div class="youth-remember-block">
                            <label class="youth-checkbox" for="remember">
                                <input
                                    type="checkbox"
                                    id="remember"
                                    name="remember"
                                    value="1"
                                    {{ old('remember') ? 'checked' : '' }}
                                >
                                <span class="checkbox-label">Remember me</span>
                            </label>
                        </div>
                        <a href="{{ route('password.request') }}" class="youth-link youth-forgot-link" id="forgotBtn">Forgot password</a>
                    </div>

                    @include('authentication::partials.signin-legal-consent')

                    {{--
                        Hidden Turnstile server-error anchor.
                        Only rendered when the backend explicitly rejected the Turnstile
                        token — NOT for wrong email/password errors. The JS checks for
                        this element on page load and auto-opens the verification modal
                        so the user can re-verify without clicking Sign In again.
                    --}}
                    @php
                        $signInErr = session('sign_in_error', '');
                        $isTurnstileErr = $signInErr && (
                            str_contains(strtolower($signInErr), 'verification') ||
                            str_contains(strtolower($signInErr), 'turnstile') ||
                            str_contains(strtolower($signInErr), 'security check')
                        );
                    @endphp
                    @if($isTurnstileErr && $turnstileService->isEnabled())
                        <div id="turnstile-server-error"
                             style="display:none;"
                             aria-hidden="true">{{ $signInErr }}</div>
                    @endif

                    <!-- Submit Button -->
                    <button type="submit" class="youth-submit-btn" id="signInBtn">
                        <span>Sign In</span>
                    </button>

                </form>

                <div class="youth-login-secondary-actions">
                    <a href="{{ route('account.activation.request') }}" class="youth-homepage-btn" id="verifyAccountBtn">
                        Activate Account
                    </a>
                </div>

                <!-- Registration Link -->
                <div class="youth-register-section">
                    <p class="register-text">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="register-link">Sign Up here</a>
                    </p>
                    <a href="{{ route('homepage') }}" class="register-link register-home-link" id="homepageBtn">
                        Back to Homepage
                    </a>
                </div>
            </div>
        </div>
    </main>

    @include('authentication::partials.legal-modals')

    <div class="auth-legal-modal" id="rememberDeviceModal" hidden aria-hidden="true" role="dialog" aria-labelledby="rememberDeviceModalTitle" aria-modal="true">
        <div class="auth-legal-modal-backdrop" data-remember-dismiss></div>
        <div class="auth-legal-modal-dialog youth-remember-modal-dialog" role="document">
            <header class="auth-legal-modal-header">
                <h2 class="auth-legal-modal-title" id="rememberDeviceModalTitle">Remember this device?</h2>
                <button type="button" class="auth-legal-modal-close" data-remember-dismiss aria-label="Close">&times;</button>
            </header>
            <div class="auth-legal-modal-body">
                <p>Only select this on a personal device. Do not use on shared or public computers.</p>
            </div>
            <footer class="auth-legal-modal-footer youth-remember-modal-footer">
                <button type="button" class="youth-remember-modal-cancel" data-remember-dismiss>Cancel</button>
                <button type="button" class="auth-legal-modal-btn" id="rememberDeviceConfirm">Continue</button>
            </footer>
        </div>
    </div>

    <script>
        // Password toggle — self-contained, no conflict with form submit handlers
        (function () {
            var btn   = document.getElementById('pwToggleBtn');
            var input = document.getElementById('password');
            if (!btn || !input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.classList.toggle('pw-visible', show);
            });
        }());
    </script>
</body>
</html>
