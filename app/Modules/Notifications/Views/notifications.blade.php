<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <title>Notifications — Kabataan</title>
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
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Notifications/assets/css/notifications.css',
        'app/Modules/Notifications/assets/js/notifications.js',
    ])
</head>
<body class="youth-notifications-page">
    @include('layout::kabataan-header', ['user' => auth()->user(), 'pageBadge' => 'Notifications'])
    @include('layout::programs-drawer', ['barangayName' => $barangayName ?? 'Your Barangay'])

    <main class="kb-notif-page">
        <div class="kb-notif-page__inner">
            <section class="kb-notif-hero">
                <div class="kb-notif-hero__copy">
                    <h1>Notifications</h1>
                    <p>Stay updated on your KK Profiling, programs, and SK announcements.</p>
                </div>
                <button type="button" class="kb-notif-page__mark-all" id="kbNotifMarkAllBtn">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Mark all as read
                </button>
            </section>

            @php
                $totalCount = count($notifications ?? []);
                $unreadTotal = (int) ($unreadCount ?? 0);
                $readTotal = max(0, $totalCount - $unreadTotal);
            @endphp

            <div class="kb-notif-stats">
                <article class="kb-notif-stat">
                    <span class="kb-notif-stat__value" id="kbNotifTotalCount">{{ $totalCount }}</span>
                    <span class="kb-notif-stat__label">Total</span>
                </article>
                <article class="kb-notif-stat kb-notif-stat--unread">
                    <span class="kb-notif-stat__value" id="kbNotifUnreadCount">{{ $unreadTotal }}</span>
                    <span class="kb-notif-stat__label">Unread</span>
                </article>
                <article class="kb-notif-stat kb-notif-stat--read">
                    <span class="kb-notif-stat__value" id="kbNotifReadCount">{{ $readTotal }}</span>
                    <span class="kb-notif-stat__label">Read</span>
                </article>
            </div>

            <section class="kb-notif-panel">
                <div class="kb-notif-panel__toolbar">
                    <div class="kb-notif-filters" role="tablist" aria-label="Filter notifications">
                        <button type="button" class="kb-notif-filter is-active" data-filter="all">All</button>
                        <button type="button" class="kb-notif-filter" data-filter="unread">Unread</button>
                        <button type="button" class="kb-notif-filter" data-filter="read">Read</button>
                    </div>
                </div>

                <div class="kb-notif-list" id="kbNotifPageList" @if($totalCount === 0) hidden @endif>
                    @foreach(($notifications ?? []) as $notification)
                        <button
                            type="button"
                            class="kb-notif-item {{ ($notification['unread'] ?? false) ? 'is-unread' : '' }}"
                            data-id="{{ $notification['id'] }}"
                            data-action-url="{{ $notification['action_url'] ?? '' }}"
                        >
                            <span class="kb-notif-item__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/>
                                    <path d="M9 17a3 3 0 006 0"/>
                                </svg>
                            </span>
                            <span class="kb-notif-item__body">
                                @if($notification['unread'] ?? false)
                                    <span class="kb-notif-item__dot" aria-hidden="true"></span>
                                @endif
                                <span class="kb-notif-item__title">{{ $notification['title'] }}</span>
                                <span class="kb-notif-item__text">{{ $notification['text'] }}</span>
                                <span class="kb-notif-item__time">{{ $notification['time'] }}</span>
                            </span>
                            <span class="kb-notif-item__chevron" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                    @endforeach
                </div>

                <div class="kb-notif-empty" id="kbNotifPageEmpty" @if($totalCount > 0) hidden @endif>
                    <div class="kb-notif-empty__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/>
                            <path d="M9 17a3 3 0 006 0"/>
                        </svg>
                    </div>
                    <p class="kb-notif-empty__title">No notifications yet</p>
                    <p class="kb-notif-empty__sub">You're all caught up. Check back later for updates from your SK.</p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
