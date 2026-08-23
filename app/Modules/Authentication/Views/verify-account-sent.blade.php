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
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
    ])
</head>
<body class="youth-signin-page youth-activate-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to resend the activation email.',
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
                    <p class="card-subtitle">Check your email</p>
                    <p class="card-helper-text">{{ $message }}</p>
                </div>

                <form class="youth-signin-form" method="POST" action="{{ route('account.activation.send') }}" id="resendActivationForm">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button
                        type="submit"
                        class="youth-submit-btn"
                        id="resendActivationBtn"
                        data-remaining="{{ (int) $cooldownRemaining }}"
                        data-cooldown="{{ (int) $cooldownSeconds }}"
                    >
                        <span class="spinner"></span>
                        <span id="resendActivationLabel">Resend activation email</span>
                    </button>
                </form>

                <div class="activate-card-actions">
                    <a href="{{ route('account.activation.sent') }}" class="youth-homepage-btn">Cancel</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            var form = document.getElementById('resendActivationForm');
            var btn = document.getElementById('resendActivationBtn');
            var label = document.getElementById('resendActivationLabel');
            if (!form || !btn || !label) {
                return;
            }

            var remaining = parseInt(btn.getAttribute('data-remaining'), 10) || 0;

            function formatTime(seconds) {
                var mins = Math.floor(seconds / 60);
                var secs = seconds % 60;
                return mins + ':' + String(secs).padStart(2, '0');
            }

            function tick() {
                if (remaining <= 0) {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    label.textContent = 'Resend activation email';
                    return;
                }

                btn.disabled = true;
                btn.classList.add('loading');
                label.textContent = 'Resend in ' + formatTime(remaining);
                remaining -= 1;
                window.setTimeout(tick, 1000);
            }

            tick();

            form.addEventListener('submit', function (e) {
                if (btn.disabled) {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                btn.disabled = true;
                btn.classList.add('loading');
                label.textContent = 'Sending...';

                var submitWithTurnstile = window.kabataanTurnstileSubmitForm;
                if (!submitWithTurnstile) {
                    form.submit();
                    return;
                }
                submitWithTurnstile(form).catch(function (err) {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    label.textContent = 'Resend activation email';
                    if (err && err.message && err.message !== 'Verification cancelled.') {
                        window.alert(err.message);
                    }
                });
            });
        }());
    </script>

</body>
</html>
