/**
 * Reusable Kabataan header — user menu, overlay exclusivity, logout modal & mobile helpers
 */
import '../css/kabataan-header-messages.css';
import '../css/chat-modal.css';
import '../css/kabataan-messages.css';
import '../css/kabataan-call.css';
import './chat-modal.js';

(function () {
    'use strict';

    function forceEmailLowercase(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const apply = () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const lower = input.value.toLowerCase();
            if (input.value !== lower) {
                input.value = lower;
                if (typeof start === 'number' && typeof end === 'number' && document.activeElement === input) {
                    input.setSelectionRange(start, end);
                }
            }
        };

        input.addEventListener('input', apply);
        input.addEventListener('blur', () => {
            input.value = input.value.trim().toLowerCase();
        });
        apply();
    }

    function bindEmailLowercase(root) {
        const scope = root || document;
        scope.querySelectorAll(
            'input[type="email"], input[name="email"], input[name="new_email"], input[name="current_email"], input[name="pending_email"]'
        ).forEach(forceEmailLowercase);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bindEmailLowercase(document));
    } else {
        bindEmailLowercase(document);
    }

    window.kabataanBindEmailLowercase = bindEmailLowercase;

    const userWrap = document.getElementById('kabataanHeaderUser');
    const avatarBtn = userWrap?.querySelector('.kabataan-header__avatar-btn');

    function closeProfileMenu() {
        if (!userWrap) {
            return;
        }

        userWrap.classList.remove('is-open');
        avatarBtn?.setAttribute('aria-expanded', 'false');
    }

    function closeMessagesPopover() {
        const pop = document.getElementById('commsMsgPopover');
        const btn = document.getElementById('commsMsgBtn');
        const onMessagesPage = !!document.body.classList.contains('comms-messages-page')
            || /^\/communications\/?$/.test(window.location.pathname || '');
        if (pop) {
            pop.classList.remove('show');
        }
        if (btn && !onMessagesPage) {
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    function closeHeaderOverlays(except) {
        if (except !== 'profile') {
            closeProfileMenu();
        }

        if (except !== 'messages') {
            closeMessagesPopover();
        }

        if (except !== 'notif' && typeof window.closeNotifPopover === 'function') {
            window.closeNotifPopover();
        }
    }

    window.kabataanCloseProfileMenu = closeProfileMenu;
    window.kabataanCloseHeaderOverlays = closeHeaderOverlays;
    window.closeMessagesPopover = closeMessagesPopover;

    if (avatarBtn && userWrap) {
        avatarBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const willOpen = !userWrap.classList.contains('is-open');
            if (willOpen) {
                closeHeaderOverlays('profile');
                userWrap.classList.add('is-open');
                avatarBtn.setAttribute('aria-expanded', 'true');
            } else {
                closeProfileMenu();
            }
        });

        document.addEventListener('click', function (e) {
            if (userWrap.classList.contains('is-open') && !userWrap.contains(e.target)) {
                closeProfileMenu();
            }
        });
    }

    const menuToggle = document.getElementById('kabataanHeaderMenuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            document.body.classList.toggle('kabataan-mobile-nav-open');
        });
    }

    const drawerBtn = document.getElementById('programsDrawerBtn');
    const drawerSidebar = document.getElementById('programsDrawerSidebar');
    const drawerBackdrop = document.getElementById('programsDrawerBackdrop');

    function closeProgramsDrawer() {
        drawerSidebar?.classList.remove('drawer-open');
        drawerBackdrop?.classList.remove('drawer-open', 'active');
        drawerBackdrop?.setAttribute('aria-hidden', 'true');
        drawerBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function openProgramsDrawer() {
        closeHeaderOverlays();
        drawerSidebar?.classList.add('drawer-open');
        drawerBackdrop?.classList.add('drawer-open', 'active');
        drawerBackdrop?.setAttribute('aria-hidden', 'false');
        drawerBtn?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    window.kabataanCloseProgramsDrawer = closeProgramsDrawer;
    window.kabataanOpenProgramsDrawer = openProgramsDrawer;

    if (drawerBtn && drawerSidebar) {
        drawerBtn.addEventListener('click', function () {
            if (drawerSidebar.classList.contains('drawer-open')) {
                closeProgramsDrawer();
            } else {
                openProgramsDrawer();
            }
        });
    }

    drawerBackdrop?.addEventListener('click', closeProgramsDrawer);
    document.querySelectorAll('[data-programs-drawer-close]').forEach(function (btn) {
        btn.addEventListener('click', closeProgramsDrawer);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }

        if (drawerSidebar?.classList.contains('drawer-open')) {
            closeProgramsDrawer();
            return;
        }

        closeHeaderOverlays();
    });
})();

(function () {
    'use strict';

    const badge = document.getElementById('commsMsgBadge');
    const msgBtn = document.getElementById('commsMsgBtn');
    const msgMenu = document.getElementById('commsMsgMenu');
    const csrf = document.querySelector('meta[name="csrf-token"]');

    function isMessagesPage() {
        return !!document.body.classList.contains('comms-messages-page')
            || /^\/communications\/?$/.test(window.location.pathname || '');
    }

    function storeMessagesReturnUrl() {
        try {
            const path = String(window.location.pathname || '') + String(window.location.search || '');
            if (!/^\/communications(\/|$)/.test(window.location.pathname || '')) {
                sessionStorage.setItem('comms_messages_return_url', path || '/dashboard');
            }
        } catch (e) { /* ignore */ }
    }

    function leaveMessagesPage() {
        let url = '/dashboard';
        try {
            const stored = sessionStorage.getItem('comms_messages_return_url');
            if (stored && !/^\/communications(\/|$)/.test(stored)) {
                url = stored;
            }
            sessionStorage.removeItem('comms_messages_return_url');
        } catch (e) { /* ignore */ }
        window.location.assign(url);
    }

    function syncMessagesPageHeaderBtn() {
        const btn = document.getElementById('commsMsgBtn');
        if (!btn) return;
        if (isMessagesPage()) {
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
            btn.setAttribute('aria-expanded', 'false');
            btn.title = 'Leave Messages';
            btn.setAttribute('aria-label', 'Leave Messages');
        }
    }

    function wireSeeAllMessagesLinks() {
        document.querySelectorAll('.comms-msg-see-all-link').forEach(function (link) {
            if (link.dataset.returnWired === '1') return;
            link.addEventListener('click', function () {
                storeMessagesReturnUrl();
            });
            link.dataset.returnWired = '1';
        });
    }

    function applyMessagesPopoverFilters() {
        const list = document.getElementById('commsMsgList');
        const empty = document.getElementById('commsMsgEmpty');
        const searchInput = document.getElementById('commsMsgSearch');
        if (!list) {
            return;
        }
        const q = String((searchInput && searchInput.value) || '').trim().toLowerCase();
        const activeFilter = document.querySelector('.comms-msg-filter-chip.is-active');
        const filter = activeFilter ? (activeFilter.getAttribute('data-filter') || 'all') : 'all';
        let visible = 0;
        list.querySelectorAll('.comms-msg-item').forEach(function (item) {
            const unread = item.getAttribute('data-unread') === '1';
            const blob = String(item.getAttribute('data-search') || '').toLowerCase();
            const matchSearch = !q || blob.indexOf(q) !== -1;
            const matchFilter = filter !== 'unread' || unread;
            const show = matchSearch && matchFilter;
            item.classList.toggle('is-filtered-out', !show);
            if (show) {
                visible += 1;
            }
        });
        if (empty) {
            const hasRows = list.querySelectorAll('.comms-msg-item').length > 0;
            empty.style.display = (!hasRows || visible === 0) ? 'flex' : 'none';
            list.style.display = (!hasRows || visible === 0) ? 'none' : '';
        }
    }

    function wireMessagesPopoverControls() {
        const searchInput = document.getElementById('commsMsgSearch');
        if (searchInput && !searchInput.dataset.wired) {
            searchInput.addEventListener('input', applyMessagesPopoverFilters);
            searchInput.dataset.wired = '1';
        }
        document.querySelectorAll('.comms-msg-filter-chip').forEach(function (chip) {
            if (chip.dataset.wired) {
                return;
            }
            chip.addEventListener('click', function () {
                document.querySelectorAll('.comms-msg-filter-chip').forEach(function (el) {
                    const on = el === chip;
                    el.classList.toggle('is-active', on);
                    el.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                applyMessagesPopoverFilters();
            });
            chip.dataset.wired = '1';
        });
    }

    function commsJsonHeaders() {
        return {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        };
    }

    function escMsg(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function relTime(iso) {
        if (!iso) {
            return '';
        }
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) {
            return '';
        }
        const sec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        if (sec < 60) {
            return sec + 's';
        }
        if (sec < 3600) {
            return Math.floor(sec / 60) + 'm';
        }
        if (sec < 86400) {
            return Math.floor(sec / 3600) + 'h';
        }
        return Math.floor(sec / 86400) + 'd';
    }

    function avatarFor(name, url) {
        if (url) {
            return url;
        }
        return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(String(name || 'U').slice(0, 40)) + '&background=0450A8&color=fff';
    }

    function paintMessagesPopover(conversations, officials) {
        const list = document.getElementById('commsMsgList');
        const empty = document.getElementById('commsMsgEmpty');
        const pill = document.getElementById('commsMsgCountPill');
        if (!list || !empty) {
            return;
        }

        conversations = Array.isArray(conversations) ? conversations : [];
        officials = Array.isArray(officials) ? officials : [];
        const items = conversations.slice(0, 8);
        window.__COMMS_HEADER_CONVERSATIONS__ = conversations;

        const chatPeerIds = {};
        conversations.forEach(function (c) {
            const peerId = Number((c.other_user && c.other_user.id) || 0);
            if (peerId) chatPeerIds[peerId] = true;
        });
        const starters = officials.filter(function (user) {
            const id = Number(user.id || 0);
            return id && !chatPeerIds[id];
        }).slice(0, Math.max(0, 8 - items.length));

        if (!items.length && !starters.length) {
            list.innerHTML = '';
            list.style.display = 'none';
            empty.style.display = 'flex';
            applyMessagesPopoverFilters();
            if (typeof window.renderFullscreenChatList === 'function') {
                window.renderFullscreenChatList(window.__COMMS_HEADER_CONVERSATIONS__);
            }
            return;
        }

        empty.style.display = 'none';
        list.style.display = '';
        list.innerHTML = items.map(function (c) {
            const peer = c.other_user || {};
            const fullName = peer.name || 'User';
            const name = (window.Comms && window.Comms.truncateText)
                ? window.Comms.truncateText(fullName, 28)
                : fullName;
            const previewRaw = (c.last_message && c.last_message.body) || 'No messages yet';
            const preview = (window.Comms && window.Comms.truncateText)
                ? window.Comms.truncateText(previewRaw, 42)
                : previewRaw;
            const unread = Number(c.unread_count || 0);
            const avatar = avatarFor(fullName, peer.profile_image_url);
            const searchBlob = String(fullName + ' ' + previewRaw).toLowerCase();
            return '<button type="button" class="comms-msg-item' + (unread > 0 ? ' comms-msg-unread' : '') + '" data-id="' + escMsg(c.id) + '" data-name="' + escMsg(fullName) + '" data-avatar="' + escMsg(avatar) + '" data-unread="' + (unread > 0 ? '1' : '0') + '" data-search="' + escMsg(searchBlob) + '" role="menuitem" title="' + escMsg(fullName) + '">' +
                '<img class="comms-msg-avatar" src="' + escMsg(avatar) + '" alt="">' +
                '<div class="comms-msg-content">' +
                '<div class="comms-msg-item-top">' +
                '<span class="comms-msg-item-name">' + escMsg(name) + '</span>' +
                '<span class="comms-msg-item-time">' + escMsg(relTime(c.updated_at)) + '</span>' +
                '</div>' +
                '<div class="comms-msg-item-preview">' + escMsg(preview) + '</div>' +
                '</div>' +
                (unread > 0 ? '<span class="comms-msg-unread-dot" aria-hidden="true"></span>' : '') +
                '</button>';
        }).join('') + starters.map(function (user) {
            const fullName = user.name || 'User';
            const name = (window.Comms && window.Comms.truncateText)
                ? window.Comms.truncateText(fullName, 28)
                : fullName;
            const previewRaw = user.position || user.user_type_label || 'SK Official';
            const preview = (window.Comms && window.Comms.truncateText)
                ? window.Comms.truncateText(previewRaw, 42)
                : previewRaw;
            const avatar = avatarFor(fullName, user.profile_image_url);
            const searchBlob = String(fullName + ' ' + previewRaw).toLowerCase();
            return '<button type="button" class="comms-msg-item" data-user-id="' + escMsg(user.id) + '" data-name="' + escMsg(fullName) + '" data-avatar="' + escMsg(avatar) + '" data-unread="0" data-search="' + escMsg(searchBlob) + '" role="menuitem" title="' + escMsg(fullName) + '">' +
                '<img class="comms-msg-avatar" src="' + escMsg(avatar) + '" alt="">' +
                '<div class="comms-msg-content">' +
                '<div class="comms-msg-item-top">' +
                '<span class="comms-msg-item-name">' + escMsg(name) + '</span>' +
                '</div>' +
                '<div class="comms-msg-item-preview">' + escMsg(preview) + '</div>' +
                '</div></button>';
        }).join('');
        applyMessagesPopoverFilters();

        if (typeof window.renderFullscreenChatList === 'function') {
            window.renderFullscreenChatList(window.__COMMS_HEADER_CONVERSATIONS__);
        }

        const unreadTotal = items.reduce(function (sum, c) { return sum + Number(c.unread_count || 0); }, 0);
        if (pill) {
            pill.style.display = unreadTotal > 0 ? '' : 'none';
            pill.textContent = unreadTotal > 99 ? '99+' : String(unreadTotal);
        }
    }

    function hydrateMessagesPopoverFromCache() {
        const inbox = window.Comms && window.Comms.readInboxCache ? window.Comms.readInboxCache() : null;
        const officials = window.Comms && window.Comms.readOfficialsCache ? window.Comms.readOfficialsCache() : null;
        if ((Array.isArray(inbox) && inbox.length) || (Array.isArray(officials) && officials.length)) {
            paintMessagesPopover(inbox || [], officials || []);
        }
    }

    function refreshMessagesPopover() {
        const list = document.getElementById('commsMsgList');
        const empty = document.getElementById('commsMsgEmpty');
        if (!list || !empty) {
            return;
        }

        hydrateMessagesPopoverFromCache();

        Promise.all([
            fetch('/api/communications/conversations', {
                headers: commsJsonHeaders(),
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; }),
            fetch('/api/communications/users/search', {
                headers: commsJsonHeaders(),
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; })
        ]).then(function (results) {
            const data = results[0];
            const people = results[1];
            const conversations = data && Array.isArray(data.conversations) ? data.conversations : [];
            const officials = people && Array.isArray(people.users) ? people.users : [];
            if (window.Comms && window.Comms.writeInboxCache) {
                window.Comms.writeInboxCache(conversations);
            }
            if (window.Comms && window.Comms.writeOfficialsCache) {
                window.Comms.writeOfficialsCache(officials);
            }
            paintMessagesPopover(conversations, officials);
        }).catch(function () { /* ignore */ });
    }

    function toggleMessagesPopover(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        // On See All Messages: header Messages btn is selected; click again leaves to prior page.
        if (isMessagesPage()) {
            leaveMessagesPage();
            return;
        }
        const pop = document.getElementById('commsMsgPopover');
        const btn = document.getElementById('commsMsgBtn');
        if (!pop || !btn) {
            return;
        }
        const chatModal = document.getElementById('commsChatModal');
        const chatOpen = chatModal && !chatModal.hidden && window.__COMMS_HEADER_ACTIVE_ID__ != null;
        // If floating chat is open, close it then show the chats list (one click).
        if (chatOpen) {
            if (typeof window.closeHeaderChatModal === 'function') {
                window.closeHeaderChatModal();
            }
            if (typeof window.kabataanCloseHeaderOverlays === 'function') {
                window.kabataanCloseHeaderOverlays('messages');
            }
            pop.classList.add('show');
            pop.style.zIndex = '5200';
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.remove('is-active');
            btn.setAttribute('aria-pressed', 'false');
            wireMessagesPopoverControls();
            wireSeeAllMessagesLinks();
            refreshMessagesPopover();
            return;
        }
        if (typeof window.kabataanCloseHeaderOverlays === 'function') {
            window.kabataanCloseHeaderOverlays('messages');
        }
        const isOpen = pop.classList.contains('show');
        if (isOpen) {
            window.closeMessagesPopover();
            return;
        }
        pop.classList.add('show');
        pop.style.zIndex = '5200';
        btn.setAttribute('aria-expanded', 'true');
        wireMessagesPopoverControls();
        wireSeeAllMessagesLinks();
        refreshMessagesPopover();
    }

    window.toggleMessagesPopover = toggleMessagesPopover;
    window.refreshMessagesPopover = refreshMessagesPopover;
    hydrateMessagesPopoverFromCache();
    wireSeeAllMessagesLinks();
    syncMessagesPageHeaderBtn();

    if (msgBtn) {
        msgBtn.addEventListener('click', function (e) {
            toggleMessagesPopover(e);
        });
    }

    document.addEventListener('click', function (e) {
        if (!msgMenu) {
            return;
        }
        if (msgMenu.contains(e.target)) {
            return;
        }
        const chatModal = document.getElementById('commsChatModal');
        if (chatModal && !chatModal.hidden && chatModal.contains(e.target)) {
            return;
        }
        window.closeMessagesPopover();
    });

    if (!badge) {
        return;
    }

    function refreshUnreadBadge() {
        fetch('/api/communications/unread-count', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
            },
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data) {
                return;
            }
            const n = Number(data.unread_count || 0);
            badge.dataset.unreadTotal = String(n);
            badge.style.display = n > 0 ? '' : 'none';
            badge.textContent = n > 99 ? '99+' : String(n);
            const pill = document.getElementById('commsMsgCountPill');
            if (pill) {
                pill.style.display = n > 0 ? '' : 'none';
                pill.textContent = n > 99 ? '99+' : String(n);
            }
        }).catch(function () {});
    }

    setInterval(refreshUnreadBadge, 30000);
})();
