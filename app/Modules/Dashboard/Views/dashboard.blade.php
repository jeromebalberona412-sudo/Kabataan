<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
        'app/Modules/Layout/assets/css/kabataan-responsive.css',
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/css/programs-drawer.css',
        'app/Modules/Layout/assets/css/kabataan-logout.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/Dashboard/assets/css/dashboard.css',
        'app/Modules/Dashboard/assets/css/community-feed-comment-preview.css',
        'app/Modules/Dashboard/assets/js/dashboard.js',
        'app/Modules/Programs/assets/js/programs.js',
        'app/Modules/Programs/assets/css/scholarship-quick-guidelines.css',
        'app/Modules/Programs/assets/js/scholarship-quick-guidelines.js',
        'app/Modules/Programs/assets/css/scholarship-data-privacy.css',
        'app/Modules/Programs/assets/js/scholarship-data-privacy.js',
        'app/Modules/Programs/assets/js/kabataan-programs.js',
        'app/Modules/Programs/assets/js/program-evaluation-prompt.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
    ])
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
    <style>
        .post-card-new {
            animation: feed-post-in 0.7s ease;
            box-shadow: 0 0 0 2px rgba(26, 86, 219, 0.28);
        }
        @keyframes feed-post-in {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: none; }
        }
        .youth-dashboard .feed-sticky-toolbar {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--bg, #f8fafc);
            overflow: hidden;
            max-height: 160px;
            flex-shrink: 0;
            transition: max-height 0.18s ease, opacity 0.16s ease, transform 0.18s ease;
        }
        .youth-dashboard .feed-sticky-toolbar.is-hidden {
            max-height: 0;
            opacity: 0;
            pointer-events: none;
        }
        .youth-dashboard .feed-filter-bar {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
        }
        .youth-dashboard .feed-filter-anchor {
            height: 0;
            flex-shrink: 0;
        }
        @media (max-width: 1200px) {
            .youth-dashboard .dashboard-main,
            .youth-dashboard .feed-section {
                overflow-x: visible;
            }
            .youth-dashboard .feed-sticky-toolbar {
                position: fixed;
                top: var(--kab-nav-h, var(--nav-h, 68px));
                left: 0;
                right: 0;
                width: 100%;
                max-height: none;
                opacity: 1;
                transform: translateY(0);
                box-shadow: 0 8px 16px -10px rgba(15, 23, 42, 0.25);
            }
            .youth-dashboard .feed-sticky-toolbar.is-hidden {
                max-height: none;
                opacity: 1;
                transform: translateY(-110%);
                pointer-events: none;
                box-shadow: none;
            }
        }
        .lightbox-nav {
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.28);
            z-index: 5;
            pointer-events: auto;
        }
        @media (max-width: 768px) {
            .lightbox-nav {
                width: 40px;
                height: 40px;
                font-size: 20px;
                background: rgba(0,0,0,0.55);
            }
            .lightbox-prev { left: 8px; }
            .lightbox-next { right: 8px; }
        }
    </style>
</head>
<body class="youth-dashboard">
    @include('layout::kabataan-header', ['user' => $user ?? auth()->user()])

    <!-- Main Content -->
    <main class="dashboard-main">
        <div class="dashboard-container">
            <!-- Left Sidebar - Programs -->
            <aside class="programs-sidebar-left">
                <div class="sidebar-card">
                    <h2 class="sidebar-title">Programs in Your Barangay</h2>
                    <p class="sidebar-subtitle">Available programs in Barangay {{ $barangayName ?? ($user->barangay ?? '1') }}</p>
                    
                    <div class="program-categories" id="programCategoriesContainer">
                        <p style="text-align:center;color:#64748b;padding:16px;font-size:14px;">Loading programs…</p>
                    </div>
                </div>
            </aside>

            <!-- Center Content - Social Feed -->
            <div class="feed-section">
                <!-- Success Message -->
                @if (session('success'))
                    <div class="alert alert-success">
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width: 20px; height: 20px; display: inline-block; margin-right: 8px;">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                <div class="feed-sticky-toolbar">
                <div class="feed-filter-bar" role="tablist" aria-label="Filter community feed">
                    <button type="button" class="feed-tab feed-tab--icon active" data-feed-filter="all" aria-label="All">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </span>
                        <span class="feed-tab-text">All</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-feed-filter="announcement" aria-label="Announcements">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8a2 2 0 004 0v-6"/></svg>
                        </span>
                        <span class="feed-tab-text">Announcements</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-feed-filter="event" aria-label="Events">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="feed-tab-text">Events</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-feed-filter="activity" aria-label="Activities">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                        </span>
                        <span class="feed-tab-text">Activities</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-feed-filter="program" aria-label="Programs">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        </span>
                        <span class="feed-tab-text">Programs</span>
                    </button>
                </div>
                </div>
                <div class="feed-filter-anchor" aria-hidden="true"></div>
                
                <div class="feed-header">
                    <div class="feed-header__intro">
                        <h1>SK Community Feed</h1>
                        <p>Posts, events, and programs from your barangay SK.</p>
                    </div>
                    <div class="feed-header__search">
                        <svg class="feed-header__search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                        <input
                            type="search"
                            id="feedSearchInput"
                            class="feed-header__search-input"
                            placeholder="Search posts, programs, announcements..."
                            autocomplete="off"
                            aria-label="Search community feed"
                        >
                    </div>
                </div>

                <div id="feed-posts">
                    <div class="post-card feed-loading-card">Loading community feed…</div>
                </div>
                <div id="feedInfiniteSentinel" class="feed-infinite-sentinel" aria-hidden="true"></div>
            </div>

            <!-- Right Sidebar - Barangay SK Profiles -->
            <aside class="barangay-sidebar-right">
                <div class="sidebar-card">
                    <h2 class="sidebar-title">Barangay SK Profiles</h2>
                    <p class="sidebar-subtitle">Browse SK officials from each barangay.</p>
                    <div class="barangay-profiles-list">
                        @include('dashboard::partials.barangay-profiles-list', ['barangayProfiles' => $barangayProfiles ?? []])
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Mobile Drawer Backdrop -->
    <div id="programsDrawerBackdrop" class="programs-drawer-backdrop"></div>

    <!-- Mobile Drawer -->
    <aside class="programs-sidebar" id="programsDrawerSidebar">
        <div class="sidebar-card">
            <div class="programs-drawer-head">
                <div class="programs-drawer-head__text">
                    <h2 class="sidebar-title">Programs in Your Barangay</h2>
                    <p class="sidebar-subtitle">Available programs in Barangay {{ $barangayName ?? ($user->barangay ?? '1') }}</p>
                </div>
                <button type="button" class="programs-drawer-close" data-programs-drawer-close aria-label="Close programs">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            
            <div class="program-categories" id="programCategoriesDrawerContainer">
                <p style="text-align:center;color:#64748b;padding:16px;font-size:14px;">Loading programs…</p>
            </div>
        </div>

        {{-- Barangay SK Profiles --}}
        <div class="sidebar-card" style="margin-top:16px;">
            <h2 class="sidebar-title">Barangay SK Profiles</h2>
            <p class="sidebar-subtitle">Browse SK officials from each barangay.</p>
            <div class="barangay-profiles-list">
                @include('dashboard::partials.barangay-profiles-list', ['barangayProfiles' => $barangayProfiles ?? []])
            </div>
        </div>
    </aside>

    <div id="educationModal" class="program-modal">
        <div class="modal-overlay"></div>
        <div class="modal-container education-modal-container" id="educationModalContainer">
            <div class="modal-header">
                <h2>Education Programs</h2>
                <div class="modal-header-actions">
                    <a href="{{ route('scholarship.apply') }}" class="modal-header-btn modal-header-btn-guide" id="educationHistoryBtn" title="View application history" hidden>Application History</a>
                    <button type="button" class="modal-header-btn modal-header-btn-guide" id="educationQuickGuideBtn" title="Quick Guidelines" hidden>Quick Guidelines</button>
                    <button type="button" class="modal-toggle-btn education-modal-toggle-btn" id="educationModalMaximize" aria-label="Maximize">□</button>
                    <button type="button" class="modal-close education-modal-close-btn" onclick="closeEducationModal()" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="modal-body education-modal-body">
                <div id="educationProgramsContainer">
                    <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Anti-Drugs Modal -->
    <div id="antiDrugsModal" class="program-modal">
        <div class="modal-overlay"></div>
        <div class="modal-container" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Anti-Drugs Programs</h2>
                <button class="modal-close" onclick="closeAntiDrugsModal()">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 32px; overflow-y: auto; max-height: calc(90vh - 80px); min-height: 400px;">
                <div class="modern-program-card">
                    <div class="program-card-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <div class="program-title-row">
                            <div>
                                <span class="program-category-tag">🚫 Anti-Drugs</span>
                                <h3 class="program-card-title">Drug-Free Youth Campaign 2026</h3>
                            </div>
                            <span class="program-status-badge status-active"><span class="status-dot"></span>Active</span>
                        </div>
                    </div>
                    <div class="program-details-grid">
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg></div>
                            <div class="detail-content"><span class="detail-label">Committee Handled By</span><span class="detail-value">Committee on Anti-Drug Abuse</span></div>
                        </div>
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>
                            <div class="detail-content"><span class="detail-label">Program Status</span><span class="detail-value">Active</span></div>
                        </div>
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg></div>
                            <div class="detail-content"><span class="detail-label">Participant Quantity</span><span class="detail-value">100 Youth</span></div>
                        </div>
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></div>
                            <div class="detail-content"><span class="detail-label">Starting Date</span><span class="detail-value">February 1, 2026</span></div>
                        </div>
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></div>
                            <div class="detail-content"><span class="detail-label">End Date</span><span class="detail-value">April 30, 2026</span></div>
                        </div>
                        <div class="detail-card">
                            <div class="detail-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg></div>
                            <div class="detail-content"><span class="detail-label">Venue</span><span class="detail-value">Barangay Covered Court, Santa Cruz</span></div>
                        </div>
                    </div>
                    <div class="program-description-section">
                        <h4 class="section-heading"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>Description</h4>
                        <p class="description-text">Join our Drug-Free Youth Campaign to promote awareness and prevention of drug abuse among young people. This program includes seminars, workshops, and community activities designed to educate youth about the dangers of drugs and empower them to make healthy life choices.</p>
                    </div>
                    <div class="terms-section">
                        <button class="terms-toggle" onclick="toggleTermsAntiDrugs(); event.stopPropagation();" id="termsToggleAntiDrugs" type="button">
                            <div class="terms-toggle-header">
                                <h4 class="section-heading"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>Terms & Conditions</h4>
                                <svg class="chevron-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </div>
                        </button>
                        <div class="terms-content" id="termsContentAntiDrugs">
                            <ul class="terms-list">
                                <li>Participant must be a resident of Santa Cruz, Laguna aged 15-30</li>
                                <li>Must attend all scheduled sessions and activities</li>
                                <li>Must sign a pledge to remain drug-free</li>
                                <li>Participants will receive a certificate of completion</li>
                                <li>Must actively participate in community outreach activities</li>
                            </ul>
                            <div class="terms-agreement">
                                <label class="agreement-checkbox" onclick="event.stopPropagation();">
                                    <input type="checkbox" id="agreeTermsAntiDrugs" onchange="toggleApplyButtonAntiDrugs()" onclick="event.stopPropagation();">
                                    <span class="checkbox-custom"></span>
                                    <span class="agreement-text">I have read and agree to the Terms & Conditions</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="program-action">
                        <button class="apply-now-button" id="applyNowBtnAntiDrugs" onclick="goToPreSurvey('anti-drugs')" disabled>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                            Apply Now
                        </button>
                        <p class="apply-note">Please read and agree to the Terms & Conditions to continue</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scholarship Application Form Modal (New PDF-style) -->
    <div id="programEvaluationPromptModal" class="program-modal" hidden>
        <div class="modal-overlay" data-eval-overlay></div>
        <div class="modal-container" style="max-width:520px;">
            <div class="modal-body" style="padding:28px;">
                <h3 style="font-size:22px;font-weight:700;color:#0f172a;margin:0 0 8px;" data-eval-title>Program Evaluation Available</h3>
                <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 16px;">
                    A program evaluation form is ready for <strong data-eval-program>your barangay program</strong>.
                    It is highly recommended that you complete this evaluation so your barangay SK can improve youth programs and services.
                </p>
                <p style="color:#64748b;font-size:13px;margin:0 0 20px;">
                    Evaluation period: <span data-eval-period>—</span>
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="apply-now-button enabled" data-eval-start style="flex:1;min-width:160px;">Start Evaluation</button>
                    <button type="button" class="gf-btn gf-btn-cancel" data-eval-later style="flex:1;min-width:140px;">Maybe Later</button>
                </div>
            </div>
        </div>
    </div>

    @include('dashboard::remaining_modals')

    <!-- Program Registration Success Modal -->
    <div id="programSuccessModal" class="program-modal">
        <div class="modal-overlay"></div>
        <div class="modal-container" style="max-width:420px;">
            <div class="modal-body" style="text-align:center; padding: 48px 32px;">
                <div style="width:72px;height:72px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 8px 24px rgba(34,197,94,0.35);">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:36px;height:36px;color:white;">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 style="font-size:22px;font-weight:700;color:#333;margin-bottom:10px;">Application Submitted!</h3>
                <p style="color:#666;font-size:15px;line-height:1.6;margin-bottom:8px;">Your application has been successfully submitted. We will review it and contact you soon.</p>
                <p style="color:#94a3b8;font-size:13px;">This window will close automatically.</p>
                <div style="margin-top:8px;height:3px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                    <div id="successProgressBar" style="height:100%;background:linear-gradient(135deg,#22c55e,#16a34a);width:100%;transition:width 5s linear;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div id="imageLightbox" class="image-lightbox" aria-hidden="true">
        <button type="button" id="lightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
        <div class="lightbox-toolbar">
            <button type="button" id="lightboxZoomOut" class="lightbox-tool-btn" aria-label="Zoom out">-</button>
            <span id="lightboxZoomLevel" class="lightbox-zoom-level">100%</span>
            <button type="button" id="lightboxZoomIn" class="lightbox-tool-btn" aria-label="Zoom in">+</button>
            <button type="button" id="lightboxZoomReset" class="lightbox-tool-btn lightbox-reset-btn" aria-label="Reset zoom">Reset</button>
        </div>
        <button type="button" id="lightboxPrev" class="lightbox-nav lightbox-prev" aria-label="Previous">&#10094;</button>
        <div class="lightbox-viewport" id="lightboxViewport">
            <img id="lightboxImage" src="" alt="Full size photo" draggable="false">
        </div>
        <button type="button" id="lightboxNext" class="lightbox-nav lightbox-next" aria-label="Next">&#10095;</button>
        <div id="lightboxCounter" class="lightbox-counter"></div>
    </div>

    <script>
    // ── Program Success Modal ─────────────────────────────────────────────────
    function showProgramSuccessModal() {
        const modal = document.getElementById('programSuccessModal');
        const bar   = document.getElementById('successProgressBar');
        modal.classList.add('active');

        // Reset and animate progress bar
        bar.style.transition = 'none';
        bar.style.width = '100%';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                bar.style.transition = 'width 5s linear';
                bar.style.width = '0%';
            });
        });

        setTimeout(() => modal.classList.remove('active'), 5000);
    }

    // Close on overlay click
    document.getElementById('programSuccessModal')
        ?.querySelector('.modal-overlay')
        ?.addEventListener('click', () => {
            document.getElementById('programSuccessModal').classList.remove('active');
        });

    // ── Education Modal Functions ──
    window.openEducationModal = function() {
        // Directly open the education modal (don't use programsModule to avoid "no program" issue)
        document.getElementById('educationModal').classList.add('active');
    };

    window.closeEducationModal = function() {
        const modal = document.getElementById('educationModal');
        const container = document.getElementById('educationModalContainer');
        if (modal) {
            modal.classList.remove('active');
            modal.classList.remove('is-maximized');
        }
        if (container) {
            container.classList.remove('is-maximized');
            const maxBtn = document.getElementById('educationModalMaximize');
            if (maxBtn) {
                maxBtn.textContent = '□';
                maxBtn.setAttribute('aria-label', 'Maximize');
            }
        }
        
        // Reset terms agreement when closing
        const checkbox = document.getElementById('agreeTerms');
        const content = document.getElementById('termsContent');
        const toggle = document.getElementById('termsToggle');
        const chevron = toggle?.querySelector('.chevron-icon');
        
        if (checkbox) {
            checkbox.checked = false;
            toggleApplyButton();
        }
        
        // Collapse terms section
        if (content) {
            content.classList.remove('expanded');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    };

    // ── Anti-Drugs Modal Functions ──
    window.openAntiDrugsModal = function() {
        document.getElementById('antiDrugsModal').classList.add('active');
    };

    window.closeAntiDrugsModal = function() {
        document.getElementById('antiDrugsModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsAntiDrugs');
        const content = document.getElementById('termsContentAntiDrugs');
        const toggle = document.getElementById('termsToggleAntiDrugs');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonAntiDrugs(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsAntiDrugs = function() {
        const content = document.getElementById('termsContentAntiDrugs');
        const toggle = document.getElementById('termsToggleAntiDrugs');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonAntiDrugs = function() {
        const checkbox = document.getElementById('agreeTermsAntiDrugs');
        const applyBtn = document.getElementById('applyNowBtnAntiDrugs');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    // Attach overlay close events
    document.getElementById('antiDrugsModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeAntiDrugsModal);

    // ── Agriculture Modal Functions ──
    window.openAgricultureModal = function() {
        document.getElementById('agricultureModal').classList.add('active');
    };

    window.closeAgricultureModal = function() {
        document.getElementById('agricultureModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsAgriculture');
        const content = document.getElementById('termsContentAgriculture');
        const toggle = document.getElementById('termsToggleAgriculture');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonAgriculture(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsAgriculture = function() {
        const content = document.getElementById('termsContentAgriculture');
        const toggle = document.getElementById('termsToggleAgriculture');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonAgriculture = function() {
        const checkbox = document.getElementById('agreeTermsAgriculture');
        const applyBtn = document.getElementById('applyNowBtnAgriculture');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    document.getElementById('agricultureModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeAgricultureModal);

    // ── Disaster Modal Functions ──
    window.openDisasterModal = function() {
        document.getElementById('disasterModal').classList.add('active');
    };

    window.closeDisasterModal = function() {
        document.getElementById('disasterModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsDisaster');
        const content = document.getElementById('termsContentDisaster');
        const toggle = document.getElementById('termsToggleDisaster');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonDisaster(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsDisaster = function() {
        const content = document.getElementById('termsContentDisaster');
        const toggle = document.getElementById('termsToggleDisaster');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonDisaster = function() {
        const checkbox = document.getElementById('agreeTermsDisaster');
        const applyBtn = document.getElementById('applyNowBtnDisaster');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    document.getElementById('disasterModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeDisasterModal);

    // ── Sports Modal Functions ──
    window.openSportsModal = function() {
        document.getElementById('sportsModal').classList.add('active');
    };

    window.closeSportsModal = function() {
        document.getElementById('sportsModal')?.classList.remove('active');
    };

    document.getElementById('sportsModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeSportsModal);

    // ── Gender Modal Functions ──
    window.openGenderModal = function() {
        document.getElementById('genderModal').classList.add('active');
    };

    window.closeGenderModal = function() {
        document.getElementById('genderModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsGender');
        const content = document.getElementById('termsContentGender');
        const toggle = document.getElementById('termsToggleGender');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonGender(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsGender = function() {
        const content = document.getElementById('termsContentGender');
        const toggle = document.getElementById('termsToggleGender');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonGender = function() {
        const checkbox = document.getElementById('agreeTermsGender');
        const applyBtn = document.getElementById('applyNowBtnGender');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    document.getElementById('genderModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeGenderModal);

    // ── Health Modal Functions ──
    window.openHealthModal = function() {
        document.getElementById('healthModal').classList.add('active');
    };

    window.closeHealthModal = function() {
        document.getElementById('healthModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsHealth');
        const content = document.getElementById('termsContentHealth');
        const toggle = document.getElementById('termsToggleHealth');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonHealth(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsHealth = function() {
        const content = document.getElementById('termsContentHealth');
        const toggle = document.getElementById('termsToggleHealth');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonHealth = function() {
        const checkbox = document.getElementById('agreeTermsHealth');
        const applyBtn = document.getElementById('applyNowBtnHealth');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    document.getElementById('healthModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeHealthModal);

    // ── Others Modal Functions ──
    window.openOthersModal = function() {
        document.getElementById('othersModal').classList.add('active');
    };

    window.closeOthersModal = function() {
        document.getElementById('othersModal').classList.remove('active');
        const checkbox = document.getElementById('agreeTermsOthers');
        const content = document.getElementById('termsContentOthers');
        const toggle = document.getElementById('termsToggleOthers');
        const chevron = toggle?.querySelector('.chevron-icon');
        if (checkbox) { checkbox.checked = false; toggleApplyButtonOthers(); }
        if (content) { content.classList.remove('expanded'); if (chevron) chevron.style.transform = 'rotate(0deg)'; }
    };

    window.toggleTermsOthers = function() {
        const content = document.getElementById('termsContentOthers');
        const toggle = document.getElementById('termsToggleOthers');
        const chevron = toggle.querySelector('.chevron-icon');
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    window.toggleApplyButtonOthers = function() {
        const checkbox = document.getElementById('agreeTermsOthers');
        const applyBtn = document.getElementById('applyNowBtnOthers');
        const note = applyBtn?.nextElementSibling;
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    document.getElementById('othersModal')?.querySelector('.modal-overlay')?.addEventListener('click', closeOthersModal);

    // ── Close No Program Modal ──
    window.closeNoProgramModal = function() {
        const modal = document.getElementById('noProgramModal');
        if (modal) {
            modal.classList.remove('active');
        }
    };

    // Attach close event listeners to No Program modal
    const noProgramModal = document.getElementById('noProgramModal');
    if (noProgramModal) {
        const closeBtn = noProgramModal.querySelector('.modal-close');
        const overlay = noProgramModal.querySelector('.modal-overlay');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeNoProgramModal);
        }
        if (overlay) {
            overlay.addEventListener('click', closeNoProgramModal);
        }
    }

    // ── Terms & Conditions Toggle ──
    window.toggleTerms = function() {
        const content = document.getElementById('termsContent');
        const toggle = document.getElementById('termsToggle');
        const chevron = toggle.querySelector('.chevron-icon');
        
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('expanded');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    // ── Toggle Apply Button based on Terms Agreement ──
    window.toggleApplyButton = function() {
        const checkbox = document.getElementById('agreeTerms');
        const applyBtn = document.getElementById('applyNowBtn');
        const note = document.querySelector('.apply-note');
        
        if (checkbox && applyBtn) {
            if (checkbox.checked) {
                applyBtn.disabled = false;
                applyBtn.classList.add('enabled');
                if (note) note.style.display = 'none';
            } else {
                applyBtn.disabled = true;
                applyBtn.classList.remove('enabled');
                if (note) note.style.display = 'block';
            }
        }
    };

    // ── Program application redirects (frontend only — see programs.js) ──
    window.scholarshipApplyUrl = @json(route('scholarship.apply'));
    window.sportsApplyUrl = @json(route('sports.apply'));

    const educationModal = document.getElementById('educationModal');
    if (educationModal) {
        educationModal.querySelector('.modal-overlay')?.addEventListener('click', closeEducationModal);
    }

    const educationModalMaximize = document.getElementById('educationModalMaximize');
    const educationModalContainer = document.getElementById('educationModalContainer');
    if (educationModalMaximize && educationModalContainer && educationModal) {
        educationModalMaximize.addEventListener('click', (event) => {
            event.stopPropagation();
            const isMax = !educationModalContainer.classList.contains('is-maximized');
            educationModalContainer.classList.toggle('is-maximized', isMax);
            educationModal.classList.toggle('is-maximized', isMax);
            educationModalMaximize.textContent = isMax ? '⧉' : '□';
            educationModalMaximize.setAttribute('aria-label', isMax ? 'Restore down' : 'Maximize');
        });
    }
    </script>

    <script>
    // Disqualify page from bfcache — back button will always hit the server
    window.addEventListener('unload', function () {});
    </script>

    <div id="feedToast" class="feed-toast" role="status" aria-live="polite"></div>

    <div id="editCommentModal" class="program-modal comment-action-modal">
        <div class="modal-overlay" onclick="closeEditCommentModal()"></div>
        <div class="modal-container" style="max-width:440px;">
            <div class="modal-header">
                <h2>Edit Comment</h2>
                <button type="button" class="modal-close" onclick="closeEditCommentModal()" aria-label="Close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
            <div class="modal-body">
                <textarea id="editCommentBody" class="edit-comment-textarea" maxlength="500" placeholder="Write a comment..."></textarea>
            </div>
            <div class="modal-footer-btns" style="display:flex;gap:10px;justify-content:flex-end;padding:14px 22px;border-top:1px solid #e0e0e0;">
                <button type="button" class="btn-secondary" onclick="closeEditCommentModal()">Cancel</button>
                <button type="button" class="btn-primary" id="confirmEditCommentBtn" onclick="confirmEditComment()">Save</button>
            </div>
        </div>
    </div>

    <div id="deleteCommentModal" class="program-modal comment-action-modal">
        <div class="modal-overlay" onclick="closeDeleteCommentModal()"></div>
        <div class="modal-container" style="max-width:440px;">
            <div class="modal-header">
                <h2>Delete Comment</h2>
                <button type="button" class="modal-close" onclick="closeDeleteCommentModal()" aria-label="Close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
            <div class="modal-body">
                <p style="font-size:14px;color:#555;line-height:1.65;margin:0;">Delete this comment? This cannot be undone.</p>
            </div>
            <div class="modal-footer-btns" style="display:flex;gap:10px;justify-content:flex-end;padding:14px 22px;border-top:1px solid #e0e0e0;">
                <button type="button" class="btn-secondary" onclick="closeDeleteCommentModal()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteCommentBtn" onclick="confirmDeleteComment()">Delete</button>
            </div>
        </div>
    </div>

    @include('dashboard::comment-preview')

    <div id="reactionViewerModal" class="reaction-viewer" aria-hidden="true">
        <div class="reaction-viewer-overlay" id="reactionViewerOverlay"></div>
        <div class="reaction-viewer-panel" role="dialog" aria-label="People who reacted">
            <div class="reaction-viewer-header">
                <div class="reaction-viewer-tabs" id="reactionViewerTabs"></div>
                <button type="button" class="reaction-viewer-close" id="reactionViewerClose" aria-label="Close">&times;</button>
            </div>
            <div class="reaction-viewer-list" id="reactionViewerList"></div>
        </div>
    </div>

    <script>
    window.CommunityFeedConfig = {
        userAvatar: @json($userAvatarUrl ?? ''),
        userDisplayName: @json($user->name ?? 'Kabataan'),
        commentsPageUrl: @json(url('/dashboard/comments/__ID__')),
        feedPollMs: 5000,
    };
    window.CommentPreviewConfig = {
        post: @json($commentPreviewPost ?? null),
        defaultLogo: @json(asset('images/SK_OnePortal_logo.png')),
        userAvatar: @json($userAvatarUrl ?? ''),
        userDisplayName: @json($user->name ?? 'Kabataan'),
        feedUrl: @json(route('dashboard')),
    };
    </script>

    <script>
    // ── Community Feed ────────────────────────────────────────────────────────
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const FEED_USER_AVATAR = @json($userAvatarUrl ?? '');
    const LIKE_THUMB_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>';
    const REACTION_EMOJI = { like: '👍', love: '❤️', haha: '😂', wow: '😮', sad: '😢', angry: '😡' };
    const REACTION_LABEL = { like: 'Like', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad', angry: 'Angry' };
    let feedPollTimer = null;
    let feedPage = 1;
    let feedLastPage = 1;
    let feedFilter = 'all';
    let feedSearch = '';
    let feedLoading = false;
    let feedRequestToken = 0;
    const renderedPostIds = new Set();
    const postCache = new Map();

    const FEED_REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
    let feedReactionAudio = null;

    function ensureFeedReactionAudio() {
        if (!feedReactionAudio) {
            feedReactionAudio = new Audio(FEED_REACTION_SOUND_URL);
            feedReactionAudio.preload = 'auto';
            feedReactionAudio.volume = 0.75;
            try { feedReactionAudio.load(); } catch (e) {}
        }
        return feedReactionAudio;
    }

    function playFeedReactionSound() {
        const audio = ensureFeedReactionAudio();
        audio.muted = false;
        audio.volume = 0.75;
        try {
            if (audio.readyState >= 2) {
                try { audio.currentTime = 0; } catch (e) {}
            }
            const playPromise = audio.play();
            if (playPromise && playPromise.catch) {
                playPromise.catch(function () {
                    const oneShot = new Audio(FEED_REACTION_SOUND_URL);
                    oneShot.volume = 0.75;
                    oneShot.play().catch(function () {});
                });
            }
        } catch (e) {
            try {
                const fallback = new Audio(FEED_REACTION_SOUND_URL);
                fallback.volume = 0.75;
                fallback.play().catch(function () {});
            } catch (err) {}
        }
    }

    window.playFeedReactionSound = playFeedReactionSound;
    ensureFeedReactionAudio();

    function showFeedToast(message, type) {
        const el = document.getElementById('feedToast');
        if (!el) return;
        el.textContent = message;
        el.className = 'feed-toast feed-toast--' + (type || 'success') + ' is-visible';
        clearTimeout(window._feedToastTimer);
        window._feedToastTimer = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 3200);
    }
    window.showFeedToast = showFeedToast;

    function notifyFeed(message, type) {
        showFeedToast(message, type || 'success');
    }

    const reactionRequestSeq = new Map();
    const reactionAbort = new Map();

    function beginReactionRequest(key) {
        const seq = (reactionRequestSeq.get(key) || 0) + 1;
        reactionRequestSeq.set(key, seq);
        reactionAbort.get(key)?.abort();
        const controller = new AbortController();
        reactionAbort.set(key, controller);
        return { seq, signal: controller.signal };
    }

    function isLatestReactionRequest(key, seq) {
        return reactionRequestSeq.get(key) === seq;
    }

    function isReplyComment(commentId) {
        return Boolean(
            document.querySelector(`.comment-item.is-reply[data-comment-id="${commentId}"]`)
            || document.querySelector(`.comment-item--reply[data-comment-id="${commentId}"]`)
            || document.querySelector(`.cp-comment.is-reply[data-comment-id="${commentId}"]`)
        );
    }

    async function apiFeed(url, opts = {}) {
        const { headers: extraHeaders, ...rest } = opts;
        const r = await fetch(url, {
            ...rest,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', ...(extraHeaders ?? {}) },
        });
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    }

    function mergeCachedPost(p) {
        const id = Number(p.id);
        if (!id) return null;
        const prev = postCache.get(id) || {};
        const next = Object.assign({}, prev, p);
        if (p.comments_loaded === false && prev.comments_loaded) {
            next.comments = prev.comments;
            next.comments_loaded = true;
        }
        postCache.set(id, next);
        return next;
    }

    function mountFeedPost(p, mode = 'append') {
        const id = Number(p.id);
        if (!id) return null;
        mergeCachedPost(p);
        const container = document.getElementById('feed-posts');
        if (!container) return null;
        const existing = container.querySelector(`.post-card[data-post-id="${id}"]`);
        if (renderedPostIds.has(String(id)) || existing) {
            const cached = postCache.get(id);
            const summary = existing?.querySelector('.reaction-summary');
            const prevLikes = Number(summary?.dataset.reactions || 0);
            const prevComments = Number(summary?.dataset.comments || 0);
            const nextLikes = Number(cached?.likes || 0);
            const nextComments = Number(cached?.comment_count ?? countFeedComments(cached?.comments || []));
            if (!summary || prevLikes !== nextLikes || prevComments !== nextComments) {
                updateReactionsSummary(id, cached);
            }
            return existing;
        }
        renderedPostIds.add(String(id));
        const emptyState = container.querySelector('.post-card:not([data-post-id])');
        emptyState?.remove();
        const el = document.createElement('article');
        el.className = 'post-card';
        el.dataset.postId = String(id);
        el.innerHTML = buildFeedPost(p);
        if (mode === 'prepend') {
            container.prepend(el);
        } else {
            container.appendChild(el);
        }
        bindFeedReactionControls(el);
        bindPostImageClicks(el);
        return el;
    }

    async function loadFeed(reset = true, options = {}) {
        if (feedLoading && !options.force) return;
        feedLoading = true;

        const requestToken = ++feedRequestToken;
        if (reset) {
            feedPage = 1;
        }

        const params = new URLSearchParams({ page: feedPage, filter: feedFilter });
        if (feedSearch) {
            params.set('search', feedSearch);
        }

        const container = document.getElementById('feed-posts');
        if (!container) {
            feedLoading = false;
            return;
        }
        if (reset) {
            renderedPostIds.clear();
            container.innerHTML = '<div class="post-card feed-loading-card">Loading community feed…</div>';
        }

        try {
            const data = await apiFeed(`/api/feed?${params}`);
            if (requestToken !== feedRequestToken) return;
            if (!data) return;

            feedLastPage = data.last_page;
            const items = data.data ?? [];
            if (reset) {
                renderedPostIds.clear();
                container.innerHTML = '';
            }

            if (reset && items.length === 0) {
                container.innerHTML =
                    '<div class="post-card" style="text-align:center;color:#64748b;padding:32px;">No community feed posts yet. Posts from your barangay SK and SK Federation will appear here.</div>';
                return;
            }

            const fragment = document.createDocumentFragment();
            items.forEach((p) => {
                const id = Number(p.id);
                if (!id) return;
                mergeCachedPost(p);
                if (renderedPostIds.has(String(id)) || container.querySelector(`.post-card[data-post-id="${id}"]`)) {
                    mountFeedPost(p, 'append');
                    return;
                }
                renderedPostIds.add(String(id));
                const el = document.createElement('article');
                el.className = 'post-card';
                el.dataset.postId = String(id);
                el.innerHTML = buildFeedPost(p);
                fragment.appendChild(el);
                bindFeedReactionControls(el);
                bindPostImageClicks(el);
            });
            container.appendChild(fragment);
        } catch (error) {
            console.error('Feed error:', error);
            if (reset && requestToken === feedRequestToken) {
                container.innerHTML =
                    '<div class="post-card" style="text-align:center;color:#64748b;padding:32px;">Unable to load the community feed right now. Please try again.</div>';
            }
        } finally {
            if (requestToken === feedRequestToken) {
                feedLoading = false;
            }
        }
    }

    function loadMorePosts() {
        if (feedLoading || feedPage >= feedLastPage) return;
        feedPage++;
        loadFeed(false);
    }

    let feedInfiniteObserver = null;
    function bindInfiniteScroll() {
        const sentinel = document.getElementById('feedInfiniteSentinel');
        if (!sentinel || feedInfiniteObserver) return;
        const root = document.querySelector('.feed-section');
        const overflowY = root ? getComputedStyle(root).overflowY : '';
        feedInfiniteObserver = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) loadMorePosts();
        }, {
            root: (overflowY === 'auto' || overflowY === 'scroll') ? root : null,
            rootMargin: '480px 0px',
        });
        feedInfiniteObserver.observe(sentinel);
    }
    bindInfiniteScroll();

    function bindFilterBarScrollHide() {
        const toolbar = document.querySelector('.feed-sticky-toolbar');
        const bar = document.querySelector('.feed-filter-bar');
        const anchor = document.querySelector('.feed-filter-anchor');
        const feedSection = document.querySelector('.feed-section');
        if (!toolbar || !bar) return;

        const DOWN_THRESHOLD = 8;
        const UP_THRESHOLD = 4;
        const TOP_SHOW = 12;
        let lastY = 0;
        let ticking = false;
        let barHeight = 64;

        function headerHeight() {
            const header = document.querySelector('.kabataan-header');
            return Math.round(header?.getBoundingClientRect().height || 68);
        }

        function scrollRoot() {
            if (!feedSection) return window;
            const overflowY = getComputedStyle(feedSection).overflowY;
            if (overflowY === 'auto' || overflowY === 'scroll') {
                return feedSection;
            }
            return window;
        }

        function isOverlay() {
            return scrollRoot() === window;
        }

        function currentY() {
            const root = scrollRoot();
            return root === window
                ? (window.scrollY || document.documentElement.scrollTop || 0)
                : root.scrollTop;
        }

        function measureBar() {
            const height = toolbar.scrollHeight || bar.offsetHeight;
            if (height > 8) barHeight = height;
            return barHeight;
        }

        function updateAnchor() {
            if (!anchor) return;
            if (!isOverlay()) {
                anchor.style.height = '0px';
                return;
            }
            anchor.style.height = toolbar.classList.contains('is-hidden') ? '0px' : (measureBar() + 'px');
        }

        function syncStickyTop() {
            toolbar.style.top = isOverlay() ? (headerHeight() + 'px') : '0px';
            updateAnchor();
        }

        function showBar() {
            toolbar.classList.remove('is-hidden');
            bar.classList.remove('is-hidden');
            updateAnchor();
        }

        function hideBar() {
            measureBar();
            toolbar.classList.add('is-hidden');
            updateAnchor();
        }

        lastY = currentY();
        showBar();
        syncStickyTop();

        function apply() {
            ticking = false;
            const y = Math.max(0, currentY());
            const delta = y - lastY;
            lastY = y;

            if (y <= TOP_SHOW) {
                showBar();
                return;
            }
            if (delta >= DOWN_THRESHOLD) {
                hideBar();
                return;
            }
            if (delta <= -UP_THRESHOLD) {
                showBar();
            }
        }

        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(apply);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        document.addEventListener('scroll', onScroll, { passive: true });
        feedSection?.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', function () {
            lastY = currentY();
            syncStickyTop();
            if (currentY() <= TOP_SHOW) showBar();
        });
        bar.addEventListener('focusin', showBar);
    }

    bindFilterBarScrollHide();

    function setFeedFilter(btn, filter) {
        document.querySelector('.feed-sticky-toolbar')?.classList.remove('is-hidden');
        document.querySelector('.feed-filter-bar')?.classList.remove('is-hidden');
        feedFilter = filter;
        document.querySelectorAll('.feed-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        loadFeed(true, { force: true });
    }

    document.querySelector('.feed-sticky-toolbar')?.classList.remove('is-hidden');
    document.querySelector('.feed-filter-bar')?.classList.remove('is-hidden');

    document.querySelectorAll('.feed-tab[data-feed-filter]').forEach(btn => {
        btn.addEventListener('click', () => setFeedFilter(btn, btn.dataset.feedFilter || 'all'));
    });

    loadFeed(true);

    const feedSearchInput = document.getElementById('feedSearchInput');
    let feedSearchTimer = null;
    feedSearchInput?.addEventListener('input', function () {
        clearTimeout(feedSearchTimer);
        feedSearchTimer = setTimeout(() => {
            feedSearch = this.value.trim();
            loadFeed(true);
        }, 300);
    });

    function feedEscape(v) {
        return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function feedAvatarUrl(url, name) {
        if (url) return url;
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&background=1a56db&color=fff&size=80`;
    }

    function formatCount(n) {
        const num = Number(n || 0);
        if (num >= 1000000) return `${(num / 1000000).toFixed(num % 1000000 === 0 ? 0 : 1).replace(/\.0$/, '')}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(num % 1000 === 0 ? 0 : 1).replace(/\.0$/, '')}K`;
        return String(num);
    }

    function reactionFacesHtml(counts) {
        return Object.keys(REACTION_EMOJI)
            .filter((type) => Number((counts || {})[type] || 0) > 0)
            .sort((a, b) => Number(counts[b] || 0) - Number(counts[a] || 0))
            .slice(0, 3)
            .map((type) => `<span class="reaction-face">${REACTION_EMOJI[type] || ''}</span>`)
            .join('');
    }

    function renderReactionsSummary(post) {
        const n = Number(post.likes || 0);
        const c = Number(post.comment_count ?? countFeedComments(post.comments || []));
        if (n <= 0 && c <= 0) return '';
        const left = n > 0
            ? `<button type="button" class="reaction-summary-left" onclick="openFeedReactionViewer(${Number(post.id)})"><span class="reaction-faces">${reactionFacesHtml(post.reaction_counts)}</span><span class="reaction-total">${formatCount(n)}</span></button>`
            : '<div class="reaction-summary-left"></div>';
        const right = c > 0
            ? `<button type="button" class="reaction-summary-comments" onclick="openComments(${Number(post.id)})">${formatCount(c)} ${c === 1 ? 'comment' : 'comments'}</button>`
            : '';
        return `<div class="post-engage"><div class="reaction-summary" id="feed-reactions-${post.id}" data-reactions="${n}" data-comments="${c}">${left}${right}</div></div>`;
    }

    function feedImgTag(url, name, className) {
        const src = feedEscape(feedAvatarUrl(url, name));
        const fallback = feedEscape(feedAvatarUrl('', name));
        return `<img src="${src}" alt="" class="${className || ''}" onerror="this.onerror=null;this.src='${fallback}'">`;
    }

    function countFeedComments(comments) {
        let total = 0;
        (comments || []).forEach(function (c) {
            total += 1;
            total += countFeedComments(c.replies || []);
        });
        return total;
    }

    window.feedReplyTarget = null;

    function reactionPickerHtml(activeType) {
        return '<div class="reaction-picker"><div class="reaction-picker-inner">'
            + Object.keys(REACTION_EMOJI).map(function (type) {
                return '<button type="button" class="reaction-option' + (activeType === type ? ' is-active' : '') + '" data-type="' + type + '" title="' + type + '">' + REACTION_EMOJI[type] + '</button>';
            }).join('')
            + '</div></div>';
    }

    function reactionLabel(type) {
        return type ? (REACTION_LABEL[type] || 'Like') : 'Like';
    }

    function isTouchDevice() {
        return window.matchMedia('(hover: none), (pointer: coarse)').matches;
    }

    function bindFeedReactionControls(root) {
        if (!root) return;
        root.querySelectorAll('.reaction-wrap').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';
            const btn = wrap.querySelector('.reaction-btn, .comment-like-btn');
            const picker = wrap.querySelector('.reaction-picker');
            const postId = Number(wrap.dataset.postId);
            const commentId = Number(wrap.dataset.commentId || 0);
            const isComment = wrap.dataset.target === 'comment';
            let hideTimer = null;
            let pressTimer = null;
            let didLongPress = false;
            function apply(type) {
                if (isComment) feedSetCommentReaction(postId, commentId, type);
                else feedSetReaction(postId, type);
            }
            wrap.addEventListener('mouseenter', function () {
                if (isTouchDevice()) return;
                clearTimeout(hideTimer);
                wrap.classList.add('is-open');
            });
            wrap.addEventListener('mouseleave', function () {
                if (isTouchDevice()) return;
                hideTimer = setTimeout(function () { wrap.classList.remove('is-open'); }, 80);
            });
            btn?.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (didLongPress) {
                    didLongPress = false;
                    return;
                }
                if (isTouchDevice() && wrap.classList.contains('is-open')) {
                    wrap.classList.remove('is-open');
                    return;
                }
                wrap.classList.remove('is-open');
                apply(btn.dataset.type || 'like');
            });
            btn?.addEventListener('touchstart', function () {
                didLongPress = false;
                clearTimeout(pressTimer);
                pressTimer = setTimeout(function () {
                    didLongPress = true;
                    document.querySelectorAll('.reaction-wrap.is-open').forEach(function (other) {
                        if (other !== wrap) other.classList.remove('is-open');
                    });
                    wrap.classList.add('is-open');
                }, 180);
            }, { passive: true });
            const cancelPress = function () { clearTimeout(pressTimer); };
            btn?.addEventListener('touchend', cancelPress);
            btn?.addEventListener('touchcancel', cancelPress);
            btn?.addEventListener('touchmove', cancelPress);
            btn?.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            picker?.querySelectorAll('.reaction-option').forEach(function (opt) {
                opt.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    wrap.classList.remove('is-open');
                    apply(opt.dataset.type);
                });
            });
        });
    }

    function commentLikeInner(type) {
        const label = type ? (REACTION_LABEL[type] || 'Like') : 'Like';
        if (type) {
            return `<span>${REACTION_EMOJI[type] || ''}</span><span>${feedEscape(label)}</span>`;
        }
        return `<span>${feedEscape(label)}</span>`;
    }

    function pickPreviewComment(comments) {
        const list = comments || [];
        if (!list.length) return null;
        return [...list].sort(function (a, b) {
            const likes = Number(b.likes || 0) - Number(a.likes || 0);
            return likes !== 0 ? likes : Number(b.id) - Number(a.id);
        })[0];
    }

    function buildCommentPreviewHtml(post) {
        const total = countFeedComments(post.comments || []);
        const comment = pickPreviewComment(post.comments || []);
        if (!comment || total <= 0) {
            return `<div class="comment-preview" id="comment-preview-${post.id}" hidden role="button" tabindex="0" onclick="openComments(${post.id})"></div>`;
        }
        const replyCount = Number(comment.reply_count || (comment.replies || []).length || 0);
        const more = total > 1 ? `<span class="comment-preview-more">View all ${total} comments</span>` : '';
        const replies = replyCount > 0
            ? `<span class="fb-view-replies">View ${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}</span>`
            : '';
        return `<div class="comment-preview" id="comment-preview-${post.id}" role="button" tabindex="0" onclick="openComments(${post.id})">
            ${more}
            <div class="fb-comment-row">
                ${feedImgTag(comment.author_avatar_url, comment.author_name, 'comment-avatar')}
                <div class="fb-comment-main">
                    <div class="fb-comment-head">
                        <span class="comment-author">${feedEscape(comment.author_name)}</span>
                        <span class="fb-comment-dot">·</span>
                        <span class="comment-time">${feedEscape(comment.time || '')}</span>
                    </div>
                    <p class="comment-text">${feedEscape(comment.body)}</p>
                    <div class="comment-meta">
                        <span class="comment-like-btn">${commentLikeInner(comment.reaction_type)}</span>
                        <span class="comment-action-btn">Reply</span>
                        ${replies}
                    </div>
                </div>
            </div>
        </div>`;
    }

    function feedStartReply(postId, commentId, authorName) {
        window.feedReplyTarget = { postId: postId, commentId: commentId };
        openComments(postId);
    }

    function toggleCommentOptions(id, event) {
        event?.preventDefault();
        event?.stopPropagation();
        const menu = document.getElementById('comment-options-' + id);
        const isOpen = menu?.classList.contains('open');
        document.querySelectorAll('.comment-options-menu.open, .cp-options-menu.open').forEach(function (m) {
            m.classList.remove('open');
        });
        if (!isOpen) menu?.classList.add('open');
    }

    function renderCommentItem(comment, postId, depth) {
        depth = depth || 0;
        const type = comment.reaction_type || (comment.liked ? 'like' : '');
        const replies = (comment.replies || []).map(function (reply) {
            return renderCommentItem(reply, postId, depth + 1);
        }).join('');
        const optionsHtml = comment.owned
            ? `<div class="comment-options-wrap">
                <button type="button" class="comment-options-btn" onclick="toggleCommentOptions(${comment.id}, event)" aria-label="Comment options">
                  <svg viewBox="0 0 20 20" fill="currentColor"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </button>
                <div class="comment-options-menu" id="comment-options-${comment.id}">
                  <button type="button" onclick="editComment(${postId}, ${comment.id})">Edit</button>
                  <button type="button" class="danger" onclick="deleteComment(${postId}, ${comment.id})">Delete</button>
                </div>
               </div>`
            : '';

        return `
            <div class="comment-item${depth ? ' is-reply comment-item--reply' : ''}" data-comment-id="${comment.id}">
               ${feedImgTag(comment.author_avatar_url, comment.author_name, 'comment-avatar')}
               <div class="comment-content">
                 <div class="fb-comment-head">
                   <p class="comment-author">${feedEscape(comment.author_name)}</p>
                   <span class="fb-comment-dot">·</span>
                   <span class="comment-time">${feedEscape(comment.time)}</span>
                   ${optionsHtml}
                 </div>
                 <p class="comment-text" id="feed-comment-text-${comment.id}">${feedEscape(comment.body)}</p>
                 <div class="comment-meta-row">
                   <div class="reaction-wrap comment-like-wrap" data-target="comment" data-post-id="${postId}" data-comment-id="${comment.id}">
                     <button type="button" class="comment-like-btn${comment.liked ? ' liked' : ''}" data-type="${feedEscape(type)}">${commentLikeInner(type)}</button>
                     ${reactionPickerHtml(type)}
                   </div>
                   <button type="button" class="comment-reply-btn" onclick="feedStartReply(${postId}, ${comment.id}, '${feedEscape(comment.author_name).replace(/'/g, "\\'")}')">Reply</button>
                 </div>
               </div>
             </div>
             ${replies ? `<div class="comment-replies">${replies}</div>` : ''}`;
    }

    function feedPostImages(p) {
        const list = Array.isArray(p.images) && p.images.length
            ? p.images
            : (p.image_url ? [p.image_url] : []);
        return [...new Set(list.filter(Boolean))];
    }

    function bindPostImageClicks(root) {
        if (!root) return;
        const grid = root.querySelector('[data-all-images]');
        if (!grid || grid.dataset.imagesBound === '1') return;
        grid.dataset.imagesBound = '1';
        let images = [];
        try {
            images = JSON.parse(grid.getAttribute('data-all-images') || '[]');
        } catch (_) {
            images = [];
        }
        if (!images.length) return;
        grid.querySelectorAll('[data-image-index]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openLightbox(images, parseInt(el.dataset.imageIndex, 10) || 0);
            });
        });
    }

    function buildFeedPost(p) {
        const images = feedPostImages(p);
        let media = '';
        if (images.length) {
            const encoded = feedEscape(JSON.stringify(images));
            const imgTag = (src, index) =>
                `<img src="${feedEscape(src)}" loading="lazy" alt="" data-image-index="${index}" onerror="this.style.display='none'">`;
            if (images.length === 1) {
                media = `<div class="post-image" data-all-images="${encoded}">${imgTag(images[0], 0)}</div>`;
            } else if (images.length === 2) {
                media = `<div class="post-images-grid grid-2" data-all-images="${encoded}">${images.map((img, i) => imgTag(img, i)).join('')}</div>`;
            } else if (images.length === 3) {
                media = `<div class="post-images-grid grid-3" data-all-images="${encoded}">${images.map((img, i) => imgTag(img, i)).join('')}</div>`;
            } else if (images.length === 4) {
                media = `<div class="post-images-grid grid-4" data-all-images="${encoded}">${images.map((img, i) => imgTag(img, i)).join('')}</div>`;
            } else {
                const remaining = images.length - 4;
                media = `<div class="post-images-grid grid-4" data-all-images="${encoded}">
                    ${images.slice(0, 3).map((img, i) => imgTag(img, i)).join('')}
                    <div class="image-more-overlay" data-image-index="3">
                        <img src="${feedEscape(images[3])}" loading="lazy" alt="">
                        <div class="more-overlay-text">+${remaining} more</div>
                    </div>
                </div>`;
            }
        }
        
        const link   = p.link_url  ? `<a href="${feedEscape(p.link_url)}" target="_blank" rel="noopener" class="post-link-preview">${feedEscape(p.link_url)}</a>` : '';
        const reactionsSummary = renderReactionsSummary(p);

        return `
          <div class="post-header">
            ${feedImgTag(p.author_avatar_url || p.barangay_logo_url, p.author_name, 'post-avatar')}
            <div class="post-info">
              <h3 class="post-author">${feedEscape(p.author_name ?? ('SK Brgy. ' + (p.barangay_name ?? '')))}</h3>
              <p class="post-meta">
                <span class="post-type ${p.type}">${feedEscape(p.type)}</span>
                <span class="post-time">${feedEscape(p.time)}</span>
              </p>
            </div>
          </div>
          <div class="post-content">
            ${p.title ? `<h2 class="post-title">${feedEscape(p.title)}</h2>` : ''}
            <p class="post-text">${feedEscape(p.body)}</p>
            ${media}${link}
          </div>
          ${reactionsSummary}
          <div class="post-actions">
            <div class="reaction-wrap" data-target="post" data-post-id="${p.id}">
              <button type="button" class="action-btn reaction-btn${p.liked ? ' liked' : ''}" data-type="${feedEscape(p.reaction_type || (p.liked ? 'like' : ''))}" id="feed-like-btn-${p.id}">
                <span class="reaction-icon">${p.reaction_type ? `<span class="reaction-current">${REACTION_EMOJI[p.reaction_type]}</span>` : LIKE_THUMB_SVG}</span>
                <span class="reaction-label">${feedEscape(reactionLabel(p.reaction_type))}</span>
              </button>
              ${reactionPickerHtml(p.reaction_type || (p.liked ? 'like' : ''))}
            </div>
            <button class="action-btn comment-btn" onclick="openComments(${p.id})">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
              <span>Comment</span>
            </button>
          </div>`;
    }

    function bumpReactionCounts(counts, fromType, toType) {
        const next = { ...(counts || {}) };
        if (fromType && fromType !== toType) {
            next[fromType] = Math.max(0, Number(next[fromType] || 0) - 1);
        }
        if (toType && fromType !== toType) {
            next[toType] = Number(next[toType] || 0) + 1;
        }
        return next;
    }

    function updateReactionsSummary(postId, postLike) {
        const cached = postCache.get(Number(postId)) || postLike || {};
        const html = renderReactionsSummary({
            id: postId,
            likes: cached.likes,
            reaction_counts: cached.reaction_counts,
            comments: cached.comments,
            comment_count: cached.comment_count,
        });
        const existing = document.getElementById(`feed-reactions-${postId}`);
        const wrap = existing?.closest('.post-engage') || existing;
        if (!html) {
            wrap?.remove();
            return;
        }
        if (wrap) {
            wrap.outerHTML = html;
            return;
        }
        const card = document.querySelector(`[data-post-id="${postId}"]`);
        const actions = card?.querySelector('.post-actions');
        if (actions) actions.insertAdjacentHTML('beforebegin', html);
    }

    function resolveNextReaction(currentType, requestedType) {
        const requested = requestedType || 'like';
        const current = currentType || '';
        if (current === requested) return { liked: false, type: '' };
        return { liked: true, type: requested };
    }

    function paintPostReaction(id, liked, nextType, count) {
        const btn = document.getElementById('feed-like-btn-' + id);
        if (!btn) return;
        btn.classList.toggle('liked', Boolean(liked));
        btn.dataset.type = nextType || '';
        const icon = btn.querySelector('.reaction-icon');
        if (icon) icon.innerHTML = nextType ? `<span class="reaction-current">${REACTION_EMOJI[nextType]}</span>` : LIKE_THUMB_SVG;
        const label = btn.querySelector('.reaction-label');
        if (label) label.textContent = reactionLabel(nextType);
        btn.closest('.reaction-wrap')?.querySelectorAll('.reaction-option').forEach(function (opt) {
            opt.classList.toggle('is-active', Boolean(nextType) && opt.dataset.type === nextType);
        });
    }

    function paintCommentReaction(commentId, liked, nextType) {
        const wrap = document.querySelector('.reaction-wrap[data-comment-id="' + commentId + '"]');
        const btn = wrap?.querySelector('.comment-like-btn');
        if (btn) {
            btn.classList.toggle('liked', Boolean(liked));
            btn.dataset.type = nextType || '';
            btn.innerHTML = commentLikeInner(nextType);
        }
        wrap?.querySelectorAll('.reaction-option').forEach(function (opt) {
            opt.classList.toggle('is-active', Boolean(nextType) && opt.dataset.type === nextType);
        });
    }

    let reactionViewerState = { postId: null, data: null, filter: 'all' };

    function renderFeedReactionViewer(filter) {
        reactionViewerState.filter = filter;
        const data = reactionViewerState.data || { reactors: [], reaction_counts: {}, count: 0 };
        const counts = data.reaction_counts || {};
        const tabs = document.getElementById('reactionViewerTabs');
        if (tabs) {
            const items = [['all', `All ${formatCount(data.count || 0)}`]].concat(
                Object.keys(REACTION_EMOJI)
                    .filter((type) => Number(counts[type] || 0) > 0)
                    .map((type) => [type, `${REACTION_EMOJI[type]} ${formatCount(counts[type])}`])
            );
            tabs.innerHTML = items.map(([type, label]) =>
                `<button type="button" class="reaction-viewer-tab${filter === type ? ' is-active' : ''}" data-type="${type}">${label}</button>`
            ).join('');
            tabs.querySelectorAll('.reaction-viewer-tab').forEach((tab) => {
                tab.addEventListener('click', () => renderFeedReactionViewer(tab.dataset.type));
            });
        }
        const reactors = (data.reactors || []).filter((row) => filter === 'all' || row.reaction_type === filter);
        const list = document.getElementById('reactionViewerList');
        if (!list) return;
        if (!reactors.length) {
            list.innerHTML = Number(data.count || 0) > 0 ? '' : '<p class="reaction-viewer-empty">No reactions yet.</p>';
            return;
        }
        list.innerHTML = reactors.map((row) => `
            <div class="reaction-viewer-row">
                <div class="reaction-viewer-avatar-wrap">
                    <img src="${feedEscape(feedAvatarUrl(row.avatar_url, row.name))}" alt="">
                    <span class="reaction-viewer-emoji">${REACTION_EMOJI[row.reaction_type] || ''}</span>
                </div>
                <span class="reaction-viewer-name">${feedEscape(row.name || 'Member')}</span>
            </div>
        `).join('');
    }

    async function openFeedReactionViewer(postId) {
        const modal = document.getElementById('reactionViewerModal');
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        const cached = postCache.get(Number(postId));
        const reactors = cached?.reactors || cached?.reactions_summary?.reactors || [];
        if (cached) {
            reactionViewerState = {
                postId,
                data: {
                    reactors,
                    reaction_counts: cached.reaction_counts || {},
                    count: Number(cached.likes || 0),
                },
                filter: 'all',
            };
            renderFeedReactionViewer('all');
            if (reactors.length) {
                return;
            }
        }
        try {
            const data = await apiFeed('/api/feed/' + postId + '/likes');
            reactionViewerState = { postId, data, filter: 'all' };
            renderFeedReactionViewer('all');
        } catch (_) {
            const list = document.getElementById('reactionViewerList');
            if (list && !list.innerHTML) list.innerHTML = '<p class="reaction-viewer-empty">Unable to load reactions.</p>';
        }
    }

    function closeFeedReactionViewer() {
        const modal = document.getElementById('reactionViewerModal');
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('reactionViewerOverlay')?.addEventListener('click', closeFeedReactionViewer);
    document.getElementById('reactionViewerClose')?.addEventListener('click', closeFeedReactionViewer);

    async function feedSetReaction(id, type) {
        const btn = document.getElementById('feed-like-btn-' + id);
        const current = btn?.dataset.type || '';
        const next = resolveNextReaction(current, type);
        const cached = postCache.get(Number(id));
        let count = Number(cached?.likes || 0);
        if (current && !next.liked) count = Math.max(0, count - 1);
        else if (!current && next.liked) count += 1;
        if (next.liked) playFeedReactionSound();
        paintPostReaction(id, next.liked, next.type, count);
        if (cached) {
            cached.liked = next.liked;
            cached.reaction_type = next.type || null;
            cached.likes = count;
            cached.reaction_counts = bumpReactionCounts(cached.reaction_counts || {}, current, next.type);
            updateReactionsSummary(id);
        }
        const key = 'post:' + id;
        const { seq, signal } = beginReactionRequest(key);
        try {
            const data = await apiFeed('/api/feed/' + id + '/react', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reaction_type: type || 'like', client_seq: seq }),
                signal,
            });
            if (!isLatestReactionRequest(key, seq)) return;
            if (typeof data?.liked !== 'boolean') return;
            const serverType = data.liked ? (data.reaction_type || 'like') : '';
            paintPostReaction(id, data.liked, serverType, data.count);
            if (cached) {
                cached.liked = data.liked;
                cached.reaction_type = serverType || null;
                cached.likes = data.count;
                cached.reaction_counts = data.reaction_counts;
            }
            updateReactionsSummary(id);
        } catch (err) {
            if (err?.name === 'AbortError') return;
            if (!isLatestReactionRequest(key, seq)) return;
            notifyFeed('Unable to update your reaction. Please try again.', 'error');
        }
    }

    async function feedSetCommentReaction(postId, commentId, type) {
        const wrap = document.querySelector('.reaction-wrap[data-comment-id="' + commentId + '"]');
        const btn = wrap?.querySelector('.comment-like-btn');
        const current = btn?.dataset.type || '';
        const next = resolveNextReaction(current, type);
        if (next.liked) playFeedReactionSound();
        paintCommentReaction(commentId, next.liked, next.type);
        const key = 'comment:' + commentId;
        const { seq, signal } = beginReactionRequest(key);
        try {
            const data = await apiFeed('/api/feed/' + postId + '/comments/' + commentId + '/reactions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reaction_type: type || 'like', client_seq: seq }),
                signal,
            });
            if (!isLatestReactionRequest(key, seq)) return;
            if (typeof data?.liked !== 'boolean') return;
            const serverType = data.liked ? (data.reaction_type || 'like') : '';
            paintCommentReaction(commentId, data.liked, serverType);
        } catch (err) {
            if (err?.name === 'AbortError') return;
            if (!isLatestReactionRequest(key, seq)) return;
            notifyFeed('Unable to update your reaction. Please try again.', 'error');
        }
    }

    let pendingCommentAction = null;

    function commentBodyText(commentId) {
        return document.getElementById('feed-comment-text-' + commentId)?.textContent
            || document.getElementById('cp-text-' + commentId)?.textContent
            || '';
    }

    function editComment(postId, commentId) {
        pendingCommentAction = { postId: Number(postId), commentId: Number(commentId), isReply: isReplyComment(commentId) };
        document.querySelectorAll('.comment-options-menu.open, .cp-options-menu.open').forEach(function (m) {
            m.classList.remove('open');
        });
        const modal = document.getElementById('editCommentModal');
        const field = document.getElementById('editCommentBody');
        if (!modal || !field) return;
        field.value = commentBodyText(commentId);
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { field.focus(); }, 50);
    }

    function closeEditCommentModal() {
        document.getElementById('editCommentModal')?.classList.remove('active');
        pendingCommentAction = null;
        if (!document.getElementById('commentPreviewShell')?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    async function confirmEditComment() {
        if (!pendingCommentAction) return;
        const postId = pendingCommentAction.postId;
        const commentId = pendingCommentAction.commentId;
        const isReply = pendingCommentAction.isReply;
        const body = document.getElementById('editCommentBody')?.value.trim();
        if (!body) return;
        if (body.length > 500) {
            notifyFeed('Comments and replies are limited to 500 characters.', 'error');
            return;
        }
        const btn = document.getElementById('confirmEditCommentBtn');
        if (btn) btn.disabled = true;
        try {
            const updated = await apiFeed(`/api/feed/${postId}/comments/${commentId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });
            const feedEl = document.getElementById('feed-comment-text-' + commentId);
            if (feedEl) feedEl.textContent = updated.body;
            const previewEl = document.getElementById('cp-text-' + commentId);
            if (previewEl) previewEl.textContent = updated.body;
            document.dispatchEvent(new CustomEvent('community-feed:comment-updated', { detail: updated }));
            closeEditCommentModal();
            notifyFeed(isReply ? 'Reply updated successfully.' : 'Comment updated successfully.');
        } catch (_) {
            notifyFeed(isReply ? 'Unable to update the reply. Please try again.' : 'Unable to update the comment. Please try again.', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function deleteComment(postId, commentId) {
        pendingCommentAction = { postId: Number(postId), commentId: Number(commentId), isReply: isReplyComment(commentId) };
        document.querySelectorAll('.comment-options-menu.open, .cp-options-menu.open').forEach(function (m) {
            m.classList.remove('open');
        });
        const modal = document.getElementById('deleteCommentModal');
        if (!modal) return;
        const title = modal.querySelector('h2');
        const body = modal.querySelector('.modal-body p');
        if (title) title.textContent = pendingCommentAction.isReply ? 'Delete Reply' : 'Delete Comment';
        if (body) body.textContent = pendingCommentAction.isReply
            ? 'Are you sure you want to delete this reply?'
            : 'Are you sure you want to delete this comment?';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteCommentModal() {
        document.getElementById('deleteCommentModal')?.classList.remove('active');
        pendingCommentAction = null;
        if (!document.getElementById('commentPreviewShell')?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    async function confirmDeleteComment() {
        if (!pendingCommentAction) return;
        const postId = pendingCommentAction.postId;
        const commentId = pendingCommentAction.commentId;
        const isReply = pendingCommentAction.isReply;
        const btn = document.getElementById('confirmDeleteCommentBtn');
        if (btn) btn.disabled = true;
        try {
            await apiFeed(`/api/feed/${postId}/comments/${commentId}`, { method: 'DELETE' });
            document.querySelectorAll('[data-comment-id="' + commentId + '"]').forEach(function (el) { el.remove(); });
            document.dispatchEvent(new CustomEvent('community-feed:comment-deleted', { detail: { postId, commentId } }));
            closeDeleteCommentModal();
            notifyFeed(isReply ? 'Reply deleted successfully.' : 'Comment deleted successfully.');
        } catch (_) {
            notifyFeed(isReply ? 'Unable to delete the reply. Please try again.' : 'Unable to delete the comment. Please try again.', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function commentPreviewIsOpen(postId) {
        const shell = document.getElementById('commentPreviewShell');
        if (!shell?.classList.contains('is-open')) return false;
        if (postId == null) return true;
        return Number(shell.dataset.postId || 0) === Number(postId);
    }

    async function refreshOpenCommentPreview() {
        const shell = document.getElementById('commentPreviewShell');
        const postId = Number(shell?.dataset.postId || 0);
        if (!commentPreviewIsOpen(postId)) return;
        if (document.querySelector('#commentPreviewShell .cp-composer-input:focus, #commentPreviewShell [data-reply-input]:focus')) return;
        try {
            const data = await apiFeed('/api/feed/' + postId);
            data.comments_loaded = true;
            const cached = mergeCachedPost(data);
            if (cached && commentPreviewIsOpen(postId) && typeof window.openCommentPreview === 'function') {
                window.openCommentPreview(cached, { skipUrl: true, preserveScroll: true });
            }
        } catch (_) { /* keep current preview */ }
    }

    async function openComments(id) {
        const postId = Number(id);
        let cached = postCache.get(postId);
        if (cached && typeof window.openCommentPreview === 'function') {
            window.openCommentPreview(cached);
        }
        const needsComments = !cached || cached.comments_loaded === false;
        if (needsComments || cached) {
            try {
                const data = await apiFeed('/api/feed/' + postId);
                data.comments_loaded = true;
                cached = mergeCachedPost(Object.assign({}, cached || {}, data));
                if (cached && typeof window.openCommentPreview === 'function' && commentPreviewIsOpen(postId)) {
                    window.openCommentPreview(cached, { skipUrl: true, preserveScroll: true });
                    return;
                }
            } catch (_) { /* fall through */ }
        }
        if (cached && typeof window.openCommentPreview === 'function') {
            return;
        }
        window.location.assign('/dashboard/comments/' + postId);
    }

    function feedToggleComments(id) {
        openComments(id);
    }

    window.feedSetReaction = feedSetReaction;
    window.editComment = editComment;
    window.deleteComment = deleteComment;
    window.confirmEditComment = confirmEditComment;
    window.confirmDeleteComment = confirmDeleteComment;
    window.closeEditCommentModal = closeEditCommentModal;
    window.closeDeleteCommentModal = closeDeleteCommentModal;
    window.openComments = openComments;
    window.openFeedReactionViewer = openFeedReactionViewer;
    window.closeFeedReactionViewer = closeFeedReactionViewer;
    window.toggleCommentOptions = toggleCommentOptions;

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.comment-options-wrap')) {
            document.querySelectorAll('.comment-options-menu.open').forEach(function (m) {
                m.classList.remove('open');
            });
        }
    });

    function findCachedComment(comments, commentId) {
        const id = String(commentId);
        for (const c of comments || []) {
            if (String(c.id) === id) return c;
            const nested = findCachedComment(c.replies || [], commentId);
            if (nested) return nested;
        }
        return null;
    }

    function removeCachedComment(comments, commentId) {
        const id = String(commentId);
        const list = comments || [];
        const index = list.findIndex(function (c) { return String(c.id) === id; });
        if (index >= 0) {
            list.splice(index, 1);
            return true;
        }
        return list.some(function (c) { return removeCachedComment(c.replies || [], commentId); });
    }

    function refreshFeedCommentUi(postId) {
        const cached = postCache.get(Number(postId));
        if (!cached) return;
        const total = countFeedComments(cached.comments || []);
        cached.comment_count = total;
        updateReactionsSummary(postId);
        const preview = document.getElementById('comment-preview-' + postId);
        if (preview) {
            preview.remove();
        }
        const list = document.getElementById('feed-comments-list-' + postId);
        if (list) {
            list.innerHTML = (cached.comments || []).map(function (c) {
                return renderCommentItem(c, postId, 0);
            }).join('');
            bindFeedReactionControls(list);
        }
    }

    function insertFeedComment(postId, comment) {
        const cached = postCache.get(Number(postId));
        if (!cached) return;
        cached.comments = cached.comments || [];
        if (comment.parent_id) {
            const parent = findCachedComment(cached.comments, comment.parent_id);
            if (parent) {
                parent.replies = parent.replies || [];
                parent.replies.push(comment);
                parent.reply_count = parent.replies.length;
            } else {
                cached.comments.push(comment);
            }
        } else {
            cached.comments.push(comment);
        }
        refreshFeedCommentUi(postId);
    }

    async function feedSubmitComment(id, input) {
        const text = input.value.trim();
        if (!text) return;
        if (text.length > 500) {
            notifyFeed('Comments and replies are limited to 500 characters.', 'error');
            return;
        }
        if (input.dataset.sending === '1') return;
        input.dataset.sending = '1';
        const parentId = (window.feedReplyTarget && window.feedReplyTarget.postId === id)
            ? window.feedReplyTarget.commentId
            : null;
        input.value = '';
        input.placeholder = 'Write a comment...';
        window.feedReplyTarget = null;

        const tempId = 'tmp-' + Date.now();
        const tempComment = {
            id: tempId,
            body: text,
            author_name: (window.CommentPreviewConfig && window.CommentPreviewConfig.userDisplayName) || 'You',
            author_avatar_url: FEED_USER_AVATAR,
            time: 'Just now',
            liked: false,
            likes: 0,
            owned: true,
            replies: [],
            parent_id: parentId,
        };
        insertFeedComment(id, tempComment);

        try {
            const r = await fetch('/api/feed/' + id + '/comment', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    body: text,
                    parent_id: parentId,
                }),
            });
            const payload = await r.json();
            if (!r.ok) {
                const cached = postCache.get(Number(id));
                if (cached) removeCachedComment(cached.comments || [], tempId);
                refreshFeedCommentUi(id);
                notifyFeed(parentId ? 'Unable to add your reply. Please try again.' : 'Unable to add your comment. Please try again.', 'error');
                input.value = text;
                return;
            }
            const cached = postCache.get(Number(id));
            if (cached) {
                removeCachedComment(cached.comments || [], tempId);
                insertFeedComment(id, payload);
            }
            notifyFeed(parentId ? 'Reply added successfully.' : 'Comment added successfully.');
        } catch (_) {
            const cached = postCache.get(Number(id));
            if (cached) removeCachedComment(cached.comments || [], tempId);
            refreshFeedCommentUi(id);
            notifyFeed(parentId ? 'Unable to add your reply. Please try again.' : 'Unable to add your comment. Please try again.', 'error');
            input.value = text;
        } finally {
            input.dataset.sending = '0';
        }
    }

    // ── Image Lightbox Functions ──────────────────────────────────────────────
    let lightboxImages = [];
    let lightboxIndex = 0;
    let lightboxZoom = 1;
    const LIGHTBOX_ZOOM_MIN = 1;
    const LIGHTBOX_ZOOM_MAX = 3;
    const LIGHTBOX_ZOOM_STEP = 0.25;

    window.openImageModal = function(imageUrl) {
        openLightbox([imageUrl], 0);
    };

    window.openImageGallery = function(postIdOrImages, imagesOrIndex, startIndex) {
        if (Array.isArray(postIdOrImages)) {
            openLightbox(postIdOrImages, Number(imagesOrIndex) || 0);
            return;
        }
        openLightbox(Array.isArray(imagesOrIndex) ? imagesOrIndex : [], Number(startIndex) || 0);
    };

    function openLightbox(images, startIndex = 0) {
        lightboxImages = images;
        lightboxIndex = startIndex;
        lightboxZoom = 1;
        const lb = document.getElementById('imageLightbox');
        if (!lb) return;
        lb.classList.add('active');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        renderLightboxImage();
        applyLightboxZoom();
    }
    window.openLightbox = openLightbox;

    function closeLightbox() {
        const lb = document.getElementById('imageLightbox');
        if (lb) {
            lb.classList.remove('active');
            lb.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
        lightboxZoom = 1;
        applyLightboxZoom();
    }

    function applyLightboxZoom() {
        const img = document.getElementById('lightboxImage');
        const label = document.getElementById('lightboxZoomLevel');
        if (img) {
            img.style.transform = `scale(${lightboxZoom})`;
        }
        if (label) {
            label.textContent = `${Math.round(lightboxZoom * 100)}%`;
        }
    }

    function lightboxZoomIn() {
        lightboxZoom = Math.min(LIGHTBOX_ZOOM_MAX, +(lightboxZoom + LIGHTBOX_ZOOM_STEP).toFixed(2));
        applyLightboxZoom();
    }

    function lightboxZoomOut() {
        lightboxZoom = Math.max(LIGHTBOX_ZOOM_MIN, +(lightboxZoom - LIGHTBOX_ZOOM_STEP).toFixed(2));
        applyLightboxZoom();
    }

    function lightboxZoomReset() {
        lightboxZoom = 1;
        applyLightboxZoom();
        const viewport = document.getElementById('lightboxViewport');
        if (viewport) {
            viewport.scrollLeft = 0;
            viewport.scrollTop = 0;
        }
    }

    function renderLightboxImage() {
        const img = document.getElementById('lightboxImage');
        const counter = document.getElementById('lightboxCounter');
        const prevBtn = document.getElementById('lightboxPrev');
        const nextBtn = document.getElementById('lightboxNext');
        
        if (!img || !lightboxImages.length) return;
        
        img.src = lightboxImages[lightboxIndex];
        lightboxZoom = 1;
        applyLightboxZoom();
        
        if (counter) {
            counter.textContent = lightboxImages.length > 1 ? `${lightboxIndex + 1} / ${lightboxImages.length}` : '';
        }
        
        // Show/hide navigation buttons based on image count
        if (prevBtn) prevBtn.style.display = lightboxImages.length > 1 ? 'flex' : 'none';
        if (nextBtn) nextBtn.style.display = lightboxImages.length > 1 ? 'flex' : 'none';
    }

    function lightboxPrev() {
        if (!lightboxImages.length) return;
        lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length;
        renderLightboxImage();
    }

    function lightboxNext() {
        if (!lightboxImages.length) return;
        lightboxIndex = (lightboxIndex + 1) % lightboxImages.length;
        renderLightboxImage();
    }

    // Lightbox event listeners
    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', lightboxPrev);
    document.getElementById('lightboxNext')?.addEventListener('click', lightboxNext);
    document.getElementById('lightboxZoomIn')?.addEventListener('click', lightboxZoomIn);
    document.getElementById('lightboxZoomOut')?.addEventListener('click', lightboxZoomOut);
    document.getElementById('lightboxZoomReset')?.addEventListener('click', lightboxZoomReset);
    
    // Close on backdrop click
    document.getElementById('imageLightbox')?.addEventListener('click', (e) => {
        if (e.target?.id === 'imageLightbox') closeLightbox();
    });
    
    // Zoom with mouse wheel
    document.getElementById('lightboxViewport')?.addEventListener('wheel', (e) => {
        const lb = document.getElementById('imageLightbox');
        if (!lb?.classList.contains('active')) return;
        e.preventDefault();
        if (e.deltaY < 0) lightboxZoomIn();
        else lightboxZoomOut();
    }, { passive: false });
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        const lb = document.getElementById('imageLightbox');
        if (!lb?.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        else if (e.key === 'ArrowLeft') lightboxPrev();
        else if (e.key === 'ArrowRight') lightboxNext();
        else if (e.key === '+' || e.key === '=') lightboxZoomIn();
        else if (e.key === '-') lightboxZoomOut();
        else if (e.key === '0') lightboxZoomReset();
    });

    let feedPollInFlight = false;

    async function pollFeedUpdates() {
        if (document.hidden || feedLoading || feedPollInFlight) return;
        if (document.querySelector('.comment-input:focus, .cp-composer-input:focus')) return;
        if (document.getElementById('editCommentModal')?.classList.contains('active')) return;

        const tokenAtStart = feedRequestToken;
        feedPollInFlight = true;
        try {
            if (commentPreviewIsOpen()) {
                await refreshOpenCommentPreview();
            }
            const params = new URLSearchParams({ page: 1, filter: feedFilter });
            if (feedSearch) params.set('search', feedSearch);
            const data = await apiFeed(`/api/feed?${params}`);
            if (tokenAtStart !== feedRequestToken) return;
            const items = data.data ?? [];
            const fresh = [];
            items.forEach((p) => {
                const id = Number(p.id);
                if (!id) return;
                if (renderedPostIds.has(String(id)) || document.querySelector(`.post-card[data-post-id="${id}"]`)) {
                    mountFeedPost(p, 'append');
                    return;
                }
                fresh.push(p);
            });
            fresh.slice().reverse().forEach((p) => {
                const el = mountFeedPost(p, 'prepend');
                if (el) el.classList.add('post-card-new');
            });
            if (fresh.length) {
                setTimeout(() => {
                    document.querySelectorAll('.post-card-new').forEach((el) => el.classList.remove('post-card-new'));
                }, 1200);
            }
        } catch (_) {
            /* silent poll failure */
        } finally {
            feedPollInFlight = false;
        }
    }

    function startFeedPolling() {
        if (feedPollTimer) clearTimeout(feedPollTimer);
        const delay = Number(window.CommunityFeedConfig?.feedPollMs || 5000);
        const tick = async function () {
            await pollFeedUpdates();
            feedPollTimer = setTimeout(tick, delay);
        };
        feedPollTimer = setTimeout(tick, delay);
    }

    document.addEventListener('DOMContentLoaded', () => {
        startFeedPolling();
        document.querySelector('a.kabataan-header__icon-btn[aria-label="Home"]')?.addEventListener('click', function (e) {
            try {
                const dest = new URL(this.href, window.location.origin);
                if (dest.pathname === window.location.pathname) {
                    e.preventDefault();
                    window.location.reload();
                }
            } catch (_) {}
        });
    });
    window.feedStartReply = feedStartReply;
    window.feedToggleComments = feedToggleComments;
    </script>

    <script>
        document.getElementById('educationQuickGuideBtn')?.addEventListener('click', function () {
            if (!window.ScholarshipQuickGuidelines) {
                return;
            }
            const stepsRaw = this.dataset.schQgSteps;
            if (stepsRaw) {
                try {
                    window.ScholarshipQuickGuidelines.open(JSON.parse(stepsRaw));
                    return;
                } catch (error) {
                    // fall through
                }
            }
            window.ScholarshipQuickGuidelines.open();
        });
    </script>

    @include('programs::scholarship.partials.data-privacy-modal')
    @vite(['app/Modules/Dashboard/assets/js/community-feed-comment-preview.js'])
</body>
</html>
