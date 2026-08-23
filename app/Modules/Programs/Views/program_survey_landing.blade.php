<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Program Survey - SK OnePortal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Programs/assets/css/scholarship_landing.css',
        'app/Modules/Programs/assets/js/program_survey_landing.js',
    ])
</head>
<body class="sl-body kabataan-app-page">
    @include('layout::kabataan-header', ['showSearch' => false, 'pageBadge' => null])

    <div class="sl-container">
        <div class="sl-header">
            <div class="sl-back-link">
                <a href="{{ route('dashboard') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span>Back to Dashboard</span>
                </a>
            </div>
            <h1 class="sl-title">Program Survey</h1>
        </div>

        <div class="sl-card sl-card-program">
            <div class="sl-card-header">
                <h2 class="sl-card-title">Survey Information</h2>
                <span class="sl-status-badge sl-status-open" id="pslSurveyStatusBadge">Open</span>
            </div>
            <div class="sl-card-body">
                <h3 class="sl-program-name" id="pslProgramName">Loading survey…</h3>
                <p class="sl-program-description" id="pslInstructions">Please wait while survey details are loaded.</p>

                <div class="sl-info-grid">
                    <div class="sl-info-item">
                        <span class="sl-info-label">Survey Period:</span>
                        <span class="sl-info-value" id="pslSurveyPeriod">—</span>
                    </div>
                    <div class="sl-info-item">
                        <span class="sl-info-label">Barangay:</span>
                        <span class="sl-info-value">{{ $barangayName }}</span>
                    </div>
                </div>

                <div class="sl-section">
                    <h4 class="sl-section-title">Announcement</h4>
                    <p class="sl-program-description" id="pslAnnouncement">—</p>
                </div>

                <div class="sl-section">
                    <h4 class="sl-section-title">About This Survey</h4>
                    <ul class="sl-list">
                        <li>Questions were created by SK Officials for your barangay program.</li>
                        <li>Your KK Profiling information is linked automatically when you submit.</li>
                        <li>You can only submit one response while the survey is open.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="sl-card sl-card-history" hidden>
            <div class="sl-card-header">
                <h2 class="sl-card-title">Your Survey History</h2>
            </div>
            <div class="sl-card-body">
                <div class="sl-table-wrapper">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Survey Period</th>
                                <th>Date Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pslHistoryTable">
                            <tr class="sl-empty-row"><td colspan="4">Loading survey history…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="sl-actions">
            <button class="sl-btn sl-btn-primary" id="pslStartSurveyBtn" type="button">
                <span>Start Survey</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>
    </div>

    <div id="pslViewModal" class="sl-view-modal" hidden>
        <div class="sl-view-modal-overlay"></div>
        <div class="sl-view-modal-container">
            <div class="sl-view-modal-header">
                <div class="sl-view-modal-header-main">
                    <h2 id="pslViewTitle">Survey Response</h2>
                    <p id="pslViewMeta" class="sl-view-modal-meta"></p>
                </div>
                <button type="button" class="sl-view-modal-icon-btn sl-view-modal-close" id="pslViewClose" aria-label="Close">×</button>
            </div>
            <div class="sl-view-modal-body">
                <section class="sl-view-section">
                    <h3 class="sl-view-section-title">Your Answers</h3>
                    <div id="pslViewAnswers" class="sl-view-answers"></div>
                </section>
            </div>
        </div>
    </div>

    <script>
        window.__abyipProgramId = @json($abyipProgramId);
    </script>
</body>
</html>
