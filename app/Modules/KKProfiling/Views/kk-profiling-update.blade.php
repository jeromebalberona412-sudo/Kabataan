<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KK Profiling Update{{ !empty($kkProfilingTargetYear) ? ' '.$kkProfilingTargetYear : '' }} - SK OnePortal</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
        'app/Modules/Layout/assets/css/kabataan-responsive.css',
        'app/Modules/Layout/assets/css/kabataan-logout.css',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/Layout/assets/js/kabataan-session-timeout.js',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-form-body.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-signature.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-responsive.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-optional-email.css',
        'app/Modules/KKProfiling/assets/css/kk-profiling-update.css',
        'app/Modules/KKProfiling/assets/js/kkprofiling.js',
        'app/Modules/KKProfiling/assets/js/kk-profiling-update.js',
    ])
</head>
<body class="kkpu-page-body">
    <header class="kkpu-lock-bar" aria-label="Required KK Profiling update">
        <div class="kkpu-lock-bar__brand">
            <img src="{{ asset('images/skoneportal_logo.webp') }}" alt="SK OnePortal" class="kkpu-lock-bar__logo">
            <span class="kkpu-lock-bar__title">
                Kabataan
                <small>SK OnePortal Santa Cruz</small>
            </span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="kkpu-lock-bar__logout logout-btn">Logout</button>
        </form>
    </header>

    <main class="kkpu-page">
        <header class="kkpu-page-header">
            <p class="kkpu-page-eyebrow">Annual KK Profiling Update</p>
            <h1 class="kkpu-page-title">
                KK Profiling Update
                @if (!empty($kkProfilingTargetYear))
                    <span class="kkpu-page-year">Profiling Year: {{ $kkProfilingTargetYear }}</span>
                @endif
            </h1>
            <p class="kkpu-page-subtitle">
                Please update your KK Profiling for {{ $kkProfilingTargetYear ?? 'this year' }} before continuing to the Kabataan Portal.
                This is an annual update for your existing account — not a new registration.
            </p>
        </header>

        @if ($errors->any())
            <div class="kkp-alert kkp-alert-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="kkp-paper kkpu-paper" id="kkpuFormSection">
            <div class="kkp-responsive-container">
                <div class="kkp-fs-scale-shell">
                    <div class="kkp-fs-scale-inner">
                        <form method="POST" action="{{ route('kkprofiling.update') }}" id="kkProfilingUpdateForm" data-email-locked="1" novalidate>
                            @csrf
                            @method('PUT')

                            @include('kkprofiling::partials.kk-profiling-form-fields', [
                                'barangay' => $kkUpdateBarangay ?? 'Santa Cruz',
                                'respondentNumber' => $kkRespondentNumber ?? '',
                                'respondentDisplay' => $kkRespondentDisplay ?? '',
                                'submitLabel' => 'Update KK Profiling',
                                'barangayLogoUrl' => $kkBarangayLogoUrl ?? null,
                                'barangayZones' => $kkBarangayZones ?? collect(),
                                'selectedPurokZone' => $kkSelectedPurokZone ?? '',
                                'emailReadonly' => true,
                                'kkProfilingUpdateMode' => true,
                            ])
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('kkprofiling::partials.kk-profiling-signature-modals')
    @include('kkprofiling::partials.kk-profiling-long-name-modal')
    @include('kkprofiling::partials.kk-profiling-clear-draft-modal', [
        'clearDraftTitle' => 'Clear form data?',
        'clearDraftConfirmLabel' => 'Clear Data',
        'clearDraftKeepEmail' => true,
    ])
    @include('kkprofiling::partials.kk-profiling-update-success-modal')
    @include('layout::kabataan-logout-modal')
    @include('layout::kabataan-session-timeout')

    <script>
        window.__KK_PROFILING_UPDATE_REQUIRED = true;
        window.__KK_PROFILING_FORM_DATA = @json($kkProfilingFormData ?? []);
        window.__KK_PROFILING_ORIGINAL_EMAIL = @json($kkProfilingOriginalEmail ?? '');
        window.__KK_PROFILING_UPDATE_REDIRECT = @json(\App\Support\MailUrl::sameOrigin(route('dashboard')));
        window.__KK_PROFILING_TARGET_YEAR = @json($kkProfilingTargetYear ?? null);
    </script>
</body>
</html>
