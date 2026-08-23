<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Update KK Profiling{{ !empty($kkProfilingTargetYear) ? ' ('.$kkProfilingTargetYear.')' : '' }} - SK OnePortal</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
        'app/Modules/Layout/assets/css/kabataan-responsive.css',
        'app/Modules/Layout/assets/css/kabataan-logout.css',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
        'app/Modules/KKProfiling/assets/css/kk-profiling-update.css',
        'app/Modules/KKProfiling/assets/js/kkprofiling.js',
        'app/Modules/KKProfiling/assets/js/kk-profiling-update.js',
    ])
</head>
<body class="kkpu-page-body">
    <header class="kkpu-lock-bar" aria-label="Required KK Profiling update">
        <div class="kkpu-lock-bar__brand">
            <img src="/images/skoneportal_logo.webp" alt="SK OnePortal" class="kkpu-lock-bar__logo">
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
            <h1 class="kkpu-page-title">
                Update Your KK Profiling
                @if (!empty($kkProfilingTargetYear))
                    ({{ $kkProfilingTargetYear }})
                @endif
            </h1>
            <p class="kkpu-page-subtitle">
                Your SK officials scheduled a KK Profiling update for this year.
                Complete this form once so your barangay record stays accurate.
                You cannot skip this page until you submit.
            </p>
        </header>

        @if ($errors->any())
            <div class="kkp-alert kkp-alert-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="kkp-paper kkpu-paper" id="kkpuFormSection">
            <form method="POST" action="{{ route('kkprofiling.update') }}" id="kkProfilingUpdateForm" data-email-locked="1">
                @csrf
                @method('PUT')

                @include('kkprofiling::partials.kk-profiling-form-fields', [
                    'barangay' => $kkUpdateBarangay ?? 'Santa Cruz',
                    'respondentNumber' => $kkRespondentNumber ?? '',
                    'respondentDisplay' => $kkRespondentDisplay ?? '01',
                    'submitLabel' => 'Update KK Profiling',
                    'barangayLogoUrl' => $kkBarangayLogoUrl ?? null,
                    'barangayZones' => $kkBarangayZones ?? collect(),
                    'selectedPurokZone' => $kkSelectedPurokZone ?? '',
                    'selectedFacebookProfileUrl' => $kkSelectedFacebookProfileUrl ?? '',
                    'emailReadonly' => true,
                ])
            </form>
        </div>
    </main>

    @include('kkprofiling::partials.kk-profiling-signature-modals')
    @include('layout::kabataan-logout-modal')

    <script>
        window.__KK_PROFILING_UPDATE_REQUIRED = true;
        window.__KK_PROFILING_FORM_DATA = @json($kkProfilingFormData ?? []);
        window.__KK_PROFILING_ORIGINAL_EMAIL = @json($kkProfilingOriginalEmail ?? '');
        window.__KK_PROFILING_UPDATE_REDIRECT = @json(route('dashboard'));
    </script>
</body>
</html>
