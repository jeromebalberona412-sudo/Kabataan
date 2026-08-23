<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - KK Profiling - SK OnePortal</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-signup.css',
        'app/Modules/KKProfiling/assets/js/kkprofiling-signup.js',
    ])
</head>
<body class="youth-login-page kk-signup-page">
    <a href="{{ route('sign-in') }}" class="kk-signup-back-link" aria-label="Back to signin">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M12.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L8.414 9H17a1 1 0 110 2H8.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
        </svg>
        Back to Signin
    </a>

    <div class="youth-bg-wrapper">
        <div class="youth-bg-image"></div>
        <div class="youth-gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <main class="kk-signup-container">
        <div class="kk-signup-section">
            <div class="kk-signup-card">
                <div class="kk-signup-header">
                    <h2 class="kk-signup-title">KK Profiling Sign Up</h2>
                    <p class="kk-signup-subtitle">Select your barangay to get started</p>
                </div>

                @if($errors->any())
                    <div class="kk-signup-alert kk-signup-alert-error" role="alert" id="flashErrorAlert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span>
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </span>
                        <button type="button" class="kk-signup-alert-close" aria-label="Dismiss" onclick="this.parentElement.remove()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                @endif

                <div class="kk-signup-alert kk-signup-alert-error" role="alert" id="jsErrorAlert" style="display:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span id="jsErrorAlertText"></span>
                    <button type="button" class="kk-signup-alert-close" aria-label="Dismiss" onclick="document.getElementById('jsErrorAlert').style.display='none'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <div class="kk-signup-notice">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <span>
                        Sign-up is only available during the <strong>grace period</strong> set by your barangay SK.
                        Check each barangay card for the open schedule (start &ndash; end date).
                    </span>
                </div>

                <div class="kk-signup-filters" role="group" aria-label="Filter barangays by schedule status">
                    <button type="button" class="kk-signup-filter is-active" data-filter="all">All</button>
                    <button type="button" class="kk-signup-filter" data-filter="Ongoing">Ongoing</button>
                    <button type="button" class="kk-signup-filter" data-filter="none">No schedule</button>
                </div>

                <div class="kk-signup-search">
                    <input type="text" id="barangaySearch" placeholder="Search barangay..." aria-label="Search barangay">
                </div>

                <div class="kk-signup-grid" id="barangayGrid">
                    @foreach($barangays as $brgy)
                        @php
                            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brgy['name']));
                            $slug = trim($slug, '-');
                            $logoUrl = $brgy['logo_url'] ?? null;
                        @endphp
                        <button
                            type="button"
                            class="kk-signup-barangay"
                            data-name="{{ $brgy['name'] }}"
                            data-slug="{{ $slug }}"
                            data-barangay-id="{{ $brgy['id'] }}"
                            data-status="none"
                        >
                            <span class="kk-signup-barangay-logo-wrap">
                                @if($logoUrl)
                                    <img
                                        src="{{ $logoUrl }}"
                                        alt=""
                                        class="kk-signup-barangay-logo"
                                        loading="lazy"
                                        onerror="this.hidden=true;this.nextElementSibling.hidden=false;"
                                    >
                                @endif
                                <span class="kk-signup-barangay-logo-fallback" @if($logoUrl) hidden @endif>{{ strtoupper(mb_substr($brgy['name'], 0, 1)) }}</span>
                            </span>
                            <span class="kk-signup-barangay-name">{{ $brgy['name'] }}</span>
                            <span class="kk-signup-barangay-meta">
                                <span class="kk-signup-badge kk-signup-badge-none">No schedule</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <div class="kk-signup-no-results" id="noResults" style="display:none;">
                    No barangay found. Try a different search term or filter.
                </div>
            </div>
        </div>
    </main>
</body>
</html>
