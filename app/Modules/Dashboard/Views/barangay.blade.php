<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>SK Barangay {{ $name }} - SK OnePortal</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Layout/assets/css/kabataan-logout.css',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/Profile/assets/css/profile.css',
        'app/Modules/Dashboard/assets/css/dashboard.css',
        'app/Modules/Dashboard/assets/css/community-feed-comment-preview.css',
        'app/Modules/Dashboard/assets/css/barangay-profile.css',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
    ])
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
    <style>
        html:has(.brgy-stalk-page) {
            overflow-x: clip;
            overflow-y: scroll;
            height: auto;
            max-width: 100%;
        }
        body.youth-profile {
            background: linear-gradient(135deg,#f5f7fa 0%,#e8eef5 100%);
            overflow-x: clip;
            overflow-y: visible;
            height: auto;
            max-width: 100%;
        }
        .bfp-profile-card .brgy-logo,
        .bfp-profile-head .brgy-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            font-size: 24px;
            font-weight: 900;
            color: #fff;
            flex-shrink: 0;
        }
        .bfp-profile-card .brgy-logo.has-logo,
        .bfp-profile-card .brgy-logo.has-logo img {
            background: #fff;
        }
        .bfp-profile-card .brgy-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .profile-main {
            position: static !important;
            overflow: visible !important;
            padding-top: 88px !important;
            height: auto !important;
            display: block;
            max-width: 100%;
        }
        .profile-container.bfp-wrap {
            overflow: visible !important;
            display: flex !important;
            flex-direction: column;
            align-items: stretch;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px 40px !important;
            width: 100%;
        }
        .bfp-back-link {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            width: auto;
            max-width: max-content;
            gap: 6px;
            padding: 6px 11px;
            margin: 0 0 14px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid rgba(4, 80, 168, 0.16);
            box-shadow: 0 1px 4px rgba(4, 80, 168, 0.08);
            color: #0450a8;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            white-space: nowrap;
        }
        .bfp-back-link:hover { background: #f8faff; }
        .bfp-back-link svg { width: 12px; height: 12px; flex-shrink: 0; }
        .bfp-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 24px;
            align-items: start;
            overflow: visible;
        }
        .bfp-main,
        .bfp-sidebar {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }
        .bfp-main { overflow: visible; }
        .bfp-sidebar {
            position: sticky;
            top: 88px;
            max-height: calc(100vh - 100px);
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .bfp-profile-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(2, 42, 84, 0.06);
        }
        .bfp-profile-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .bfp-profile-copy { min-width: 0; flex: 1; }
        .bfp-name {
            font-size: 18px;
            font-weight: 800;
            color: #022a54;
            margin: 0 0 4px;
            line-height: 1.3;
        }
        .bfp-loc {
            font-size: 12px;
            color: #64748b;
            margin: 0;
            line-height: 1.45;
        }
        .bfp-loc svg { color: #0450a8; width: 14px; height: 14px; vertical-align: -2px; margin-right: 4px; }
        .bfp-stats {
            display: grid;
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1fr) minmax(0, 1.4fr);
            gap: 8px;
        }
        .bfp-stat {
            text-align: center;
            padding: 10px 6px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            min-width: 0;
        }
        .bfp-stat strong { display: block; font-size: 16px; font-weight: 800; color: #0450a8; line-height: 1.2; }
        .bfp-stat--term strong { font-size: 13px; white-space: nowrap; }
        .bfp-stat span { display: block; font-size: 10px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.04em; }
        .bfp-sidebar-close,
        .bfp-sidebar-backdrop,
        .bfp-sidebar-fab { display: none; }
        @media (max-width: 1100px) {
            html:has(.brgy-stalk-page) { height: auto; overflow-x: clip; overflow-y: scroll; }
            body.youth-profile { height: auto; overflow-x: clip; overflow-y: visible; }
            .profile-main {
                height: auto !important;
                overflow: visible !important;
                display: block;
            }
            .profile-container.bfp-wrap {
                display: flex !important;
                flex-direction: column;
                align-items: stretch;
                overflow: visible !important;
                height: auto;
                padding: 0 12px 88px !important;
            }
            .bfp-back-link {
                align-self: flex-start;
                width: auto;
                max-width: max-content;
                padding: 6px 10px;
                font-size: 12px;
                margin: 0 0 12px;
            }
            .bfp-grid {
                grid-template-columns: 1fr;
                overflow: visible;
                height: auto;
            }
            .bfp-main { overflow: visible; height: auto; }
            .bfp-sidebar {
                position: fixed;
                top: 76px;
                right: 0;
                width: min(320px, 92vw);
                height: calc(100vh - 76px);
                max-height: none;
                padding: 16px 14px 24px;
                background: #f5f7fb;
                box-shadow: -4px 0 24px rgba(0, 0, 0, 0.14);
                z-index: 1060;
                transform: translateX(110%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y: auto;
            }
            .bfp-sidebar.drawer-open { transform: translateX(0); }
            .bfp-sidebar-close {
                display: flex;
                align-items: center;
                justify-content: center;
                position: absolute;
                top: 10px;
                right: 10px;
                width: 32px;
                height: 32px;
                border: none;
                border-radius: 8px;
                background: #fff;
                color: #64748b;
                font-size: 22px;
                line-height: 1;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                z-index: 2;
            }
            .bfp-sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 1050;
                backdrop-filter: blur(2px);
            }
            .bfp-sidebar-backdrop.active { display: block; }
            .bfp-sidebar-fab {
                display: inline-flex;
                position: fixed;
                bottom: 24px;
                right: 16px;
                z-index: 1040;
                align-items: center;
                gap: 8px;
                padding: 12px 16px;
                border: none;
                border-radius: 999px;
                background: linear-gradient(135deg, #0450a8, #667eea);
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                font-family: inherit;
                box-shadow: 0 4px 16px rgba(4, 80, 168, 0.35);
                cursor: pointer;
            }
            .bfp-profile-card { position: relative; padding-top: 36px; }
        }
        @media (max-width: 768px) {
            .profile-container.bfp-wrap { padding: 0 12px 88px !important; }
            .profile-main { padding-top: 76px !important; }
            .bfp-sidebar-fab { bottom: 18px; right: 12px; padding: 11px 14px; font-size: 12px; }
            .bfp-back-link { padding: 6px 10px; font-size: 12px; }
        }
        .brgy-posts-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 22px 8px;
            border-bottom: none;
        }
        .brgy-posts-header h2 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 800;
            color: #022a54;
            margin: 0;
        }
        .brgy-posts-header h2 svg {
            width: 20px;
            height: 20px;
            color: #0450a8;
        }
        .brgy-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #eff6ff; border: 1px solid #dbeafe;
            color: #0450a8; font-size: 11px; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase;
            padding: 4px 12px; border-radius: 999px; margin-bottom: 6px;
        }
        .brgy-stat-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 14px;
            width: 100%;
        }
        .brgy-stat-item {
            text-align: center;
            min-width: 0;
            padding: 10px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .brgy-stat-item strong { display: block; font-size: 18px; font-weight: 800; color: #0450a8; line-height: 1.2; }
        .brgy-stat-item span { font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; }
        .officer-list { display: flex; flex-direction: column; gap: 0; }
        .officer-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 0; border-bottom: 1px solid #f0f0f0;
        }
        .officer-item:last-child { border-bottom: none; }
        .officer-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800; color: #fff; flex-shrink: 0;
            overflow: hidden;
        }
        .officer-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .post-avatar-lg {
            width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 800; color: #fff;
        }
        .post-avatar-lg img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .post-image-wrap img {
            width: 100%; max-height: 320px; object-fit: cover;
            border-radius: 10px; margin-top: 12px;
        }
        .brgy-empty-state {
            text-align: center; padding: 28px 16px; color: #94a3b8; font-size: 13px;
        }
        .officer-details { flex: 1; }
        .officer-name  { font-size: 14px; font-weight: 700; color: #1a1d25; }
        .officer-role  { font-size: 12px; color: #999; margin-top: 1px; }
        .councilor-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px;
        }
        .councilor-chip {
            display: flex; align-items: center; gap: 8px;
            background: #f5f7fa; border-radius: 10px; padding: 8px 10px;
        }
        .councilor-dot {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .councilor-name { font-size: 11px; font-weight: 600; color: #444; }
        .contact-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px;
        }
        .contact-row:last-child { border-bottom: none; }
        .contact-icon {
            width: 34px; height: 34px; border-radius: 10px;
            background: #f0f4ff; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .contact-icon svg { width: 16px; height: 16px; color: #667eea; }
        .contact-label { font-size: 11px; color: #999; }
        .contact-value { font-size: 13px; font-weight: 600; color: #333; }
    </style>
</head>
<body class="youth-profile brgy-stalk-page">
    {{-- KABATAAN HEADER (consistent across all pages) --}}
    @include('layout::kabataan-header')

    {{-- MAIN --}}
    <main class="profile-main">
        <div class="profile-container bfp-wrap">

            <a href="{{ route('dashboard') }}" class="bfp-back-link">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                <span>Back to Community Feed</span>
            </a>

            <div class="bfp-grid">
                <div class="bfp-main">
                    <div class="info-card brgy-feed-wrap{{ ($canEngage ?? false) ? '' : ' is-view-only' }}">
                        <div class="card-header brgy-posts-header">
                            <h2>
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd"/><path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z"/></svg>
                                Posts from Barangay {{ $name }}
                            </h2>
                        </div>
                        <div class="card-body brgy-feed-body">
                            @unless ($canEngage ?? false)
                                <p class="brgy-view-only-note">You are viewing another barangay. You can read posts and comments, but you cannot like, comment, or reply.</p>
                            @endunless
                            <div class="feed-filter-bar" id="feedTabBar" role="tablist" aria-label="Filter posts">
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
                            <div id="brgyFeed" class="brgy-feed-list">
                                @forelse ($posts as $post)
                                    @include('dashboard::partials.barangay-feed-post', ['post' => $post, 'canEngage' => $canEngage ?? false])
                                @empty
                                    <div class="brgy-feed-empty">No posts yet from this barangay.</div>
                                @endforelse
                                <div class="brgy-feed-empty" id="brgyFeedEmptyFilter" hidden>No posts in this category.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="bfp-sidebar" id="bfpSidebar">
                    <button type="button" class="bfp-sidebar-close" id="bfpSidebarClose" aria-label="Close barangay profile">&times;</button>

                    <div class="bfp-profile-card">
                        <div class="bfp-profile-head">
                            <div class="brgy-logo{{ empty($logo_url) ? '' : ' has-logo' }}" @if(empty($logo_url)) style="background:{{ $color }};" @endif>
                                @if(!empty($logo_url))
                                    <img src="{{ $logo_url }}" alt="Barangay {{ $name }} logo" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                    <span class="brgy-logo-fallback" hidden>{{ $initials ?? strtoupper(substr($name, 0, 2)) }}</span>
                                @else
                                    <span class="brgy-logo-fallback">{{ $initials ?? strtoupper(substr($name, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div class="bfp-profile-copy">
                                <h1 class="bfp-name">SK Barangay {{ $name }}</h1>
                                <p class="bfp-loc">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                                    {{ $location ?? ('Barangay ' . $name . ', Santa Cruz, Laguna') }}
                                </p>
                            </div>
                        </div>
                        <div class="bfp-stats">
                            <div class="bfp-stat"><strong>{{ $post_count ?? 0 }}</strong><span>Posts</span></div>
                            <div class="bfp-stat"><strong>{{ $officer_count ?? 0 }}</strong><span>Officers</span></div>
                            <div class="bfp-stat bfp-stat--term"><strong>{{ $term_label ?? '—' }}</strong><span>SK Term</span></div>
                        </div>
                    </div>

                    <div class="info-card brgy-officers-card">
                        <div class="card-header">
                            <h2>
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                                SK Officers
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="officer-list">
                            @forelse ($officials as $official)
                            <div class="officer-item">
                                <div class="officer-avatar{{ empty($official['logo_url']) ? '' : ' has-logo' }}" @if(empty($official['logo_url'])) style="background:{{ $color }};" @endif>
                                    @if(!empty($official['logo_url']))
                                        <img src="{{ $official['logo_url'] }}" alt="{{ $official['name'] }}" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                        <span hidden>{{ $official['initials'] }}</span>
                                    @else
                                        {{ $official['initials'] }}
                                    @endif
                                </div>
                                <div class="officer-details">
                                    <p class="officer-name">{{ $official['name'] }}</p>
                                    <p class="officer-role">{{ $official['role'] }}</p>
                                </div>
                            </div>
                            @empty
                            <div class="brgy-empty-state">No SK officials found for this barangay.</div>
                            @endforelse
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="bfp-sidebar-backdrop" id="bfpSidebarBackdrop"></div>
            <button type="button" class="bfp-sidebar-fab" id="bfpSidebarFab" aria-label="View barangay profile and officers">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                <span>Barangay Info</span>
            </button>
        </div>
    </main>

    @include('dashboard::comment-preview')

    <script>
    window.CommunityFeedConfig = {
        commentsPageUrl: @json(url('/barangay/'.$slug.'/__ID__')),
        feedPollMs: 5000,
    };
    window.CommentPreviewConfig = {
        post: @json($commentPreviewPost ?? null),
        defaultLogo: @json(asset('images/SK_OnePortal_logo.png')),
        userAvatar: @json($userAvatarUrl ?? ''),
        userDisplayName: @json($user->name ?? 'Kabataan'),
        feedUrl: @json(url('/barangay/'.$slug)),
        viewOnly: @json(! ($canEngage ?? false)),
        syncUrl: true,
    };
    window.BarangayProfileConfig = {
        canEngage: @json((bool) ($canEngage ?? false)),
        barangayId: @json((int) ($barangayId ?? 0)),
        posts: @json($posts ?? []),
    };
    </script>
    @vite([
        'app/Modules/Dashboard/assets/js/community-feed-comment-preview.js',
        'app/Modules/Dashboard/assets/js/barangay-profile.js',
    ])
    <script>
    window.addEventListener('unload', function () {});
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { window.location.replace(window.location.href); }
    });
    </script>
</body>
</html>
