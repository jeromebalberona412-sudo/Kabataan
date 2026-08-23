<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
    ])
</head>
<body class="youth-signin-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to reset your password.',
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

        <!-- Right Side - Reset Password Card -->
        <div class="youth-signin-section youth-signin-section--fp">
            <div class="youth-signin-card">

                {{-- Card Header — centered, same pattern as forgot-password --}}
                <div class="card-header fp-card-header">
                    <p class="card-subtitle">Reset Your Password</p>
                    <p class="card-helper-text">Enter your new password below.</p>
                </div>

                <!-- Reset Password Form -->
                <form class="youth-signin-form" method="POST" action="{{ route('password.update') }}" id="resetPasswordForm" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token ?? '' }}">
                    <input type="hidden" name="email" value="{{ $email ?? request()->email }}">

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
                            <button type="button" class="pw-toggle-btn" id="pwToggleBtn1" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        <ul class="rp-password-rules" id="passwordRules" aria-live="polite">
                            <li id="rule-length">
                                <span class="rp-rule-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3.1 6-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                At least 8 characters
                            </li>
                            <li id="rule-lowercase">
                                <span class="rp-rule-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3.1 6-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                At least one lowercase letter
                            </li>
                            <li id="rule-uppercase">
                                <span class="rp-rule-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3.1 6-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                At least one uppercase letter
                            </li>
                            <li id="rule-number">
                                <span class="rp-rule-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3.1 6-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                At least one number
                            </li>
                            <li id="rule-special">
                                <span class="rp-rule-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3.1 6-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                At least one special character
                            </li>
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
                                placeholder="Re-enter your password"
                            >
                            <button type="button" class="pw-toggle-btn" id="pwToggleBtn2" aria-label="Show password" tabindex="-1">
                                <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        <span class="youth-field-error" id="confirmPasswordError" style="display:none;"></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="youth-submit-btn" id="resetSubmitBtn">
                        <span id="resetBtnText">Reset Password</span>
                    </button>
                </form>

                <!-- Back to Sign In -->
                <div class="youth-register-section">
                    <p class="register-text">
                        Remember your password?
                        <a href="{{ route('sign-in') }}" class="register-link">Back to Sign In</a>
                    </p>
                </div>

            </div>
        </div>
    </main>

    <!-- Success Modal -->
    <div class="success-modal-overlay" id="successModal" style="display:none;">
        <div class="success-modal">
            <div class="success-modal-content">
                <div class="success-modal-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path d="M9 12l2 2 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2 class="success-modal-title">Password Reset Successful</h2>
                <p class="success-modal-body">Your password has been reset successfully.</p>
                <p class="success-modal-redirect">
                    Redirecting to sign in page in <span id="countdown">3</span> seconds...
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ── Password toggle (using sign-in.css pw-toggle-btn classes) ──
            document.querySelectorAll('.pw-toggle-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var wrapper = this.closest('.password-wrapper');
                    var input   = wrapper ? wrapper.querySelector('.password-input') : null;
                    if (!input) return;
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                    this.classList.toggle('pw-visible', show);
                });
            });

            // ── Password strength validation ──
            function validatePasswordStrength(password) {
                return {
                    hasMinLength : password.length >= 8,
                    hasLowerCase : /[a-z]/.test(password),
                    hasUpperCase : /[A-Z]/.test(password),
                    hasNumber    : /[0-9]/.test(password),
                    hasSpecial   : /[^A-Za-z0-9]/.test(password),
                    get isValid() {
                        return this.hasMinLength && this.hasLowerCase &&
                               this.hasUpperCase && this.hasNumber && this.hasSpecial;
                    }
                };
            }

            // ── Live password rules checklist ──
            var passwordInput = document.getElementById('password');
            var passwordRules = document.getElementById('passwordRules');
            var confirmInput  = document.getElementById('password_confirmation');
            var confirmErr    = document.getElementById('confirmPasswordError');

            function updatePasswordRules(value) {
                if (!passwordRules) return;

                var s = validatePasswordStrength(value);
                var rules = [
                    { id: 'rule-length',    ok: s.hasMinLength  },
                    { id: 'rule-lowercase', ok: s.hasLowerCase  },
                    { id: 'rule-uppercase', ok: s.hasUpperCase  },
                    { id: 'rule-number',    ok: s.hasNumber     },
                    { id: 'rule-special',   ok: s.hasSpecial    },
                ];

                rules.forEach(function (rule) {
                    var node = document.getElementById(rule.id);
                    if (node) node.classList.toggle('ok', rule.ok);
                });

                if (!value.length) {
                    passwordRules.classList.remove('active', 'is-complete');
                    return;
                }

                if (s.isValid) {
                    passwordRules.classList.add('is-complete');
                    passwordRules.classList.remove('active');
                    return;
                }

                passwordRules.classList.add('active');
                passwordRules.classList.remove('is-complete');
            }

            function updateConfirmMatch() {
                if (!confirmErr || !confirmInput) return;

                var newPassword = passwordInput ? passwordInput.value : '';
                var confirmation = confirmInput.value;

                if (!confirmation.length) {
                    confirmErr.textContent = '';
                    confirmErr.style.display = 'none';
                    confirmInput.classList.remove('error');
                    return;
                }

                if (newPassword !== confirmation) {
                    confirmErr.textContent = 'Passwords do not match.';
                    confirmErr.style.display = 'block';
                    confirmInput.classList.add('error');
                    return;
                }

                confirmErr.textContent = '';
                confirmErr.style.display = 'none';
                confirmInput.classList.remove('error');
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', function () {
                    updatePasswordRules(this.value);
                    updateConfirmMatch();
                });
            }

            if (confirmInput) {
                confirmInput.addEventListener('input', updateConfirmMatch);
                confirmInput.addEventListener('blur', updateConfirmMatch);
            }

            // ── Form submit ──
            var form     = document.getElementById('resetPasswordForm');
            var submitBtn  = document.getElementById('resetSubmitBtn');
            var btnText    = document.getElementById('resetBtnText');

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var password     = document.getElementById('password').value;
                var confirmation = document.getElementById('password_confirmation').value;

                updateConfirmMatch();

                var strength = validatePasswordStrength(password);
                if (!strength.isValid) {
                    updatePasswordRules(password);
                    if (passwordRules) passwordRules.classList.add('active');
                    return;
                }

                if (password !== confirmation) {
                    if (confirmErr && confirmInput) {
                        confirmErr.textContent = 'Passwords do not match.';
                        confirmErr.style.display = 'block';
                        confirmInput.classList.add('error');
                    }
                    return;
                }

                // All valid — show loading
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                btnText.textContent = 'Resetting...';

                var gateSubmit = window.kabataanTurnstileSubmitForm;
                if (!gateSubmit) {
                    form.submit();
                    return;
                }
                gateSubmit(form).catch(function (err) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    btnText.textContent = 'Reset Password';
                    if (err && err.message && err.message !== 'Verification cancelled.') {
                        window.alert(err.message);
                    }
                });
            });
        });
    </script>

    <style>
        /* ── Success Modal ── */
        .success-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .success-modal {
            background: #fff;
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 90%;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        .success-modal-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #44a53e 0%, #5cb854 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(68, 165, 62, 0.3);
        }

        .success-modal-icon svg {
            width: 44px;
            height: 44px;
            color: #fff;
        }

        .success-modal-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0450a8;
            margin: 0 0 0.75rem;
            letter-spacing: -0.02em;
        }

        .success-modal-body {
            font-size: 1rem;
            color: #334155;
            font-weight: 500;
            margin: 0 0 0.5rem;
        }

        .success-modal-redirect {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
        }

        /* Keep fields close; only grow when rules are visible */
        #resetPasswordForm {
            gap: 0.7rem;
        }

        #resetPasswordForm .youth-form-group {
            gap: 0.4rem;
        }

        .youth-signin-section--fp .fp-card-header {
            margin-bottom: 1.1rem;
        }

        .youth-signin-section--fp .fp-card-header .card-helper-text {
            margin-top: 0.2rem;
        }

        /* ── Password rules checklist ── */
        .rp-password-rules {
            list-style: none;
            margin: -0.4rem 0 0;
            padding: 0;
            background: #f8fafc;
            border: 0 solid #e2e8f0;
            border-radius: 10px;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            pointer-events: none;
            transition: opacity 0.22s ease, max-height 0.28s ease, margin 0.22s ease, padding 0.22s ease, border-width 0.22s ease;
        }

        .rp-password-rules.active {
            opacity: 1;
            max-height: 240px;
            margin-top: 0.2rem;
            padding: 0.55rem 0.75rem;
            border-width: 1px;
            pointer-events: auto;
        }

        .rp-password-rules.is-complete {
            opacity: 0;
            max-height: 0;
            margin-top: -0.4rem;
            padding: 0;
            border-width: 0;
            pointer-events: none;
        }

        .rp-password-rules li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: #64748b;
            padding: 3px 0;
            line-height: 1.35;
            transition: color 0.2s ease;
        }

        .rp-password-rules li.ok {
            color: #15803d;
            font-weight: 600;
        }

        .rp-rule-icon {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #fff;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.18s ease, border-color 0.18s ease;
        }

        .rp-rule-icon svg {
            width: 10px;
            height: 10px;
            opacity: 0;
        }

        .ok .rp-rule-icon {
            background: #16a34a;
            border-color: #16a34a;
        }

        .ok .rp-rule-icon svg {
            opacity: 1;
        }

        /* Spinner animation */
        @keyframes rp-spin {
            to { transform: rotate(360deg); }
        }
    </style>

</body>
</html>
