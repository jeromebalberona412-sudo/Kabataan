/**
 * Kabataan Messenger-style floating header chat modal.
 * Opens a conversation in a bottom-right dock without leaving the page.
 */
import '../../../Communications/assets/js/communication.js';
import '../../../Communications/assets/js/realtime.js';
import '../../../Communications/assets/js/webrtc.js';

(function () {
'use strict';

function escapeCommsHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}

function formatCommsRelativeTime(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    var diffSec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (diffSec < 60) return diffSec + 's';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h';
    return Math.floor(diffSec / 86400) + 'd';
}

function defaultCommsAvatar(name) {
    var label = encodeURIComponent(String(name || 'U').slice(0, 40));
    return 'https://ui-avatars.com/api/?name=' + label + '&background=2C2C3E&color=fff';
}

// Header chat modal (open conversation without leaving page)
var headerChatState = {
    conversationId: null,
    loading: false,
    sending: false,
    fullscreen: false,
    messages: [],
    reactionEmojis: ['👍', '❤️', '😆', '😮', '😢', '🙏'],
    openReactionPickerId: null,
    editingId: null,
    msgChangeSkipToast: {},
    reactInFlight: {},
    reactSkipRealtime: {},
    reactSeq: {},
    pendingAttachments: [],
    pendingPreviewUrls: []
};

function getCommsCsrfToken() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    return csrf ? csrf.getAttribute('content') : '';
}

function clearHeaderChatAttachments() {
    headerChatState.pendingPreviewUrls.forEach(function (u) { if (u) URL.revokeObjectURL(u); });
    headerChatState.pendingAttachments = [];
    headerChatState.pendingPreviewUrls = [];
    var preview = document.getElementById('commsChatAttachPreview');
    if (preview) preview.hidden = true;
    var inner = document.getElementById('commsChatAttachPreviewInner');
    if (inner) inner.innerHTML = '';
}

function isHeaderImageFile(f) {
    return /^image\/(jpeg|png|webp|gif)$/i.test(f.type || '');
}

function renderHeaderAttachPreview() {
    var inner = document.getElementById('commsChatAttachPreviewInner');
    var wrap = document.getElementById('commsChatAttachPreview');
    if (!inner || !wrap) return;
    if (!headerChatState.pendingAttachments.length) { wrap.hidden = true; inner.innerHTML = ''; return; }
    wrap.hidden = false;
    inner.innerHTML = headerChatState.pendingAttachments.map(function (f, i) {
        if (isHeaderImageFile(f) && headerChatState.pendingPreviewUrls[i]) {
            return '<div class="comms-chat-attach-preview-item"><img src="' + headerChatState.pendingPreviewUrls[i] + '" alt="preview"></div>';
        }
        return '<div class="comms-chat-attach-preview-item"><span class="file-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span><span>' + escapeCommsHtml(f.name) + '</span></div>';
    }).join('');
}

function pushHeaderFiles(files, kind) {
    var maxImg = 100, maxFile = 10, maxBytes = 25 * 1024 * 1024;
    var imgCount = 0, fileCount = 0, totalBytes = 0;
    headerChatState.pendingAttachments.forEach(function (f) {
        totalBytes += f.size || 0;
        if (isHeaderImageFile(f)) imgCount++; else fileCount++;
    });
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        totalBytes += f.size || 0;
        if (totalBytes > maxBytes) { alert('Total size exceeds 25 MB.'); break; }
        if (kind === 'image') {
            imgCount++;
            if (imgCount > maxImg) { alert('Max ' + maxImg + ' images.'); break; }
        } else {
            fileCount++;
            if (fileCount > maxFile) { alert('Max ' + maxFile + ' files.'); break; }
        }
        headerChatState.pendingAttachments.push(f);
        headerChatState.pendingPreviewUrls.push(isHeaderImageFile(f) ? URL.createObjectURL(f) : null);
    }
    renderHeaderAttachPreview();
}

function headerFileKindClass(a) {
    var name = String((a && a.file_name) || '');
    var mime = String((a && a.mime_type) || '').toLowerCase();
    if (/\.pdf$/i.test(name) || mime === 'application/pdf') return ' is-pdf';
    if (/\.docx?$/i.test(name) || mime.indexOf('msword') !== -1 || mime.indexOf('wordprocessingml') !== -1) return ' is-word';
    return '';
}

function renderHeaderAttachmentBubbles(attachments) {
    if (!attachments || !attachments.length) return '';
    var html = '<div class="comms-chat-attachment-grid">';
    attachments.forEach(function (a) {
        if (a.file_type === 'image' || a.storage_provider === 'cloudinary') {
            var src = a.url || a.file_path || a.download_url || '';
            html += '<img src="' + escapeCommsHtml(src) + '" alt="' + escapeCommsHtml(a.file_name || 'image') + '" loading="lazy">';
        } else {
            var dlUrl = a.download_url || '#';
            html += '<a href="' + escapeCommsHtml(dlUrl) + '" class="comms-chat-attachment-file' + headerFileKindClass(a) + '" download="' + escapeCommsHtml(a.file_name || 'document') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg><span>' + escapeCommsHtml(a.file_name || 'file') + '</span></a>';
        }
    });
    html += '</div>';
    return html;
}

function closeHeaderChatModal() {
    var modal = document.getElementById('commsChatModal');
    if (!modal) return;
    modal.hidden = true;
    modal.classList.remove('is-fullscreen', 'is-empty-thread', 'is-thread-open');
    document.body.classList.remove('comms-chat-modal-open');
    headerChatState.conversationId = null;
    headerChatState.messages = [];
    headerChatState.fullscreen = false;
    window.__COMMS_HEADER_ACTIVE_ID__ = null;
    hideHeaderReactionPicker();
    clearHeaderChatAttachments();
    var sidebar = document.getElementById('commsChatFsSidebar');
    var empty = document.getElementById('commsChatFsEmpty');
    var active = document.getElementById('commsChatFsActive');
    var backBtn = document.getElementById('commsChatFsBack');
    if (sidebar) sidebar.hidden = true;
    if (empty) empty.hidden = true;
    if (active) active.hidden = false;
    if (backBtn) backBtn.hidden = true;
}

function setHeaderChatEmptyThread(isEmpty) {
    var modal = document.getElementById('commsChatModal');
    var empty = document.getElementById('commsChatFsEmpty');
    var active = document.getElementById('commsChatFsActive');
    var backBtn = document.getElementById('commsChatFsBack');
    if (!modal) return;

    if (!modal.classList.contains('is-fullscreen')) {
        modal.classList.remove('is-empty-thread');
        modal.classList.toggle('is-thread-open', !!headerChatState.conversationId);
        if (empty) empty.hidden = true;
        if (active) active.hidden = false;
        if (backBtn) backBtn.hidden = true;
        return;
    }

    modal.classList.toggle('is-empty-thread', !!isEmpty);
    modal.classList.toggle('is-thread-open', !isEmpty);
    if (empty) empty.hidden = !isEmpty;
    if (active) active.hidden = !!isEmpty;
    if (backBtn) {
        backBtn.hidden = !(!isEmpty && window.innerWidth <= 768);
    }
}

function renderFullscreenChatList(conversations) {
    var list = document.getElementById('commsChatFsList');
    var empty = document.getElementById('commsChatFsListEmpty');
    if (!list) return;

    var items = Array.isArray(conversations) ? conversations : [];
    window.__COMMS_HEADER_CONVERSATIONS__ = items;

    if (!items.length) {
        list.innerHTML = '';
        if (empty) empty.hidden = false;
        return;
    }

    if (empty) empty.hidden = true;
    list.innerHTML = items.map(function (c) {
        var peer = c.other_user || {};
        var name = peer.name || 'User';
        var preview = (c.last_message && c.last_message.body) || 'No messages yet';
        var unread = Number(c.unread_count || 0);
        var avatar = peer.profile_image_url || defaultCommsAvatar(name);
        var searchBlob = String(name + ' ' + preview).toLowerCase();
        var active = Number(c.id) === Number(headerChatState.conversationId) ? ' is-active' : '';
        return '<button type="button" class="comms-chat-fs-item' + active + (unread > 0 ? ' is-unread' : '') + '" data-id="' + c.id + '" data-name="' + escapeCommsHtml(name) + '" data-avatar="' + escapeCommsHtml(avatar) + '" data-unread="' + (unread > 0 ? '1' : '0') + '" data-search="' + escapeCommsHtml(searchBlob) + '" role="listitem">' +
            '<img class="comms-chat-fs-item-avatar" src="' + escapeCommsHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(name)) + '\'">' +
            '<div class="comms-chat-fs-item-main">' +
            '<span class="comms-chat-fs-item-name">' + escapeCommsHtml(name) + '</span>' +
            '<span class="comms-chat-fs-item-preview">' + escapeCommsHtml(preview) + '</span>' +
            '</div>' +
            '<div class="comms-chat-fs-item-meta">' +
            '<span class="comms-chat-fs-item-time">' + escapeCommsHtml(formatCommsRelativeTime(c.updated_at)) + '</span>' +
            (unread > 0 ? '<span class="comms-chat-fs-item-badge">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
            '</div>' +
            '</button>';
    }).join('');

    applyFullscreenChatFilters();
}

function applyFullscreenChatFilters() {
    var list = document.getElementById('commsChatFsList');
    var empty = document.getElementById('commsChatFsListEmpty');
    var searchInput = document.getElementById('commsChatFsSearch');
    var activeChip = document.querySelector('.comms-chat-fs-filter.is-active');
    if (!list) return;

    var query = String(searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
    var filter = activeChip ? String(activeChip.getAttribute('data-fs-filter') || 'all') : 'all';
    var items = list.querySelectorAll('.comms-chat-fs-item');
    var visible = 0;

    items.forEach(function (item) {
        var unread = item.getAttribute('data-unread') === '1';
        var haystack = String(item.getAttribute('data-search') || '').toLowerCase();
        var show = (filter === 'all' || (filter === 'unread' && unread))
            && (!query || haystack.indexOf(query) !== -1);
        item.classList.toggle('is-filtered-out', !show);
        if (show) visible += 1;
    });

    if (empty) {
        empty.hidden = items.length > 0 && visible > 0;
        if (items.length && visible === 0) {
            empty.hidden = false;
            empty.innerHTML = '<p>' + (filter === 'unread' ? 'No unread messages' : 'No matching chats') + '</p>';
        } else if (!items.length) {
            empty.hidden = false;
            empty.innerHTML = '<p>No conversations yet</p>';
        }
    }
}

function setHeaderChatFullscreen(enabled) {
    var modal = document.getElementById('commsChatModal');
    var sidebar = document.getElementById('commsChatFsSidebar');
    var expandBtn = document.getElementById('commsChatModalOpenFull');
    if (!modal) return;

    headerChatState.fullscreen = !!enabled;
    modal.classList.toggle('is-fullscreen', !!enabled);
    if (sidebar) sidebar.hidden = !enabled;

    if (expandBtn) {
        var icon = expandBtn.querySelector('i, svg');
        if (icon) {
            icon.outerHTML = enabled ? '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14h6v6"/><path d="M20 10h-6V4"/><path d="M14 10l7-7"/><path d="M3 21l7-7"/></svg>' : '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>';
        }
        expandBtn.title = enabled ? 'Exit fullscreen' : 'Toggle fullscreen';
        expandBtn.setAttribute('aria-label', enabled ? 'Exit fullscreen' : 'Toggle fullscreen messages');
    }

    setHeaderChatEmptyThread(!headerChatState.conversationId);
}

function openHeaderMessagesFullscreen(conversationId, name, avatarUrl) {
    var modal = document.getElementById('commsChatModal');
    if (!modal) return;

    closeMessagesPopover();
    modal.hidden = false;
    document.body.classList.add('comms-chat-modal-open');
    setHeaderChatFullscreen(true);
    wireFullscreenChatControls();

    refreshMessagesPopover();
    var cached = window.__COMMS_HEADER_CONVERSATIONS__;
    if (Array.isArray(cached)) {
        renderFullscreenChatList(cached);
    }

    if (conversationId) {
        openHeaderChatModal(conversationId, name, avatarUrl);
        setHeaderChatFullscreen(true);
    } else {
        headerChatState.conversationId = null;
        headerChatState.messages = [];
        setHeaderChatEmptyThread(true);
        var body = document.getElementById('commsChatModalBody');
        if (body) {
            body.innerHTML = '<p class="comms-chat-modal-empty">Select a conversation</p>';
        }
    }
}

function startHeaderChatWithUser(userId, name, avatarUrl) {
    if (!userId) return;
    fetch('/api/communications/conversations', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ user_id: Number(userId) })
    }).then(function (r) {
        return r.ok ? r.json() : r.json().then(function (data) {
            throw new Error((data && data.message) || 'Unable to start conversation.');
        });
    }).then(function (data) {
        var conversation = data && data.conversation ? data.conversation : {};
        var peer = conversation.other_user || {};
        openHeaderChatModal(
            conversation.id,
            peer.name || name || 'Chat',
            peer.profile_image_url || avatarUrl || ''
        );
        if (typeof window.refreshMessagesPopover === 'function') {
            window.refreshMessagesPopover();
        }
    }).catch(function (err) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to start conversation.', 'error');
        }
    });
}

function openHeaderChatModal(conversationId, name, avatarUrl) {
    var modal = document.getElementById('commsChatModal');
    var title = document.getElementById('commsChatModalTitle');
    var sub = document.getElementById('commsChatModalSub');
    var input = document.getElementById('commsChatModalInput');
    var body = document.getElementById('commsChatModalBody');
    if (!modal || !conversationId) return;

    closeMessagesPopover();
    hideHeaderReactionPicker();
    headerChatState.conversationId = Number(conversationId);
    window.__COMMS_HEADER_ACTIVE_ID__ = headerChatState.conversationId;
    if (title) title.textContent = name || 'Chat';
    if (sub) sub.textContent = 'Active now';
    setHeaderChatAvatar(name, avatarUrl);
    if (input) {
        input.value = '';
    }

    var cached = readHeaderChatCache(conversationId);
    if (cached && Array.isArray(cached.messages) && cached.messages.length) {
        var cachedEmojis = cached.reactionEmojis || cached.reaction_emojis;
        if (Array.isArray(cachedEmojis) && cachedEmojis.length) {
            headerChatState.reactionEmojis = cachedEmojis;
        }
        headerChatState.messages = cached.messages;
        renderHeaderChatMessages(cached.messages);
    } else if (body) {
        body.innerHTML = '';
    }

    modal.hidden = false;
    document.body.classList.add('comms-chat-modal-open');
    setHeaderChatEmptyThread(false);
    if (headerChatState.fullscreen) {
        setHeaderChatFullscreen(true);
        document.querySelectorAll('#commsChatFsList .comms-chat-fs-item').forEach(function (el) {
            el.classList.toggle('is-active', Number(el.getAttribute('data-id')) === Number(conversationId));
        });
    }

    // Subscribe immediately so new messages appear while history loads.
    if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
        window.CommsRealtime.subscribeConversation(conversationId);
    }
    loadHeaderChatMessages(conversationId);
    loadHeaderConversationPeer(conversationId);
    markHeaderConversationRead(conversationId);
    window.setTimeout(function () {
        if (input) input.focus();
    }, 50);
}

function setHeaderChatAvatar(name, avatarUrl) {
    var el = document.getElementById('commsChatModalAvatar');
    if (!el) return;
    var src = avatarUrl || defaultCommsAvatar(name);
    el.innerHTML = '<img src="' + escapeCommsHtml(src) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(name)) + '\'">';
    el.classList.remove('comms-chat-modal-avatar--fallback');
}

function loadHeaderConversationPeer(conversationId) {
    if (!conversationId) return;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || !data.conversation) return;
        if (Number(headerChatState.conversationId) !== Number(conversationId)) return;
        var peer = data.conversation.other_user || {};
        var title = document.getElementById('commsChatModalTitle');
        var sub = document.getElementById('commsChatModalSub');
        if (title && peer.name) title.textContent = peer.name;
        if (sub) {
            var bits = [peer.position, peer.barangay_name, peer.user_type_label].filter(Boolean);
            sub.textContent = bits.length ? bits.join(' · ') : 'Active now';
        }
        if (peer.profile_image_url || peer.name) {
            setHeaderChatAvatar(peer.name, peer.profile_image_url || '');
        }
    }).catch(function () { /* keep the values already shown */ });
}

function wireFullscreenChatControls() {
    var searchInput = document.getElementById('commsChatFsSearch');
    var chips = document.querySelectorAll('.comms-chat-fs-filter');
    var list = document.getElementById('commsChatFsList');
    var expandBtn = document.getElementById('commsChatModalOpenFull');
    var headerExpand = document.getElementById('commsMsgOpenFull');
    var backBtn = document.getElementById('commsChatFsBack');

    if (searchInput && searchInput.dataset.wired !== 'true') {
        searchInput.addEventListener('input', applyFullscreenChatFilters);
        searchInput.dataset.wired = 'true';
    }
    chips.forEach(function (chip) {
        if (chip.dataset.wired === 'true') return;
        chip.addEventListener('click', function () {
            chips.forEach(function (other) {
                other.classList.toggle('is-active', other === chip);
                other.setAttribute('aria-selected', other === chip ? 'true' : 'false');
            });
            applyFullscreenChatFilters();
        });
        chip.dataset.wired = 'true';
    });
    if (list && list.dataset.fsWired !== 'true') {
        list.addEventListener('click', function (e) {
            var item = e.target.closest('.comms-chat-fs-item');
            if (!item) return;
            e.preventDefault();
            openHeaderChatModal(
                item.getAttribute('data-id'),
                item.getAttribute('data-name') || 'Chat',
                item.getAttribute('data-avatar') || ''
            );
        });
        list.dataset.fsWired = 'true';
    }
    if (expandBtn && expandBtn.dataset.fsToggleWired !== 'true') {
        expandBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var modal = document.getElementById('commsChatModal');
            if (!modal || modal.hidden) {
                openHeaderMessagesFullscreen(headerChatState.conversationId || null);
                return;
            }
            if (headerChatState.fullscreen) {
                setHeaderChatFullscreen(false);
                if (!headerChatState.conversationId) {
                    closeHeaderChatModal();
                }
                return;
            }
            setHeaderChatFullscreen(true);
            wireFullscreenChatControls();
            refreshMessagesPopover();
        });
        expandBtn.dataset.fsToggleWired = 'true';
    }
    if (headerExpand && headerExpand.dataset.fsWired !== 'true') {
        headerExpand.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openHeaderMessagesFullscreen(null);
        });
        headerExpand.dataset.fsWired = 'true';
    }
    if (backBtn && backBtn.dataset.wired !== 'true') {
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            headerChatState.conversationId = null;
            headerChatState.messages = [];
            setHeaderChatEmptyThread(true);
            var body = document.getElementById('commsChatModalBody');
            if (body) body.innerHTML = '<p class="comms-chat-modal-empty">Select a conversation</p>';
        });
        backBtn.dataset.wired = 'true';
    }
}

function renderHeaderReactionChips(reactions, messageId) {
    var items = Array.isArray(reactions)
        ? reactions.filter(function (r) { return Number(r.count || 0) > 0; })
        : [];
    if (!items.length) return '';
    var total = 0;
    var anyMine = false;
    items.forEach(function (r) {
        total += Number(r.count || 0);
        if (r.mine) anyMine = true;
    });
    var escape = window.Comms && Comms.escapeHtml ? Comms.escapeHtml : escapeCommsHtml;
    return '<div class="comms-chat-reaction-chips" role="group" aria-label="Reactions">' +
        '<button type="button" class="comms-reaction-summary' + (anyMine ? ' is-mine' : '') + '"' +
        ' data-react-people="' + Number(messageId || 0) + '"' +
        ' aria-haspopup="true" aria-label="See who reacted">' +
        '<span class="comms-reaction-stack">' +
        items.map(function (r) {
            return '<span class="comms-reaction-stack-btn' + (r.mine ? ' is-mine' : '') + '" data-react-people-emoji="' + escape(r.emoji) + '">' +
                escape(r.emoji) +
                '</span>';
        }).join('') +
        '</span>' +
        (total > 1 ? '<span class="comms-reaction-count">' + total + '</span>' : '') +
        '</button></div>';
}

function renderHeaderReactionPicker() {
    return '';
}

function getHeaderReactionPickerEl() {
    var el = document.getElementById('commsChatReactionPicker');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'commsChatReactionPicker';
    el.className = 'comms-chat-reaction-picker comms-chat-reaction-picker--fixed';
    el.setAttribute('role', 'menu');
    el.setAttribute('aria-label', 'Choose reaction');
    el.style.display = 'none';
    el.style.position = 'fixed';
    el.style.zIndex = '40050';
    document.body.appendChild(el);
    return el;
}

function hideHeaderReactionPicker() {
    headerChatState.openReactionPickerId = null;
    var el = document.getElementById('commsChatReactionPicker');
    if (el) {
        el.style.display = 'none';
        el.innerHTML = '';
        el.removeAttribute('data-react-picker');
    }
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap.is-picker-open').forEach(function (wrap) {
        wrap.classList.remove('is-picker-open');
        var btn = wrap.querySelector('[data-react-toggle]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    });
}

function positionHeaderReactionPicker(triggerBtn) {
    var el = getHeaderReactionPickerEl();
    if (!triggerBtn) return;
    var rect = triggerBtn.getBoundingClientRect();
    var pickerW = el.offsetWidth || 240;
    var pickerH = el.offsetHeight || 48;
    var pad = 8;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var top = rect.top - pickerH - 8;
    var left = rect.left + (rect.width / 2) - (pickerW / 2);
    if (top < pad) {
        top = rect.bottom + 8;
    }
    left = Math.max(pad, Math.min(left, vw - pickerW - pad));
    top = Math.max(pad, Math.min(top, vh - pickerH - pad));
    el.style.top = top + 'px';
    el.style.left = left + 'px';
}

function showHeaderReactionPicker(messageId, triggerBtn) {
    hideHeaderMsgMenu();
    headerChatState.openReactionPickerId = Number(messageId) || null;
    if (!headerChatState.openReactionPickerId || !triggerBtn) {
        hideHeaderReactionPicker();
        return;
    }
    var el = getHeaderReactionPickerEl();
    el.setAttribute('data-react-picker', String(headerChatState.openReactionPickerId));
    el.innerHTML = headerChatState.reactionEmojis.map(function (emoji) {
        return '<button type="button" class="comms-chat-reaction-pick" data-react-emoji="' +
            escapeCommsHtml(emoji) + '" role="menuitem">' +
            escapeCommsHtml(emoji) + '</button>';
    }).join('');
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap').forEach(function (wrap) {
        var isOpen = Number(wrap.getAttribute('data-id')) === headerChatState.openReactionPickerId;
        wrap.classList.toggle('is-picker-open', isOpen);
        var btn = wrap.querySelector('[data-react-toggle]');
        if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    el.style.display = 'inline-flex';
    positionHeaderReactionPicker(triggerBtn);
    requestAnimationFrame(function () {
        positionHeaderReactionPicker(triggerBtn);
    });
}

var headerReactBtnIcon = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>';

function playHeaderChatReactionSound() {
    if (window.Comms && typeof window.Comms.playReactionSound === 'function') {
        window.Comms.playReactionSound();
        return;
    }
    try {
        var audio = new Audio('/sounds/reactions_ux.mp3');
        audio.volume = 0.75;
        audio.play().catch(function () {});
    } catch (e) {}
}

function headerCallBubbleClass(body) {
    var t = String(body || '').toLowerCase();
    if (t.indexOf('ended') !== -1 || t.indexOf('missed') !== -1 || t.indexOf('declined') !== -1 || t.indexOf('cancelled') !== -1 || t.indexOf('canceled') !== -1) return 'is-system is-call-ended';
    if (t.indexOf('started') !== -1 || t.indexOf('ringing') !== -1 || t.indexOf('answered') !== -1) return 'is-system is-call-started';
    return 'is-system';
}

function headerCallEventIcon(body) {
    var t = String(body || '').toLowerCase();
    if (t.indexOf('video') !== -1) {
        return '<svg class="comms-chat-call-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
    }
    return '<svg class="comms-chat-call-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>';
}

var headerMsgMoreIcon = '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.85"/><circle cx="12" cy="12" r="1.85"/><circle cx="12" cy="19" r="1.85"/></svg>';

function renderHeaderMessageActions(msg) {
    return '<div class="comms-chat-msg-actions">' +
        '<button type="button" class="comms-chat-msg-more" data-msg-more="' + msg.id + '" aria-label="Message actions" aria-haspopup="menu" aria-expanded="false" title="More">' + headerMsgMoreIcon + '</button>' +
        '</div>';
}

function positionHeaderOverlay(el, triggerBtn) {
    if (!el || !triggerBtn) return;
    var rect = triggerBtn.getBoundingClientRect();
    var pad = 8;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var w = el.offsetWidth || 180;
    var h = el.offsetHeight || 80;
    var top = rect.top - h - 8;
    var left = rect.left + (rect.width / 2) - (w / 2);
    if (top < pad) top = rect.bottom + 8;
    left = Math.max(pad, Math.min(left, vw - w - pad));
    top = Math.max(pad, Math.min(top, vh - h - pad));
    el.style.top = top + 'px';
    el.style.left = left + 'px';
}

function getHeaderMsgMenuEl() {
    var el = document.getElementById('commsChatMsgMenu');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'commsChatMsgMenu';
    el.className = 'comms-chat-msg-menu comms-chat-msg-menu--fixed';
    el.setAttribute('role', 'menu');
    el.hidden = true;
    document.body.appendChild(el);
    el.addEventListener('click', function (e) {
        var editBtn = e.target.closest('[data-msg-edit]');
        if (editBtn) {
            e.preventDefault();
            e.stopPropagation();
            hideHeaderMsgMenu();
            headerChatState.editingId = Number(editBtn.getAttribute('data-msg-edit'));
            renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
            var editor = document.querySelector('#commsChatModalBody [data-edit-input="' + headerChatState.editingId + '"]');
            if (editor) editor.focus();
            return;
        }
        var deleteChoice = e.target.closest('[data-msg-delete]');
        if (deleteChoice) {
            e.preventDefault();
            e.stopPropagation();
            hideHeaderMsgMenu();
            openHeaderDeletePrompt(Number(deleteChoice.getAttribute('data-msg-delete')));
        }
    });
    return el;
}

function hideHeaderMsgMenu() {
    var el = document.getElementById('commsChatMsgMenu');
    if (el) {
        el.hidden = true;
        el.innerHTML = '';
        el.removeAttribute('data-msg-menu');
    }
    document.querySelectorAll('#commsChatModalBody [data-msg-more]').forEach(function (btn) {
        btn.setAttribute('aria-expanded', 'false');
    });
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap.is-menu-open').forEach(function (wrap) {
        wrap.classList.remove('is-menu-open');
    });
}

function showHeaderMsgMenu(messageId, triggerBtn) {
    var msg = headerChatState.messages.find(function (m) { return Number(m.id) === Number(messageId); });
    if (!msg || !triggerBtn) return;
    hideHeaderReactionPicker();
    var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
    var items = '';
    if (!deleted && msg.mine && msg.message_type === 'text') {
        items += '<button type="button" role="menuitem" data-msg-edit="' + msg.id + '">Edit</button>';
    }
    items += '<button type="button" role="menuitem" data-msg-delete="' + msg.id + '">Delete</button>';
    var el = getHeaderMsgMenuEl();
    el.innerHTML = items;
    el.setAttribute('data-msg-menu', String(msg.id));
    el.hidden = false;
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap').forEach(function (wrap) {
        var isOpen = Number(wrap.getAttribute('data-id')) === Number(msg.id);
        wrap.classList.toggle('is-menu-open', isOpen);
        var btn = wrap.querySelector('[data-msg-more]');
        if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    positionHeaderOverlay(el, triggerBtn);
    requestAnimationFrame(function () { positionHeaderOverlay(el, triggerBtn); });
}

function closeHeaderMessageMenus() {
    hideHeaderMsgMenu();
}

function persistHeaderThreadCache() {
    if (!headerChatState.conversationId) return;
    writeHeaderChatCache(headerChatState.conversationId, {
        messages: headerChatState.messages,
        reactionEmojis: headerChatState.reactionEmojis
    });
}

function replaceHeaderMessage(updated) {
    if (!updated) return;
    var idx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(updated.id); });
    if (idx === -1) return;
    headerChatState.messages[idx] = updated;
    persistHeaderThreadCache();
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
}

function markHeaderDeletedLocal(messageId) {
    var idx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(messageId); });
    if (idx === -1) return;
    headerChatState.messages[idx] = Object.assign({}, headerChatState.messages[idx], {
        deleted_for_all: true,
        message_type: 'deleted',
        body: null,
        attachments: [],
        reactions: []
    });
    persistHeaderThreadCache();
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
}

function removeHeaderMessageLocal(messageId) {
    headerChatState.messages = headerChatState.messages.filter(function (m) {
        return Number(m.id) !== Number(messageId);
    });
    persistHeaderThreadCache();
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
}

function headerMessageMaxLines() {
    var input = document.getElementById('commsChatModalInput');
    var raw = input && input.getAttribute('data-max-lines');
    var n = Number(raw || 500);
    return Number.isFinite(n) && n > 0 ? n : 500;
}

function countHeaderMessageLines(text) {
    var value = String(text == null ? '' : text);
    if (value === '') return 0;
    return value.split(/\r\n|\r|\n/).length;
}

function clampHeaderMessageToMaxLines(text) {
    var max = headerMessageMaxLines();
    var value = String(text == null ? '' : text);
    var parts = value.split(/\r\n|\r|\n/);
    if (parts.length <= max) return value;
    return parts.slice(0, max).join('\n');
}

function syncHeaderComposerLineLimit() {
    var input = document.getElementById('commsChatModalInput');
    if (!input) return 0;
    var clamped = clampHeaderMessageToMaxLines(input.value);
    if (clamped !== input.value) input.value = clamped;
    var lines = countHeaderMessageLines(input.value);
    var atLimit = lines >= headerMessageMaxLines();
    input.classList.toggle('is-line-limit', atLimit);
    input.setAttribute('aria-invalid', atLimit ? 'true' : 'false');
    return lines;
}

function isHeaderComposerNavKey(e) {
    var key = e.key;
    return key === 'Backspace'
        || key === 'Delete'
        || key === 'ArrowLeft'
        || key === 'ArrowRight'
        || key === 'ArrowUp'
        || key === 'ArrowDown'
        || key === 'Home'
        || key === 'End'
        || key === 'Tab'
        || key === 'Escape'
        || e.ctrlKey
        || e.metaKey
        || e.altKey;
}

function saveHeaderEditedMessage(messageId) {
    var bodyEl = document.getElementById('commsChatModalBody');
    var input = bodyEl ? bodyEl.querySelector('[data-edit-input="' + messageId + '"]') : null;
    if (!input) return;
    var body = clampHeaderMessageToMaxLines(String(input.value || ''));
    if (body === '') {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot be empty.', 'error');
        }
        return;
    }
    if (countHeaderMessageLines(body) > headerMessageMaxLines()) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot exceed ' + headerMessageMaxLines() + ' lines.', 'error');
        }
        return;
    }
    if (body.length > 5000) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message must be 5000 characters or less.', 'error');
        }
        return;
    }
    headerChatState.msgChangeSkipToast[String(messageId)] = true;
    fetch('/api/communications/messages/' + encodeURIComponent(messageId), {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ body: body })
    }).then(function (r) {
        return r.ok ? r.json() : r.json().then(function (data) { throw new Error((data && data.message) || 'Unable to edit message.'); });
    }).then(function (data) {
        headerChatState.editingId = null;
        replaceHeaderMessage(data.message);
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message edited.', 'success');
        }
        window.setTimeout(function () { delete headerChatState.msgChangeSkipToast[String(messageId)]; }, 800);
    }).catch(function (err) {
        delete headerChatState.msgChangeSkipToast[String(messageId)];
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to edit message.', 'error');
        }
    });
}

function deleteHeaderMessage(messageId, scope) {
    var snapshot = headerChatState.messages.slice();
    headerChatState.msgChangeSkipToast[String(messageId)] = true;
    if (scope === 'me') {
        removeHeaderMessageLocal(messageId);
    } else {
        markHeaderDeletedLocal(messageId);
    }
    fetch('/api/communications/messages/' + encodeURIComponent(messageId), {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ scope: scope })
    }).then(function (r) {
        return r.ok ? r.json() : r.json().then(function (data) { throw new Error((data && data.message) || 'Unable to delete message.'); });
    }).then(function (data) {
        headerChatState.editingId = null;
        if (data && data.scope === 'me') {
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Message deleted for you.', 'success');
            }
            return;
        }
        if (data && data.message) {
            replaceHeaderMessage(data.message);
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Message deleted for everyone.', 'success');
            }
        }
        window.setTimeout(function () { delete headerChatState.msgChangeSkipToast[String(messageId)]; }, 800);
    }).catch(function (err) {
        headerChatState.messages = snapshot;
        persistHeaderThreadCache();
        renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
        delete headerChatState.msgChangeSkipToast[String(messageId)];
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to delete message.', 'error');
        }
    });
}

function openHeaderDeletePrompt(messageId) {
    var msg = headerChatState.messages.find(function (m) { return Number(m.id) === Number(messageId); });
    if (!msg) return;
    var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
    var canDeleteAll = !!msg.mine && !deleted;
    if (!window.Comms || typeof window.Comms.openDeleteMessageDialog !== 'function') {
        deleteHeaderMessage(messageId, canDeleteAll ? 'all' : 'me');
        return;
    }
    window.Comms.openDeleteMessageDialog({ canDeleteAll: canDeleteAll }).then(function (scope) {
        if (!scope) return;
        deleteHeaderMessage(messageId, scope);
    });
}

function renderHeaderChatMessages(messages, opts) {
    opts = opts || {};
    var body = document.getElementById('commsChatModalBody');
    if (!body) return;
    var prevScroll = body.scrollTop;
    var items = Array.isArray(messages) ? messages.slice() : [];
    headerChatState.messages = items;
    if (!items.length) {
        hideHeaderReactionPicker();
        body.innerHTML = '<p class="comms-chat-modal-empty">No messages yet. Say hello.</p>';
        return;
    }
    body.innerHTML = items.map(function (msg) {
        if (msg.message_type === 'call' || msg.message_type === 'system') {
            var callCls = headerCallBubbleClass(msg.body);
            return '<div class="comms-chat-bubble-wrap ' + (msg.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + msg.id + '">' +
                '<div class="comms-chat-bubble-row comms-chat-bubble-row--call">' +
                '<div class="comms-chat-bubble ' + callCls + '">' +
                headerCallEventIcon(msg.body) +
                '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body || '') + '</div>' +
                '<div class="comms-chat-bubble-time">' + escapeCommsHtml(formatCommsRelativeTime(msg.created_at)) + '</div>' +
                '</div></div></div>';
        }
        var mine = Boolean(msg.mine);
        var pickerOpen = Number(headerChatState.openReactionPickerId) === Number(msg.id);
        var editing = Number(headerChatState.editingId) === Number(msg.id);
        if (msg.deleted_for_all || msg.message_type === 'deleted') {
            return '<div class="comms-chat-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + '" data-id="' + msg.id + '">' +
                '<div class="comms-chat-bubble-row">' +
                '<div class="comms-chat-bubble comms-chat-bubble--deleted">This message was deleted</div>' +
                renderHeaderMessageActions(msg) +
                '</div></div>';
        }
        var bodyHtml = '';
        if (editing) {
            bodyHtml = '<textarea class="comms-chat-edit-input" data-edit-input="' + msg.id + '" maxlength="5000" rows="2">' + escapeCommsHtml(msg.body || '') + '</textarea>' +
                '<div class="comms-chat-edit-actions">' +
                '<button type="button" class="comms-chat-edit-cancel" data-edit-cancel="' + msg.id + '">Cancel</button>' +
                '<button type="button" class="comms-chat-edit-save" data-edit-save="' + msg.id + '">Save</button>' +
                '</div>';
        } else if (msg.body) {
            bodyHtml = '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body) +
                (msg.edited ? ' <em class="comms-chat-edited">edited</em>' : '') + '</div>';
        }
        return '<div class="comms-chat-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') + '" data-id="' + msg.id + '">' +
            '<div class="comms-chat-bubble-row">' +
            '<div class="comms-chat-bubble-tools">' +
            renderHeaderMessageActions(msg) +
            '<button type="button" class="comms-chat-react-btn" data-react-toggle="' + msg.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + headerReactBtnIcon + '</button>' +
            '</div>' +
            '<div class="comms-chat-bubble ' + (mine ? 'is-mine' : 'is-theirs') + '">' +
            renderHeaderAttachmentBubbles(msg.attachments) +
            bodyHtml +
            '<div class="comms-chat-bubble-time">' + escapeCommsHtml(formatCommsRelativeTime(msg.created_at)) + '</div>' +
            '</div>' +
            '</div>' +
            renderHeaderReactionPicker(msg.id) +
            renderHeaderReactionChips(msg.reactions, msg.id) +
            '</div>';
    }).join('');
    if (opts.preserveScroll) {
        body.scrollTop = prevScroll;
    } else {
        body.scrollTop = body.scrollHeight;
    }
    if (headerChatState.openReactionPickerId) {
        var pickerBtn = body.querySelector('[data-react-toggle="' + headerChatState.openReactionPickerId + '"]');
        if (pickerBtn) {
            positionHeaderReactionPicker(pickerBtn);
        } else {
            hideHeaderReactionPicker();
        }
    }
}

function cloneHeaderReactions(reactions) {
    return (Array.isArray(reactions) ? reactions : []).map(function (r) {
        return { emoji: r.emoji, count: Number(r.count || 0), mine: !!r.mine };
    });
}

function optimisticHeaderToggleReactions(reactions, emoji) {
    var list = cloneHeaderReactions(reactions).filter(function (r) { return r.count > 0; });
    var mineEntry = null;
    var target = null;
    list.forEach(function (r) {
        if (r.mine) mineEntry = r;
        if (r.emoji === emoji) target = r;
    });

    if (target && target.mine) {
        target.count -= 1;
        target.mine = false;
    } else {
        if (mineEntry && mineEntry !== target) {
            mineEntry.count -= 1;
            mineEntry.mine = false;
        }
        if (target) {
            target.count += 1;
            target.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: true });
        }
    }

    return list.filter(function (r) { return Number(r.count) > 0; });
}

function applyHeaderMessageReactions(messageId, reactions, opts) {
    opts = opts || {};
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg) return;
    msg.reactions = Array.isArray(reactions) ? reactions : [];
    if (opts.closePicker !== false) {
        hideHeaderReactionPicker();
    }
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
    if (headerChatState.conversationId) {
        writeHeaderChatCache(headerChatState.conversationId, {
            messages: headerChatState.messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    }
}

function patchHeaderRemoteReaction(messageId, eventType, row) {
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg || !row) return false;
    var boot = document.getElementById('commsRealtimeBoot');
    var list = cloneHeaderReactions(msg.reactions);
    var emoji = String(row.emoji || '');
    if (!emoji) return false;
    var isMine = boot
        && Number(row.user_id) === Number(boot.dataset.currentUserId)
        && String(row.user_type || '') === String(boot.dataset.portalUserType || '');
    if (isMine && headerChatState.reactSkipRealtime[String(messageId)]) return true;
    var entry = null;
    list.forEach(function (r) {
        if (r.emoji === emoji) entry = r;
    });

    if (eventType === 'DELETE') {
        if (!entry) return true;
        entry.count = Math.max(0, entry.count - 1);
        if (isMine) entry.mine = false;
    } else if (eventType === 'INSERT') {
        if (entry) {
            entry.count += 1;
            if (isMine) entry.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: isMine });
        }
    } else if (eventType === 'UPDATE') {
        var oldEmoji = String(row._old_emoji || '');
        if (!oldEmoji || oldEmoji === emoji) return false;
        var oldEntry = null;
        list.forEach(function (r) {
            if (r.emoji === oldEmoji) oldEntry = r;
        });
        if (oldEntry) {
            oldEntry.count = Math.max(0, oldEntry.count - 1);
            if (isMine) oldEntry.mine = false;
        }
        if (entry) {
            entry.count += 1;
            if (isMine) entry.mine = true;
        } else {
            list.push({ emoji: emoji, count: 1, mine: isMine });
        }
    } else {
        return false;
    }

    applyHeaderMessageReactions(messageId, list.filter(function (r) { return Number(r.count) > 0; }), { closePicker: false });
    return true;
}

function toggleHeaderChatReaction(messageId, emoji) {
    if (!messageId || !emoji) return;
    var key = String(messageId);
    var msg = headerChatState.messages.find(function (m) {
        return Number(m.id) === Number(messageId);
    });
    if (!msg) return;

    var previous = cloneHeaderReactions(msg.reactions);
    var next = optimisticHeaderToggleReactions(previous, emoji);
    var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
        && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

    var seq = (headerChatState.reactSeq[key] || 0) + 1;
    headerChatState.reactSeq[key] = seq;
    headerChatState.reactSkipRealtime[key] = true;

    if (added) playHeaderChatReactionSound();
    applyHeaderMessageReactions(messageId, next);

    fetch('/api/communications/messages/' + encodeURIComponent(messageId) + '/reactions', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        body: JSON.stringify({ emoji: emoji })
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (headerChatState.reactSeq[key] !== seq) return;
        if (!data) {
            applyHeaderMessageReactions(messageId, previous, { closePicker: false });
            return;
        }
        applyHeaderMessageReactions(
            data.message_id || messageId,
            Array.isArray(data.reactions) ? data.reactions : next,
            { closePicker: false }
        );
    }).catch(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        applyHeaderMessageReactions(messageId, previous, { closePicker: false });
    }).finally(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        window.setTimeout(function () {
            if (headerChatState.reactSeq[key] !== seq) return;
            delete headerChatState.reactSkipRealtime[key];
        }, 500);
    });
}

function headerChatCacheKey(conversationId) {
    return 'comms:thread:' + String(conversationId);
}

function readHeaderChatCache(conversationId) {
    if (window.Comms && typeof window.Comms.readThreadCache === 'function') {
        var payload = window.Comms.readThreadCache(conversationId);
        if (payload && Array.isArray(payload.messages)) return payload;
    }
    if (window.SkLocalCache && typeof window.SkLocalCache.get === 'function') {
        var entry = window.SkLocalCache.get(headerChatCacheKey(conversationId));
        if (entry && entry.payload && Array.isArray(entry.payload.messages)) return entry.payload;
    }
    return null;
}

function writeHeaderChatCache(conversationId, payload) {
    var stored = {
        messages: payload.messages || [],
        reactionEmojis: payload.reactionEmojis || payload.reaction_emojis || [],
        ts: Date.now()
    };
    if (window.Comms && typeof window.Comms.writeThreadCache === 'function') {
        window.Comms.writeThreadCache(conversationId, stored);
        return;
    }
    if (window.SkLocalCache && typeof window.SkLocalCache.set === 'function') {
        window.SkLocalCache.set(headerChatCacheKey(conversationId), stored);
    }
}

function refreshHeaderChatReaction(messageId, meta) {
    var conversationId = headerChatState.conversationId;
    if (!conversationId || !messageId) return;
    if (!headerChatState.messages.some(function (m) { return Number(m.id) === Number(messageId); })) return;
    if (headerChatState.reactSkipRealtime[String(messageId)]) return;

    if (meta && meta.eventType && patchHeaderRemoteReaction(messageId, meta.eventType, meta.row)) {
        return;
    }

    var watchSeq = headerChatState.reactSeq[String(messageId)] || 0;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || Number(headerChatState.conversationId) !== Number(conversationId)) return;
        if ((headerChatState.reactSeq[String(messageId)] || 0) !== watchSeq) return;
        if (headerChatState.reactSkipRealtime[String(messageId)]) return;
        var fresh = (data.messages || []).find(function (m) {
            return Number(m.id) === Number(messageId);
        });
        if (!fresh) return;
        applyHeaderMessageReactions(messageId, Array.isArray(fresh.reactions) ? fresh.reactions : [], { closePicker: false });
    }).catch(function () { /* ignore */ });
}

window.onCommsHeaderReactionChange = function (messageId, meta) {
    refreshHeaderChatReaction(messageId, meta || null);
};

window.onCommsHeaderMessageInsert = function (message) {
    if (!message || Number(message.conversation_id) !== Number(headerChatState.conversationId)) return;
    if (headerChatState.messages.some(function (m) { return Number(m.id) === Number(message.id); })) return;
    var boot = document.getElementById('commsRealtimeBoot');
    var mine = boot
        && Number(message.sender_id) === Number(boot.dataset.currentUserId)
        && message.sender_type === boot.dataset.portalUserType;
    message.mine = !!mine;
    if (!message.reactions) message.reactions = [];
    headerChatState.messages.push(message);
    renderHeaderChatMessages(headerChatState.messages);
    writeHeaderChatCache(headerChatState.conversationId, {
        messages: headerChatState.messages,
        reactionEmojis: headerChatState.reactionEmojis
    });
    if (typeof window.refreshMessagesPopover === 'function') {
        window.refreshMessagesPopover();
    }
};

window.onCommsHeaderMessageChange = function (row) {
    if (!row || Number(row.conversation_id) !== Number(headerChatState.conversationId)) return;
    var idx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(row.id); });
    if (idx === -1) return;
    var skipToast = !!headerChatState.msgChangeSkipToast[String(row.id)];
    if (row.deleted_for_all_at) {
        headerChatState.messages[idx].deleted_for_all = true;
        headerChatState.messages[idx].message_type = 'deleted';
        headerChatState.messages[idx].body = null;
        headerChatState.messages[idx].attachments = [];
        if (!skipToast && window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message deleted.', 'info');
        }
    } else if (row.body != null) {
        var prevBody = headerChatState.messages[idx].body;
        headerChatState.messages[idx].body = row.body;
        headerChatState.messages[idx].edited = true;
        if (!skipToast && String(prevBody || '') !== String(row.body || '')
            && window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message edited.', 'info');
        }
    }
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
    persistHeaderThreadCache();
};

function loadHeaderChatMessages(conversationId) {
    if (headerChatState.loading) return;
    headerChatState.loading = true;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || Number(headerChatState.conversationId) !== Number(conversationId)) return;
        var messages = Array.isArray(data.messages) ? data.messages : [];
        if (Array.isArray(data.reaction_emojis) && data.reaction_emojis.length) {
            headerChatState.reactionEmojis = data.reaction_emojis;
        }
        var same = JSON.stringify(messages.map(function (m) {
            return [m.id, m.body, m.message_type, m.deleted_for_all, m.edited, (m.attachments || []).length, m.reactions];
        })) === JSON.stringify(headerChatState.messages.map(function (m) {
            return [m.id, m.body, m.message_type, m.deleted_for_all, m.edited, (m.attachments || []).length, m.reactions];
        }));
        if (!same) {
            renderHeaderChatMessages(messages);
        }
        writeHeaderChatCache(conversationId, {
            messages: same ? headerChatState.messages : messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    }).catch(function () {
        var body = document.getElementById('commsChatModalBody');
        if (body && !headerChatState.messages.length) {
            body.innerHTML = '<p class="comms-chat-modal-empty">Unable to load messages.</p>';
        }
    }).finally(function () {
        headerChatState.loading = false;
    });
}

function markHeaderConversationRead(conversationId) {
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/read', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin'
    }).catch(function () { /* ignore */ });
}

function sendHeaderChatMessage(event) {
    if (event) event.preventDefault();
    var input = document.getElementById('commsChatModalInput');
    var conversationId = headerChatState.conversationId;
    if (!input || !conversationId) return;
    var body = clampHeaderMessageToMaxLines(String(input.value || ''));
    var files = headerChatState.pendingAttachments.slice();
    if (body === '' && !files.length) return;
    if (countHeaderMessageLines(body) > headerMessageMaxLines()) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot exceed ' + headerMessageMaxLines() + ' lines.', 'error');
        }
        return;
    }
    if (body.length > 5000) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message must be 5000 characters or less.', 'error');
        }
        return;
    }
    if (headerChatState.sending && files.length) return;

    var sendBtn = document.getElementById('commsChatModalSend');
    input.value = '';
    syncHeaderComposerLineLimit();
    clearHeaderChatAttachments();

    var tempId = 'local-' + Date.now();
    if (body !== '') {
        headerChatState.messages.push({
            id: tempId,
            conversation_id: conversationId,
            body: body,
            message_type: 'text',
            mine: true,
            created_at: new Date().toISOString(),
            reactions: [],
            attachments: []
        });
        renderHeaderChatMessages(headerChatState.messages);
    }

    var headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCommsCsrfToken()
    };
    var jobs = [];

    function parseMessageResponse(r) {
        return r.json().then(function (data) {
            if (!r.ok || !data || !data.message) {
                var msg = (data && (data.message || (data.errors && data.errors.attachment && data.errors.attachment[0]))) || 'Unable to send.';
                throw new Error(msg);
            }
            return data;
        }).catch(function (err) {
            if (err instanceof Error && err.message && err.message !== 'Unexpected end of JSON input') {
                throw err;
            }
            throw new Error('Unable to send.');
        });
    }

    function applySentMessage(data, replaceTempId) {
        if (!data || !data.message) return;
        var realIdx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(data.message.id); });
        var tempIdx = replaceTempId ? headerChatState.messages.findIndex(function (m) { return String(m.id) === replaceTempId; }) : -1;
        if (realIdx !== -1 && tempIdx !== -1) {
            headerChatState.messages.splice(tempIdx, 1);
        } else if (tempIdx !== -1) {
            headerChatState.messages[tempIdx] = data.message;
        } else if (realIdx === -1) {
            headerChatState.messages.push(data.message);
        }
        renderHeaderChatMessages(headerChatState.messages);
    }

    if (files.length) {
        headerChatState.sending = true;
        if (sendBtn) sendBtn.disabled = true;
        files.forEach(function (file, index) {
            var fd = new FormData();
            if (index === 0 && body !== '') {
                fd.append('body', body);
            }
            fd.append('attachment', file, file.name);
            jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: fd
            }).then(parseMessageResponse).then(function (data) {
                applySentMessage(data, index === 0 && body !== '' ? tempId : null);
            }));
        });
    } else if (body !== '') {
        jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
            credentials: 'same-origin',
            body: JSON.stringify({ body: body })
        }).then(parseMessageResponse).then(function (data) {
            applySentMessage(data, tempId);
        }));
    }

    Promise.all(jobs).catch(function (err) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(err.message || 'Unable to send file.', 'error');
        }
    }).finally(function () {
        headerChatState.sending = false;
        if (sendBtn) sendBtn.disabled = false;
        if (input) input.focus();
        writeHeaderChatCache(conversationId, {
            messages: headerChatState.messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    });
}

function wireHeaderChatAttachButtons() {
    var attachBtn = document.getElementById('commsChatAttachBtn');
    var attachMenu = document.getElementById('commsChatAttachMenu');
    var pickPhotos = document.getElementById('commsChatPickPhotos');
    var pickFiles = document.getElementById('commsChatPickFiles');
    var photoInput = document.getElementById('commsChatPhotoInput');
    var fileInput = document.getElementById('commsChatFileInput');
    var clearBtn = document.getElementById('commsChatAttachClear');
    if (!attachBtn || attachBtn.dataset.wired === 'true') return;
    if (attachMenu) attachMenu.hidden = true;

    attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (attachMenu) attachMenu.hidden = !attachMenu.hidden;
    });
    if (pickPhotos && photoInput) {
        pickPhotos.addEventListener('click', function () { photoInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (pickFiles && fileInput) {
        pickFiles.addEventListener('click', function () { fileInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            if (photoInput.files && photoInput.files.length) pushHeaderFiles(photoInput.files, 'image');
            photoInput.value = '';
        });
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) pushHeaderFiles(fileInput.files, 'file');
            fileInput.value = '';
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { clearHeaderChatAttachments(); });
    }
    document.addEventListener('click', function (e) {
        if (attachMenu && !attachMenu.hidden && !e.target.closest('.comms-chat-attach-wrap')) {
            attachMenu.hidden = true;
        }
    });
    attachBtn.dataset.wired = 'true';
}

function wireHeaderChatCallButtons() {
    var voiceBtn = document.getElementById('commsChatModalVoiceBtn');
    var videoBtn = document.getElementById('commsChatModalVideoBtn');

    function startHeaderCall(callType) {
        var cid = headerChatState.conversationId;
        if (!cid) return;
        if (!window.CommsWebRTC || typeof window.CommsWebRTC.startCall !== 'function') {
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Calling is not available yet. Refresh the page.', 'error');
            }
            return;
        }
        if (window.CommsRealtime && window.CommsRealtime.enabled === false) {
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                window.Comms.showToast('Realtime is not configured for calls.', 'error');
            }
            return;
        }
        var peerName = (document.getElementById('commsChatModalTitle') || {}).textContent || 'Contact';
        var avatarEl = document.querySelector('#commsChatModalAvatar img');
        var conversation = {
            id: cid,
            other_user: {
                name: peerName,
                profile_image_url: avatarEl ? avatarEl.getAttribute('src') : ''
            }
        };
        window.CommsWebRTC.startCall(cid, callType, conversation);
    }

    if (voiceBtn && voiceBtn.dataset.wired !== 'true') {
        voiceBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startHeaderCall('voice');
        });
        voiceBtn.dataset.wired = 'true';
    }
    if (videoBtn && videoBtn.dataset.wired !== 'true') {
        videoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startHeaderCall('video');
        });
        videoBtn.dataset.wired = 'true';
    }
}

function wireHeaderChatModal() {
    var modal = document.getElementById('commsChatModal');
    var list = document.getElementById('commsMsgList');
    var form = document.getElementById('commsChatModalForm');
    var input = document.getElementById('commsChatModalInput');
    wireHeaderChatAttachButtons();
    wireHeaderChatCallButtons();
    wireFullscreenChatControls();
    var chatBody = document.getElementById('commsChatModalBody');
    if (chatBody && chatBody.dataset.menuScroll !== 'true') {
        chatBody.addEventListener('scroll', function () {
            closeHeaderMessageMenus();
            if (headerChatState.openReactionPickerId) {
                hideHeaderReactionPicker();
            }
        }, { passive: true });
        chatBody.dataset.menuScroll = 'true';
    }
    var headerPicker = getHeaderReactionPickerEl();
    if (headerPicker && headerPicker.dataset.wired !== 'true') {
        headerPicker.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-react-emoji]');
            if (!pick) return;
            e.preventDefault();
            e.stopPropagation();
            var mid = Number(headerChatState.openReactionPickerId);
            var emoji = pick.getAttribute('data-react-emoji');
            if (mid && emoji) toggleHeaderChatReaction(mid, emoji);
        });
        headerPicker.dataset.wired = 'true';
    }
    if (document.documentElement.dataset.commsHeaderPickerResize !== 'true') {
        window.addEventListener('resize', function () {
            if (headerChatState.openReactionPickerId) {
                hideHeaderReactionPicker();
            }
        }, { passive: true });
        document.documentElement.dataset.commsHeaderPickerResize = 'true';
    }
    if (modal && modal.dataset.wired !== 'true') {
        modal.addEventListener('click', function (e) {
            if (e.target && e.target.closest('[data-comms-chat-close]')) {
                closeHeaderChatModal();
                return;
            }
            var editBtn = e.target.closest('[data-msg-edit]');
            if (editBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                closeHeaderMessageMenus();
                headerChatState.editingId = Number(editBtn.getAttribute('data-msg-edit'));
                renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
                var editor = document.querySelector('#commsChatModalBody [data-edit-input="' + headerChatState.editingId + '"]');
                if (editor) editor.focus();
                return;
            }
            var saveBtn = e.target.closest('[data-edit-save]');
            if (saveBtn) {
                e.preventDefault();
                saveHeaderEditedMessage(Number(saveBtn.getAttribute('data-edit-save')));
                return;
            }
            var cancelBtn = e.target.closest('[data-edit-cancel]');
            if (cancelBtn) {
                e.preventDefault();
                headerChatState.editingId = null;
                renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
                return;
            }
            var deleteChoice = e.target.closest('[data-msg-delete]');
            if (deleteChoice && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                closeHeaderMessageMenus();
                openHeaderDeletePrompt(Number(deleteChoice.getAttribute('data-msg-delete')));
                return;
            }
            var moreBtn = e.target.closest('[data-msg-more]');
            if (moreBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                e.stopPropagation();
                var wrapMore = moreBtn.closest('.comms-chat-bubble-wrap');
                var alreadyOpen = wrapMore && wrapMore.classList.contains('is-menu-open');
                hideHeaderMsgMenu();
                if (!alreadyOpen) {
                    showHeaderMsgMenu(Number(moreBtn.getAttribute('data-msg-more')), moreBtn);
                }
                return;
            }
            var peopleBtn = e.target.closest('[data-react-people]');
            if (peopleBtn && e.target.closest('#commsChatModal')) {
                e.preventDefault();
                e.stopPropagation();
                var pid = Number(peopleBtn.getAttribute('data-react-people'));
                var pmsg = headerChatState.messages.find(function (m) { return Number(m.id) === pid; });
                var emojiEl = e.target.closest('[data-react-people-emoji]');
                if (window.Comms && Comms.toggleReactionPeople) {
                    Comms.toggleReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                        pinned: true,
                        emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                    });
                }
                return;
            }
            var toggle = e.target.closest('[data-react-toggle]');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                var tid = Number(toggle.getAttribute('data-react-toggle'));
                if (Number(headerChatState.openReactionPickerId) === tid) {
                    hideHeaderReactionPicker();
                } else {
                    showHeaderReactionPicker(tid, toggle);
                }
                return;
            }
            var emojiBtn = e.target.closest('[data-react-emoji]');
            if (emojiBtn && e.target.closest('#commsChatModalBody')) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = emojiBtn.closest('.comms-chat-bubble-wrap');
                var mid = wrap ? Number(wrap.getAttribute('data-id')) : 0;
                var emoji = emojiBtn.getAttribute('data-react-emoji');
                if (mid && emoji) toggleHeaderChatReaction(mid, emoji);
            }
        });
        modal.addEventListener('pointerover', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn || !modal.contains(peopleBtn)) return;
            var pid = Number(peopleBtn.getAttribute('data-react-people'));
            var pmsg = headerChatState.messages.find(function (m) { return Number(m.id) === pid; });
            var emojiEl = e.target.closest('[data-react-people-emoji]');
            if (window.Comms && Comms.showReactionPeople) {
                Comms.showReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                    pinned: false,
                    emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                });
            }
        });
        modal.addEventListener('pointerout', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn) return;
            var related = e.relatedTarget;
            if (related && (peopleBtn.contains(related) || (related.closest && related.closest('#commsReactionPeople')))) {
                return;
            }
            if (window.Comms && Comms.scheduleHideReactionPeople) Comms.scheduleHideReactionPeople();
        });
        modal.dataset.wired = 'true';
    }
    if (document.documentElement.dataset.commsReactOutside !== 'true') {
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-msg-more]') && !e.target.closest('#commsChatMsgMenu')) {
                closeHeaderMessageMenus();
            }
            if (!headerChatState.openReactionPickerId) return;
            if (e.target.closest('[data-react-toggle]') || e.target.closest('.comms-chat-reaction-picker')) return;
            hideHeaderReactionPicker();
        });
        document.documentElement.dataset.commsReactOutside = 'true';
    }
    if (list && list.dataset.chatWired !== 'true') {
        list.addEventListener('click', function (e) {
            var item = e.target.closest('.comms-msg-item');
            if (!item) return;
            e.preventDefault();
            e.stopPropagation();
            var userId = item.getAttribute('data-user-id');
            var conversationId = item.getAttribute('data-id');
            if (userId && !conversationId) {
                startHeaderChatWithUser(
                    userId,
                    item.getAttribute('data-name') || 'Chat',
                    item.getAttribute('data-avatar') || ''
                );
                return;
            }
            openHeaderChatModal(
                conversationId,
                item.getAttribute('data-name') || 'Chat',
                item.getAttribute('data-avatar') || ''
            );
        });
        list.dataset.chatWired = 'true';
    }
    if (form && form.dataset.wired !== 'true') {
        form.addEventListener('submit', sendHeaderChatMessage);
        form.dataset.wired = 'true';
    }
    if (input && input.dataset.lineLimitWired !== 'true') {
        input.addEventListener('input', syncHeaderComposerLineLimit);
        input.addEventListener('paste', function (e) {
            var paste = (e.clipboardData || window.clipboardData);
            if (!paste) return;
            var incoming = paste.getData('text');
            if (incoming == null) return;
            e.preventDefault();
            var start = input.selectionStart || 0;
            var end = input.selectionEnd || 0;
            var value = input.value || '';
            input.value = clampHeaderMessageToMaxLines(value.slice(0, start) + incoming + value.slice(end));
            syncHeaderComposerLineLimit();
        });
        input.addEventListener('keydown', function (e) {
            var lines = countHeaderMessageLines(input.value || '');
            if (lines >= headerMessageMaxLines() && !isHeaderComposerNavKey(e)) {
                e.preventDefault();
            }
        });
        input.dataset.lineLimitWired = 'true';
    }
    // Enter inserts a new line only; send via the send button.
    if (document.documentElement.dataset.commsChatEscWired !== 'true') {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var modal = document.getElementById('commsChatModal');
            if (modal && !modal.hidden) {
                closeHeaderChatModal();
            }
        });
        document.documentElement.dataset.commsChatEscWired = 'true';
    }
}

window.openHeaderChatModal = openHeaderChatModal;
window.closeHeaderChatModal = closeHeaderChatModal;
window.openHeaderMessagesFullscreen = openHeaderMessagesFullscreen;
window.renderFullscreenChatList = renderFullscreenChatList;
window.reloadHeaderChatMessages = function (conversationId) {
    var id = conversationId || headerChatState.conversationId;
    if (!id) return;
    if (headerChatState.conversationId && Number(id) !== Number(headerChatState.conversationId)) {
        return;
    }
    loadHeaderChatMessages(headerChatState.conversationId || id);
};

function bootHeaderChatModal() {
    if (typeof wireHeaderChatModal === 'function') {
        wireHeaderChatModal();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootHeaderChatModal);
} else {
    bootHeaderChatModal();
}

})();
