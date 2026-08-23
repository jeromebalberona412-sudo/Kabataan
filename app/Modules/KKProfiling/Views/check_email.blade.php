<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Check Your Email - KK Profiling</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
    ])
    <style>
        .youth-login-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            background: linear-gradient(135deg, #022a54, #0450a8 55%, #1a6fd4);
            overflow: hidden;
        }

        .youth-login-container {
            position: relative;
            z-index: 10;
            flex: 1;
            display: flex;
            min-height: 100vh;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
            overflow: hidden;
            animation: pageFadeIn 0.6s ease forwards;
        }

        @keyframes pageFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .youth-branding-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 6rem;
        }

        .branding-content {
            max-width: 600px;
            text-align: center;
        }

        .logo-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(68, 165, 62, 0.15) 0%, rgba(253, 192, 32, 0.10) 50%, transparent 70%);
            border-radius: 50%;
        }

        .youth-logo {
            width: 240px;
            height: 240px;
            object-fit: cover;
            border-radius: 50%;
            filter: drop-shadow(0 16px 32px rgba(0, 0, 0, 0.4));
            position: relative;
            z-index: 1;
            border: 4px solid rgba(253, 192, 32, 0.6);
        }

        .youth-main-title {
            font-size: 5rem;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 1.5rem;
            letter-spacing: -0.04em;
            line-height: 1;
            text-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
        }

        .youth-tagline {
            font-size: 1.5rem;
            color: #fdc020;
            font-weight: 600;
            line-height: 1.6;
            letter-spacing: 0.01em;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        }

        .youth-login-section {
            width: 580px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            margin-left: auto;
            margin-right: 8rem;
            overflow: hidden;
        }

        .youth-login-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(40px);
            border-radius: 28px;
            padding: 2.5rem 3rem;
            box-shadow: 0 32px 80px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .youth-login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #44a53e 0%, #fdc020 50%, #0450a8 100%);
        }

        .card-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .card-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0450a8 0%, #0d5fc4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.625rem;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .card-subtitle {
            font-size: 0.95rem;
            color: #666;
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        .mail-icon-wrap {
            width: 68px;
            height: 68px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #e0ecff;
            color: #0450a8;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mail-icon-wrap svg { width: 34px; height: 34px; }

        .resend-wrap {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .resend-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            background: #0450a8;
            color: #fff;
            cursor: pointer;
        }
        .resend-btn:disabled {
            background: #93c5fd;
            cursor: not-allowed;
        }
        .resend-timer {
            font-size: 13px;
            color: #334155;
            font-weight: 600;
        }

        .email-display {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            color: #0450a8;
            margin: 1.5rem 0;
            border: 2px solid #e5e7eb;
        }

        .message-text {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .expiry-text {
            font-size: 0.85rem;
            color: #999;
            margin-top: 1rem;
        }

        .check-email-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .back-btn,
        .login-btn {
            display: inline-block;
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: box-shadow 0.3s;
        }

        .back-btn {
            background: linear-gradient(135deg, #44a53e 0%, #5cb854 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(68, 165, 62, 0.25);
        }

        .back-btn:hover {
            box-shadow: 0 12px 32px rgba(68, 165, 62, 0.35);
        }

        .login-btn {
            background: linear-gradient(135deg, #0450a8 0%, #0d5fc4 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(4, 80, 168, 0.25);
        }

        .login-btn:hover {
            box-shadow: 0 12px 32px rgba(4, 80, 168, 0.35);
        }

        @media (max-width: 1024px) {
            .youth-login-container {
                flex-direction: column;
                padding: 0;
                overflow-y: auto;
                height: auto;
            }
            .youth-branding-section {
                padding: 4rem 3rem 3rem;
                text-align: center;
            }
            .youth-main-title { font-size: 4rem; }
            .youth-tagline { font-size: 1.35rem; max-width: 600px; }
            .youth-login-section {
                width: 100%;
                max-width: 580px;
                margin: 0 auto;
                padding: 0 3rem 4rem;
                overflow-y: visible;
            }
        }

        @media (max-width: 768px) {
            .youth-branding-section { padding: 3rem 2rem 2rem; }
            .youth-main-title { font-size: 3rem; }
            .youth-tagline { font-size: 1.2rem; }
            .logo-wrapper::before { width: 240px; height: 240px; }
            .youth-logo { width: 180px; height: 180px; }
            .youth-login-section { padding: 0 2rem 3rem; }
            .youth-login-card { padding: 3rem 2.5rem; border-radius: 28px; }
            .card-title { font-size: 2.25rem; }
        }

        @media (max-width: 640px) {
            .youth-branding-section { padding: 2.5rem 1.5rem 2rem; }
            .youth-main-title { font-size: 2.5rem; }
            .youth-tagline { font-size: 1.1rem; }
            .logo-wrapper { margin-bottom: 2rem; }
            .logo-wrapper::before { width: 200px; height: 200px; }
            .youth-logo { width: 150px; height: 150px; }
            .youth-login-section { padding: 0 1.5rem 2.5rem; }
            .youth-login-card { padding: 2.5rem 2rem; border-radius: 24px; }
            .card-header { margin-bottom: 2rem; }
            .card-title { font-size: 2rem; }
        }
    </style>
</head>
<body class="youth-login-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to resend the verification email.',
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

    <main class="youth-login-container">
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
                <p class="youth-tagline">Official Youth Portal � Santa Cruz, Laguna</p>
            </div>
        </div>

        <!-- Right Side - Card -->
        <div class="youth-login-section">
            <div class="youth-login-card">
                <div class="card-header">
                    <div class="mail-icon-wrap" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                            <path d="M3 7l9 6 9-6"></path>
                        </svg>
                    </div>
                    <h2 class="card-title">Check Your Email ??</h2>
                    <p class="card-subtitle">Verify your email to continue</p>
                </div>

                <p class="message-text">Thank you for submitting your KK Profiling form!</p>
                <p class="message-text">We've sent a verification link to:</p>
                
                <div class="email-display">{{ $email }}</div>
                
                <p class="message-text">Please check your email and click the verification link to continue with your registration.</p>
                <p class="expiry-text">The link will expire in 24 hours.</p>
                <div class="resend-wrap">
                    <button
                        type="button"
                        class="resend-btn"
                        id="resendBtn"
                        disabled
                        data-email="{{ $email }}"
                        @if($barangay) data-barangay="{{ $barangay }}" @endif
                    >Resend Email Verification</button>
                    <span class="resend-timer" id="resendTimer">(1:00)</span>
                </div>
                <p id="resendStatus" class="message-text" style="display:none; margin-top:0.5rem;"></p>

                <div class="check-email-actions">
                    @if($barangay)
                        <?php
                            $barangaySlug = strtolower(trim($barangay));
                            $barangaySlug = preg_replace('/[^a-z0-9]+/', '-', $barangaySlug);
                            $barangaySlug = trim($barangaySlug, '-');
                        ?>
                        <a href="{{ route('kkprofiling', ['barangay' => $barangaySlug]) }}" class="back-btn" onclick="handleBackClick(event)">Back to KK Profiling</a>
                    @else
                        <a href="{{ route('kkprofiling.signup') }}" class="back-btn" onclick="handleBackClick(event)">Back to KK Profiling</a>
                    @endif
                    <a href="{{ route('sign-in') }}" class="login-btn" onclick="handleBackClick(event)">Go to Login</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            if (!resendBtn || !resendTimer) return;

            const emailKey = (resendBtn.dataset.email || 'default').toLowerCase();
            const cooldownKey = 'kabataan_kk_resend_cooldown_' + emailKey;
            const cooldownMs = 60 * 1000;
            let timerInterval = null;

            function formatTimer(seconds) {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return '(' + m + ':' + (s < 10 ? '0' : '') + s + ')';
            }

            function clearCooldown() {
                localStorage.removeItem(cooldownKey);
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Email Verification';
                resendTimer.style.display = 'none';
                resendTimer.textContent = '';
            }

            function applyCooldown(untilTimestamp, persist) {
                if (persist) {
                    localStorage.setItem(cooldownKey, String(untilTimestamp));
                }

                if (timerInterval) {
                    clearInterval(timerInterval);
                }

                resendBtn.disabled = true;
                resendBtn.textContent = 'Resend Email Verification';
                resendTimer.style.display = 'inline';

                timerInterval = setInterval(function () {
                    const remainingMs = untilTimestamp - Date.now();
                    const remainingSeconds = Math.ceil(remainingMs / 1000);

                    if (remainingSeconds <= 0) {
                        clearCooldown();
                        return;
                    }

                    resendTimer.textContent = formatTimer(remainingSeconds);
                }, 250);
            }

            function startNewCooldown() {
                applyCooldown(Date.now() + cooldownMs, true);
            }

            const storedUntil = Number(localStorage.getItem(cooldownKey) || 0);
            if (storedUntil > Date.now()) {
                applyCooldown(storedUntil, false);
            } else if (storedUntil > 0) {
                localStorage.removeItem(cooldownKey);
                clearCooldown();
            } else {
                startNewCooldown();
            }

            resendBtn.addEventListener('click', async function() {
                if (this.disabled) return;

                const email = this.dataset.email;
                const barangay = this.dataset.barangay || '';
                const statusEl = document.getElementById('resendStatus');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                this.disabled = true;
                if (statusEl) {
                    statusEl.style.display = 'none';
                    statusEl.style.color = '#666';
                }

                try {
                    let turnstileToken = '';
                    if (window.kabataanTurnstileChallenge) {
                        try {
                            turnstileToken = await window.kabataanTurnstileChallenge();
                        } catch {
                            this.disabled = false;
                            return;
                        }
                    } else if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                        try {
                            turnstileToken = await window.KabataanTurnstileGate.challenge();
                        } catch {
                            this.disabled = false;
                            return;
                        }
                    }

                    const response = await fetch('/api/kkprofiling/resend-verification', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            email: email,
                            barangay: barangay,
                            'cf-turnstile-response': turnstileToken,
                        }),
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        this.textContent = 'Email sent!';
                        if (statusEl) {
                            statusEl.textContent = data.message || 'Verification email has been resent. Please check your inbox.';
                            statusEl.style.color = '#15803d';
                            statusEl.style.display = 'block';
                        }
                    } else {
                        if (statusEl) {
                            statusEl.textContent = data.message || 'Failed to resend verification email. Please try again.';
                            statusEl.style.color = '#b91c1c';
                            statusEl.style.display = 'block';
                        }
                        this.disabled = false;
                        return;
                    }
                } catch (err) {
                    if (statusEl) {
                        statusEl.textContent = 'Failed to resend verification email. Please check your connection and try again.';
                        statusEl.style.color = '#b91c1c';
                        statusEl.style.display = 'block';
                    }
                    this.disabled = false;
                    return;
                }

                setTimeout(() => {
                    this.textContent = 'Resend Email Verification';
                    startNewCooldown();
                }, 2000);
            });
        })();

        function handleBackClick(e) {
            e.preventDefault();
            window.location.href = e.target.href;
        }
    </script>
</body>
</html>
