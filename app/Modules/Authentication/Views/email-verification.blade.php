<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Email Verification - SK Kabataan Portal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/youth-email-verification.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/youth-email-verification.js',
    ])
</head>
<body class="youth-signin-page">
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to resend the verification email.',
    ])
    <div id="email-verify-data"
         data-resend-url="{{ route('verification.resend', [], false) }}"
         data-email="{{ $email ?? '' }}"
         hidden
         aria-hidden="true"></div>
    <!-- Animated Background (same as signin page) -->
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
        <!-- Left Side - Logo & Branding (same as signin page) -->
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

        <!-- Right Side - Verification Card -->
        <div class="youth-signin-section">
            <div class="verification-card">
            <div class="verification-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 4H4C2.9 4 2.01 4.9 2.01 6L2 18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z" fill="#4CAF50"/>
                </svg>
            </div>
            
            <h1>Verify Your Email to Continue</h1>
            
            <p class="verification-message">
                We sent a verification email to <strong>"{{ $email ?? 'your email' }}"</strong>. 
                You can verify on any device. This page will continue automatically when verification is complete.
            </p>
            
            <div class="waiting-indicator">
                <div class="spinner"></div>
                <span>Waiting for verification...</span>
            </div>
            
            <div class="timer-container">
                <p>Email verification expires in: <span id="countdown-timer">10:00</span></p>
            </div>
            
            <button type="button" id="resend-btn" class="resend-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4C7.58 4 4.01 7.58 4.01 12C4.01 16.42 7.58 20 12 20C15.73 20 18.84 17.45 19.73 14H17.65C16.83 16.33 14.61 18 12 18C8.69 18 6 15.31 6 12C6 8.69 8.69 6 12 6C13.66 6 15.14 6.69 16.22 7.78L13 11H20V4L17.65 6.35Z" fill="currentColor"/>
                </svg>
                Resend Verification Email
            </button>
            
            <a href="{{ route('sign-in') }}" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 11H7.83L13.42 5.41L12 4L4 12L12 20L13.41 18.59L7.83 13H20V11Z" fill="currentColor"/>
                </svg>
                Back to Sign In
            </a>
            </div>
        </div>
    </main>

    <!-- Success Modal -->
    <div id="success-modal" class="modal">
        <div class="modal-content">
            <div class="success-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z" fill="#4CAF50"/>
                </svg>
            </div>
            <h2>Verification Successful!</h2>
            <p>Your email has been verified successfully. Redirecting to dashboard in 5 seconds...</p>
            <div class="modal-spinner"></div>
        </div>
    </div>
</body>
</html>
