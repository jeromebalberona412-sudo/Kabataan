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
    <title>{{ $title }} - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
    ])
</head>
<body class="youth-signin-page youth-activate-page">

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
                    <p class="card-subtitle">{{ $title }}</p>
                    <p class="card-helper-text">{{ $message }}</p>
                </div>

                <div class="activate-card-actions">
                    @if (! empty($showVerifyButton))
                        <a href="{{ route('account.activation.request') }}" class="youth-submit-btn">Activate Account</a>
                        <a href="{{ route('sign-in') }}" class="youth-homepage-btn">Back to Login</a>
                    @else
                        <a href="{{ route('sign-in') }}" class="youth-submit-btn">Go to Login</a>
                    @endif
                </div>
            </div>
        </div>
    </main>

</body>
</html>
