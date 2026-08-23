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
    <title>Change Email - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Profile/assets/css/change-email.css',
        'app/Modules/Profile/assets/js/change-email.js',
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

        <!-- Left Side — Logo & Branding -->
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
                <p class="youth-tagline">Official Youth Portal – Santa Cruz, Laguna</p>
            </div>
        </div>

        <!-- Right Side — Card -->
        <div class="youth-signin-section">
            <div class="youth-signin-card">

                {{-- ── STEP 1: Change Email Form ─────────────────── --}}
                <div id="ceStep1">
                    <div class="card-header ce-card-header">
                        <h2 class="card-title">Change Email</h2>
                        <p class="card-helper-text">Enter your current email, new email address, and current password to request a change.</p>
                    </div>

                    @if ($errors->any())
                        <div class="youth-alert youth-alert-error">
                            <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Form -->
                    <form class="youth-signin-form" id="ceForm" action="{{ route('change-email.request') }}" method="POST" novalidate>
                        @csrf

                        {{-- Current Email --}}
                        <div class="youth-form-group">
                            <label for="ceCurrentEmail" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                Current Email
                            </label>
                            <input
                                type="email"
                                id="ceCurrentEmail"
                                name="current_email"
                                class="youth-input"
                                placeholder="Enter your current email"
                                autocomplete="email"
                                maxlength="100"
                                value="{{ old('current_email', $user->email) }}"
                                autofocus
                                required
                            >
                            <div class="youth-field-error" id="ceCurrentEmailError" hidden></div>
                        </div>

                        {{-- New Email Address --}}
                        <div class="youth-form-group">
                            <label for="ceNewEmail" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                New Email Address
                            </label>
                            <input
                                type="email"
                                id="ceNewEmail"
                                name="new_email"
                                class="youth-input"
                                placeholder="Enter your new email address"
                                autocomplete="off"
                                maxlength="100"
                                required
                            >
                            <div class="youth-field-error" id="ceNewEmailError" hidden></div>
                        </div>

                        {{-- Current Password --}}
                        <div class="youth-form-group">
                            <label for="cePassword" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Current Password
                            </label>
                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="cePassword"
                                    name="password"
                                    class="youth-input password-input"
                                    placeholder="Enter your current password"
                                    autocomplete="current-password"
                                    maxlength="64"
                                    required
                                >
                                <button type="button" class="pw-toggle-btn" data-target="cePassword" aria-label="Show password" tabindex="-1">
                                    <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
                            <div class="youth-field-error" id="cePasswordError" hidden></div>
                        </div>

                        <button type="submit" class="youth-submit-btn" id="ceSubmitBtn">
                            <span id="ceBtnText">Send Verification Link</span>
                        </button>
                    </form>

                    <div class="youth-register-section ce-back-section">
                        <p class="register-text">
                            <a href="{{ route('profile') }}" class="register-link">← Back to Profile</a>
                        </p>
                    </div>
                </div>

            </div>
        </div>

    </main>

</body>
</html>
