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
                    <img src="{{ asset('images/skoneportal_logo.webp') }}" alt="SK OnePortal Logo" class="youth-logo">
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
            </div>
        </div>

        <div class="youth-signin-section">
            <div class="youth-signin-card">
                <div id="ceVerifySection"
                     data-status-url="{{ route('change-email.verify.status', [], false) }}"
                     data-resend-url="{{ route('change-email.resend', [], false) }}"
                     data-signin-url="{{ route('sign-in', [], false) }}"
                     data-dashboard-url="{{ route('dashboard', [], false) }}">
                    <div class="card-header ce-card-header">
                        <h2 class="card-title">Verify Email Change</h2>
                        <p class="card-helper-text">Check your new email and tap the confirmation link. This page will detect it automatically.</p>
                    </div>

                    <div class="ce-verify-content">
                        @if (session('error'))
                            <div class="youth-alert youth-alert-error" role="alert" id="ceVerifyFlashError">
                                <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ session('error') }}</span>
                            </div>
                        @elseif ($errors->any())
                            <div class="youth-alert youth-alert-error" role="alert">
                                <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="youth-alert youth-alert-error ce-live-alert" role="alert" id="ceVerifyLiveError" hidden></div>

                        @if (session('status'))
                            <div class="youth-alert youth-alert-success" role="status">
                                <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ session('status') }}</span>
                            </div>
                        @endif

                        <div class="ce-info-box" id="ceInfoBox">
                            A confirmation link has been sent to <strong id="cePendingEmail">{{ $user->pending_email }}</strong>. Your current email stays active until you verify the new one. After you confirm, you will be taken to your dashboard.
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
                                    <span class="ce-badge-awaiting" id="ceStatusBadge">Awaiting verification</span>
                                </span>
                            </div>
                        </div>

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

                        <div class="youth-register-section ce-back-section">
                            <p class="register-text">
                                <a href="{{ route('profile') }}" class="register-link">Back to Profile</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.ceResendCooldown = {{ (int) $resendCooldown }};
    </script>
</body>
</html>
