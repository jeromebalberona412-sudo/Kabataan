@php
    $headerUser = $user ?? auth()->user();
    $userName = \Illuminate\Support\Str::limit($headerUser->name ?? 'Youth User', 50, '...');
    $userEmail = $headerUser->email ?? 'youth@skportal.com';
    $avatarUrl = $headerUser
        ? app(\App\Modules\Profile\Services\ProfileImageService::class)->resolveDisplayUrl($headerUser)
        : 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=667eea&color=fff';
    $pageBadge = $pageBadge ?? null;
    $kabataanViewOnly = $kabataanViewOnly ?? false;
@endphp

<nav class="kabataan-header{{ $kabataanViewOnly ? ' kabataan-header--view-only' : '' }}" id="kabataanHeader" aria-label="Main navigation">
    <div class="kabataan-header__container">
        <a href="{{ route('dashboard') }}" class="kabataan-header__brand">
            <img src="{{ asset('images/skoneportal_logo.webp') }}" alt="SK OnePortal" class="kabataan-header__logo">
            <span class="kabataan-header__title">
                Kabataan
                <small>SK OnePortal Santa Cruz</small>
            </span>
        </a>

        @if ($pageBadge)
            <span class="kabataan-header__page-badge">{{ $pageBadge }}</span>
        @endif

        @if (session('kabataan_toast'))
            <div class="kabataan-header__center-toast" id="kabataanHeaderToast" role="status" aria-live="polite">
                <span class="kabataan-header__center-toast-title">{{ session('kabataan_toast.title', 'Congratulations!') }}</span>
                <span class="kabataan-header__center-toast-text">{{ session('kabataan_toast.message') }}</span>
            </div>
            <script>document.body.classList.add('kabataan-has-header-toast');</script>
        @endif

        <div class="kabataan-header__actions">
            <button type="button" class="kabataan-header__icon-btn programs-drawer-btn" id="programsDrawerBtn" data-tour="programs-menu" title="Programs & Barangay Profiles" aria-label="Programs and Barangay Profiles" aria-haspopup="true" aria-expanded="false">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3z"/><path d="M3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>
            </button>

            <a href="{{ route('dashboard') }}" class="kabataan-header__icon-btn" data-tour="home" title="Home" aria-label="Home">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
            </a>

            @auth
            <div class="kabataan-header__tutorial-menu" id="tutorialGuideHeaderMenu">
                <button
                    type="button"
                    class="kabataan-header__icon-btn tutorial-header-btn"
                    id="tutorialGuideBtn"
                    data-tour="tutorial-guide"
                    aria-label="Tutorial Guide"
                    title="Tutorial Guide"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </button>
            </div>
            @endauth

            @php
                $unreadMsgCount = (int) ($unreadMessagesCount ?? 0);
                $unreadMsgLabel = $unreadMsgCount > 99 ? '99+' : (string) $unreadMsgCount;
            @endphp
            <div class="comms-header-msg notif-menu" id="commsMsgMenu" data-tour="messages">
                <button
                    type="button"
                    class="comms-header-msg-btn"
                    id="commsMsgBtn"
                    data-tour="messages"
                    title="Messages"
                    aria-label="Messages"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="commsMsgPopover"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span
                        class="comms-header-msg-badge"
                        id="commsMsgBadge"
                        data-unread-total="{{ $unreadMsgCount }}"
                        style="{{ $unreadMsgCount > 0 ? '' : 'display: none;' }}"
                    >{{ $unreadMsgLabel }}</span>
                </button>
                @include('layout::messages-dropdown')
            </div>

            @include('dashboard::notification')

            <div class="kabataan-header__user" id="kabataanHeaderUser" data-tour="account-menu">
                <button type="button" class="kabataan-header__avatar-btn user-avatar-btn" data-tour="account-menu" aria-expanded="false" aria-haspopup="true" aria-label="Account menu">
                    <img src="{{ $avatarUrl }}" alt="{{ $userName }}">
                    <span class="kabataan-header__caret kabataan-header__caret--profile" aria-hidden="true"></span>
                </button>
                <div class="kabataan-header__dropdown user-dropdown">
                    <div class="kabataan-header__dropdown-user-card">
                        <img src="{{ $avatarUrl }}" alt="{{ $userName }}" class="kabataan-header__dropdown-avatar">
                        <div class="kabataan-header__dropdown-user-info">
                            <span class="kabataan-header__dropdown-name">{{ $userName }}</span>
                            <span class="kabataan-header__dropdown-role">{{ $userEmail }}</span>
                        </div>
                    </div>

                    <div class="dropdown-divider"></div>

                    <a href="{{ route('profile') }}" class="kabataan-header__dropdown-link dropdown-item">
                        <span class="kabataan-header__dropdown-icon kabataan-header__dropdown-icon--profile">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                        </span>
                        View Profile
                    </a>

                    <a href="{{ route('profile') }}#account-settings" class="kabataan-header__dropdown-link dropdown-item">
                        <span class="kabataan-header__dropdown-icon kabataan-header__dropdown-icon--settings">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </span>
                        Account Settings
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="kabataan-header__dropdown-link dropdown-item kabataan-header__dropdown-link--logout logout-btn">
                            <span class="kabataan-header__dropdown-icon kabataan-header__dropdown-icon--logout">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </span>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @if ($kabataanViewOnly)
        <div class="kabataan-view-only-banner" role="status">
            {{ \App\Services\KabataanEligibilityService::VIEW_ONLY_MESSAGE }}
        </div>
    @endif
</nav>

@if ($kabataanViewOnly)
    <script>document.body.classList.add('kabataan-has-view-only-banner');</script>
@endif

@include('layout::kabataan-logout-modal')
@include('layout::kabataan-session-timeout')
@include('kkprofiling::partials.kk-profiling-update-mandatory-modal')

@auth
@include('communications::partials.chat-modal')
@include('communications::partials.incoming-call-modal')
@include('communications::partials.in-call-ui')
<div
    id="commsRealtimeBoot"
    hidden
    data-current-user-id="{{ auth()->id() }}"
    data-portal-user-type="{{ config('communications.portal_user_type', 'kabataan') }}"
    data-current-user-name="{{ auth()->user()->name ?? '' }}"
    data-current-user-avatar="{{ $avatarUrl }}"
    data-unread-count-url="{{ \App\Support\MailUrl::uri('/api/communications/unread-count') }}"
></div>
<script>
    window.CommsChat = window.CommsChat || {
        routes: {
            conversations: @json(\App\Support\MailUrl::uri('/api/communications/conversations')),
            storeConversation: @json(\App\Support\MailUrl::uri('/api/communications/conversations')),
            messages: @json(\App\Support\MailUrl::uri('/api/communications/conversations/__ID__/messages')),
            react: @json(\App\Support\MailUrl::uri('/api/communications/messages/__ID__/reactions')),
            updateMessage: @json(\App\Support\MailUrl::uri('/api/communications/messages/__ID__')),
            deleteMessage: @json(\App\Support\MailUrl::uri('/api/communications/messages/__ID__')),
            read: @json(\App\Support\MailUrl::uri('/api/communications/conversations/__ID__/read')),
            showConversation: @json(\App\Support\MailUrl::uri('/api/communications/conversations/__ID__')),
            searchUsers: @json(\App\Support\MailUrl::uri('/api/communications/users/search')),
            unreadCount: @json(\App\Support\MailUrl::uri('/api/communications/unread-count')),
            startCall: @json(\App\Support\MailUrl::uri('/api/communications/conversations/__ID__/calls')),
            callStatus: @json(\App\Support\MailUrl::uri('/api/communications/calls/__ID__/status')),
            calls: @json(\App\Support\MailUrl::uri('/api/communications/calls')),
            presence: @json(\App\Support\MailUrl::uri('/api/communications/presence')),
            faqSuggestions: @json(\App\Support\MailUrl::uri('/api/communications/conversations/__ID__/faq-suggestions'))
        },
        getActiveId: function () {
            return (window.__COMMS_HEADER_ACTIVE_ID__ != null) ? window.__COMMS_HEADER_ACTIVE_ID__ : null;
        },
        reloadActiveMessages: function () {
            if (typeof window.reloadHeaderChatMessages === 'function') {
                window.reloadHeaderChatMessages();
            }
        },
        reloadConversations: function () {
            if (typeof window.refreshMessagesPopover === 'function') {
                window.refreshMessagesPopover();
            }
        },
        onReactionChange: function () {},
        appendMessage: function (message) {
            if (typeof window.onCommsHeaderMessageInsert === 'function') {
                window.onCommsHeaderMessageInsert(message);
            }
        },
        setTyping: function (visible, meta) {
            var el = document.getElementById('commsChatModalTyping');
            if (!el) return;
            if (window.Comms && typeof window.Comms.paintTypingEl === 'function') {
                window.Comms.paintTypingEl(el, !!visible, meta || null);
                return;
            }
            if (!visible) {
                el.hidden = true;
                el.textContent = '';
                return;
            }
            var who = meta && meta.name ? String(meta.name).trim() : '';
            el.textContent = who ? (who + ' is typing...') : 'Typing...';
            el.hidden = false;
        }
    };
    window.CommsChat.messageMaxLength = {{ (int) config('communications.message_max_length', 1000) }};
    window.__COMMS_HEADER_OFFICIALS__ = @json($headerOfficials ?? []);
    window.__COMMS_HEADER_CONVERSATIONS__ = @json($headerConversations ?? []);
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@include('tutorial_guide::tutorial')
@vite([
    'app/Modules/Tutorial_Guide/assets/css/tutorial-guide.css',
    'app/Modules/Tutorial_Guide/assets/js/tutorial-guide.js',
])
@endauth
