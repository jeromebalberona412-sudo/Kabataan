<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($mode ?? 'form') === 'sent' ? 'Check your email' : 'Add your email' }}</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Guest_Kabataan/assets/css/guest_kabataan.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Guest_Kabataan/assets/js/guest_kabataan.js',
    ])
</head>
<body class="guest-kabataan-body">
    @include('layout::kabataan-header', [
        'guestHeader' => true,
        'barangay' => $barangay,
    ])
    @include('guest_kabataan::partials.guest_kabataan_subnav')
    @include('guest_kabataan::partials.guest_kabataan_header')
    @if (($mode ?? 'form') === 'form')
        @include('authentication::partials.turnstile-gate', [
            'turnstileSubtitle' => 'Complete the security check before we send the set-password email.',
        ])
    @endif

    <main class="guest-kabataan-main guest-kabataan-activate" id="guestKabataanActivate" data-mode="{{ $mode ?? 'form' }}" data-send-url="{{ route('guest_kabataan.activate.send') }}" data-sent-url="{{ route('guest_kabataan.activate.sent') }}" data-resend-url="{{ route('guest_kabataan.activate.resend') }}" data-check-url="{{ route('kkprofiling.check-email-exists') }}" data-current-email="{{ $email }}" data-cooldown="{{ (int) ($cooldown ?? 0) }}">
        @if (($mode ?? 'form') === 'form')
            <section class="guest-kabataan-panel" id="guestKabataanEmailForm">
                <p class="guest-kabataan-kicker">Account activation</p>
                <h1>Add your email</h1>
                <p>Your KK Profiling matched. Enter the email you will use to sign in. We will send a set-password link, the same way KK Profiling email verification does.</p>
                <form id="guestKabataanEmailSend" novalidate>
                    <p class="guest-kabataan-error" id="guestKabataanEmailError" role="alert" hidden></p>
                    <label class="guest-kabataan-email-label">Email address
                        <input
                            type="email"
                            name="email"
                            maxlength="64"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Enter your permanent email address"
                            required
                            value=""
                        >
                        <span class="guest-kabataan-field-error" id="guestKabataanEmailFieldError" hidden></span>
                    </label>
                    <p class="guest-kabataan-email-hint">Use your own permanent email. Temporary or disposable email addresses (like 10minutemail or tempmail) are not accepted.</p>
                    <button type="submit" class="guest-kabataan-btn">Send set-password link</button>
                </form>
            </section>
        @else
            <section class="guest-kabataan-panel" id="guestKabataanEmailSent">
                <p class="guest-kabataan-kicker">Account activation</p>
                <h1>Check your email</h1>
                <p>We sent a set password link to:</p>
                <p class="guest-kabataan-email-shown" id="guestKabataanEmailShown">{{ $email }}</p>
                <p>Open your inbox and click the <strong>Set Password</strong> link. After you create your password, you can sign in.</p>
                <p class="guest-kabataan-error" id="guestKabataanResendError" role="alert" hidden></p>
                <div class="guest-kabataan-sent-actions">
                    <button type="button" class="guest-kabataan-btn guest-kabataan-btn--resend" id="guestKabataanResend">Resend set password link</button>
                    <p class="guest-kabataan-resend-timer" id="guestKabataanResendTimer" hidden></p>
                    <a href="{{ route('guest_kabataan.activate') }}" class="guest-kabataan-btn guest-kabataan-btn--ghost">Cancel</a>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
