<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sports Application - SK OnePortal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Programs/assets/css/scholarship_application.css',
        'app/Modules/Programs/assets/css/scholarship_landing.css',
        'app/Modules/Programs/assets/css/sports-registration.css',
        'app/Modules/Programs/assets/css/sports-applications-history.css',
        'app/Modules/Programs/assets/js/sports-applications-history.js',
        'app/Modules/Programs/assets/js/sports_apply_wizard.js',
    ])
</head>
<body class="sch-app-body kabataan-app-page">
    @include('layout::kabataan-header')

    <main class="gf-container sports-apply-page">
        <div class="gf-back-button">
            <a href="{{ $backRoute ?? route('dashboard', ['open' => 'sports']) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Sports Programs
            </a>
        </div>

        <div id="sportsDynamicBanner"></div>

        <div id="sportsProgramInfo"></div>

        <form id="sportsApplyForm" class="sports-apply-form" novalidate>
            <div class="gf-card sports-kk-card">
                <h2 class="gf-section-title">KK Profiling Information</h2>
                <p class="sports-kk-note">The following details are auto-filled from your KK Profile and will be included in your application.</p>
                <div id="sportsKkProfileFields" class="sports-kk-grid"></div>
            </div>

            <div class="gf-card">
                <h2 class="gf-section-title">Application Questions</h2>
                <p class="sports-kk-note">Answer the questions configured by SK Officials for this sports program.</p>
                <div id="sportsQuestionsContainer"></div>
            </div>

            <div class="gf-card">
                <h2 class="gf-section-title">Confirmation</h2>
                <label class="gf-checkbox-row">
                    <input type="checkbox" id="sportsAgreeTerms">
                    <span>I certify that all information provided is true and correct. I understand that false information may result in disqualification.</span>
                </label>
            </div>

            <div class="gf-submit-row">
                <button type="submit" class="gf-btn gf-btn-submit" id="sportsSubmitBtn" disabled>Submit Application</button>
            </div>
        </form>

        <section class="gf-card sports-form-history">
            <h2 class="gf-section-title">My Sports Applications</h2>
            <p class="sports-kk-note">All sports you have previously applied for, grouped by year and sport type.</p>
            <div id="sportsApplicationsHistory" class="sports-applications-history">
                <p class="sports-history-loading">Loading your sports applicationsù</p>
            </div>
        </section>
    </main>

    @include('programs::partials.sports-applications-history-modals')

    <div id="sportsSuccessModal" class="gf-success-modal" hidden>
        <div class="gf-success-card">
            <h2 class="gf-success-title">Application Submitted Successfully</h2>
            <p class="gf-success-message">Your sports application has been submitted and is pending review by SK Officials.</p>
            <div class="gf-success-actions">
                <button type="button" class="gf-btn gf-btn-primary" id="sportsSuccessClose">Back to Sports Programs</button>
            </div>
        </div>
    </div>

    <script>
        window.__scheduleProgramId = @json($scheduleProgramId ?? null);
        window.__sportsProgram = @json($program ?? null);
        window.__kkFieldLabels = @json($kkFieldLabels ?? []);
        window.__sportsBackUrl = @json($backRoute ?? route('dashboard', ['open' => 'sports']));
    </script>
</body>
</html>
