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
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Communications/assets/css/communication.css',
        'app/Modules/Layout/assets/css/kabataan-messages.css',
        'app/Modules/Layout/assets/css/kabataan-call.css',
        'app/Modules/Communications/assets/js/communication.js',
        'app/Modules/Communications/assets/js/chat.js',
    ])
</head>
<body class="comms-messages-page">
@include('layout::kabataan-header')

<main class="container-fluid comms-page-main">
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
        'faqSuggestions' => url('/api/communications/conversations/__ID__/faq-suggestions'),
    ];
@endphp
<div
    class="comms-app"
    id="commsApp"
    data-current-user-id="{{ $currentUserId }}"
    data-portal-user-type="{{ $portalUserType }}"
    data-current-user-name="{{ auth()->user()->name ?? '' }}"
    data-current-user-avatar="{{ $currentUserAvatar ?? '' }}"
    data-initial-conversation="{{ $initialConversationId ?? '' }}"
    data-message-max-length="{{ (int) config('communications.message_max_length', 1000) }}"
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

                <div class="comms-messages-wrap">
                    <div class="comms-messages" id="commsMessages" role="log"></div>
                    <button type="button" class="comms-scroll-bottom" id="commsScrollBottom" hidden title="Jump to latest" aria-label="Jump to latest messages">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </div>
                <div class="comms-typing" id="commsTyping" hidden aria-live="polite"></div>

                <form class="comms-composer" id="commsComposer" autocomplete="off">
                    <input type="file" id="commsPhotoInput" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple aria-hidden="true" tabindex="-1">
                    <input type="file" id="commsFileInput" class="visually-hidden" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple aria-hidden="true" tabindex="-1">
                    <div class="comms-composer-tools">
                        <div class="comms-attach-wrap">
                            <button type="button" class="comms-attach-btn" id="commsAttachBtn" title="Maximum 25 MB" aria-label="Attach. Maximum 25 MB" aria-expanded="false" aria-controls="commsAttachMenu">
                                <span aria-hidden="true">+</span>
                            </button>
                            <div class="comms-attach-menu" id="commsAttachMenu" hidden>
                                <button type="button" class="comms-attach-menu-item" id="commsPickPhotos" title="Maximum 25 MB">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> Photos
                                </button>
                                <button type="button" class="comms-attach-menu-item" id="commsPickFiles" title="Maximum 25 MB">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h6"/></svg> Files
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="comms-composer-main">
                        <div class="comms-attach-preview" id="commsAttachPreview" hidden>
                            <div class="comms-attach-preview-inner" id="commsAttachPreviewInner"></div>
                            <button type="button" class="comms-attach-clear" id="commsAttachClear" aria-label="Remove attachments">&times;</button>
                        </div>
                        <div class="comms-upload-progress" id="commsUploadProgress" hidden>
                            <div class="comms-upload-progress-bar" id="commsUploadProgressBar"></div>
                        </div>
                        <label class="visually-hidden" for="commsMessageInput">Message</label>
                        <textarea id="commsMessageInput" rows="1" placeholder="Type a message"></textarea>
                    </div>
                    <div class="comms-composer-end">
                        <div class="comms-faq-menu" id="commsFaqMenu" hidden>
                            <button
                                type="button"
                                class="comms-faq-menu-toggle"
                                id="commsFaqMenuToggle"
                                aria-expanded="false"
                                aria-controls="commsFaqSuggestions"
                                title="Suggested questions"
                                aria-label="Suggested questions"
                            >
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <line x1="4" y1="7" x2="20" y2="7"/>
                                    <line x1="4" y1="12" x2="20" y2="12"/>
                                    <line x1="4" y1="17" x2="20" y2="17"/>
                                </svg>
                            </button>
                            <div class="comms-faq-suggestions" id="commsFaqSuggestions" hidden>
                                <div class="comms-faq-suggestions-list" id="commsFaqSuggestionsList" role="list"></div>
                            </div>
                        </div>
                        <button type="submit" class="comms-send-btn" id="commsSendBtn" title="Send" aria-label="Send message" hidden>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
</main>

</body>
</html>
