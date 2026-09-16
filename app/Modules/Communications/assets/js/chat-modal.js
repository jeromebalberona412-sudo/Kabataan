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

function stopHeaderLocalTyping(conversationId) {
    var cid = conversationId != null ? conversationId : headerChatState.conversationId;
    if (!cid || !window.CommsRealtime) return;
    if (typeof window.CommsRealtime.stopTyping === 'function') {
        window.CommsRealtime.stopTyping(cid);
    } else if (typeof window.CommsRealtime.broadcastTyping === 'function') {
        window.CommsRealtime.broadcastTyping(cid, { stop: true });
    }
}

function notifyHeaderComposerTyping(rawValue) {
    var cid = headerChatState.conversationId;
    if (!cid || !window.CommsRealtime || typeof window.CommsRealtime.broadcastTyping !== 'function') {
        return;
    }
    var hasText = !!String(rawValue == null ? '' : rawValue).replace(/\s/g, '');
    if (!hasText) {
        stopHeaderLocalTyping(cid);
        return;
    }
    window.CommsRealtime.broadcastTyping(cid);
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

function formatCommsClockTime(iso) {
    if (window.Comms && typeof window.Comms.formatTime === 'function') {
        return window.Comms.formatTime(iso);
    }
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    var now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function formatCommsFullDateTime(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString([], {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

function formatCommsDaySeparator(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    var now = new Date();
    var time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
    if (d.toDateString() === now.toDateString()) {
        return time;
    }
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Yesterday ' + time;
    }
    var weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
    if (d >= weekAgo) {
        return d.toLocaleDateString([], { weekday: 'short' }) + ' ' + time;
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' + time;
}

function shouldInsertCommsDateSeparator(prevIso, currIso) {
    if (!currIso) return false;
    if (!prevIso) return true;
    var prev = new Date(prevIso);
    var curr = new Date(currIso);
    if (Number.isNaN(prev.getTime()) || Number.isNaN(curr.getTime())) return true;
    if (prev.toDateString() !== curr.toDateString()) return true;
    return Math.abs(curr.getTime() - prev.getTime()) >= 45 * 60 * 1000;
}

function renderCommsDateSeparator(iso) {
    var label = formatCommsDaySeparator(iso);
    if (!label) return '';
    return '<div class="comms-chat-date-sep" role="separator">' +
        '<span class="comms-chat-date-sep-text">' + escapeCommsHtml(label) + '</span>' +
        '</div>';
}

function defaultCommsAvatar(name) {
    var label = encodeURIComponent(String(name || 'U').slice(0, 40));
    return 'https://ui-avatars.com/api/?name=' + label + '&background=0450A8&color=fff';
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
    reactLocalUntil: {},
    pendingAttachments: [],
    pendingPreviewUrls: [],
    faqSuggestions: [],
    faqLoading: false,
    faqKnownEmpty: false,
    faqCooldownUntil: {},
    faqCooldownTimer: null,
    peerRefreshTimer: null,
    openRequestId: 0
};

function truncateCommsName(name, maxChars) {
    if (window.Comms && typeof window.Comms.truncateText === 'function') {
        return window.Comms.truncateText(name, maxChars || 34);
    }
    var text = String(name || '').trim();
    var max = maxChars || 34;
    if (text.length <= max) return text;
    return text.slice(0, max - 1) + '…';
}

function isCommsPeerOnline(peer) {
    if (window.Comms && typeof window.Comms.isUserOnline === 'function') {
        return window.Comms.isUserOnline(peer);
    }
    return String((peer && peer.online_status) || '').toLowerCase() === 'online';
}

function applyHeaderPeerHeader(peer, fallbackName) {
    peer = peer || {};
    var title = document.getElementById('commsChatModalTitle');
    var sub = document.getElementById('commsChatModalSub');
    var fullName = String(peer.name || fallbackName || 'Chat').trim() || 'Chat';
    if (title) {
        title.textContent = truncateCommsName(fullName, 34);
        title.setAttribute('title', fullName);
    }
    if (sub) {
        var online = isCommsPeerOnline(peer);
        var roleBits = [];
        if (peer.position) roleBits.push(String(peer.position).trim());
        if (peer.barangay_name) roleBits.push('Brgy. ' + String(peer.barangay_name).trim());
        var presence = online ? 'Online' : 'Offline';
        var line = roleBits.length ? (roleBits.join(' · ') + ' · ' + presence) : presence;
        sub.textContent = truncateCommsName(line, 48);
        sub.setAttribute('title', roleBits.length ? (roleBits.join(' · ') + ' · ' + presence) : presence);
        sub.classList.toggle('is-online', online);
        sub.classList.toggle('is-offline', !online);
    }
    if (peer.profile_image_url || fullName) {
        setHeaderChatAvatar(fullName, peer.profile_image_url || '');
    }
}

function findCachedHeaderPeer(conversationId) {
    var lists = [
        window.__COMMS_HEADER_CONVERSATIONS__,
        (window.Comms && window.Comms.readInboxCache) ? window.Comms.readInboxCache() : null
    ];
    for (var i = 0; i < lists.length; i++) {
        var list = lists[i];
        if (!Array.isArray(list)) continue;
        for (var j = 0; j < list.length; j++) {
            if (Number(list[j].id) === Number(conversationId)) {
                return list[j].other_user || null;
            }
        }
    }
    var cached = readHeaderChatCache(conversationId);
    if (cached && cached.conversation && cached.conversation.other_user) {
        return cached.conversation.other_user;
    }
    return null;
}

function stopHeaderPeerRefresh() {
    if (headerChatState.peerRefreshTimer) {
        window.clearInterval(headerChatState.peerRefreshTimer);
        headerChatState.peerRefreshTimer = null;
    }
}

function startHeaderPeerRefresh(conversationId) {
    stopHeaderPeerRefresh();
    if (!conversationId) return;
    headerChatState.peerRefreshTimer = window.setInterval(function () {
        if (Number(headerChatState.conversationId) !== Number(conversationId)) {
            stopHeaderPeerRefresh();
            return;
        }
        loadHeaderConversationPeer(conversationId);
    }, 15000);
}

function getCommsCsrfToken() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    return csrf ? csrf.getAttribute('content') : '';
}

function clearHeaderChatAttachments(opts) {
    opts = opts || {};
    if (!opts.keepObjectUrls) {
        headerChatState.pendingPreviewUrls.forEach(function (u) { if (u) URL.revokeObjectURL(u); });
    }
    headerChatState.pendingAttachments = [];
    headerChatState.pendingPreviewUrls = [];
    var preview = document.getElementById('commsChatAttachPreview');
    if (preview) preview.hidden = true;
    var inner = document.getElementById('commsChatAttachPreviewInner');
    if (inner) inner.innerHTML = '';
    syncHeaderComposerEndControls();
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
            var src = headerChatState.pendingPreviewUrls[i];
            return '<button type="button" class="comms-chat-attach-preview-item is-image" data-comms-lightbox-src="' +
                escapeCommsHtml(src) + '" aria-label="Zoom preview image">' +
                '<img src="' + escapeCommsHtml(src) + '" alt="preview"></button>';
        }
        return '<div class="comms-chat-attach-preview-item"><span class="file-icon"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span><span>' + escapeCommsHtml(f.name) + '</span></div>';
    }).join('');
    if (wrap.dataset.zoomWired !== 'true') {
        wrap.addEventListener('click', function (e) {
            var thumb = e.target.closest('[data-comms-lightbox-src]');
            if (!thumb || !wrap.contains(thumb)) return;
            e.preventDefault();
            e.stopPropagation();
            var images = Array.prototype.slice.call(wrap.querySelectorAll('[data-comms-lightbox-src]')).map(function (node) {
                return node.getAttribute('data-comms-lightbox-src') || '';
            }).filter(Boolean);
            var start = thumb.getAttribute('data-comms-lightbox-src') || '';
            if (start && window.Comms && typeof window.Comms.openImageLightbox === 'function') {
                if (Date.now() < (window.__commsIgnoreLightboxUntil || 0)) return;
                window.Comms.openImageLightbox(start, images);
            }
        });
        wrap.dataset.zoomWired = 'true';
    }
}

function pushHeaderFiles(files, kind) {
    var maxImg = 50, maxFile = 10, maxBytes = 25 * 1024 * 1024;
    var list = Array.prototype.slice.call(files || []);
    if (!list.length) return;

    var selectBytes = 0;
    var selectImg = 0;
    var selectFile = 0;
    list.forEach(function (f) {
        selectBytes += f.size || 0;
        if (isHeaderImageFile(f)) selectImg++;
        else selectFile++;
    });

    var pendingBytes = 0;
    var imgCount = 0;
    var fileCount = 0;
    headerChatState.pendingAttachments.forEach(function (f) {
        pendingBytes += f.size || 0;
        if (isHeaderImageFile(f)) imgCount++;
        else fileCount++;
    });

    if (selectBytes > maxBytes || (pendingBytes + selectBytes) > maxBytes) {
        var overMsg = 'Total attachment size cannot exceed 25 MB. Selection was not added.';
        if (window.Comms && typeof window.Comms.showToast === 'function') window.Comms.showToast(overMsg, 'error');
        else alert(overMsg);
        return;
    }
    if (kind === 'image' && (imgCount + selectImg) > maxImg) {
        var imgMsg = 'Max ' + maxImg + ' images (still within 25 MB). Selection was not added.';
        if (window.Comms && typeof window.Comms.showToast === 'function') window.Comms.showToast(imgMsg, 'error');
        else alert(imgMsg);
        return;
    }
    if (kind !== 'image' && (fileCount + selectFile) > maxFile) {
        var fileMsg = 'Max ' + maxFile + ' files. Selection was not added.';
        if (window.Comms && typeof window.Comms.showToast === 'function') window.Comms.showToast(fileMsg, 'error');
        else alert(fileMsg);
        return;
    }

    list.forEach(function (f) {
        headerChatState.pendingAttachments.push(f);
        headerChatState.pendingPreviewUrls.push(isHeaderImageFile(f) ? URL.createObjectURL(f) : null);
    });
    renderHeaderAttachPreview();
    syncHeaderComposerEndControls();
}

function headerFileKindClass(a) {
    var name = String((a && a.file_name) || '');
    var mime = String((a && a.mime_type) || '').toLowerCase();
    if (/\.pdf$/i.test(name) || mime === 'application/pdf') return ' is-pdf';
    if (/\.docx?$/i.test(name) || mime.indexOf('msword') !== -1 || mime.indexOf('wordprocessingml') !== -1) return ' is-word';
    return '';
}

function formatHeaderFileBytes(n) {
    n = Number(n || 0);
    if (!n) return '';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(2) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
}

function isHeaderImageAttachment(a) {
    if (!a) return false;
    return a.file_type === 'image'
        || a.storage_provider === 'cloudinary'
        || (a.url && /^image\//i.test(String(a.mime_type || '')))
        || /\.(jpe?g|png|gif|webp|bmp|heic)$/i.test(String(a.file_name || ''));
}

function isHeaderImageOnlyMessage(msg) {
    if (!isHeaderAttachmentOnlyMessage(msg)) return false;
    var atts = msg.attachments || [];
    if (!atts.length) return false;
    return atts.every(isHeaderImageAttachment);
}

function headerSameImageBatch(a, b) {
    if (!isHeaderImageOnlyMessage(a) || !isHeaderImageOnlyMessage(b)) return false;
    if (!!a.mine !== !!b.mine) return false;
    if (a.batch_id && b.batch_id) return String(a.batch_id) === String(b.batch_id);
    if (a.batch_id || b.batch_id) return false;
    var t1 = Date.parse(a.created_at || '') || 0;
    var t2 = Date.parse(b.created_at || '') || 0;
    return !!(t1 && t2 && Math.abs(t2 - t1) <= 4000);
}

function headerImageBatchEndIndex(items, start) {
    var end = start;
    while (end + 1 < items.length && headerSameImageBatch(items[start], items[end + 1])) {
        end += 1;
    }
    return end;
}

function collectHeaderImageAttachments(messages) {
    var out = [];
    (messages || []).forEach(function (m) {
        (m.attachments || []).forEach(function (a) {
            if (!isHeaderImageAttachment(a)) return;
            var src = a.url || a.file_path || a.download_url || '';
            if (!src) return;
            out.push({ src: src, file_name: a.file_name || 'image', message: m, attachment: a });
        });
    });
    return out;
}

function renderHeaderAttachmentBubbles(attachments, opts) {
    opts = opts || {};
    if (!attachments || !attachments.length) return '';
    var images = [];
    var files = [];
    attachments.forEach(function (a) {
        if (isHeaderImageAttachment(a)) images.push(a);
        else files.push(a);
    });

    var html = '';
    if (images.length) {
        var countAttr = Math.min(images.length, 9);
        html += '<div class="comms-chat-attachment-grid" data-count="' + countAttr + '"' +
            (images.length > 9 ? ' data-extra="' + (images.length - 9) + '"' : '') + '>';
        images.forEach(function (a, i) {
            if (i >= 9) return;
            var src = a.url || a.file_path || a.download_url || '';
            var extra = (i === 8 && images.length > 9)
                ? '<span class="comms-chat-attachment-more">+' + (images.length - 9) + '</span>'
                : '';
            html += '<button type="button" class="comms-chat-attachment-image-btn" data-comms-lightbox-src="' +
                escapeCommsHtml(src) + '" aria-label="View image fullscreen">' +
                '<img src="' + escapeCommsHtml(src) + '" alt="' + escapeCommsHtml(a.file_name || 'image') + '" loading="lazy">' +
                extra +
                '</button>';
        });
        // Keep remaining images in DOM (hidden) so conversation lightbox can include them.
        if (images.length > 9) {
            images.slice(9).forEach(function (a) {
                var src = a.url || a.file_path || a.download_url || '';
                html += '<button type="button" class="comms-chat-attachment-image-btn is-overflow-hidden" tabindex="-1" aria-hidden="true" data-comms-lightbox-src="' +
                    escapeCommsHtml(src) + '"><img src="' + escapeCommsHtml(src) + '" alt=""></button>';
            });
        }
        html += '</div>';
    }

    files.forEach(function (a) {
        var dlUrl = a.download_url || '#';
        var sizeLabel = formatHeaderFileBytes(a.file_size);
        html += '<a href="' + escapeCommsHtml(dlUrl) + '" class="comms-chat-attachment-file' + headerFileKindClass(a) + '" download="' + escapeCommsHtml(a.file_name || 'document') + '">' +
            '<span class="comms-chat-attachment-file-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>' +
            '<span class="comms-chat-attachment-file-meta"><span class="comms-chat-attachment-file-name">' + escapeCommsHtml(a.file_name || 'file') + '</span>' +
            (sizeLabel ? '<span class="comms-chat-attachment-file-size">' + escapeCommsHtml(sizeLabel) + '</span>' : '') +
            '</span></a>';
    });
    return html;
}

function renderHeaderImageBatchBubble(groupMsgs) {
    var images = collectHeaderImageAttachments(groupMsgs);
    if (!images.length) return '';
    var last = groupMsgs[groupMsgs.length - 1];
    var mine = !!last.mine;
    var sideCls = mine ? 'is-mine' : 'is-theirs';
    var pickerOpen = Number(headerChatState.openReactionPickerId) === Number(last.id);
    var isLocalMsg = String(last.id || '').indexOf('local-') === 0;
    var showMsgActions = !!mine && !isLocalMsg;
    var batchAttr = last.batch_id ? ' data-batch-id="' + escapeCommsHtml(String(last.batch_id)) + '"' : '';
    var countAttr = Math.min(images.length, 9);
    var grid = '<div class="comms-chat-attachment-grid" data-count="' + countAttr + '"' +
        (images.length > 9 ? ' data-extra="' + (images.length - 9) + '"' : '') + '>';
    images.forEach(function (item, i) {
        if (i >= 9) {
            grid += '<button type="button" class="comms-chat-attachment-image-btn is-overflow-hidden" tabindex="-1" aria-hidden="true" data-comms-lightbox-src="' +
                escapeCommsHtml(item.src) + '"><img src="' + escapeCommsHtml(item.src) + '" alt=""></button>';
            return;
        }
        var extra = (i === 8 && images.length > 9)
            ? '<span class="comms-chat-attachment-more">+' + (images.length - 9) + '</span>'
            : '';
        grid += '<button type="button" class="comms-chat-attachment-image-btn" data-comms-lightbox-src="' +
            escapeCommsHtml(item.src) + '" aria-label="View image fullscreen">' +
            '<img src="' + escapeCommsHtml(item.src) + '" alt="' + escapeCommsHtml(item.file_name) + '" loading="lazy">' +
            extra + '</button>';
    });
    grid += '</div>';

    var html = '<div class="comms-chat-bubble-wrap ' + sideCls + (pickerOpen ? ' is-picker-open' : '') +
        ' is-image-batch" data-id="' + last.id + '" data-batch-size="' + images.length + '"' + batchAttr + '>' +
        '<div class="comms-chat-bubble-row">' +
        '<div class="comms-chat-bubble-tools">' +
        (showMsgActions ? renderHeaderMessageActions(last) : '') +
        '<button type="button" class="comms-chat-react-btn" data-react-toggle="' + last.id +
        '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' +
        headerReactBtnIcon + '</button>' +
        '</div>' +
        '<div class="comms-chat-bubble-cluster">' +
        '<div class="comms-chat-bubble ' + sideCls + ' has-attachment-only">' +
        grid +
        '</div>' +
        '</div></div>' +
        renderHeaderReactionChips(last.reactions, last.id) +
        '</div>';

    // Hidden anchors so each batched message remains addressable for delete/edit handlers by data-id.
    groupMsgs.slice(0, -1).forEach(function (m) {
        html += '<div class="comms-chat-batch-anchor" hidden data-id="' + m.id + '" aria-hidden="true"></div>';
    });
    return html;
}

function isHeaderAttachmentOnlyMessage(msg) {
    if (!msg) return false;
    if (msg.message_type === 'call' || msg.message_type === 'system' || msg.message_type === 'deleted' || msg.deleted_for_all) return false;
    var hasAttachments = Array.isArray(msg.attachments) && msg.attachments.length > 0;
    return hasAttachments && !String(msg.body || '').trim();
}

function headerBatchClassForIndex(items, index) {
    var msg = items[index];
    if (!isHeaderAttachmentOnlyMessage(msg)) return '';
    var prev = items[index - 1];
    var next = items[index + 1];
    var samePrev = isHeaderAttachmentOnlyMessage(prev)
        && !!prev.mine === !!msg.mine
        && String(prev.batch_id || '') === String(msg.batch_id || '')
        && String(msg.batch_id || '') !== '';
    var sameNext = isHeaderAttachmentOnlyMessage(next)
        && !!next.mine === !!msg.mine
        && String(next.batch_id || '') === String(msg.batch_id || '')
        && String(msg.batch_id || '') !== '';
    // Also tighten consecutive attachment-only messages without batch_id (server reload).
    if (!msg.batch_id) {
        samePrev = isHeaderAttachmentOnlyMessage(prev) && !!prev.mine === !!msg.mine;
        sameNext = isHeaderAttachmentOnlyMessage(next) && !!next.mine === !!msg.mine;
    }
    if (!samePrev && !sameNext) return '';
    var cls = ' is-batch-attach';
    if (!samePrev && sameNext) cls += ' is-batch-first';
    else if (samePrev && sameNext) cls += ' is-batch-mid';
    else if (samePrev && !sameNext) cls += ' is-batch-last';
    return cls;
}

function setHeaderMessagesBtnActive(active) {
    var btn = document.getElementById('commsMsgBtn');
    if (!btn) return;
    var onMessagesPage = !!document.body.classList.contains('comms-messages-page')
        || /^\/communications\/?$/.test(window.location.pathname || '');
    // Stay selected while on See All Messages.
    if (onMessagesPage) {
        btn.classList.add('is-active');
        btn.setAttribute('aria-pressed', 'true');
        return;
    }
    btn.classList.toggle('is-active', !!active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
}

function onCommsHeaderPresence(presenceMap) {
    var onlineIds = {};
    Object.keys(presenceMap || {}).forEach(function (key) {
        var metas = presenceMap[key] || [];
        if (!metas.length) {
            var keyId = Number(key || 0);
            if (keyId) onlineIds[keyId] = true;
            return;
        }
        metas.forEach(function (meta) {
            var uid = Number((meta && meta.user_id) || key || 0);
            if (uid) onlineIds[uid] = true;
        });
    });
    window.__COMMS_PRESENCE_ONLINE_IDS__ = onlineIds;
    if (!headerChatState.conversationId) return;
    var peer = findCachedHeaderPeer(headerChatState.conversationId);
    if (!peer || !peer.id) return;
    var next = onlineIds[Number(peer.id)] ? 'online' : 'offline';
    peer.online_status = next;
    peer.is_online = next === 'online';
    if (next === 'online') peer.last_seen = new Date().toISOString();
    applyHeaderPeerHeader(peer, peer.name || 'Chat');
}

function closeHeaderChatModal() {
    var modal = document.getElementById('commsChatModal');
    if (!modal) return;
    stopHeaderLocalTyping();
    stopHeaderPeerRefresh();
    modal.hidden = true;
    modal.setAttribute('hidden', '');
    modal.classList.remove('is-fullscreen', 'is-empty-thread', 'is-thread-open');
    document.body.classList.remove('comms-chat-modal-open');
    headerChatState.conversationId = null;
    headerChatState.messages = [];
    headerChatState.faqSuggestions = [];
    headerChatState.faqLoading = false;
    headerChatState.faqKnownEmpty = false;
    headerChatState.fullscreen = false;
    window.__COMMS_HEADER_ACTIVE_ID__ = null;
    setHeaderMessagesBtnActive(false);
    var typingEl = document.getElementById('commsChatModalTyping');
    if (typingEl) {
        typingEl.hidden = true;
        typingEl.textContent = '';
    }
    hideHeaderReactionPicker();
    clearHeaderChatAttachments();
    var faqPanel = document.getElementById('commsChatFaqSuggestions');
    var faqList = document.getElementById('commsChatFaqSuggestionsList');
    if (faqPanel) faqPanel.hidden = true;
    if (faqList) faqList.innerHTML = '';
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

function renderFullscreenChatList(conversations, officials) {
    var list = document.getElementById('commsChatFsList');
    var empty = document.getElementById('commsChatFsListEmpty');
    if (!list) return;

    var items = Array.isArray(conversations) ? conversations.slice() : [];
    items.sort(function (a, b) {
        var ta = Date.parse((a && a.updated_at) || '') || 0;
        var tb = Date.parse((b && b.updated_at) || '') || 0;
        return tb - ta;
    });
    var officialList = Array.isArray(officials)
        ? officials
        : (Array.isArray(window.__COMMS_HEADER_OFFICIALS__) ? window.__COMMS_HEADER_OFFICIALS__ : []);
    window.__COMMS_HEADER_CONVERSATIONS__ = items;
    if (Array.isArray(officials)) {
        window.__COMMS_HEADER_OFFICIALS__ = officialList;
    }

    var chatPeerIds = {};
    items.forEach(function (c) {
        var peerId = Number((c.other_user && c.other_user.id) || 0);
        if (peerId) chatPeerIds[peerId] = true;
    });
    var starters = officialList.filter(function (user) {
        var id = Number(user && user.id || 0);
        return id && !chatPeerIds[id];
    });

    if (!items.length && !starters.length) {
        list.innerHTML = '';
        if (empty) {
            empty.hidden = false;
            empty.innerHTML = '<p>No conversations yet</p>';
        }
        return;
    }

    if (empty) empty.hidden = true;
    list.innerHTML = items.map(function (c) {
        var peer = c.other_user || {};
        var fullName = peer.name || 'User';
        var name = truncateCommsName(fullName, 28);
        var previewRaw = (c.last_message && c.last_message.body) || 'No messages yet';
        var preview = truncateCommsName(previewRaw, 40);
        var unread = Number(c.unread_count || 0);
        var avatar = peer.profile_image_url || defaultCommsAvatar(fullName);
        var searchBlob = String(fullName + ' ' + previewRaw).toLowerCase();
        var active = Number(c.id) === Number(headerChatState.conversationId) ? ' is-active' : '';
        return '<button type="button" class="comms-chat-fs-item' + active + (unread > 0 ? ' is-unread' : '') + '" data-id="' + c.id + '" data-name="' + escapeCommsHtml(fullName) + '" data-avatar="' + escapeCommsHtml(avatar) + '" data-unread="' + (unread > 0 ? '1' : '0') + '" data-search="' + escapeCommsHtml(searchBlob) + '" role="listitem" title="' + escapeCommsHtml(fullName) + '">' +
            '<img class="comms-chat-fs-item-avatar" src="' + escapeCommsHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(fullName)) + '\'">' +
            '<div class="comms-chat-fs-item-main">' +
            '<span class="comms-chat-fs-item-name">' + escapeCommsHtml(name) + '</span>' +
            '<span class="comms-chat-fs-item-preview">' + escapeCommsHtml(preview) + '</span>' +
            '</div>' +
            '<div class="comms-chat-fs-item-meta">' +
            '<span class="comms-chat-fs-item-time">' + escapeCommsHtml(formatCommsRelativeTime(c.updated_at)) + '</span>' +
            (unread > 0 ? '<span class="comms-chat-fs-item-badge">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
            '</div>' +
            '</button>';
    }).join('') + starters.map(function (user) {
        var fullName = user.name || 'User';
        var name = truncateCommsName(fullName, 28);
        var previewRaw = user.position || user.user_type_label || 'SK Official';
        var preview = truncateCommsName(previewRaw, 40);
        var avatar = user.profile_image_url || defaultCommsAvatar(fullName);
        var searchBlob = String(fullName + ' ' + previewRaw).toLowerCase();
        return '<button type="button" class="comms-chat-fs-item" data-user-id="' + escapeCommsHtml(user.id) + '" data-name="' + escapeCommsHtml(fullName) + '" data-avatar="' + escapeCommsHtml(avatar) + '" data-unread="0" data-search="' + escapeCommsHtml(searchBlob) + '" role="listitem" title="' + escapeCommsHtml(fullName) + '">' +
            '<img class="comms-chat-fs-item-avatar" src="' + escapeCommsHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + escapeCommsHtml(defaultCommsAvatar(fullName)) + '\'">' +
            '<div class="comms-chat-fs-item-main">' +
            '<span class="comms-chat-fs-item-name">' + escapeCommsHtml(name) + '</span>' +
            '<span class="comms-chat-fs-item-preview">' + escapeCommsHtml(preview) + '</span>' +
            '</div></button>';
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

function syncHeaderComposerEndControls() {
    var input = document.getElementById('commsChatModalInput');
    var sendBtn = document.getElementById('commsChatModalSend');
    var composerEnd = document.querySelector('#commsChatModalForm .comms-chat-composer-end');
    var hasText = !!(input && String(input.value || '').trim());
    var hasFiles = !!(headerChatState.pendingAttachments && headerChatState.pendingAttachments.length);
    var canSend = hasText || hasFiles;

    // Send button is always visible; disabled only when empty / in-flight.
    if (sendBtn) {
        sendBtn.hidden = false;
        sendBtn.removeAttribute('hidden');
        sendBtn.disabled = headerChatState.sending || !canSend;
        sendBtn.setAttribute('aria-hidden', 'false');
    }
    if (composerEnd) {
        composerEnd.classList.add('has-send');
        composerEnd.classList.remove('has-faq');
    }
}

function clearHeaderConversationUnreadLocal(conversationId) {
    var cid = Number(conversationId);
    if (!cid) return 0;
    var cleared = 0;
    var list = Array.isArray(window.__COMMS_HEADER_CONVERSATIONS__)
        ? window.__COMMS_HEADER_CONVERSATIONS__
        : [];
    list.forEach(function (c) {
        if (Number(c.id) !== cid) return;
        cleared = Number(c.unread_count || 0);
        c.unread_count = 0;
    });
    if (window.Comms && typeof window.Comms.writeInboxCache === 'function') {
        window.Comms.writeInboxCache(list);
    }
    if (typeof window.renderFullscreenChatList === 'function') {
        window.renderFullscreenChatList(list, window.__COMMS_HEADER_OFFICIALS__ || []);
    }
    if (typeof window.refreshMessagesPopover === 'function') {
        // Instant paint from updated local list (no wait for network).
        try {
            var paintFn = window.__COMMS_PAINT_MESSAGES_POPOVER__;
            if (typeof paintFn === 'function') {
                paintFn(list, window.__COMMS_HEADER_OFFICIALS__ || []);
            } else {
                window.refreshMessagesPopover();
            }
        } catch (e) {
            window.refreshMessagesPopover();
        }
    }
    var badge = document.getElementById('commsMsgBadge');
    if (badge && cleared > 0) {
        var next = Math.max(0, Number(badge.dataset.unreadTotal || 0) - cleared);
        badge.dataset.unreadTotal = String(next);
        badge.textContent = next > 99 ? '99+' : String(next);
        badge.style.display = next > 0 ? '' : 'none';
        if (next <= 0) badge.setAttribute('hidden', 'hidden');
        else badge.removeAttribute('hidden');
    }
    return cleared;
}

function syncHeaderExpandButtonVisibility() {
    // Expand/fullscreen control removed — keep as no-op for callers.
}

function storeMessagesReturnUrl() {
    try {
        var path = String(window.location.pathname || '') + String(window.location.search || '');
        if (!/^\/communications(\/|$)/.test(window.location.pathname || '')) {
            sessionStorage.setItem('comms_messages_return_url', path || '/dashboard');
        }
    } catch (e) { /* ignore */ }
}

function goToSeeAllMessages(conversationId) {
    storeMessagesReturnUrl();
    var cid = conversationId || headerChatState.conversationId || null;
    try {
        if (cid) {
            sessionStorage.setItem('comms_boot_conversation', String(cid));
        }
        // Warm the destination document so navigation feels instant.
        var warm = document.createElement('link');
        warm.rel = 'prefetch';
        warm.href = '/communications' + (cid ? ('?conversation=' + encodeURIComponent(cid)) : '');
        document.head.appendChild(warm);
    } catch (e) { /* ignore */ }
    var url = '/communications';
    if (cid) {
        url += '?conversation=' + encodeURIComponent(cid);
    }
    // location.replace is slightly faster (no history push delay) when coming from modal.
    window.location.replace(url);
}

function clearHeaderChatFullscreenUi() {
    var modal = document.getElementById('commsChatModal');
    var sidebar = document.getElementById('commsChatFsSidebar');
    headerChatState.fullscreen = false;
    if (modal) {
        modal.classList.remove('is-fullscreen', 'is-empty-thread');
        modal.classList.toggle('is-thread-open', !!headerChatState.conversationId);
    }
    if (sidebar) sidebar.hidden = true;
}

function refreshHeaderFullscreenChatList() {
    if (Array.isArray(window.__COMMS_HEADER_CONVERSATIONS__)) {
        renderFullscreenChatList(window.__COMMS_HEADER_CONVERSATIONS__, window.__COMMS_HEADER_OFFICIALS__ || []);
    }
}

function setHeaderChatFullscreen() {
    // Fullscreen mode removed — always keep the small floating dock.
    clearHeaderChatFullscreenUi();
}

function openHeaderMessagesFullscreen(conversationId) {
    goToSeeAllMessages(conversationId || null);
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
    // See All Messages already has the full chat UI — never overlay the floating dock.
    if (document.body.classList.contains('comms-messages-page')
        || /^\/communications\/?$/.test(window.location.pathname || '')) {
        return;
    }
    var modal = document.getElementById('commsChatModal');
    var input = document.getElementById('commsChatModalInput');
    var body = document.getElementById('commsChatModalBody');
    if (!modal || !conversationId) return;

    closeMessagesPopover();
    hideHeaderReactionPicker();
    var switching = !!(headerChatState.conversationId
        && Number(headerChatState.conversationId) !== Number(conversationId));
    if (switching) {
        stopHeaderLocalTyping(headerChatState.conversationId);
    }
    headerChatState.conversationId = Number(conversationId);
    headerChatState.openRequestId = (headerChatState.openRequestId || 0) + 1;
    var openRequestId = headerChatState.openRequestId;
    window.__COMMS_HEADER_ACTIVE_ID__ = headerChatState.conversationId;
    setHeaderMessagesBtnActive(true);
    var typingEl = document.getElementById('commsChatModalTyping');
    if (typingEl) {
        typingEl.hidden = true;
        typingEl.textContent = '';
    }

    var cachedPeer = findCachedHeaderPeer(conversationId) || {
        name: name || 'Chat',
        profile_image_url: avatarUrl || '',
        is_online: false,
        online_status: 'offline'
    };
    applyHeaderPeerHeader(cachedPeer, name || 'Chat');
    if (input) {
        input.value = '';
    }

    // Prefer FAQ cache so chips do not flash hide/show when switching officials.
    var faqCached = readHeaderFaqCache(conversationId);
    var faqCachedList = (faqCached && Array.isArray(faqCached.faqs)) ? faqCached.faqs : null;
    if (faqCachedList && faqCachedList.length) {
        headerChatState.faqSuggestions = faqCachedList;
        headerChatState.faqLoading = false;
        headerChatState.faqKnownEmpty = false;
    } else {
        headerChatState.faqSuggestions = [];
        headerChatState.faqLoading = true;
        headerChatState.faqKnownEmpty = false;
    }
    renderHeaderFaqChips();
    syncHeaderComposerEndControls();

    var cached = readHeaderChatCache(conversationId);
    if (cached && Array.isArray(cached.messages) && cached.messages.length) {
        var cachedEmojis = cached.reactionEmojis || cached.reaction_emojis;
        if (Array.isArray(cachedEmojis) && cachedEmojis.length) {
            headerChatState.reactionEmojis = cachedEmojis;
        }
        headerChatState.messages = cached.messages;
        renderHeaderChatMessages(cached.messages);
        if (cached.conversation && cached.conversation.other_user) {
            applyHeaderPeerHeader(cached.conversation.other_user, name || 'Chat');
        }
    } else {
        headerChatState.messages = [];
        if (body) {
            body.innerHTML = '<p class="comms-chat-modal-empty">No messages yet. Say hello.</p>';
        }
    }

    // Show the floating dock (Messenger-style small modal).
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = '';
    document.body.classList.add('comms-chat-modal-open');
    clearHeaderChatFullscreenUi();
    setHeaderChatEmptyThread(false);
    syncHeaderComposerEndControls();
    // Pin to newest messages after the dock is visible (cache paint may have scrolled while hidden).
    jumpHeaderChatToLatest();

    // Clear unread indicator instantly (0s), then confirm with API.
    clearHeaderConversationUnreadLocal(conversationId);
    markHeaderConversationRead(conversationId);

    // Subscribe immediately so new messages appear while history loads.
    if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
        window.CommsRealtime.subscribeConversation(conversationId);
    }
    loadHeaderChatMessages(conversationId);
    loadHeaderFaqSuggestions(conversationId);
    loadHeaderConversationPeer(conversationId);
    startHeaderPeerRefresh(conversationId);
    window.setTimeout(function () {
        jumpHeaderChatToLatest();
        if (input) {
            input.focus();
            syncHeaderComposerLineLimit();
            syncHeaderComposerEndControls();
        }
    }, 0);
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
        applyHeaderPeerHeader(peer, peer.name || 'Chat');
        var cached = readHeaderChatCache(conversationId) || {};
        writeHeaderChatCache(conversationId, Object.assign({}, cached, {
            messages: headerChatState.messages,
            reactionEmojis: headerChatState.reactionEmojis,
            conversation: data.conversation
        }));
        if (Array.isArray(window.__COMMS_HEADER_CONVERSATIONS__)) {
            window.__COMMS_HEADER_CONVERSATIONS__.forEach(function (c) {
                if (Number(c.id) === Number(conversationId) && data.conversation.other_user) {
                    c.other_user = data.conversation.other_user;
                }
            });
        }
        if (window.Comms && typeof window.Comms.writeInboxCache === 'function' && Array.isArray(window.__COMMS_HEADER_CONVERSATIONS__)) {
            window.Comms.writeInboxCache(window.__COMMS_HEADER_CONVERSATIONS__);
        }
    }).catch(function () { /* keep the values already shown */ });
}

function wireFullscreenChatControls() {
    var searchInput = document.getElementById('commsChatFsSearch');
    var chips = document.querySelectorAll('.comms-chat-fs-filter');
    var list = document.getElementById('commsChatFsList');
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
        list.dataset.fsWired = 'true';
    }
    if (backBtn && backBtn.dataset.wired !== 'true') {
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeHeaderChatModal();
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
    var mid = Number(messageId) || 0;
    if (!mid || !triggerBtn) {
        hideHeaderReactionPicker();
        return;
    }
    if (Number(headerChatState.openReactionPickerId) === mid) {
        hideHeaderReactionPicker();
        return;
    }
    headerChatState.openReactionPickerId = mid;
    var msg = headerChatState.messages.find(function (m) { return Number(m.id) === mid; });
    var mineEmoji = '';
    if (msg && Array.isArray(msg.reactions)) {
        msg.reactions.forEach(function (r) {
            if (r && r.mine) mineEmoji = String(r.emoji || '');
        });
    }
    var el = getHeaderReactionPickerEl();
    el.setAttribute('data-react-picker', String(headerChatState.openReactionPickerId));
    el.innerHTML = headerChatState.reactionEmojis.map(function (emoji) {
        var active = mineEmoji && emoji === mineEmoji ? ' is-active' : '';
        return '<button type="button" class="comms-chat-reaction-pick' + active + '" data-react-emoji="' +
            escapeCommsHtml(emoji) + '" role="menuitem" aria-pressed="' + (active ? 'true' : 'false') + '">' +
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
    var canEdit = !deleted && !!msg.mine && msg.message_type === 'text' && String(msg.body || '').trim() !== '';
    var items = '';
    if (canEdit) {
        items += '<button type="button" role="menuitem" data-msg-edit="' + msg.id + '">Edit</button>';
    }
    items += '<button type="button" role="menuitem" data-msg-delete="' + msg.id + '">Delete</button>';
    var el = getHeaderMsgMenuEl();
    el.innerHTML = items;
    el.setAttribute('data-msg-menu', String(msg.id));
    el.hidden = false;
    document.querySelectorAll('#commsChatModalBody .comms-chat-bubble-wrap').forEach(function (wrap) {
        var isOpen = String(wrap.getAttribute('data-id')) === String(msg.id);
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

function headerMessageMaxChars() {
    return (window.CommsChat && Number(window.CommsChat.messageMaxLength)) || 1000;
}

function headerMeasuredLength(text) {
    var input = document.getElementById('commsChatModalInput');
    var cpl = (window.Comms && window.Comms.estimateComposerCharsPerLine)
        ? window.Comms.estimateComposerCharsPerLine(input)
        : 36;
    if (window.Comms && typeof window.Comms.measureMessageLength === 'function') {
        return window.Comms.measureMessageLength(text, cpl);
    }
    return String(text == null ? '' : text).length;
}

function syncHeaderComposerLineLimit() {
    var input = document.getElementById('commsChatModalInput');
    if (!input) return 0;
    if (window.Comms && typeof window.Comms.resizeGrowTextarea === 'function') {
        // Header dock: 3 visible lines, then scroll.
        window.Comms.resizeGrowTextarea(input, 3, 1);
    }
    var len = headerMeasuredLength(input.value || '');
    var max = headerMessageMaxChars();
    var atLimit = len >= max;
    input.classList.toggle('is-line-limit', atLimit);
    input.setAttribute('aria-invalid', atLimit ? 'true' : 'false');
    return len;
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
    var body = String(input.value || '');
    if (body === '') {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message cannot be empty.', 'error');
        }
        return;
    }
    var maxChars = headerMessageMaxChars();
    if (headerMeasuredLength(body) > maxChars) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message exceeds the ' + maxChars + ' character limit.', 'error');
        }
        return;
    }
    var saveBtn = bodyEl.querySelector('[data-edit-save="' + messageId + '"]');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.classList.add('is-loading');
        saveBtn.setAttribute('aria-busy', 'true');
        if (!saveBtn.dataset.label) saveBtn.dataset.label = saveBtn.textContent || 'Save';
        saveBtn.textContent = 'Saving';
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
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('is-loading');
            saveBtn.removeAttribute('aria-busy');
            saveBtn.textContent = saveBtn.dataset.label || 'Save';
        }
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

function headerLatestMineMessageId(items) {
    for (var i = items.length - 1; i >= 0; i -= 1) {
        if (items[i] && items[i].mine && items[i].message_type !== 'call' && items[i].message_type !== 'system') {
            return String(items[i].id);
        }
    }
    return null;
}

function renderHeaderSendStatus(msg, isLatestMine) {
    if (!msg || !msg.mine || !isLatestMine) return '';
    var status = msg.send_status || '';
    if (!status && String(msg.id || '').indexOf('local-') === 0) status = 'sending';
    if (status !== 'sending' && status !== 'sent' && status !== 'failed') return '';
    var label = status === 'sending' ? 'Sending...' : (status === 'sent' ? 'Sent' : 'Failed');
    return '<div class="comms-send-status is-' + status + '" role="status">' + label + '</div>';
}

function renderHeaderChatMessages(messages, opts) {
    opts = opts || {};
    var body = document.getElementById('commsChatModalBody');
    if (!body) return;
    var prevScroll = body.scrollTop;
    var items = sortHeaderMessagesAscending(messages);
    headerChatState.messages = items;
    if (!items.length) {
        hideHeaderReactionPicker();
        body.innerHTML = '<p class="comms-chat-modal-empty">No messages yet. Say hello.</p>';
        return;
    }
    var parts = [];
    var skipUntil = -1;
    var prevCreatedAt = null;
    var latestIndex = items.length - 1;
    var latestMineId = headerLatestMineMessageId(items);
    items.forEach(function (msg, index) {
        if (index <= skipUntil) return;

        var sepAt = msg.created_at;
        var insertedSep = false;
        if (shouldInsertCommsDateSeparator(prevCreatedAt, sepAt)) {
            parts.push(renderCommsDateSeparator(sepAt));
            insertedSep = true;
        }
        // Always show the newest chat date near the bottom (latest message / batch).
        if (index === latestIndex && !insertedSep) {
            parts.push(renderCommsDateSeparator(sepAt || prevCreatedAt));
        }

        if (msg.message_type === 'call' || msg.message_type === 'system') {
            var callCls = headerCallBubbleClass(msg.body);
            parts.push('<div class="comms-chat-bubble-wrap ' + (msg.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + msg.id + '" title="' + escapeCommsHtml(formatCommsFullDateTime(msg.created_at)) + '">' +
                '<div class="comms-chat-bubble-row comms-chat-bubble-row--call">' +
                '<div class="comms-chat-bubble-cluster">' +
                '<div class="comms-chat-bubble ' + callCls + '">' +
                headerCallEventIcon(msg.body) +
                '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body || '') + '</div>' +
                '</div>' +
                '</div></div></div>');
            prevCreatedAt = msg.created_at;
            return;
        }
        var mine = Boolean(msg.mine);
        var pickerOpen = Number(headerChatState.openReactionPickerId) === Number(msg.id);
        var editing = Number(headerChatState.editingId) === Number(msg.id);
        if (msg.deleted_for_all || msg.message_type === 'deleted') {
            parts.push('<div class="comms-chat-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + '" data-id="' + msg.id + '" title="' + escapeCommsHtml(formatCommsFullDateTime(msg.created_at)) + '">' +
                '<div class="comms-chat-bubble-row">' +
                '<div class="comms-chat-bubble comms-chat-bubble--deleted">This message was deleted</div>' +
                renderHeaderMessageActions(msg) +
                '</div></div>');
            prevCreatedAt = msg.created_at;
            return;
        }

        if (!editing && isHeaderImageOnlyMessage(msg)) {
            var batchEnd = headerImageBatchEndIndex(items, index);
            var group = items.slice(index, batchEnd + 1);
            var batchImages = collectHeaderImageAttachments(group);
            if (batchImages.length >= 2) {
                if (batchEnd === latestIndex && !insertedSep) {
                    parts.push(renderCommsDateSeparator(sepAt || prevCreatedAt));
                }
                parts.push(renderHeaderImageBatchBubble(group));
                var batchLast = group[group.length - 1];
                if (batchLast && latestMineId != null && String(batchLast.id) === latestMineId) {
                    parts.push(renderHeaderSendStatus(batchLast, true));
                }
                skipUntil = batchEnd;
                prevCreatedAt = (group[group.length - 1] && group[group.length - 1].created_at) || msg.created_at;
                return;
            }
        }

        var bodyHtml = '';
        var bubbleContent = '';
        if (editing) {
            var maxLen = headerMessageMaxChars();
            bubbleContent =
                '<div class="comms-chat-edit-panel" role="group" aria-label="Edit message">' +
                '<textarea class="comms-chat-edit-input" data-edit-input="' + msg.id + '" maxlength="' + maxLen + '" rows="3">' +
                escapeCommsHtml(msg.body || '') +
                '</textarea>' +
                '<div class="comms-chat-edit-footer">' +
                '<div class="comms-chat-edit-actions">' +
                '<button type="button" class="comms-chat-edit-cancel" data-edit-cancel="' + msg.id + '">Cancel</button>' +
                '<button type="button" class="comms-chat-edit-save" data-edit-save="' + msg.id + '">Save</button>' +
                '</div></div></div>';
        } else if (msg.body) {
            bodyHtml = '<div class="comms-chat-bubble-text">' + escapeCommsHtml(msg.body) +
                (msg.edited ? ' <em class="comms-chat-edited">edited</em>' : '') + '</div>';
        }
        var isAutomation = msg.message_type === 'automation';
        var hasAttachments = Array.isArray(msg.attachments) && msg.attachments.length > 0;
        var hasTextBody = !!String(msg.body || '').trim() || editing;
        var attachmentOnly = hasAttachments && !hasTextBody;
        var splitAttachText = hasAttachments && hasTextBody && !editing;
        var batchClass = headerBatchClassForIndex(items, index);
        var batchSize = 0;
        if (batchClass.indexOf('is-batch-attach') !== -1) {
            var bStart = index;
            while (bStart > 0 && headerBatchClassForIndex(items, bStart - 1)) bStart -= 1;
            var bEnd = index;
            while (bEnd + 1 < items.length && headerBatchClassForIndex(items, bEnd + 1)) bEnd += 1;
            batchSize = bEnd - bStart + 1;
        }
        var batchAttr = (msg.batch_id ? ' data-batch-id="' + escapeCommsHtml(String(msg.batch_id)) + '"' : '') +
            (batchSize ? ' data-batch-size="' + batchSize + '"' : '');
        var isLocalMsg = String(msg.id || '').indexOf('local-') === 0;
        var showMsgActions = !isAutomation && !!mine && !isLocalMsg && !editing;
        var sideCls = mine ? 'is-mine' : 'is-theirs';
        var autoCls = isAutomation ? ' comms-bubble--automation' : '';
        var attachHtml = renderHeaderAttachmentBubbles(msg.attachments);
        if (!editing) {
            if (splitAttachText) {
                bubbleContent =
                    '<div class="comms-chat-bubble-cluster">' +
                    '<div class="comms-chat-bubble ' + sideCls + ' has-attachment-only">' + attachHtml + '</div>' +
                    '<div class="comms-chat-bubble ' + sideCls + autoCls + '">' + bodyHtml + '</div>' +
                    '</div>';
            } else {
                bubbleContent =
                    '<div class="comms-chat-bubble-cluster">' +
                    '<div class="comms-chat-bubble ' + sideCls + autoCls + (attachmentOnly ? ' has-attachment-only' : '') + '">' +
                    attachHtml +
                    bodyHtml +
                    '</div>' +
                    '</div>';
            }
        } else {
            bubbleContent = '<div class="comms-chat-bubble-cluster">' + bubbleContent + '</div>';
        }
        parts.push('<div class="comms-chat-bubble-wrap ' + sideCls + (pickerOpen ? ' is-picker-open' : '') + (isAutomation ? ' is-automation' : '') + batchClass + '" data-id="' + msg.id + '"' + batchAttr +
            ' title="' + escapeCommsHtml(formatCommsFullDateTime(msg.created_at)) + '">' +
            '<div class="comms-chat-bubble-row">' +
            '<div class="comms-chat-bubble-tools">' +
            (showMsgActions ? renderHeaderMessageActions(msg) : '') +
            '<button type="button" class="comms-chat-react-btn" data-react-toggle="' + msg.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + headerReactBtnIcon + '</button>' +
            '</div>' +
            bubbleContent +
            '</div>' +
            renderHeaderReactionChips(msg.reactions, msg.id) +
            renderHeaderSendStatus(msg, latestMineId != null && String(msg.id) === latestMineId) +
            '</div>');
        prevCreatedAt = msg.created_at;
    });
    body.innerHTML = parts.join('');
    if (opts.preserveScroll) {
        body.scrollTop = prevScroll;
        syncHeaderScrollBottomBtn();
    } else {
        jumpHeaderChatToLatest();
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

function syncHeaderScrollBottomBtn() {
    var body = document.getElementById('commsChatModalBody');
    var btn = document.getElementById('commsChatScrollBottom');
    if (!body || !btn) return;
    var distance = body.scrollHeight - body.scrollTop - body.clientHeight;
    btn.hidden = distance < 72;
}

function jumpHeaderChatToLatest() {
    var body = document.getElementById('commsChatModalBody');
    if (!body) return;
    var pin = function () {
        body.scrollTop = body.scrollHeight;
        syncHeaderScrollBottomBtn();
    };
    pin();
    window.requestAnimationFrame(function () {
        pin();
        window.requestAnimationFrame(pin);
    });
    window.setTimeout(pin, 50);
    window.setTimeout(pin, 200);
}

function sortHeaderMessagesAscending(messages) {
    var items = Array.isArray(messages) ? messages.slice() : [];
    items.sort(function (a, b) {
        var idA = Number(a && a.id) || 0;
        var idB = Number(b && b.id) || 0;
        if (idA && idB && String(a.id).indexOf('local-') !== 0 && String(b.id).indexOf('local-') !== 0) {
            return idA - idB;
        }
        var tA = Date.parse((a && a.created_at) || '') || 0;
        var tB = Date.parse((b && b.created_at) || '') || 0;
        return tA - tB;
    });
    return items;
}

function cloneHeaderReactions(reactions) {
    return (Array.isArray(reactions) ? reactions : []).map(function (r) {
        return {
            emoji: r.emoji,
            count: Number(r.count || 0),
            mine: !!r.mine,
            users: Array.isArray(r.users) ? r.users.map(function (u) {
                return {
                    id: Number(u.id || 0),
                    user_type: String(u.user_type || ''),
                    name: String(u.name || ''),
                    mine: !!u.mine,
                    profile_image_url: String(u.profile_image_url || '')
                };
            }) : []
        };
    });
}

function optimisticHeaderToggleReactions(reactions, emoji) {
    if (window.Comms && typeof window.Comms.optimisticToggleReactions === 'function') {
        return window.Comms.optimisticToggleReactions(reactions, emoji);
    }
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
        target.users = (target.users || []).filter(function (u) { return !u.mine; });
    } else {
        if (mineEntry && mineEntry !== target) {
            mineEntry.count -= 1;
            mineEntry.mine = false;
            mineEntry.users = (mineEntry.users || []).filter(function (u) { return !u.mine; });
        }
        if (target) {
            target.count += 1;
            target.mine = true;
            target.users = target.users || [];
            if (!(target.users || []).some(function (u) { return u.mine; })) {
                target.users.push({ id: 0, user_type: '', name: 'You', mine: true });
            }
        } else {
            list.push({
                emoji: emoji,
                count: 1,
                mine: true,
                users: [{ id: 0, user_type: '', name: 'You', mine: true }]
            });
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
    // Temp optimistic messages cannot be reacted to until the server assigns a real id.
    if (!Number(messageId) || String(messageId).indexOf('local-') === 0) return;

    var previous = cloneHeaderReactions(msg.reactions);
    var next = optimisticHeaderToggleReactions(previous, emoji);
    var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
        && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

    var seq = (headerChatState.reactSeq[key] || 0) + 1;
    headerChatState.reactSeq[key] = seq;
    headerChatState.reactSkipRealtime[key] = true;
    headerChatState.reactLocalUntil[key] = Date.now() + 3200;

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
        return r.json().then(function (data) {
            return { ok: r.ok, data: data };
        }).catch(function () {
            return { ok: false, data: null };
        });
    }).then(function (result) {
        if (headerChatState.reactSeq[key] !== seq) return;
        if (!result.ok || !result.data) {
            applyHeaderMessageReactions(messageId, previous, { closePicker: false });
            if (window.Comms && typeof window.Comms.showToast === 'function') {
                var errMsg = (result.data && result.data.message) || 'Unable to update reaction.';
                window.Comms.showToast(errMsg, 'error');
            }
            return;
        }
        applyHeaderMessageReactions(
            result.data.message_id || messageId,
            Array.isArray(result.data.reactions) ? result.data.reactions : next,
            { closePicker: false }
        );
        headerChatState.reactLocalUntil[key] = Date.now() + 3200;
    }).catch(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        applyHeaderMessageReactions(messageId, previous, { closePicker: false });
    }).finally(function () {
        if (headerChatState.reactSeq[key] !== seq) return;
        window.setTimeout(function () {
            if (headerChatState.reactSeq[key] !== seq) return;
            delete headerChatState.reactSkipRealtime[key];
        }, 2800);
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
    var mid = String(message.id);
    if (headerChatState.messages.some(function (m) { return String(m.id) === mid; })) return;
    var boot = document.getElementById('commsRealtimeBoot');
    var mine = boot
        && Number(message.sender_id) === Number(boot.dataset.currentUserId)
        && message.sender_type === boot.dataset.portalUserType;
    message.mine = !!mine;
    if (!message.reactions) message.reactions = [];
    if (!Array.isArray(message.attachments)) message.attachments = [];

    var needsReload = message.message_type === 'image'
        || message.message_type === 'file'
        || (message.attachments && message.attachments.length > 0);

    if (message.message_type === 'automation') {
        var autoTempIdx = headerChatState.messages.findIndex(function (m) {
            return String(m.id).indexOf('local-auto-') === 0;
        });
        if (autoTempIdx !== -1) {
            headerChatState.messages[autoTempIdx] = message;
            renderHeaderChatMessages(headerChatState.messages);
            writeHeaderChatCache(headerChatState.conversationId, {
                messages: headerChatState.messages,
                reactionEmojis: headerChatState.reactionEmojis
            });
            return;
        }
    }
    headerChatState.messages.push(message);
    renderHeaderChatMessages(headerChatState.messages);
    writeHeaderChatCache(headerChatState.conversationId, {
        messages: headerChatState.messages,
        reactionEmojis: headerChatState.reactionEmojis
    });
    if (needsReload && typeof window.reloadHeaderChatMessages === 'function') {
        window.reloadHeaderChatMessages(headerChatState.conversationId);
    }
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
            window.Comms.showToast('Message edited.', 'success');
        }
    }
    renderHeaderChatMessages(headerChatState.messages, { preserveScroll: true });
    persistHeaderThreadCache();
};

function clearHeaderFaqPanelPosition() {
    var panel = document.getElementById('commsChatFaqSuggestions');
    if (!panel) return;
    panel.classList.remove('is-fixed-pos');
    panel.style.position = '';
    panel.style.left = '';
    panel.style.right = '';
    panel.style.top = '';
    panel.style.bottom = '';
    panel.style.width = '';
    panel.style.maxHeight = '';
    panel.style.zIndex = '';
}

function positionHeaderFaqPanel() {
    clearHeaderFaqPanelPosition();
}

function setHeaderFaqMenuOpen() {
    // Burger toggle removed — FAQs are always visible when available.
    renderHeaderFaqChips();
}

function applyHeaderFaqSuggestions(faqs) {
    headerChatState.faqSuggestions = Array.isArray(faqs) ? faqs : [];
    headerChatState.faqKnownEmpty = headerChatState.faqSuggestions.length === 0;
    headerChatState.faqLoading = false;
    renderHeaderFaqChips();
}

function headerFaqCooldownRemaining() {
    var cid = String(headerChatState.conversationId || '');
    return Math.max(0, Math.ceil((Number(headerChatState.faqCooldownUntil[cid] || 0) - Date.now()) / 1000));
}

function stopHeaderFaqCooldownTimer() {
    if (headerChatState.faqCooldownTimer) {
        window.clearInterval(headerChatState.faqCooldownTimer);
        headerChatState.faqCooldownTimer = null;
    }
}

function updateHeaderFaqCooldownUiOnly() {
    var panel = document.getElementById('commsChatFaqSuggestions');
    var list = document.getElementById('commsChatFaqSuggestionsList');
    if (!panel || !list) return;
    var remaining = headerFaqCooldownRemaining();
    var cooling = remaining > 0;
    var hint = document.getElementById('commsChatFaqCooldownHint')
        || panel.querySelector('.comms-faq-cooldown-hint');
    if (hint) {
        if (cooling) {
            hint.hidden = false;
            hint.removeAttribute('hidden');
            hint.textContent = 'Wait ' + remaining + 's';
        } else {
            hint.hidden = true;
            hint.setAttribute('hidden', '');
            hint.textContent = '';
        }
    }
    list.querySelectorAll('.comms-faq-chip').forEach(function (chip) {
        chip.classList.toggle('is-cooldown', cooling);
        chip.disabled = cooling;
        if (cooling) chip.setAttribute('aria-disabled', 'true');
        else chip.removeAttribute('aria-disabled');
    });
}

function startHeaderFaqCooldown(seconds) {
    var cid = String(headerChatState.conversationId || '');
    if (!cid) return;
    var secs = Math.max(1, Number(seconds) || 10);
    headerChatState.faqCooldownUntil[cid] = Date.now() + (secs * 1000);
    stopHeaderFaqCooldownTimer();
    updateHeaderFaqCooldownUiOnly();
    headerChatState.faqCooldownTimer = window.setInterval(function () {
        if (headerFaqCooldownRemaining() <= 0) {
            stopHeaderFaqCooldownTimer();
            delete headerChatState.faqCooldownUntil[String(headerChatState.conversationId || '')];
            updateHeaderFaqCooldownUiOnly();
            return;
        }
        updateHeaderFaqCooldownUiOnly();
    }, 200);
}

function headerComposerHasDraftText() {
    var input = document.getElementById('commsChatModalInput');
    return !!(input && String(input.value || '').trim());
}

function syncHeaderFaqVisibilityForComposer() {
    var panel = document.getElementById('commsChatFaqSuggestions');
    if (!panel) return;
    var faqs = headerChatState.faqSuggestions || [];
    if (!faqs.length || headerComposerHasDraftText()) {
        panel.hidden = true;
        panel.setAttribute('hidden', '');
    } else {
        panel.hidden = false;
        panel.removeAttribute('hidden');
    }
}

function renderHeaderFaqChips() {
    var panel = document.getElementById('commsChatFaqSuggestions');
    var list = document.getElementById('commsChatFaqSuggestionsList');
    var hint = document.getElementById('commsChatFaqCooldownHint')
        || (panel && panel.querySelector('.comms-faq-cooldown-hint'));
    if (!list || !panel) {
        syncHeaderComposerEndControls();
        return;
    }
    var faqs = headerChatState.faqSuggestions || [];
    if (!faqs.length) {
        list.innerHTML = '';
        panel.hidden = true;
        panel.setAttribute('hidden', '');
        if (hint) {
            hint.hidden = true;
            hint.textContent = '';
        }
        syncHeaderComposerEndControls();
        return;
    }

    var remaining = headerFaqCooldownRemaining();
    var cooling = remaining > 0;
    list.innerHTML = faqs.map(function (faq) {
        var q = String(faq.question || '').trim();
        var answer = String(faq.automated_response || '');
        var faqId = Number(faq.id) || 0;
        return '<button type="button" class="comms-faq-chip' + (cooling ? ' is-cooldown' : '') + '"' +
            (cooling ? ' disabled aria-disabled="true"' : '') +
            ' role="listitem" title="' + escapeCommsHtml(q) + '"' +
            (faqId ? ' data-faq-id="' + faqId + '"' : '') +
            ' data-faq-question="' + escapeCommsHtml(q) + '"' +
            (answer ? ' data-faq-response="' + escapeCommsHtml(answer) + '"' : '') +
            '><span class="comms-faq-chip-text">' + escapeCommsHtml(q) + '</span></button>';
    }).join('');

    if (hint) {
        if (cooling) {
            hint.hidden = false;
            hint.removeAttribute('hidden');
            hint.textContent = 'Wait ' + remaining + 's';
        } else {
            hint.hidden = true;
            hint.setAttribute('hidden', '');
            hint.textContent = '';
        }
    }
    syncHeaderFaqVisibilityForComposer();
    syncHeaderComposerEndControls();
}

function headerFaqCacheKey(conversationId) {
    return 'comms_faq_sugg_v8_' + String(conversationId || '0');
}

function readHeaderFaqCache(conversationId) {
    if (window.Comms && typeof window.Comms.readFaqSuggestionsCache === 'function') {
        var shared = window.Comms.readFaqSuggestionsCache(conversationId);
        return shared && Array.isArray(shared.faqs) ? shared : null;
    }
    try {
        var raw = localStorage.getItem(headerFaqCacheKey(conversationId));
        if (!raw) raw = sessionStorage.getItem(headerFaqCacheKey(conversationId));
        if (!raw) return null;
        var parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.faqs)) return null;
        if (Date.now() - Number(parsed.at || 0) > 30 * 60 * 1000) return null;
        return { faqs: parsed.faqs, at: parsed.at, fresh: (Date.now() - Number(parsed.at || 0) <= 15 * 1000) };
    } catch (e) {
        return null;
    }
}

function writeHeaderFaqCache(conversationId, faqs) {
    if (window.Comms && typeof window.Comms.writeFaqSuggestionsCache === 'function') {
        window.Comms.writeFaqSuggestionsCache(conversationId, faqs);
        return;
    }
    try {
        localStorage.setItem(headerFaqCacheKey(conversationId), JSON.stringify({
            at: Date.now(),
            faqs: Array.isArray(faqs) ? faqs : []
        }));
    } catch (e) { /* ignore */ }
}

function matchHeaderFaqResponse(question) {
    var needle = String(question || '').trim().toLowerCase();
    if (!needle) return null;
    var faqs = headerChatState.faqSuggestions || [];
    for (var i = 0; i < faqs.length; i++) {
        if (String(faqs[i].question || '').trim().toLowerCase() === needle) {
            return faqs[i];
        }
    }
    return null;
}

function matchHeaderFaqById(faqId) {
    var id = Number(faqId);
    if (!id) return null;
    var faqs = headerChatState.faqSuggestions || [];
    for (var i = 0; i < faqs.length; i++) {
        if (Number(faqs[i].id) === id) return faqs[i];
    }
    return null;
}

function resolveHeaderFaqMatch(text, opts) {
    opts = opts || {};
    if (opts.faqResponse) {
        return {
            id: Number(opts.faqId) || 0,
            question: text,
            automated_response: String(opts.faqResponse)
        };
    }
    if (opts.faqId) {
        var byId = matchHeaderFaqById(opts.faqId);
        if (byId) return byId;
    }
    return matchHeaderFaqResponse(text);
}

function loadHeaderFaqSuggestions(conversationId) {
    var panel = document.getElementById('commsChatFaqSuggestions');
    var list = document.getElementById('commsChatFaqSuggestionsList');
    if (!panel || !list || !conversationId) return;

    var cachedFaqs = readHeaderFaqCache(conversationId);
    var cachedList = (cachedFaqs && Array.isArray(cachedFaqs.faqs)) ? cachedFaqs.faqs : null;
    // Paint non-empty cache immediately so chips appear with 0s delay.
    if (cachedList && cachedList.length) {
        applyHeaderFaqSuggestions(cachedList);
    } else {
        headerChatState.faqLoading = true;
        headerChatState.faqKnownEmpty = false;
        syncHeaderComposerEndControls();
    }

    var routes = (window.CommsChat && window.CommsChat.routes) || {};
    var urlTemplate = routes.faqSuggestions || '/api/communications/conversations/__ID__/faq-suggestions';
    var url = String(urlTemplate).replace('__ID__', String(conversationId));
    fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        cache: 'no-store'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (Number(headerChatState.conversationId) !== Number(conversationId)) return;
        var faqs = (data && Array.isArray(data.faqs)) ? data.faqs : [];
        writeHeaderFaqCache(conversationId, faqs);
        applyHeaderFaqSuggestions(faqs);
    }).catch(function () {
        if (Number(headerChatState.conversationId) !== Number(conversationId)) return;
        headerChatState.faqLoading = false;
        if (!headerChatState.faqSuggestions.length) {
            headerChatState.faqKnownEmpty = true;
            setHeaderFaqMenuOpen(false);
            list.innerHTML = '';
            syncHeaderComposerEndControls();
        }
    });
}

function loadHeaderChatMessages(conversationId) {
    var requestId = headerChatState.openRequestId || 0;
    headerChatState.loading = true;
    fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCommsCsrfToken()
        },
        credentials: 'same-origin',
        cache: 'no-store'
    }).then(function (r) {
        return r.ok ? r.json() : null;
    }).then(function (data) {
        if (!data || Number(headerChatState.conversationId) !== Number(conversationId)) return;
        if (requestId !== headerChatState.openRequestId) return;
        var messages = mergeHeaderPendingLocalMessages(
            mergeHeaderPreservedReactions(Array.isArray(data.messages) ? data.messages : [])
        );
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
        } else {
            jumpHeaderChatToLatest();
        }
        writeHeaderChatCache(conversationId, {
            messages: same ? headerChatState.messages : messages,
            reaction_emojis: headerChatState.reactionEmojis
        });
    }).catch(function () {
        if (Number(headerChatState.conversationId) !== Number(conversationId)) return;
        if (requestId !== headerChatState.openRequestId) return;
        var body = document.getElementById('commsChatModalBody');
        if (body && !headerChatState.messages.length) {
            body.innerHTML = '<p class="comms-chat-modal-empty">Unable to load messages.</p>';
        }
    }).finally(function () {
        if (Number(headerChatState.conversationId) === Number(conversationId)
            && requestId === headerChatState.openRequestId) {
            headerChatState.loading = false;
            jumpHeaderChatToLatest();
        }
    });
}

function mergeHeaderPreservedReactions(incoming) {
    var localById = {};
    (headerChatState.messages || []).forEach(function (m) {
        localById[String(m.id)] = m;
    });
    var now = Date.now();
    return (incoming || []).map(function (m) {
        var key = String(m.id);
        var until = Number(headerChatState.reactLocalUntil[key] || 0);
        var skip = !!headerChatState.reactSkipRealtime[key];
        if ((!skip && until <= now) || !localById[key]) return m;
        var local = localById[key];
        if (!Array.isArray(local.reactions)) return m;
        return Object.assign({}, m, { reactions: cloneHeaderReactions(local.reactions) });
    });
}

function mergeHeaderPendingLocalMessages(serverMessages) {
    var locals = (headerChatState.messages || []).filter(function (m) {
        return String(m.id || '').indexOf('local-') === 0;
    });
    if (!locals.length) return serverMessages || [];
    var merged = (serverMessages || []).slice();
    locals.forEach(function (local) {
        var lid = String(local.id || '');
        if (lid.indexOf('local-auto-') === 0) {
            var hasAuto = merged.some(function (m) {
                return String(m.message_type) === 'automation'
                    && String(m.body || '') === String(local.body || '');
            });
            if (!hasAuto) merged.push(local);
            return;
        }
        if (lid.indexOf('local-att-') === 0) {
            merged.push(local);
            return;
        }
        var hasText = merged.some(function (m) {
            return !!m.mine
                && Number(m.id)
                && String(m.body || '') === String(local.body || '');
        });
        if (!hasText) merged.push(local);
    });
    return merged;
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

function sendHeaderChatMessage(event, opts) {
    opts = opts || {};
    if (event) event.preventDefault();
    var input = document.getElementById('commsChatModalInput');
    var conversationId = headerChatState.conversationId;
    if (!input || !conversationId) return;
    var body = opts.bodyOverride != null
        ? String(opts.bodyOverride || '').replace(/\r\n/g, '\n').replace(/^\s+|\s+$/g, '')
        : String(input.value || '').replace(/\r\n/g, '\n').replace(/^\s+|\s+$/g, '');
    var files = headerChatState.pendingAttachments.slice();
    var previewUrls = headerChatState.pendingPreviewUrls.slice();
    if (!body && !files.length) return;
    if (opts.fromFaq && headerFaqCooldownRemaining() > 0) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Please wait ' + headerFaqCooldownRemaining() + 's before sending another FAQ.', 'error');
        }
        return;
    }
    var maxChars = headerMessageMaxChars();
    if (headerMeasuredLength(body) > maxChars) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast('Message exceeds the ' + maxChars + ' character limit.', 'error');
        }
        return;
    }
    if (headerChatState.sending && files.length) return;

    stopHeaderLocalTyping(conversationId);

    var sendBtn = document.getElementById('commsChatModalSend');
    input.value = '';
    syncHeaderComposerLineLimit();
    syncHeaderFaqVisibilityForComposer();
    // Keep blob URLs for optimistic attachment bubbles.
    clearHeaderChatAttachments({ keepObjectUrls: true });

    var tempId = 'local-' + Date.now();
    var autoTempId = null;
    var faqMatch = (body !== '' && !files.length) ? resolveHeaderFaqMatch(body, opts) : null;
    var expectAutoReply = !!(faqMatch && String(faqMatch.automated_response || '').trim());
    var faqId = faqMatch && Number(faqMatch.id) ? Number(faqMatch.id) : (Number(opts.faqId) || null);
    var batchId = files.length ? ('batch-' + Date.now()) : null;

    if (body !== '') {
        headerChatState.messages.push({
            id: tempId,
            conversation_id: conversationId,
            body: body,
            message_type: 'text',
            mine: true,
            send_status: 'sending',
            created_at: new Date().toISOString(),
            reactions: [],
            attachments: []
        });
        if (expectAutoReply) {
            // Do not show automation until the user message POST succeeds.
            autoTempId = null;
        }
    }

    if (files.length) {
        var attachBase = Date.now();
        files.forEach(function (file, index) {
            var isImage = isHeaderImageFile(file);
            var blobUrl = previewUrls[index] || (isImage ? URL.createObjectURL(file) : null);
            headerChatState.messages.push({
                id: 'local-att-' + attachBase + '-' + index,
                conversation_id: conversationId,
                body: '',
                message_type: isImage ? 'image' : 'file',
                mine: true,
                send_status: 'sending',
                created_at: new Date().toISOString(),
                reactions: [],
                batch_id: batchId,
                attachments: [{
                    id: 0,
                    file_name: file.name,
                    file_type: isImage ? 'image' : 'file',
                    mime_type: file.type || null,
                    file_size: file.size || null,
                    storage_provider: 'local',
                    url: blobUrl,
                    download_url: blobUrl || '#'
                }]
            });
        });
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

    function applySentMessage(data, replaceTempId, keepBatchId) {
        if (!data || !data.message) return;
        var realIdx = headerChatState.messages.findIndex(function (m) { return Number(m.id) === Number(data.message.id); });
        var tempIdx = replaceTempId ? headerChatState.messages.findIndex(function (m) { return String(m.id) === String(replaceTempId); }) : -1;
        var nextMsg = Object.assign({}, data.message, { send_status: 'sent', mine: true });
        if (keepBatchId) nextMsg.batch_id = keepBatchId;
        if (window.CommsRealtime && typeof window.CommsRealtime.broadcastMessage === 'function') {
            var peer = typeof findCachedHeaderPeer === 'function'
                ? findCachedHeaderPeer(headerChatState.conversationId)
                : null;
            window.CommsRealtime.broadcastMessage(headerChatState.conversationId, nextMsg, {
                peerId: peer && (peer.id || peer.user_id),
                peerType: peer && (peer.user_type || peer.type || peer.portal_user_type)
            });
        }
        if (realIdx !== -1 && tempIdx !== -1) {
            headerChatState.messages.splice(tempIdx, 1);
            headerChatState.messages[realIdx] = Object.assign({}, headerChatState.messages[realIdx], nextMsg);
        } else if (tempIdx !== -1) {
            var prevAtt = headerChatState.messages[tempIdx].attachments || [];
            prevAtt.forEach(function (a) {
                if (a && a.url && String(a.url).indexOf('blob:') === 0) {
                    try { URL.revokeObjectURL(a.url); } catch (e) { /* ignore */ }
                }
            });
            headerChatState.messages[tempIdx] = nextMsg;
        } else if (realIdx === -1) {
            headerChatState.messages.push(nextMsg);
        } else {
            headerChatState.messages[realIdx] = Object.assign({}, headerChatState.messages[realIdx], { send_status: 'sent' });
        }
        if (data.automated_message) {
            var autoTempIdx = headerChatState.messages.findIndex(function (m) {
                return String(m.id).indexOf('local-auto-') === 0;
            });
            var autoIdx = headerChatState.messages.findIndex(function (m) {
                return Number(m.id) === Number(data.automated_message.id);
            });
            if (autoIdx !== -1 && autoTempIdx !== -1) {
                headerChatState.messages.splice(autoTempIdx, 1);
            } else if (autoTempIdx !== -1) {
                headerChatState.messages[autoTempIdx] = data.automated_message;
            } else if (autoIdx === -1) {
                headerChatState.messages.push(data.automated_message);
            }
            if (expectAutoReply) startHeaderFaqCooldown(10);
        } else {
            headerChatState.messages = headerChatState.messages.filter(function (m) {
                return String(m.id).indexOf('local-auto-') !== 0;
            });
        }
        renderHeaderChatMessages(headerChatState.messages);
    }

    if (body !== '') {
        var postBody = { body: body };
        if (faqId) postBody.faq_id = faqId;
        // Start network before paint so FAQ send feels immediate.
        jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
            credentials: 'same-origin',
            body: JSON.stringify(postBody)
        }).then(parseMessageResponse).then(function (data) {
            applySentMessage(data, tempId, null);
        }));
    }

    if (body !== '' || files.length) {
        renderHeaderChatMessages(headerChatState.messages);
    }

    if (files.length) {
        headerChatState.sending = true;
        if (sendBtn) sendBtn.disabled = true;
        files.forEach(function (file, index) {
            var fd = new FormData();
            fd.append('attachment', file, file.name);
            var optimistic = headerChatState.messages.filter(function (m) {
                return String(m.id).indexOf('local-att-') === 0;
            })[index];
            var replaceId = optimistic ? optimistic.id : null;
            jobs.push(fetch('/api/communications/conversations/' + encodeURIComponent(conversationId) + '/messages', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: fd
            }).then(parseMessageResponse).then(function (data) {
                applySentMessage(data, replaceId, batchId);
            }));
        });
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
    var pickCamera = document.getElementById('commsChatPickCamera');
    var pickPhotos = document.getElementById('commsChatPickPhotos');
    var pickFiles = document.getElementById('commsChatPickFiles');
    var cameraInput = document.getElementById('commsChatCameraInput');
    var photoInput = document.getElementById('commsChatPhotoInput');
    var fileInput = document.getElementById('commsChatFileInput');
    var clearBtn = document.getElementById('commsChatAttachClear');
    if (!attachBtn || attachBtn.dataset.wired === 'true') return;
    if (attachMenu) attachMenu.hidden = true;

    attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (attachMenu) attachMenu.hidden = !attachMenu.hidden;
    });
    if (pickCamera) {
        pickCamera.addEventListener('click', function () {
            if (attachMenu) attachMenu.hidden = true;
            openCommsLiveCamera(function (file) {
                if (file) pushHeaderFiles([file], 'image');
            });
        });
    }
    if (pickPhotos && photoInput) {
        pickPhotos.addEventListener('click', function () { photoInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (pickFiles && fileInput) {
        pickFiles.addEventListener('click', function () { fileInput.click(); if (attachMenu) attachMenu.hidden = true; });
    }
    if (cameraInput) {
        cameraInput.addEventListener('change', function () {
            if (cameraInput.files && cameraInput.files.length) pushHeaderFiles(cameraInput.files, 'image');
            cameraInput.value = '';
        });
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

var liveCameraState = {
    stream: null,
    facingMode: 'user',
    onCapture: null,
    wired: false,
};

function stopCommsLiveCameraStream() {
    if (liveCameraState.stream) {
        liveCameraState.stream.getTracks().forEach(function (track) {
            try { track.stop(); } catch (e) { /* ignore */ }
        });
        liveCameraState.stream = null;
    }
    var video = document.getElementById('commsLiveCameraVideo');
    if (video) video.srcObject = null;
}

function closeCommsLiveCamera() {
    var modal = document.getElementById('commsLiveCameraModal');
    stopCommsLiveCameraStream();
    liveCameraState.onCapture = null;
    if (modal) modal.hidden = true;
    document.body.classList.remove('comms-live-camera-open');
}

async function startCommsLiveCameraStream() {
    var video = document.getElementById('commsLiveCameraVideo');
    var hint = document.getElementById('commsLiveCameraHint');
    var stage = document.querySelector('.comms-live-camera-stage');
    var captureBtn = document.getElementById('commsLiveCameraCapture');
    if (!video || !navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        if (hint) hint.textContent = 'Live camera is not supported in this browser.';
        if (captureBtn) captureBtn.disabled = true;
        return;
    }

    stopCommsLiveCameraStream();
    if (captureBtn) captureBtn.disabled = true;
    if (hint) hint.textContent = 'Starting camera…';

    try {
        liveCameraState.stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: liveCameraState.facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });
        video.srcObject = liveCameraState.stream;
        await video.play().catch(function () { /* autoplay policies */ });
        if (stage) stage.classList.toggle('is-rear', liveCameraState.facingMode === 'environment');
        if (hint) hint.textContent = 'Frame your shot, then tap Capture.';
        if (captureBtn) captureBtn.disabled = false;
    } catch (err) {
        if (hint) {
            hint.textContent = (err && err.name === 'NotAllowedError')
                ? 'Camera permission denied. Allow camera access and try again.'
                : ((err && err.message) || 'Unable to open the camera.');
        }
        if (captureBtn) captureBtn.disabled = true;
    }
}

function captureCommsLiveCameraFrame() {
    var video = document.getElementById('commsLiveCameraVideo');
    var canvas = document.getElementById('commsLiveCameraCanvas');
    if (!video || !canvas || !video.videoWidth) return null;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    var ctx = canvas.getContext('2d');
    if (!ctx) return null;

    if (liveCameraState.facingMode === 'user') {
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
    }
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
            if (!blob) {
                resolve(null);
                return;
            }
            var file = new File([blob], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            resolve(file);
        }, 'image/jpeg', 0.92);
    });
}

function wireCommsLiveCameraOnce() {
    if (liveCameraState.wired) return;
    var modal = document.getElementById('commsLiveCameraModal');
    if (!modal) return;
    liveCameraState.wired = true;

    modal.querySelectorAll('[data-comms-live-camera-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeCommsLiveCamera(); });
    });

    document.getElementById('commsLiveCameraFlip')?.addEventListener('click', function () {
        liveCameraState.facingMode = liveCameraState.facingMode === 'user' ? 'environment' : 'user';
        startCommsLiveCameraStream();
    });

    document.getElementById('commsLiveCameraCapture')?.addEventListener('click', function () {
        var btn = document.getElementById('commsLiveCameraCapture');
        if (btn) btn.disabled = true;
        Promise.resolve(captureCommsLiveCameraFrame()).then(function (file) {
            var cb = liveCameraState.onCapture;
            closeCommsLiveCamera();
            if (file && typeof cb === 'function') cb(file);
        }).catch(function () {
            if (btn) btn.disabled = false;
        });
    });
}

function openCommsLiveCamera(onCapture) {
    wireCommsLiveCameraOnce();
    var modal = document.getElementById('commsLiveCameraModal');
    if (!modal) {
        // Fallback: native camera file input when overlay is missing.
        var cameraInput = document.getElementById('commsChatCameraInput') || document.getElementById('commsCameraInput');
        if (cameraInput) cameraInput.click();
        return;
    }
    liveCameraState.onCapture = typeof onCapture === 'function' ? onCapture : null;
    liveCameraState.facingMode = 'user';
    modal.hidden = false;
    document.body.classList.add('comms-live-camera-open');
    startCommsLiveCameraStream();
}

window.CommsLiveCamera = {
    open: openCommsLiveCamera,
    close: closeCommsLiveCamera,
};

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
        var known = null;
        var list = Array.isArray(window.__COMMS_HEADER_CONVERSATIONS__) ? window.__COMMS_HEADER_CONVERSATIONS__ : [];
        for (var i = 0; i < list.length; i += 1) {
            if (Number(list[i].id) === Number(cid)) {
                known = list[i];
                break;
            }
        }
        var other = (known && known.other_user) || {};
        var conversation = {
            id: cid,
            conversation_type: (known && known.conversation_type) || 'private',
            other_user: Object.assign({}, other, {
                name: other.name || peerName,
                profile_image_url: other.profile_image_url
                    || (avatarEl ? avatarEl.getAttribute('src') : '')
            })
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
    var chatBodyForLightbox = document.getElementById('commsChatModalBody');
    if (chatBodyForLightbox && window.Comms && typeof window.Comms.wireChatImageLightbox === 'function') {
        window.Comms.wireChatImageLightbox(chatBodyForLightbox);
    }
    if (chatBodyForLightbox && chatBodyForLightbox.dataset.scrollWired !== 'true') {
        chatBodyForLightbox.addEventListener('scroll', function () {
            syncHeaderScrollBottomBtn();
        }, { passive: true });
        chatBodyForLightbox.dataset.scrollWired = 'true';
    }
    var scrollBottomBtn = document.getElementById('commsChatScrollBottom');
    if (scrollBottomBtn && scrollBottomBtn.dataset.wired !== 'true') {
        scrollBottomBtn.addEventListener('click', function (e) {
            e.preventDefault();
            jumpHeaderChatToLatest();
        });
        scrollBottomBtn.dataset.wired = 'true';
    }
    var faqList = document.getElementById('commsChatFaqSuggestionsList');
    if (faqList && faqList.dataset.wired !== 'true') {
        faqList.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-faq-question]');
            if (!chip || chip.disabled) return;
            if (headerFaqCooldownRemaining() > 0) return;
            var question = chip.getAttribute('data-faq-question') || '';
            var faqId = Number(chip.getAttribute('data-faq-id') || 0) || null;
            var faqResponse = chip.getAttribute('data-faq-response') || '';
            if (!question) return;
            // One-tap: send FAQ question immediately (Messenger quick-reply style).
            sendHeaderChatMessage(null, {
                fromFaq: true,
                faqId: faqId,
                faqResponse: faqResponse,
                bodyOverride: question
            });
        });
        faqList.dataset.wired = 'true';
    }
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
        input.removeAttribute('maxlength');
        input.removeAttribute('data-max-lines');
        input.addEventListener('input', function () {
            syncHeaderComposerLineLimit();
            syncHeaderComposerEndControls();
            syncHeaderFaqVisibilityForComposer();
            notifyHeaderComposerTyping(input.value);
            var maxChars = headerMessageMaxChars();
            var len = headerMeasuredLength(input.value || '');
            if (len >= maxChars) {
                if (input.dataset.limitToast !== '1') {
                    input.dataset.limitToast = '1';
                    if (window.Comms && typeof window.Comms.showToast === 'function') {
                        window.Comms.showToast('Message cannot exceed ' + maxChars + ' characters.', 'error');
                    }
                }
            } else {
                input.dataset.limitToast = '0';
            }
        });
        input.addEventListener('keydown', function (e) {
            var maxChars = headerMessageMaxChars();
            var current = String(input.value || '');
            var len = headerMeasuredLength(current);
            if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) {
                if (headerMeasuredLength(current + '\n') > maxChars) {
                    e.preventDefault();
                    if (window.Comms && typeof window.Comms.showToast === 'function') {
                        window.Comms.showToast('Message cannot exceed ' + maxChars + ' characters.', 'error');
                    }
                    return;
                }
            }
            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (headerMeasuredLength(current + e.key) > maxChars) {
                    e.preventDefault();
                    if (window.Comms && typeof window.Comms.showToast === 'function') {
                        window.Comms.showToast('Message cannot exceed ' + maxChars + ' characters.', 'error');
                    }
                }
            }
        });
        input.addEventListener('paste', function (e) {
            var paste = (e.clipboardData || window.clipboardData);
            if (!paste) return;
            var incoming = paste.getData('text');
            if (incoming == null) return;
            e.preventDefault();
            var start = input.selectionStart || 0;
            var end = input.selectionEnd || 0;
            var value = input.value || '';
            var next = value.slice(0, start) + incoming + value.slice(end);
            var maxChars = headerMessageMaxChars();
            while (next.length && headerMeasuredLength(next) > maxChars) {
                next = next.slice(0, -1);
            }
            input.value = next;
            syncHeaderComposerLineLimit();
            syncHeaderComposerEndControls();
            notifyHeaderComposerTyping(input.value);
        });
        input.dataset.lineLimitWired = 'true';
    }
    syncHeaderComposerEndControls();
    syncHeaderExpandButtonVisibility();
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
window.onCommsHeaderPresence = onCommsHeaderPresence;
window.openHeaderMessagesFullscreen = openHeaderMessagesFullscreen;
window.setHeaderChatFullscreen = setHeaderChatFullscreen;
window.goToSeeAllMessages = goToSeeAllMessages;
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
