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
    <title>Verify Email Change - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Profile/assets/css/change-email.css',
        'app/Modules/Profile/assets/js/change-email-verify.js',
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
                    <img src="/images/skoneportal_logo.webp" alt="SK OnePortal Logo" class="youth-logo">
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
            </div>
        </div>

        <div class="youth-signin-section">
            <div class="youth-signin-card">
                <div id="ceVerifySection"
                     data-status-url="{{ route('change-email.verify.status', [], false) }}">
                    <div class="card-header ce-card-header">
                        <h2 class="card-title">Verify Email Change</h2>
                        <p class="card-helper-text">Check your new email and tap the confirmation link. This page will detect it automatically.</p>
                    </div>

                    <div class="ce-verify-content">
                        <div class="cp-listening-badge {{ $awaitingPassword ? 'is-confirmed' : '' }}" id="ceListeningBadge">
                            <span class="cp-listening-dot"></span>
                            {{ $awaitingPassword ? 'Waiting for new password…' : 'Listening for email confirmation…' }}
                        </div>

                        @if ($errors->any())
                            <div class="youth-alert youth-alert-error">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="ce-info-box">{{ session('status') }}</div>
                        @endif

                        <div class="ce-info-box">
                            A confirmation link has been sent to <strong id="cePendingEmail">{{ $user->pending_email }}</strong>. Your current email stays active until you verify the new one.
                        </div>

                        <div class="ce-status-table">
                            <div class="ce-status-row">
                                <span class="ce-status-key">Current email</span>
                                <span class="ce-status-val" id="ceCurrentEmailVal">{{ $user->email }}</span>
                            </div>
                            <div class="ce-status-row">
                                <span class="ce-status-key">Pending email</span>
                                <span class="ce-status-val" id="cePendingEmailVal">{{ $user->pending_email }}</span>
                            </div>
                            <div class="ce-status-row">
                                <span class="ce-status-key">Status</span>
                                <span class="ce-status-val">
                                    <span class="ce-badge-awaiting" id="ceStatusBadge" @if($awaitingPassword) style="background:#fef3c7;color:#92400e;" @endif>{{ $awaitingPassword ? 'Awaiting password' : 'Awaiting verification' }}</span>
                                </span>
                            </div>
                        </div>

                        @unless($awaitingPassword)
                            <div class="ce-resend-timer" id="ceTimer" @if($resendCooldown <= 0) style="display:none;" @endif>
                                Resend available in <strong id="ceTimerCount">{{ $resendCooldown > 0 ? sprintf('%d:%02d', intdiv($resendCooldown, 60), $resendCooldown % 60) : '1:00' }}</strong>
                            </div>

                            <div class="ce-actions">
                                <form action="{{ route('change-email.resend') }}" method="POST" id="ceResendForm">
                                    @csrf
                                    <button type="submit" class="ce-btn-resend" id="ceResendBtn" @if($resendCooldown > 0) disabled @endif>
                                        Resend Verification
                                    </button>
                                </form>
                                <form action="{{ route('change-email.cancel') }}" method="POST" id="ceCancelForm">
                                    @csrf
                                    <button type="submit" class="ce-btn-cancel" id="ceCancelBtn">
                                        Cancel Request
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="ce-info-box">
                                Complete the <strong>Set New Password</strong> step on the tab where you opened the confirmation link. This page will sign you out automatically once your password is set.
                            </div>
                        @endunless

                        <div class="youth-register-section ce-back-section">
                            <p class="register-text">
                                <a href="{{ route('profile') }}" class="register-link">← Back to Profile</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.ceResendCooldown = {{ (int) $resendCooldown }};
        window.ceAwaitingPassword = {{ $awaitingPassword ? 'true' : 'false' }};
    </script>
</body>
</html>
