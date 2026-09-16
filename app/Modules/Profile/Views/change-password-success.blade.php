<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Password Changed - SK Kabataan</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Profile/assets/css/change-email.css',
        'app/Modules/Profile/assets/css/account-change-success.css',
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
                <div class="kab-success-content" role="status" aria-live="polite">
                    <div class="kab-check-wrap" aria-hidden="true">
                        <span class="kab-check-icon">✓</span>
                    </div>
                    <h1 class="kab-success-title">Password changed successfully</h1>
                    <p class="kab-success-message">
                        @if (!empty($email))
                            Your password for <strong>{{ $email }}</strong> has been updated.
                        @else
                            Your password has been updated.
                        @endif
                    </p>
                    <p class="kab-success-message">Please sign in with your new password.</p>
                    <a href="{{ route('sign-in') }}" class="kab-success-btn">Go to Login</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
