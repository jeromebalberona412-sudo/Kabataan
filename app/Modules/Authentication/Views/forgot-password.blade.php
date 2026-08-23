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
    <title>Forgot Password - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/auth-legal.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
    ])
</head>
<body class="youth-signin-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to send a reset link.',
    ])

    <main class="youth-signin-container">

        {{-- ─── Left Side — Logo & Branding ─────────────────────────────────────── --}}
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

        {{-- ─── Right Side — Forgot Password Card ───────────────────────────────── --}}
        <div class="youth-signin-section youth-signin-section--fp">
            <div class="youth-signin-card">

                {{-- Card Header: centered bold title + small helper text --}}
                <div class="card-header fp-card-header">
                    <p class="card-subtitle">Forgot Your Password</p>
                    <p class="card-helper-text">Enter the email address associated with your account and we will send you a link to reset your password.</p>
                </div>

                {{-- Alerts --}}
                @if (session('forgot_password_error'))
                    <div class="youth-alert youth-alert-error" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('forgot_password_error') }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="youth-alert youth-alert-success" id="resetStatusAlert" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                {{-- Forgot Password Form --}}
                <form class="youth-signin-form" method="POST" action="{{ route('password.email') }}" id="forgotPasswordForm" novalidate>
                    @csrf

                    {{-- Email Field --}}
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
                        <div class="youth-field-error" id="email-error" hidden></div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" class="youth-submit-btn" id="sendResetLinkBtn">
                        <span class="spinner"></span>
                        <span id="fpBtnText">Send Reset Link</span>
                    </button>
                </form>

                {{-- Back to Sign In --}}
                <div class="youth-register-section">
                    <p class="register-text">
                        Remember your password?
                        <a href="{{ route('sign-in') }}" class="register-link">Back to Sign In</a>
                    </p>
                </div>

            </div>{{-- /.youth-signin-card --}}
        </div>{{-- /.youth-signin-section --}}

    </main>

    <script>
        (function () {
            var form        = document.getElementById('forgotPasswordForm');
            var btn         = document.getElementById('sendResetLinkBtn');
            var btnText     = document.getElementById('fpBtnText');
            var emailInput  = document.getElementById('email');
            var emailError  = document.getElementById('email-error');
            var cooldownKey = 'kabataan_forgot_password_cooldown_until';
            var cooldownSec = 60;
            var timerIv     = null;

            function validEmail(v) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            }

            function showErr(msg) {
                emailInput.classList.add('error');
                emailError.textContent = msg;
                emailError.hidden = false;
                emailError.style.display = 'block';
            }

            function clearErr() {
                emailInput.classList.remove('error');
                emailError.hidden = true;
                emailError.style.display = 'none';
            }

            function setBtn(disabled, text, loading) {
                if (!btn || !btnText) return;
                btn.disabled = disabled;
                btnText.textContent = text;
                if (loading) {
                    btn.classList.add('loading');
                } else {
                    btn.classList.remove('loading');
                }
            }

            function clearCooldown() {
                localStorage.removeItem(cooldownKey);
                if (timerIv) { clearInterval(timerIv); timerIv = null; }
                setBtn(false, 'Send Reset Link', false);
            }

            function applyCooldown(until) {
                if (!until) return;
                if (timerIv) clearInterval(timerIv);
                timerIv = setInterval(function () {
                    var remaining = Math.ceil((until - Date.now()) / 1000);
                    if (remaining <= 0) { clearCooldown(); return; }
                    setBtn(true, 'Send Reset Link (' + remaining + 's)', false);
                }, 250);
            }

            // Restore any active cooldown on page load
            var storedUntil = Number(localStorage.getItem(cooldownKey) || 0);
            if (storedUntil > Date.now()) {
                applyCooldown(storedUntil);
            } else if (storedUntil) {
                clearCooldown();
            }

            // If the success alert is visible, start the cooldown timer
            var successAlert = document.getElementById('resetStatusAlert');
            if (successAlert) {
                var nextUntil = Date.now() + cooldownSec * 1000;
                localStorage.setItem(cooldownKey, String(nextUntil));
                applyCooldown(nextUntil);
            }

            // Clear inline error while typing
            if (emailInput) {
                emailInput.addEventListener('input', clearErr);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    var currentUntil = Number(localStorage.getItem(cooldownKey) || 0);
                    if (currentUntil > Date.now()) {
                        applyCooldown(currentUntil);
                        return;
                    }

                    clearErr();
                    var email = emailInput ? emailInput.value.trim() : '';

                    if (!email) {
                        showErr('Please enter your email address.');
                        return;
                    }
                    if (!validEmail(email)) {
                        showErr('Please enter a valid email address.');
                        return;
                    }

                    setBtn(true, 'Sending...', true);
                    if (typeof window.kabataanTurnstileSubmitForm === 'function') {
                        window.kabataanTurnstileSubmitForm(form).catch(function (err) {
                            setBtn(false, 'Send Reset Link', false);
                            if (err && err.message && err.message !== 'Verification cancelled.') {
                                showErr(err.message);
                            }
                        });
                        return;
                    }
                    form.submit();
                });
            }
        }());
    </script>

</body>
</html>
