<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $program['title'] }} Pre-Survey - SK OnePortal</title>
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
        'app/Modules/Programs/assets/css/programs-pre-survey.css',
        'app/Modules/Programs/assets/js/programs-pre-survey.js',
    ])
</head>
<body class="pps-body kabataan-app-page" data-program-slug="{{ $slug }}" data-program-title="{{ $program['title'] }}">
    @include('layout::kabataan-header', ['user' => auth()->user()])

    <main class="pps-main">
        <div class="pps-shell">
            <header class="pps-hero" style="--pps-accent: {{ $program['accent'] }}">
                <span class="pps-hero__icon" aria-hidden="true">{{ $program['icon'] }}</span>
                <div class="pps-hero__text">
                    <p class="pps-hero__eyebrow">Program Pre-Survey</p>
                    <h1>{{ $program['title'] }}</h1>
                    <p class="pps-hero__subtitle">{{ $program['subtitle'] }}</p>
                </div>
            </header>

            <div class="pps-notice" role="note">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <p>Your information will automatically be retrieved from your <strong>Kabataan Profiling (KK Profiling)</strong> account after submitting this pre-survey. No need to re-enter personal details.</p>
            </div>

            <aside class="pps-profile-card" aria-label="Profile preview">
                <div class="pps-profile-card__avatar">
                    @php
                        $user = auth()->user();
                        $displayName = $user->name ?? 'Youth Member';
                        $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=667eea&color=fff';
                    @endphp
                    <img src="{{ $avatarUrl }}" alt="">
                </div>
                <div class="pps-profile-card__body">
                    <h2>Profile Preview</h2>
                    <dl>
                        <div><dt>Name</dt><dd id="ppsPreviewName">{{ $displayName }}</dd></div>
                        <div><dt>Email</dt><dd id="ppsPreviewEmail">{{ $user->email ?? 'ù' }}</dd></div>
                        <div><dt>Status</dt><dd><span class="pps-badge">From KK Profiling</span></dd></div>
                    </dl>
                    <p class="pps-profile-card__hint">Displayed for confirmation only ù sourced automatically from your profiling record.</p>
                </div>
            </aside>

            <form id="ppsForm" class="pps-form" novalidate>
                <section class="pps-card">
                    <h3 class="pps-card__title"><span>1</span> Attendance Confirmation</h3>
                    <p class="pps-card__desc">This helps us estimate the expected number of participants for this seminar or program.</p>

                    <fieldset class="pps-fieldset">
                        <legend>
                            Will you be able to attend this seminar/program? <span class="pps-req">*</span>
                            <span class="pps-legend-sub">(Makakadalo ka ba sa seminar/program na ito?)</span>
                        </legend>
                        <div class="pps-options pps-options--cards" role="radiogroup">
                            <label class="pps-option-card">
                                <input type="radio" name="attendance" value="yes" required>
                                <span class="pps-option-card__inner">
                                    <strong>Yes, I will attend</strong>
                                    <small>Oo, makakadalo ako</small>
                                </span>
                            </label>
                            <label class="pps-option-card">
                                <input type="radio" name="attendance" value="maybe" required>
                                <span class="pps-option-card__inner">
                                    <strong>Maybe / Not Sure Yet</strong>
                                    <small>Maybe / Hindi pa sigurado</small>
                                </span>
                            </label>
                            <label class="pps-option-card">
                                <input type="radio" name="attendance" value="no" required>
                                <span class="pps-option-card__inner">
                                    <strong>No, I will not attend</strong>
                                    <small>Hindi ako makakadalo</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </section>

                <div class="pps-actions">
                    <a href="{{ route('dashboard') }}" class="pps-btn pps-btn--ghost">Back to Dashboard</a>
                    <button type="submit" class="pps-btn pps-btn--primary" id="ppsSubmitBtn">
                        Submit Pre-Survey
                    </button>
                </div>
            </form>
        </div>
    </main>

    <div id="ppsSnackbar" class="pps-snackbar" role="status" aria-live="polite" hidden>
        <span id="ppsSnackbarText"></span>
    </div>

    <div id="ppsSuccessModal" class="pps-modal" role="dialog" aria-modal="true" aria-labelledby="ppsSuccessTitle" hidden>
        <div class="pps-modal__backdrop"></div>
        <div class="pps-modal__panel">
            <div class="pps-modal__icon" aria-hidden="true">?</div>
            <h2 id="ppsSuccessTitle">Pre-Survey Submitted!</h2>
            <p>Thank you for your response. Your profiling information will be linked automatically when the program opens for final registration.</p>
            <button type="button" class="pps-btn pps-btn--primary" id="ppsSuccessClose">Back to Dashboard</button>
        </div>
    </div>
</body>
</html>
