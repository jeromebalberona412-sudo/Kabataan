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
    <title>Activate Account - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/auth-legal.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
    ])
</head>
<body class="youth-signin-page youth-activate-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to send an activation link.',
    ])

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
                <p class="youth-tagline">Official Youth Portal &ndash; Santa Cruz, Laguna</p>
            </div>
        </div>

        <div class="youth-signin-section">
            <div class="youth-signin-card">
                <div class="card-header activate-card-header">
                    <p class="card-subtitle">Activate Account</p>
                    <p class="card-helper-text">Enter the email address registered to your account. If your previous activation link expired, we can send you a new activation link.</p>
                </div>

                @if (session('verify_account_error'))
                    <div class="youth-alert youth-alert-error" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('verify_account_error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="youth-alert youth-alert-error" role="alert">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form class="youth-signin-form" method="POST" action="{{ route('account.activation.send') }}" id="verifyAccountForm" novalidate>
                    @csrf

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
                            inputmode="email"
                            placeholder="Enter example@gmail.com"
                            maxlength="150"
                            required
                        >
                        <div class="youth-field-error" id="email-error" hidden></div>
                    </div>

                    <button type="submit" class="youth-submit-btn" id="sendActivationLinkBtn">
                        <span class="spinner"></span>
                        <span id="verifyBtnText">Send Activation Link</span>
                    </button>
                </form>

                <div class="youth-register-section">
                    <p class="register-text">
                        <a href="{{ route('sign-in') }}" class="register-link">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            var form = document.getElementById('verifyAccountForm');
            var btn = document.getElementById('sendActivationLinkBtn');
            var btnText = document.getElementById('verifyBtnText');
            var emailInput = document.getElementById('email');
            var emailError = document.getElementById('email-error');

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

            if (emailInput) {
                emailInput.addEventListener('input', clearErr);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    clearErr();
                    var email = emailInput ? emailInput.value.trim() : '';

                    if (!email) {
                        showErr('Invalid email or no email.');
                        return;
                    }
                    if (!validEmail(email)) {
                        showErr('Invalid email or no email.');
                        return;
                    }
                    if (btn && btn.disabled) {
                        return;
                    }
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('loading');
                    }
                    if (btnText) {
                        btnText.textContent = 'Sending...';
                    }
                    if (typeof window.kabataanTurnstileSubmitForm === 'function') {
                        window.kabataanTurnstileSubmitForm(form).catch(function (err) {
                            if (btn) {
                                btn.disabled = false;
                                btn.classList.remove('loading');
                            }
                            if (btnText) {
                                btnText.textContent = 'Send Activation Link';
                            }
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
