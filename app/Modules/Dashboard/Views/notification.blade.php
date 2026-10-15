{{-- Notification Popover — included in Kabataan header --}}
@php
    $unreadNotifCount = (int) ($unreadNotificationCount ?? 0);
    $unreadNotifLabel = $unreadNotifCount > 99 ? '99+' : (string) $unreadNotifCount;
@endphp
<div class="notif-nav-wrapper" data-tour="notifications">
    <button class="nav-icon-btn notif-nav-btn" id="notifNavBtn" data-tour="notifications" title="Notifications" aria-label="Notifications" aria-expanded="false" onclick="toggleNotifPopover(event)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="notif-nav-badge" id="notifNavBadge" data-unread-total="{{ $unreadNotifCount }}" @if($unreadNotifCount <= 0) hidden @endif>{{ $unreadNotifLabel }}</span>
    </button>

    <div class="notif-popover" id="notifPopover" role="dialog" aria-label="Notifications">
        <div class="np-inner">
            <div class="np-header">
                <div class="np-header-left">
                    <span class="np-title">Notifications</span>
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
                                <span class="np-item-title">{{ \Illuminate\Support\Str::limit((string) ($notification['title'] ?? ''), 48, '...') }}</span>
                                <span class="np-item-text">{{ \Illuminate\Support\Str::limit((string) ($notification['text'] ?? ''), 64, '...') }}</span>
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
