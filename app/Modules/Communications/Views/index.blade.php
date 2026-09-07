<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Messages — SK OnePortal Kabataan</title>
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
        'app/Modules/Layout/assets/js/kabataan-session-timeout.js',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Communications/assets/css/communication.css',
        'app/Modules/Layout/assets/css/kabataan-messages.css',
        'app/Modules/Layout/assets/css/kabataan-call.css',
    ])
</head>
<body>
@include('layout::kabataan-header')

<main class="container-fluid py-3">
@php
    $commsRoutes = [
        'conversations' => route('api.communications.conversations.index'),
        'storeConversation' => route('api.communications.conversations.store'),
        'messages' => url('/api/communications/conversations/__ID__/messages'),
        'read' => url('/api/communications/conversations/__ID__/read'),
        'showConversation' => url('/api/communications/conversations/__ID__'),
        'searchUsers' => route('api.communications.users.search'),
        'unreadCount' => route('api.communications.unread-count'),
        'react' => url('/api/communications/messages/__ID__/reactions'),
        'updateMessage' => url('/api/communications/messages/__ID__'),
        'deleteMessage' => url('/api/communications/messages/__ID__'),
        'startCall' => url('/api/communications/conversations/__ID__/calls'),
        'callStatus' => url('/api/communications/calls/__ID__/status'),
        'calls' => route('api.communications.calls.index'),
        'presence' => route('api.communications.presence'),
        'callHistoryPage' => route('communications.calls'),
    ];
@endphp
<div
    class="comms-app"
    id="commsApp"
    data-current-user-id="{{ $currentUserId }}"
    data-portal-user-type="{{ $portalUserType }}"
    data-initial-conversation="{{ $initialConversationId ?? '' }}"
    data-routes='@json($commsRoutes)'
>
    <div class="comms-shell">
        <aside class="comms-sidebar" id="commsSidebar">
            <div class="comms-sidebar-header">
                <div class="comms-sidebar-title-row">
                    <h1>Messages</h1>
                    <a href="{{ route('communications.calls') }}" class="comms-link-btn" title="Call history">Call history</a>
                </div>
                <label class="visually-hidden" for="commsUserSearch">Search people</label>
                <div class="comms-search-wrap">
                    <input type="search" id="commsUserSearch" class="comms-search-input" placeholder="Search SK Officials in your barangay" autocomplete="off">
                    <div class="comms-search-results" id="commsSearchResults" hidden></div>
                </div>
                <label class="visually-hidden" for="commsConvFilter">Filter conversations</label>
                <input type="search" id="commsConvFilter" class="comms-search-input comms-search-input--filter" placeholder="Filter conversations" autocomplete="off">
            </div>
            <div class="comms-conv-list" id="commsConvList" role="list"></div>
            <div class="comms-empty" id="commsConvEmpty" hidden>
                <p>No SK Officials found in your barangay yet.</p>
            </div>
        </aside>

        <section class="comms-thread" id="commsThread" aria-live="polite">
            <div class="comms-thread-empty" id="commsThreadEmpty">
                <h2>Your messages</h2>
                <p>Select an SK Official from your barangay to start chatting.</p>
            </div>

            <div class="comms-thread-active" id="commsThreadActive" hidden>
                <header class="comms-thread-header">
                    <button type="button" class="comms-back-btn" id="commsBackBtn" aria-label="Back to conversations">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <img src="" alt="" class="comms-avatar" id="commsPeerAvatar" width="40" height="40">
                    <div class="comms-thread-peer">
                        <div class="comms-peer-name" id="commsPeerName"></div>
                        <div class="comms-peer-meta" id="commsPeerMeta"></div>
                    </div>
                    <div class="comms-thread-actions">
                        <button type="button" class="comms-icon-btn comms-call-action-btn" id="commsVoiceBtn" aria-label="Voice call">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>
                            <span class="comms-call-action-label">Voice call</span>
                        </button>
                        <button type="button" class="comms-icon-btn comms-call-action-btn" id="commsVideoBtn" aria-label="Video call">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                            <span class="comms-call-action-label">Video call</span>
                        </button>
                    </div>
                </header>

                <div class="comms-messages" id="commsMessages" role="log"></div>
                <div class="comms-typing" id="commsTyping" hidden>Typing...</div>

                <form class="comms-composer" id="commsComposer" autocomplete="off">
                    <div class="comms-composer-tools">
                        <input type="file" id="commsAttachInput" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" aria-hidden="true" tabindex="-1">
                        <button type="button" class="comms-attach-btn" id="commsAttachBtn" title="Attach image or file" aria-label="Attach image or file">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 0 1-7.78-7.78l8.49-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.49a1.5 1.5 0 1 1-2.12-2.12l7.78-7.78"/>
                            </svg>
                        </button>
                    </div>
                    <div class="comms-composer-main">
                        <div class="comms-attach-preview" id="commsAttachPreview" hidden>
                            <div class="comms-attach-preview-inner" id="commsAttachPreviewInner"></div>
                            <button type="button" class="comms-attach-clear" id="commsAttachClear" aria-label="Remove attachment">&times;</button>
                        </div>
                        <div class="comms-upload-progress" id="commsUploadProgress" hidden>
                            <div class="comms-upload-progress-bar" id="commsUploadProgressBar"></div>
                        </div>
                        <label class="visually-hidden" for="commsMessageInput">Message</label>
                        <textarea id="commsMessageInput" rows="1" maxlength="5000" placeholder="Write a message"></textarea>
                    </div>
                    <button type="submit" class="comms-send-btn" id="commsSendBtn">Send</button>
                </form>
            </div>
        </section>
    </div>
</div>
</main>

@vite([
    'app/Modules/Communications/assets/js/chat.js',
])
</body>
</html>
