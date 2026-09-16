@php
    $msgItems = collect($headerConversations ?? [])->values();
    $officialItems = collect($headerOfficials ?? []);
    $chatPeerIds = $msgItems->map(fn ($c) => (int) data_get($c, 'other_user.id', 0))->filter()->all();
    $starterOfficials = $officialItems
        ->filter(fn ($u) => ($id = (int) ($u['id'] ?? 0)) && ! in_array($id, $chatPeerIds, true))
        ->values();
    $hasMsgItems = $msgItems->isNotEmpty() || $starterOfficials->isNotEmpty();
    $unreadMsgCount = (int) ($unreadMessagesCount ?? 0);
    $messagesIndexUrl = \Illuminate\Support\Facades\Route::has('communications.index')
        ? route('communications.index')
        : url('/communications');
@endphp

<div class="comms-msg-popover" id="commsMsgPopover" role="menu" aria-label="Messages">
    <div class="comms-msg-popover-header">
        <div class="comms-msg-popover-title">
            <span class="comms-msg-header-icon-btn comms-msg-header-icon-btn--title" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </span>
            <h4>Chats</h4>
        </div>
        <div class="comms-msg-header-actions">
            <a
                href="{{ $messagesIndexUrl }}"
                class="comms-msg-header-icon-btn comms-msg-see-all-link"
                data-no-loading
                title="Open Messages page"
                aria-label="Open Messages page"
            >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </a>
        </div>
    </div>

    <div class="comms-msg-search-wrap">
        <label class="comms-msg-search" for="commsMsgSearch">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input
                type="search"
                id="commsMsgSearch"
                class="comms-msg-search-input"
                placeholder="Search Messages"
                autocomplete="off"
                aria-label="Search chats"
            >
        </label>
    </div>

    <div class="comms-msg-filters" role="tablist" aria-label="Message filters">
        <button type="button" class="comms-msg-filter-chip is-active" data-filter="all" role="tab" aria-selected="true">All</button>
        <button type="button" class="comms-msg-filter-chip" data-filter="unread" role="tab" aria-selected="false">Unread</button>
    </div>

    <div class="comms-msg-list" id="commsMsgList" @if(! $hasMsgItems) style="display: none;" @endif>
        @foreach($msgItems as $conversation)
            @php
                $peer = $conversation['other_user'] ?? [];
                $preview = $conversation['last_message']['body'] ?? 'No messages yet';
                $unread = (int) ($conversation['unread_count'] ?? 0);
                $name = $peer['name'] ?? 'User';
                $avatar = $peer['profile_image_url'] ?? ('https://ui-avatars.com/api/?name='.urlencode((string) $name).'&background=2C2C3E&color=fff');
                $convId = (int) ($conversation['id'] ?? 0);
                $searchBlob = mb_strtolower(trim($name.' '.$preview));
            @endphp
            <button
                type="button"
                class="comms-msg-item {{ $unread > 0 ? 'comms-msg-unread' : '' }}"
                data-id="{{ $convId }}"
                data-name="{{ e($name) }}"
                data-avatar="{{ e((string) ($avatar ?? '')) }}"
                data-unread="{{ $unread > 0 ? '1' : '0' }}"
                data-search="{{ e($searchBlob) }}"
                role="menuitem"
            >
                <img class="comms-msg-avatar" src="{{ $avatar }}" alt="" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=U&background=2C2C3E&color=fff'">
                <div class="comms-msg-content">
                    <div class="comms-msg-item-top">
                        <span class="comms-msg-item-name">{{ \Illuminate\Support\Str::limit($name, 28) }}</span>
                        <span class="comms-msg-item-time">
                            @if(!empty($conversation['updated_at']))
                                {{ \Illuminate\Support\Carbon::parse($conversation['updated_at'])->diffForHumans(null, true) }}
                            @endif
                        </span>
                    </div>
                    <div class="comms-msg-item-preview">{{ \Illuminate\Support\Str::limit((string) $preview, 42) }}</div>
                </div>
                @if($unread > 0)
                    <span class="comms-msg-unread-dot" aria-hidden="true"></span>
                @endif
            </button>
        @endforeach
        @if($starterOfficials->isNotEmpty())
            <div class="comms-msg-section-label" role="presentation">SK Officials</div>
        @endif
        @foreach($starterOfficials as $official)
            @php
                $name = $official['name'] ?? 'SK Official';
                $preview = $official['position'] ?? ($official['user_type_label'] ?? 'SK Official');
                $avatar = $official['profile_image_url'] ?? ('https://ui-avatars.com/api/?name='.urlencode((string) $name).'&background=0450A8&color=fff');
                $searchBlob = mb_strtolower(trim($name.' '.$preview));
            @endphp
            <button
                type="button"
                class="comms-msg-item"
                data-user-id="{{ (int) ($official['id'] ?? 0) }}"
                data-name="{{ e($name) }}"
                data-avatar="{{ e((string) $avatar) }}"
                data-unread="0"
                data-search="{{ e($searchBlob) }}"
                role="menuitem"
            >
                <img class="comms-msg-avatar" src="{{ $avatar }}" alt="" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=U&background=0450A8&color=fff'">
                <div class="comms-msg-content">
                    <div class="comms-msg-item-top">
                        <span class="comms-msg-item-name">{{ \Illuminate\Support\Str::limit($name, 28) }}</span>
                    </div>
                    <div class="comms-msg-item-preview">{{ \Illuminate\Support\Str::limit((string) $preview, 42) }}</div>
                </div>
            </button>
        @endforeach
    </div>

    <div class="comms-msg-empty" id="commsMsgEmpty" @if($hasMsgItems) style="display: none;" @endif>
        <p>No messages yet</p>
        <p class="comms-msg-empty-sub">SK Officials from your barangay will appear here.</p>
    </div>

    <div class="comms-msg-popover-footer">
        <a href="{{ $messagesIndexUrl }}" class="comms-msg-see-all-btn comms-msg-see-all-link" data-no-loading>
            See All in Messages
        </a>
    </div>
</div>
