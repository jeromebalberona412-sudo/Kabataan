<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $evaluation['program_name'] ?? 'Program Evaluation' }} - SK OnePortal</title>
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
        'app/Modules/Programs/assets/css/scholarship_application.css',
        'app/Modules/Programs/assets/js/program_evaluation_form.js',
    ])
</head>
<body class="sch-app-body kabataan-app-page">
    @include('layout::kabataan-header', ['showSearch' => false, 'pageBadge' => null])

    <div class="gf-container">
        <div class="gf-back-button">
            <a href="{{ route('dashboard') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <div class="gf-header">
            <div class="gf-banner">
                <div class="gf-banner-content">
                    <h1 class="gf-title">Program Evaluation</h1>
                    <p class="gf-description">{{ $evaluation['program_name'] ?? 'Program' }}</p>
                    @if(!empty($evaluation['instructions']))
                        <p class="gf-description" style="margin-top:8px;">{{ $evaluation['instructions'] }}</p>
                    @endif
                </div>
            </div>
            <div class="gf-info-bar">
                <div class="gf-info-item">
                    <span class="gf-info-label">Evaluation Period:</span>
                    <span class="gf-info-value">{{ ($evaluation['start_date_display'] ?? '—') . ' - ' . ($evaluation['end_date_display'] ?? '—') }}</span>
                </div>
                <div class="gf-info-item">
                    <span class="gf-info-label">Status:</span>
                    <span class="gf-status-badge gf-status-open">Open</span>
                </div>
            </div>
        </div>

        <div class="gf-card">
            <div class="gf-kk-notice">
                <p class="gf-kk-notice-text">Your feedback helps your barangay SK improve programs and services. All responses are linked to your Kabataan profiling record.</p>
            </div>
        </div>

        <form id="programEvaluationForm" class="gf-form" novalidate>
            <div id="evaluationQuestionsContainer"></div>
            <div class="gf-actions">
                <button type="button" class="gf-btn gf-btn-cancel" id="evaluationCancelBtn">Back to Dashboard</button>
                <button type="submit" class="gf-btn gf-btn-submit" id="evaluationSubmitBtn">Submit Evaluation</button>
            </div>
        </form>
    </div>

    <div id="evaluationSuccessModal" class="gf-success-modal" hidden>
        <div class="gf-success-modal-overlay"></div>
        <div class="gf-success-modal-content">
            <div class="gf-success-icon">✓</div>
            <h2>Evaluation Submitted</h2>
            <p>Thank you for helping improve your barangay's youth programs.</p>
            <button type="button" class="gf-btn gf-btn-submit" id="evaluationSuccessClose">Back to Dashboard</button>
        </div>
    </div>

    <script>
        window.__programEvaluation = @json($evaluation);
        window.__evaluationId = @json($evaluationId);
    </script>
</body>
</html>
