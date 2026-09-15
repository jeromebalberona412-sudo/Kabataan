<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($response['program_name'] ?? 'Survey Response') }} - SK OnePortal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Layout/assets/js/kabataan-session-timeout.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Programs/assets/css/scholarship_application.css',
    ])
</head>
<body class="sch-app-body kabataan-app-page">
    @include('layout::kabataan-header', ['showSearch' => false, 'pageBadge' => null])

    <div class="gf-container">
        <div class="gf-back-button">
            <a href="{{ $landingUrl }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span>Back</span>
            </a>
        </div>

        <div class="gf-header">
            <div class="gf-banner">
                <div class="gf-banner-content">
                    <h1 class="gf-title">{{ $response['program_name'] ?? 'Program Survey' }}</h1>
                    @if(!empty($response['announcement']))
                        <p class="gf-description">{{ $response['announcement'] }}</p>
                    @endif
                    @if(!empty($response['instructions']))
                        <p class="gf-description" style="margin-top:8px;">{{ $response['instructions'] }}</p>
                    @endif
                </div>
            </div>
            <div class="gf-info-bar">
                <div class="gf-info-item">
                    <span class="gf-info-label">Survey Period:</span>
                    <span class="gf-info-value">{{ ($response['open_date_display'] ?? '—') . ' - ' . ($response['close_date_display'] ?? '—') }}</span>
                </div>
                <div class="gf-info-item">
                    <span class="gf-info-label">Submitted:</span>
                    <span class="gf-info-value">{{ $response['submitted_at'] ?? '—' }}</span>
                </div>
                <div class="gf-info-item">
                    <span class="gf-info-label">Status:</span>
                    <span class="gf-status-badge gf-status-pending">Submitted</span>
                </div>
            </div>
        </div>

        <div class="gf-card">
            <div class="gf-kk-notice">
                <p class="gf-kk-notice-text">This is your submitted survey response. Answers below are read-only.</p>
            </div>
        </div>

        <div class="gf-form gf-form-readonly">
            @forelse(($response['answers'] ?? []) as $index => $answer)
                @php
                    $label = $answer['question_label'] ?? ('Question '.($index + 1));
                    $displayAnswer = is_array($answer['answer'] ?? null)
                        ? implode(', ', array_map('strval', $answer['answer']))
                        : (string) ($answer['answer'] ?? '');
                    if (trim($displayAnswer) === '') {
                        $displayAnswer = '—';
                    }
                    $type = strtolower((string) ($answer['question_type'] ?? 'text'));
                @endphp
                <div class="gf-card gf-question">
                    <label class="gf-question-label">{{ $label }}</label>
                    @if($type === 'paragraph')
                        <div class="gf-input gf-answer-readonly gf-answer-paragraph">{{ $displayAnswer }}</div>
                    @else
                        <div class="gf-input gf-answer-readonly">{{ $displayAnswer }}</div>
                    @endif
                </div>
            @empty
                <div class="gf-card gf-question">
                    <p class="gf-description" style="margin:0;text-align:center;">No answers found for this response.</p>
                </div>
            @endforelse

            <div class="gf-actions">
                <a href="{{ $landingUrl }}" class="gf-btn gf-btn-cancel">Back to Survey Page</a>
            </div>
        </div>
    </div>
</body>
</html>
