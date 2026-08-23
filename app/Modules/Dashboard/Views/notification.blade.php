{{-- Notification Popover — included in Kabataan header --}}
@php
    $unreadNotifCount = (int) ($unreadNotificationCount ?? 0);
    $unreadNotifLabel = $unreadNotifCount > 99 ? '99+' : (string) $unreadNotifCount;
@endphp
<div class="notif-nav-wrapper">
    <button class="nav-icon-btn notif-nav-btn" id="notifNavBtn" title="Notifications" aria-label="Notifications" aria-expanded="false" onclick="toggleNotifPopover(event)">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
        </svg>
        <span class="notif-nav-badge" id="notifNavBadge" data-unread-total="{{ $unreadNotifCount }}" @if($unreadNotifCount <= 0) hidden @endif>{{ $unreadNotifLabel }}</span>
    </button>

    <div class="notif-popover" id="notifPopover" role="dialog" aria-label="Notifications">
        <div class="np-inner">
            <div class="np-header">
                <div class="np-header-left">
                    <span class="np-title">Notifications</span>
                    <span class="np-count-pill" id="notifCountPill" data-unread-total="{{ $unreadNotifCount }}" @if($unreadNotifCount <= 0) hidden @endif>{{ $unreadNotifLabel }}</span>
                </div>
                <div class="np-header-actions">
                    <button type="button" class="np-mark-all-btn" id="notifMarkAllBtn" title="Mark all as read">Mark all read</button>
                </div>
            </div>
            <div class="np-body">
                <div class="np-list" id="notifList" @if(empty($headerNotifications ?? [])) hidden @endif>
                    @foreach(($headerNotifications ?? []) as $notification)
                        <button
                            type="button"
                            class="np-item {{ ($notification['unread'] ?? false) ? 'np-unread' : '' }}"
                            data-id="{{ $notification['id'] }}"
                            data-action-url="{{ $notification['action_url'] ?? '' }}"
                        >
                            <span class="np-item-body">
                                <span class="np-item-title">{{ $notification['title'] }}</span>
                                <span class="np-item-text">{{ $notification['text'] }}</span>
                                <span class="np-item-time">{{ $notification['time'] }}</span>
                            </span>
                            @if($notification['unread'] ?? false)
                                <span class="np-unread-dot" aria-hidden="true"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="np-empty" id="notifEmpty" @if(!empty($headerNotifications ?? [])) hidden @endif>
                    <svg width="72" height="72" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z" stroke="#cbd5e1" stroke-width="1.5" fill="none"/>
                        <path d="M10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" stroke="#cbd5e1" stroke-width="1.5" fill="none"/>
                    </svg>
                    <p class="np-empty-title">No Notifications</p>
                    <p class="np-empty-sub">You're all caught up! Check back later for updates from SK.</p>
                </div>
            </div>
            <div class="np-footer">
                <a href="{{ route('notifications') }}" class="np-see-all-btn">See All Notifications</a>
            </div>
        </div>
    </div>
</div>
