<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($errorType ?? '') === 'expired' ? 'Activation Link Expired' : 'Activation Link Unavailable' }} - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
    ])
</head>
<body class="youth-signin-page kkp-setpw-page">
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
            <div class="youth-signin-card kkp-setpw-card">
                <div class="card-header">
                    <p class="card-subtitle">{{ ($errorType ?? '') === 'expired' ? 'Activation Link Expired' : 'Activation Link Unavailable' }}</p>
                    <p class="card-helper-text">
                        @if (($errorType ?? '') === 'expired')
                            Your activation link is no longer valid. You can request a new activation link from the login page.
                        @else
                            {{ $message }}
                        @endif
                    </p>
                </div>

                <a href="{{ route('account.activation.request') }}" class="youth-submit-btn kkp-setpw-signin-link">Activate Account</a>
                <a href="{{ route('sign-in') }}" class="youth-homepage-btn">Back to Login</a>
            </div>
        </div>
    </main>
</body>
</html>
