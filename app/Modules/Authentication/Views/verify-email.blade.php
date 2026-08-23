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
    <title>Check Your Email - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/youth-fp-verify-email.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/youth-fp-verify-email.js',
    ])
</head>
<body class="youth-signin-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to resend the reset link.',
    ])

    <script>
        (function () {
            /* Prevent back-button from navigating away from this page */
            window.history.pushState(null, '', window.location.href);
            window.onpopstate = function () {
                window.history.pushState(null, '', window.location.href);
            };
        })();
    </script>

    {{-- ── Data block: backend passes the source-of-truth timestamp to the JS ── --}}
    <div id="fp-verify-data"
         data-email="{{ $email }}"
         data-resend-url="{{ route('password.verify-email.resend', [], false) }}"
         data-signin-url="{{ route('password.request', [], false) }}"
         data-resend-available-at="{{ $resendAvailableAt }}"
         data-cooldown-secs="{{ $resendCooldownSecs }}"
         hidden
         aria-hidden="true"></div>

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

        {{-- ─── Right Side — Verify Email Card ──────────────────────────────────── --}}
        <div class="youth-signin-section youth-signin-section--fp">
            <div class="youth-signin-card" style="padding-top: 1.75rem;">

                {{-- Header --}}
                <div class="fpve-header">
                    {{-- Envelope icon --}}
                    <div class="fpve-icon-wrap" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                             stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="M22 7l-10 7L2 7"/>
                        </svg>
                    </div>
                    <p class="fpve-title">Check Your Email</p>
                    <p class="fpve-subtitle">
                        We sent a password reset link to<br>
                        <strong id="fpve-email">{{ $email }}</strong>
                    </p>
                </div>

                {{-- Status messages --}}
                <div id="fpve-status" class="fpve-status" hidden role="alert"></div>

                {{-- Resend section --}}
                <div class="fpve-resend-section">

                    {{-- Countdown display --}}
                    <div id="fpve-cooldown" class="fpve-cooldown" hidden>
                        Resend available in&nbsp;<strong id="fpve-countdown">1:00</strong>
                    </div>

                    {{-- Resend button --}}
                    <button type="button" id="fpve-resend-btn" class="fpve-resend-btn" disabled>
                        <span id="fpve-resend-spinner" class="fpve-spinner" hidden aria-hidden="true"></span>
                        <span id="fpve-resend-label">Resend Reset Link</span>
                    </button>

                    {{-- Cancel button — redirects back to /forgot-password --}}
                    <a href="{{ route('password.request') }}" class="fpve-cancel-btn">
                        Cancel
                    </a>

                </div>

                {{-- Back to signin --}}
                <div class="youth-register-section">
                    <a href="{{ route('sign-in') }}" class="fpve-back-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Back to Sign In
                    </a>
                </div>

            </div>{{-- /.youth-signin-card --}}
        </div>{{-- /.youth-signin-section --}}

    </main>

</body>
</html>
