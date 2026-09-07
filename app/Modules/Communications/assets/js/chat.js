/**
 * Messenger UI: conversation list, thread, send/read, user search.
 */
(function () {
    'use strict';

    var root = document.getElementById('commsApp');
    if (!root || !window.Comms) return;

    var routes = {};
    try { routes = JSON.parse(root.dataset.routes || '{}'); } catch (e) { routes = {}; }

    var state = {
        currentUserId: Number(root.dataset.currentUserId || 0),
        portalUserType: root.dataset.portalUserType || '',
        conversations: [],
        barangayOfficials: [],
        activeId: null,
        activeConversation: null,
        messages: [],
        sendInFlight: false,
        searchTimer: null,
        filterTimer: null,
        loadingOlder: false,
        listMode: 'conversations',
        directoryFilter: 'officials',
        barangayId: '',
        reactionEmojis: ['👍', '❤️', '😆', '😮', '😢', '🙏'],
        openReactionPickerId: null,
        reactInFlight: {},
        reactSkipRealtime: {},
        pendingAttachment: null,
        pendingPreviewUrl: null,
        messageCache: {},
        editingId: null,
        msgChangeSkipToast: {}
    };

    var els = {
        sidebar: document.getElementById('commsSidebar'),
        convList: document.getElementById('commsConvList'),
        directoryList: document.getElementById('commsDirectoryList'),
        convEmpty: document.getElementById('commsConvEmpty'),
        threadEmpty: document.getElementById('commsThreadEmpty'),
        threadActive: document.getElementById('commsThreadActive'),
        messages: document.getElementById('commsMessages'),
        composer: document.getElementById('commsComposer'),
        input: document.getElementById('commsMessageInput'),
        sendBtn: document.getElementById('commsSendBtn'),
        userSearch: document.getElementById('commsUserSearch'),
        searchResults: document.getElementById('commsSearchResults'),
        convFilter: document.getElementById('commsConvFilter'),
        peerName: document.getElementById('commsPeerName'),
        peerMeta: document.getElementById('commsPeerMeta'),
        peerAvatar: document.getElementById('commsPeerAvatar'),
        backBtn: document.getElementById('commsBackBtn'),
        typing: document.getElementById('commsTyping'),
        voiceBtn: document.getElementById('commsVoiceBtn'),
        videoBtn: document.getElementById('commsVideoBtn'),
        filterBar: document.getElementById('commsFilterBar'),
        barangayFilter: document.getElementById('commsBarangayFilter'),
        attachBtn: document.getElementById('commsAttachBtn'),
        attachInput: document.getElementById('commsAttachInput'),
        attachPreview: document.getElementById('commsAttachPreview'),
        attachPreviewInner: document.getElementById('commsAttachPreviewInner'),
        attachClear: document.getElementById('commsAttachClear'),
        uploadProgress: document.getElementById('commsUploadProgress'),
        uploadProgressBar: document.getElementById('commsUploadProgressBar')
    };

    function setThreadOpen(open) {
        root.classList.toggle('is-thread-open', !!open);
    }

    function conversationPeerId(conversation) {
        return Number((conversation && conversation.other_user && conversation.other_user.id) || 0);
    }

    function renderConversations(filter) {
        var q = String(filter || '').trim().toLowerCase();
        var items = state.conversations;
        if (q) {
            items = items.filter(function (c) {
                return String((c.other_user && c.other_user.name) || '').toLowerCase().indexOf(q) !== -1;
            });
        }

        var chatPeerIds = {};
        state.conversations.forEach(function (c) {
            var peerId = conversationPeerId(c);
            if (peerId) chatPeerIds[peerId] = true;
        });
        var starters = (state.barangayOfficials || []).filter(function (user) {
            var id = Number(user.id || 0);
            if (!id || chatPeerIds[id]) return false;
            if (!q) return true;
            return String(user.name || '').toLowerCase().indexOf(q) !== -1
                || String(user.position || '').toLowerCase().indexOf(q) !== -1
                || String(user.user_type_label || '').toLowerCase().indexOf(q) !== -1;
        });

        if (!items.length && !starters.length) {
            els.convList.innerHTML = '';
            els.convEmpty.hidden = false;
            return;
        }
        els.convEmpty.hidden = true;
        els.convList.innerHTML = items.map(function (c) {
            var peer = c.other_user || {};
            var preview = 'No messages yet';
            if (c.last_message) {
                if (c.last_message.deleted_for_all || c.last_message.message_type === 'deleted') {
                    preview = 'This message was deleted';
                } else if (c.last_message.message_type === 'image') {
                    preview = 'Sent a photo';
                } else if (c.last_message.message_type === 'file') {
                    preview = 'Sent a file';
                } else if (c.last_message.message_type === 'call' || c.last_message.message_type === 'system') {
                    preview = c.last_message.body || 'Call update';
                } else {
                    preview = c.last_message.body || 'No messages yet';
                }
            }
            var unread = Number(c.unread_count || 0);
            var active = Number(c.id) === Number(state.activeId) ? ' is-active' : '';
            var avatar = peer.profile_image_url || Comms.defaultAvatar(peer.name);
            return '<button type="button" class="comms-conv-item' + active + '" data-id="' + c.id + '" role="listitem">' +
                '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                '<div class="comms-conv-main">' +
                '<div class="comms-conv-name">' + Comms.escapeHtml(peer.name || 'User') + '</div>' +
                '<div class="comms-conv-preview">' + Comms.escapeHtml(preview) + '</div>' +
                '</div>' +
                '<div class="comms-conv-meta">' +
                '<span class="comms-conv-time">' + Comms.escapeHtml(Comms.formatTime(c.updated_at)) + '</span>' +
                (unread > 0 ? '<span class="comms-unread">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
                '</div></button>';
        }).join('') + starters.map(function (user) {
            var avatar = user.profile_image_url || Comms.defaultAvatar(user.name);
            var preview = user.position || user.user_type_label || 'SK Official';
            return '<button type="button" class="comms-conv-item" data-user-id="' + user.id + '" role="listitem">' +
                '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                '<div class="comms-conv-main">' +
                '<div class="comms-conv-name">' + Comms.escapeHtml(user.name || 'User') + '</div>' +
                '<div class="comms-conv-preview">' + Comms.escapeHtml(preview) + '</div>' +
                '</div></button>';
        }).join('');
    }

    function formatRelativeTime(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        var diffSec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        if (diffSec < 60) return diffSec + 's';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm';
        if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h';
        return Math.floor(diffSec / 86400) + 'd';
    }

    function callEventIcon(body) {
        var text = String(body || '').toLowerCase();
        if (text.indexOf('video') !== -1) {
            return '<svg class="comms-call-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
        }
        return '<svg class="comms-call-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>';
    }

    function callBubbleClass(body) {
        var text = String(body || '').toLowerCase();
        if (text.indexOf('ended') !== -1) return 'comms-bubble--call comms-bubble--call-ended';
        if (text.indexOf('missed') !== -1) return 'comms-bubble--call comms-bubble--call-missed';
        if (text.indexOf('declined') !== -1) return 'comms-bubble--call comms-bubble--call-declined';
        if (text.indexOf('cancelled') !== -1) return 'comms-bubble--call comms-bubble--call-cancelled';
        if (text.indexOf('answered') !== -1) return 'comms-bubble--call comms-bubble--call-answered';
        if (text.indexOf('started') !== -1) return 'comms-bubble--call comms-bubble--call-started';
        return 'comms-bubble--call';
    }

    function renderReactionChips(reactions) {
        var items = Array.isArray(reactions) ? reactions : [];
        if (!items.length) return '';
        return '<div class="comms-reaction-chips" role="group" aria-label="Reactions">' +
            items.map(function (r) {
                return '<button type="button" class="comms-reaction-chip' + (r.mine ? ' is-mine' : '') + '" data-react-emoji="' + Comms.escapeHtml(r.emoji) + '" title="React ' + Comms.escapeHtml(r.emoji) + '">' +
                    '<span aria-hidden="true">' + Comms.escapeHtml(r.emoji) + '</span>' +
                    '<span class="comms-reaction-count">' + Number(r.count || 0) + '</span>' +
                    '</button>';
            }).join('') +
            '</div>';
    }

    function renderReactionPicker() {
        return '';
    }

    var reactBtnIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>';
    var msgMoreIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.85"/><circle cx="12" cy="12" r="1.85"/><circle cx="12" cy="19" r="1.85"/></svg>';

    function renderMessageActions(m) {
        return '<div class="comms-msg-actions">' +
            '<button type="button" class="comms-msg-more" data-msg-more="' + m.id + '" aria-label="Message actions" aria-haspopup="menu" aria-expanded="false" title="More">' + msgMoreIcon + '</button>' +
            '</div>';
    }

    function positionFixedOverlay(el, triggerBtn) {
        if (!el || !triggerBtn) return;
        var rect = triggerBtn.getBoundingClientRect();
        var pad = 8;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var w = el.offsetWidth || 220;
        var h = el.offsetHeight || 48;
        var top = rect.top - h - 8;
        var left = rect.left + (rect.width / 2) - (w / 2);
        if (top < pad) top = rect.bottom + 8;
        left = Math.max(pad, Math.min(left, vw - w - pad));
        top = Math.max(pad, Math.min(top, vh - h - pad));
        el.style.top = top + 'px';
        el.style.left = left + 'px';
    }

    function getPageReactionPickerEl() {
        var el = document.getElementById('commsPageReactionPicker');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'commsPageReactionPicker';
        el.className = 'comms-reaction-picker comms-reaction-picker--fixed';
        el.setAttribute('role', 'menu');
        el.setAttribute('aria-label', 'Choose reaction');
        el.style.display = 'none';
        document.body.appendChild(el);
        el.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-react-emoji]');
            if (!pick) return;
            e.preventDefault();
            e.stopPropagation();
            var mid = Number(state.openReactionPickerId);
            var emoji = pick.getAttribute('data-react-emoji');
            if (mid && emoji) toggleReaction(mid, emoji);
        });
        return el;
    }

    function hidePageReactionPicker() {
        state.openReactionPickerId = null;
        var el = document.getElementById('commsPageReactionPicker');
        if (el) {
            el.style.display = 'none';
            el.innerHTML = '';
            el.removeAttribute('data-react-picker');
        }
        if (!els.messages) return;
        els.messages.querySelectorAll('.comms-bubble-wrap.is-picker-open').forEach(function (wrap) {
            wrap.classList.remove('is-picker-open');
            var btn = wrap.querySelector('[data-react-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    function showPageReactionPicker(messageId, triggerBtn) {
        var id = Number(messageId) || 0;
        if (!id || !triggerBtn) {
            hidePageReactionPicker();
            return;
        }
        if (Number(state.openReactionPickerId) === id) {
            hidePageReactionPicker();
            return;
        }
        hidePageMsgMenu();
        state.openReactionPickerId = id;
        var el = getPageReactionPickerEl();
        el.setAttribute('data-react-picker', String(id));
        el.innerHTML = state.reactionEmojis.map(function (emoji) {
            return '<button type="button" class="comms-reaction-pick" data-react-emoji="' + Comms.escapeHtml(emoji) + '" role="menuitem">' +
                Comms.escapeHtml(emoji) +
                '</button>';
        }).join('');
        if (els.messages) {
            els.messages.querySelectorAll('.comms-bubble-wrap').forEach(function (wrap) {
                var isOpen = Number(wrap.getAttribute('data-id')) === id;
                wrap.classList.toggle('is-picker-open', isOpen);
                var btn = wrap.querySelector('[data-react-toggle]');
                if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }
        el.style.display = 'inline-flex';
        positionFixedOverlay(el, triggerBtn);
        requestAnimationFrame(function () { positionFixedOverlay(el, triggerBtn); });
    }

    function getPageMsgMenuEl() {
        var el = document.getElementById('commsPageMsgMenu');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'commsPageMsgMenu';
        el.className = 'comms-msg-menu comms-msg-menu--fixed';
        el.setAttribute('role', 'menu');
        el.hidden = true;
        document.body.appendChild(el);
        el.addEventListener('click', function (e) {
            var editBtn = e.target.closest('[data-msg-edit]');
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                hidePageMsgMenu();
                state.editingId = Number(editBtn.getAttribute('data-msg-edit'));
                renderMessages({ preserveScroll: true });
                var editor = els.messages && els.messages.querySelector('[data-edit-input="' + state.editingId + '"]');
                if (editor) editor.focus();
                return;
            }
            var deleteChoice = e.target.closest('[data-msg-delete]');
            if (deleteChoice) {
                e.preventDefault();
                e.stopPropagation();
                hidePageMsgMenu();
                openDeletePrompt(Number(deleteChoice.getAttribute('data-msg-delete')));
            }
        });
        return el;
    }

    function hidePageMsgMenu() {
        var el = document.getElementById('commsPageMsgMenu');
        if (el) {
            el.hidden = true;
            el.innerHTML = '';
            el.removeAttribute('data-msg-menu');
        }
        if (!els.messages) return;
        els.messages.querySelectorAll('[data-msg-more]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
        els.messages.querySelectorAll('.comms-bubble-wrap.is-menu-open').forEach(function (wrap) {
            wrap.classList.remove('is-menu-open');
        });
    }

    function showPageMsgMenu(messageId, triggerBtn) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg || !triggerBtn) return;
        hidePageReactionPicker();
        var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
        var items = '';
        if (!deleted && msg.mine && msg.message_type === 'text') {
            items += '<button type="button" role="menuitem" data-msg-edit="' + msg.id + '">Edit</button>';
        }
        items += '<button type="button" role="menuitem" data-msg-delete="' + msg.id + '">Delete</button>';
        var el = getPageMsgMenuEl();
        el.innerHTML = items;
        el.setAttribute('data-msg-menu', String(msg.id));
        el.hidden = false;
        if (els.messages) {
            els.messages.querySelectorAll('.comms-bubble-wrap').forEach(function (wrap) {
                var isOpen = Number(wrap.getAttribute('data-id')) === Number(msg.id);
                wrap.classList.toggle('is-menu-open', isOpen);
                var btn = wrap.querySelector('[data-msg-more]');
                if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }
        positionFixedOverlay(el, triggerBtn);
        requestAnimationFrame(function () { positionFixedOverlay(el, triggerBtn); });
    }

    function closeMessageMenus() {
        hidePageMsgMenu();
    }

    function replaceMessage(updated) {
        if (!updated) return;
        var idx = state.messages.findIndex(function (m) { return Number(m.id) === Number(updated.id); });
        if (idx === -1) return;
        state.messages[idx] = updated;
        persistActiveThreadCache();
        renderMessages({ preserveScroll: true });
    }

    function markDeletedLocal(messageId) {
        var idx = state.messages.findIndex(function (m) { return Number(m.id) === Number(messageId); });
        if (idx === -1) return;
        state.messages[idx] = Object.assign({}, state.messages[idx], {
            deleted_for_all: true,
            message_type: 'deleted',
            body: null,
            attachments: [],
            reactions: []
        });
        persistActiveThreadCache();
        renderMessages({ preserveScroll: true });
        noteInboxActivity({
            id: messageId,
            conversation_id: state.activeId,
            deleted_for_all: true,
            message_type: 'deleted',
            body: null
        });
    }

    function removeMessageLocal(messageId) {
        state.messages = state.messages.filter(function (m) { return Number(m.id) !== Number(messageId); });
        persistActiveThreadCache();
        renderMessages({ preserveScroll: true });
        scheduleReloadConversations();
    }

    function saveEditedMessage(messageId) {
        var input = els.messages.querySelector('[data-edit-input="' + messageId + '"]');
        if (!input || !routes.updateMessage) return;
        var body = String(input.value || '').trim();
        if (!body) {
            Comms.showToast('Message cannot be empty.', 'error');
            return;
        }
        state.msgChangeSkipToast[String(messageId)] = true;
        Comms.api(Comms.route(routes.updateMessage, messageId), {
            method: 'PATCH',
            body: JSON.stringify({ body: body }),
            dedupe: false
        }).then(function (data) {
            state.editingId = null;
            replaceMessage(data.message);
            Comms.showToast('Message edited.', 'success');
            window.setTimeout(function () { delete state.msgChangeSkipToast[String(messageId)]; }, 800);
        }).catch(function (err) {
            delete state.msgChangeSkipToast[String(messageId)];
            Comms.showToast(err.message || 'Unable to edit message.', 'error');
        });
    }

    function deleteMessage(messageId, scope) {
        if (!routes.deleteMessage) return;
        var snapshot = state.messages.slice();
        state.msgChangeSkipToast[String(messageId)] = true;
        if (scope === 'me') {
            removeMessageLocal(messageId);
        } else {
            markDeletedLocal(messageId);
        }
        Comms.api(Comms.route(routes.deleteMessage, messageId), {
            method: 'DELETE',
            body: JSON.stringify({ scope: scope }),
            dedupe: false
        }).then(function (data) {
            state.editingId = null;
            if (data && data.scope === 'me') {
                Comms.showToast('Message deleted for you.', 'success');
                return;
            }
            if (data && data.message) {
                replaceMessage(data.message);
                Comms.showToast('Message deleted for everyone.', 'success');
            }
            window.setTimeout(function () { delete state.msgChangeSkipToast[String(messageId)]; }, 800);
        }).catch(function (err) {
            state.messages = snapshot;
            persistActiveThreadCache();
            renderMessages({ preserveScroll: true });
            delete state.msgChangeSkipToast[String(messageId)];
            Comms.showToast(err.message || 'Unable to delete message.', 'error');
        });
    }

    function openDeletePrompt(messageId) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg) return;
        var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
        var canDeleteAll = !!msg.mine && !deleted;
        if (typeof Comms.openDeleteMessageDialog !== 'function') {
            deleteMessage(messageId, canDeleteAll ? 'all' : 'me');
            return;
        }
        Comms.openDeleteMessageDialog({ canDeleteAll: canDeleteAll }).then(function (scope) {
            if (!scope) return;
            deleteMessage(messageId, scope);
        });
    }

    function formatBytes(bytes) {
        var n = Number(bytes || 0);
        if (!n) return '';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function fileExtLabel(name) {
        var parts = String(name || '').split('.');
        return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
    }

    function renderAttachments(attachments) {
        var items = Array.isArray(attachments) ? attachments : [];
        if (!items.length) return '';
        return items.map(function (att) {
            if (att.file_type === 'image' || att.storage_provider === 'cloudinary') {
                var src = att.url || att.download_url;
                return '<a class="comms-bubble-image" href="' + Comms.escapeHtml(src) + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + Comms.escapeHtml(src) + '" alt="' + Comms.escapeHtml(att.file_name || 'Image') + '" loading="lazy">' +
                    '</a>';
            }
            return '<a class="comms-bubble-file" href="' + Comms.escapeHtml(att.download_url) + '" target="_blank" rel="noopener noreferrer">' +
                '<span class="comms-bubble-file-ext">' + Comms.escapeHtml(fileExtLabel(att.file_name)) + '</span>' +
                '<span class="comms-bubble-file-meta">' +
                '<span class="comms-bubble-file-name">' + Comms.escapeHtml(att.file_name || 'Document') + '</span>' +
                '<span class="comms-bubble-file-size">' + Comms.escapeHtml(formatBytes(att.file_size)) + '</span>' +
                '</span></a>';
        }).join('');
    }

    function clearPendingAttachment() {
        if (state.pendingPreviewUrl) {
            try { URL.revokeObjectURL(state.pendingPreviewUrl); } catch (e) {}
        }
        state.pendingAttachment = null;
        state.pendingPreviewUrl = null;
        if (els.attachInput) els.attachInput.value = '';
        if (els.attachPreview) els.attachPreview.hidden = true;
        if (els.attachPreviewInner) els.attachPreviewInner.innerHTML = '';
        setUploadProgress(0, true);
    }

    function setUploadProgress(percent, hidden) {
        if (!els.uploadProgress || !els.uploadProgressBar) return;
        if (hidden) {
            els.uploadProgress.hidden = true;
            els.uploadProgressBar.style.width = '0%';
            return;
        }
        els.uploadProgress.hidden = false;
        els.uploadProgressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
    }

    function setPendingAttachment(file) {
        if (!file) return;
        clearPendingAttachment();
        state.pendingAttachment = file;
        var isImage = String(file.type || '').indexOf('image/') === 0;
        if (els.attachPreview) els.attachPreview.hidden = false;
        if (!els.attachPreviewInner) return;
        if (isImage) {
            state.pendingPreviewUrl = URL.createObjectURL(file);
            els.attachPreviewInner.innerHTML = '<img src="' + state.pendingPreviewUrl + '" alt="Selected image preview">' +
                '<span class="comms-attach-preview-name">' + Comms.escapeHtml(file.name) + '</span>';
        } else {
            els.attachPreviewInner.innerHTML = '<span class="comms-attach-preview-file">' +
                '<strong>' + Comms.escapeHtml(fileExtLabel(file.name)) + '</strong> ' +
                Comms.escapeHtml(file.name) +
                '</span>';
        }
    }

    function renderMessages(opts) {
        opts = opts || {};
        hidePageReactionPicker();
        hidePageMsgMenu();
        var prevScroll = els.messages ? els.messages.scrollTop : 0;
        var stickBottom = false;
        if (opts.stickBottom === true) {
            stickBottom = true;
        } else if (els.messages && opts.stickBottom !== false && !opts.preserveScroll) {
            stickBottom = els.messages.scrollTop + els.messages.clientHeight >= els.messages.scrollHeight - 48;
        }
        els.messages.innerHTML = state.messages.map(function (m) {
            if (m.message_type === 'call' || m.message_type === 'system') {
                return '<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + m.id + '">' +
                    '<div class="comms-bubble-row comms-bubble-row--call">' +
                    '<div class="' + callBubbleClass(m.body) + '" role="status">' +
                    callEventIcon(m.body) +
                    '<span class="comms-call-text">' + Comms.escapeHtml(m.body) + '</span>' +
                    '<span class="comms-bubble-time">' + Comms.escapeHtml(formatRelativeTime(m.created_at)) + '</span>' +
                    '</div></div></div>';
            }
            if (m.deleted_for_all || m.message_type === 'deleted') {
                return '<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + '" data-id="' + m.id + '">' +
                    '<div class="comms-bubble-row">' +
                    '<div class="comms-bubble comms-bubble--deleted">' +
                    '<div class="comms-bubble-text">This message was deleted</div>' +
                    '<span class="comms-bubble-time">' + Comms.escapeHtml(Comms.formatTime(m.created_at)) + '</span>' +
                    '</div>' +
                    '<div class="comms-bubble-tools">' + renderMessageActions(m) + '</div>' +
                    '</div></div>';
            }
            var mine = !!m.mine;
            var pickerOpen = Number(state.openReactionPickerId) === Number(m.id);
            var editing = Number(state.editingId) === Number(m.id);
            var bodyHtml = '';
            if (editing) {
                bodyHtml = '<textarea class="comms-edit-input" data-edit-input="' + m.id + '" maxlength="5000" rows="2">' + Comms.escapeHtml(m.body || '') + '</textarea>' +
                    '<div class="comms-edit-actions">' +
                    '<button type="button" class="comms-edit-cancel" data-edit-cancel="' + m.id + '">Cancel</button>' +
                    '<button type="button" class="comms-edit-save" data-edit-save="' + m.id + '">Save</button>' +
                    '</div>';
            } else if (m.body) {
                bodyHtml = '<div class="comms-bubble-text">' + Comms.escapeHtml(m.body) +
                    (m.edited ? ' <span class="comms-edited">edited</span>' : '') +
                    '</div>';
            }
            return '<div class="comms-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') + '" data-id="' + m.id + '">' +
                '<div class="comms-bubble-row">' +
                '<div class="comms-bubble ' + (mine ? 'comms-bubble--mine' : 'comms-bubble--theirs') + (m.message_type === 'image' || m.message_type === 'file' ? ' has-attachment' : '') + '">' +
                renderAttachments(m.attachments) +
                bodyHtml +
                '<span class="comms-bubble-time">' + Comms.escapeHtml(Comms.formatTime(m.created_at)) + '</span>' +
                '</div>' +
                '<div class="comms-bubble-tools">' +
                renderMessageActions(m) +
                '<button type="button" class="comms-react-btn" data-react-toggle="' + m.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + reactBtnIcon + '</button>' +
                '</div>' +
                '</div>' +
                renderReactionChips(m.reactions) +
                '</div>';
        }).join('');
        if (!els.messages) return;
        if (opts.preserveScroll) {
            els.messages.scrollTop = prevScroll;
        } else if (stickBottom || opts.stickBottom === true) {
            els.messages.scrollTop = els.messages.scrollHeight;
        } else {
            els.messages.scrollTop = prevScroll;
        }
    }

    function cloneReactions(reactions) {
        return (Array.isArray(reactions) ? reactions : []).map(function (r) {
            return { emoji: r.emoji, count: Number(r.count || 0), mine: !!r.mine };
        });
    }

    function optimisticToggleReactions(reactions, emoji) {
        var list = cloneReactions(reactions).filter(function (r) { return r.count > 0; });
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

    function applyMessageReactions(messageId, reactions) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (msg) {
            msg.reactions = Array.isArray(reactions) ? reactions : [];
        }
        persistActiveThreadCache();
        renderMessages();
    }

    function patchRemoteReaction(messageId, eventType, row) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg || !row) return false;
        var list = cloneReactions(msg.reactions);
        var emoji = String(row.emoji || '');
        if (!emoji) return false;
        var isMine = Number(row.user_id) === Number(state.currentUserId)
            && String(row.user_type || '') === String(state.portalUserType || '');
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
            var oldEmoji = String((row._old_emoji) || '');
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

        msg.reactions = list.filter(function (r) { return Number(r.count) > 0; });
        renderMessages();
        return true;
    }

    function onReactionChange(messageId, meta) {
        if (!state.activeId || !messageId) return;
        if (!state.messages.some(function (m) { return Number(m.id) === Number(messageId); })) return;
        if (state.reactSkipRealtime[String(messageId)]) return;

        if (meta && meta.eventType && patchRemoteReaction(messageId, meta.eventType, meta.row)) {
            return;
        }

        Comms.api(Comms.route(routes.messages, state.activeId)).then(function (data) {
            var fresh = (data.messages || []).find(function (m) {
                return Number(m.id) === Number(messageId);
            });
            if (!fresh) return;
            applyMessageReactions(messageId, fresh.reactions || []);
        }).catch(function () { /* ignore */ });
    }

    function toggleReaction(messageId, emoji) {
        if (!routes.react || !messageId || !emoji) return;
        var key = String(messageId);
        if (state.reactInFlight[key]) return;

        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg) return;

        var previous = cloneReactions(msg.reactions);
        var next = optimisticToggleReactions(previous, emoji);
        var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
            && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

        state.openReactionPickerId = null;
        hidePageReactionPicker();
        applyMessageReactions(messageId, next);
        if (added && typeof Comms.playReactionSound === 'function') {
            Comms.playReactionSound();
        }

        state.reactInFlight[key] = true;
        state.reactSkipRealtime[key] = true;

        Comms.api(Comms.route(routes.react, messageId), {
            method: 'POST',
            body: JSON.stringify({ emoji: emoji })
        }).then(function (data) {
            applyMessageReactions(data.message_id || messageId, data.reactions || next);
        }).catch(function (err) {
            applyMessageReactions(messageId, previous);
            Comms.showToast(err.message || 'Unable to react.', 'error');
        }).finally(function () {
            delete state.reactInFlight[key];
            window.setTimeout(function () {
                delete state.reactSkipRealtime[key];
            }, 800);
        });
    }

    function updatePeerHeader(conversation) {
        var peer = (conversation && conversation.other_user) || {};
        els.peerName.textContent = peer.name || 'User';
        var statusBits = [peer.user_type_label || ''];
        if (peer.position) statusBits.push(String(peer.position));
        if (peer.barangay_name) statusBits.push(String(peer.barangay_name));
        if (peer.online_status) statusBits.push(String(peer.online_status));
        else if (peer.last_seen) statusBits.push('Last seen ' + Comms.formatTime(peer.last_seen));
        els.peerMeta.textContent = statusBits.filter(Boolean).join(' · ');
        els.peerAvatar.src = peer.profile_image_url || Comms.defaultAvatar(peer.name);
        els.peerAvatar.alt = peer.name || 'User';
    }

    function setListMode(mode) {
        state.listMode = mode;
        var isChats = mode === 'conversations';
        if (els.convList) els.convList.hidden = !isChats;
        if (els.directoryList) els.directoryList.hidden = isChats;
        if (els.convFilter) els.convFilter.hidden = !isChats;
        if (els.barangayFilter) {
            els.barangayFilter.hidden = !(mode === 'officials' || mode === 'chairpersons');
        }
        if (els.convEmpty && !isChats) {
            els.convEmpty.hidden = true;
        }
    }

    function loadDirectory() {
        if (!routes.directory) return Promise.resolve();
        var params = new URLSearchParams();
        params.set('filter', state.directoryFilter || 'officials');
        if (state.barangayId) params.set('barangay_id', state.barangayId);
        return Comms.api(routes.directory + '?' + params.toString()).then(function (data) {
            renderDirectory(data.groups || []);
        }).catch(function (err) {
            Comms.showToast(err.message || 'Failed to load directory.', 'error');
        });
    }

    function renderDirectory(groups) {
        if (!els.directoryList) return;
        if (!groups.length || groups.every(function (g) { return !(g.users || []).length; })) {
            els.directoryList.innerHTML = '<div class="comms-empty"><p>No people found for this filter.</p></div>';
            return;
        }
        els.directoryList.innerHTML = groups.map(function (group) {
            var users = group.users || [];
            if (!users.length) return '';
            return '<div class="comms-dir-group-title">' + Comms.escapeHtml(group.title || 'People') + '</div>' +
                users.map(function (u) {
                    var avatar = u.profile_image_url || Comms.defaultAvatar(u.name);
                    var meta = [u.position || u.user_type_label || '', u.barangay_name || ''].filter(Boolean).join(' · ');
                    return '<div class="comms-dir-item" role="listitem" data-user-id="' + u.id + '">' +
                        '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                        '<button type="button" class="comms-dir-open" data-user-id="' + u.id + '" style="border:0;background:transparent;text-align:left;padding:0;cursor:pointer;min-width:0;">' +
                        '<div class="comms-conv-name">' + Comms.escapeHtml(u.name || 'User') + '</div>' +
                        '<div class="comms-dir-meta">' + Comms.escapeHtml(meta) + '</div>' +
                        '</button>' +
                        '<div class="comms-dir-actions">' +
                        '<button type="button" class="comms-icon-btn" data-dir-call="voice" data-user-id="' + u.id + '" title="Voice call" aria-label="Voice call">' +
                        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>' +
                        '</button>' +
                        '<button type="button" class="comms-icon-btn" data-dir-call="video" data-user-id="' + u.id + '" title="Video call" aria-label="Video call">' +
                        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>' +
                        '</button>' +
                        '</div></div>';
                }).join('');
        }).join('');
    }

    function openOrCallUser(userId, callType) {
        return Comms.api(routes.storeConversation, {
            method: 'POST',
            body: JSON.stringify({ user_id: Number(userId) }),
            dedupe: false
        }).then(function (data) {
            var conversation = data.conversation;
            return openConversation(conversation.id).then(function () {
                if (callType && window.CommsWebRTC) {
                    window.CommsWebRTC.startCall(conversation.id, callType, conversation);
                }
            });
        }).catch(function (err) {
            Comms.showToast(err.message || 'Unable to open conversation.', 'error');
        });
    }

    function saveMsgCache(id, messages, emojis) {
        var payload = {
            messages: messages,
            reactionEmojis: emojis,
            conversation: state.activeConversation || null,
            ts: Date.now()
        };
        state.messageCache[String(id)] = payload;
        if (Comms.writeThreadCache) Comms.writeThreadCache(id, payload);
    }

    function loadMsgCache(id) {
        if (state.messageCache[String(id)]) return state.messageCache[String(id)];
        var stored = Comms.readThreadCache ? Comms.readThreadCache(id) : null;
        if (stored) {
            state.messageCache[String(id)] = stored;
            return stored;
        }
        return null;
    }

    function persistActiveThreadCache() {
        if (!state.activeId) return;
        saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
    }

    function hydrateFromCache() {
        var inbox = Comms.readInboxCache ? Comms.readInboxCache() : null;
        var officials = Comms.readOfficialsCache ? Comms.readOfficialsCache() : null;
        if (Array.isArray(inbox)) state.conversations = inbox;
        if (Array.isArray(officials)) state.barangayOfficials = officials;
        if ((inbox && inbox.length) || (officials && officials.length)) {
            renderConversations(els.convFilter ? els.convFilter.value : '');
        }
    }

    var inboxReloadTimer = null;

    function scheduleReloadConversations() {
        window.clearTimeout(inboxReloadTimer);
        inboxReloadTimer = window.setTimeout(function () {
            loadConversations();
        }, 400);
    }

    function previewFromRealtimeRow(row) {
        if (!row) return 'No messages yet';
        if (row.deleted_for_all_at || row.deleted_for_all || row.message_type === 'deleted') {
            return 'This message was deleted';
        }
        if (row.message_type === 'image') return 'Sent a photo';
        if (row.message_type === 'file') return 'Sent a file';
        if (row.message_type === 'call' || row.message_type === 'system') return row.body || 'Call update';
        return row.body || 'No messages yet';
    }

    function noteInboxActivity(row) {
        if (!row || row.conversation_id == null) {
            scheduleReloadConversations();
            return;
        }
        var id = Number(row.conversation_id);
        var idx = state.conversations.findIndex(function (c) { return Number(c.id) === id; });
        if (idx === -1) {
            scheduleReloadConversations();
            return;
        }
        var conv = state.conversations[idx];
        var mine = Number(row.sender_id) === Number(state.currentUserId)
            && String(row.sender_type || '') === String(state.portalUserType || '');
        conv.last_message = {
            id: row.id,
            body: previewFromRealtimeRow(row),
            message_type: row.deleted_for_all_at || row.deleted_for_all ? 'deleted' : row.message_type,
            sender_id: row.sender_id,
            deleted_for_all: !!(row.deleted_for_all_at || row.deleted_for_all),
            created_at: row.created_at
        };
        conv.updated_at = row.created_at || conv.updated_at;
        if (!mine && Number(state.activeId) !== id && row.id) {
            conv.unread_count = Number(conv.unread_count || 0) + 1;
        }
        state.conversations.splice(idx, 1);
        state.conversations.unshift(conv);
        if (Comms.writeInboxCache) Comms.writeInboxCache(state.conversations);
        renderConversations(els.convFilter ? els.convFilter.value : '');
        scheduleReloadConversations();
    }

    function messagesFingerprint(list) {
        return (list || []).map(function (m) {
            return [
                m.id,
                m.body || '',
                m.message_type || '',
                m.deleted_for_all ? 1 : 0,
                m.edited ? 1 : 0,
                (m.attachments && m.attachments.length) || 0,
                JSON.stringify(m.reactions || [])
            ].join(':');
        }).join('|');
    }

    function loadConversations() {
        return Comms.api(routes.conversations).then(function (data) {
            state.conversations = data.conversations || [];
            if (Comms.writeInboxCache) Comms.writeInboxCache(state.conversations);
            renderConversations(els.convFilter ? els.convFilter.value : '');
        }).catch(function (err) {
            if (!state.conversations.length) {
                Comms.showToast(err.message || 'Failed to load conversations.', 'error');
            }
        });
    }

    function loadBarangayOfficials() {
        if (!routes.searchUsers) return Promise.resolve();
        return Comms.api(routes.searchUsers).then(function (data) {
            state.barangayOfficials = data.users || [];
            if (Comms.writeOfficialsCache) Comms.writeOfficialsCache(state.barangayOfficials);
            renderConversations(els.convFilter ? els.convFilter.value : '');
        }).catch(function () {
            if (!state.barangayOfficials.length) state.barangayOfficials = [];
        });
    }

    function openConversation(id, opts) {
        opts = opts || {};
        state.activeId = Number(id);
        setThreadOpen(true);
        els.threadEmpty.hidden = true;
        els.threadActive.hidden = false;
        renderConversations(els.convFilter.value);

        if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
            window.CommsRealtime.subscribeConversation(state.activeId);
        }

        var listConv = state.conversations.find(function (c) {
            return Number(c.id) === Number(id);
        });
        if (listConv && listConv.other_user) {
            updatePeerHeader(listConv);
        }

        var cached = loadMsgCache(id);
        var hadCache = !!(cached && Array.isArray(cached.messages) && cached.messages.length);
        if (hadCache) {
            state.messages = cached.messages;
            if (Array.isArray(cached.reactionEmojis) && cached.reactionEmojis.length) {
                state.reactionEmojis = cached.reactionEmojis;
            }
            if (cached.conversation) {
                state.activeConversation = cached.conversation;
                updatePeerHeader(cached.conversation);
            }
            renderMessages({ stickBottom: true });
        }

        var convPromise = Comms.api(Comms.route(routes.showConversation, id));
        var msgPromise = Comms.api(Comms.route(routes.messages, id));

        convPromise.then(function (data) {
            if (!data || !data.conversation) return;
            if (Number(state.activeId) !== Number(id)) return;
            state.activeConversation = data.conversation;
            updatePeerHeader(data.conversation);
            persistActiveThreadCache();
        }).catch(function () { /* non-fatal */ });

        return msgPromise.then(function (data) {
            var freshMessages = data.messages || [];
            var freshEmojis = Array.isArray(data.reaction_emojis) ? data.reaction_emojis : [];
            if (Number(state.activeId) === Number(id)) {
                var changed = messagesFingerprint(freshMessages) !== messagesFingerprint(state.messages);
                if (freshEmojis.length) state.reactionEmojis = freshEmojis;
                if (changed || !hadCache) {
                    state.messages = freshMessages;
                    renderMessages({ stickBottom: true });
                }
            }
            saveMsgCache(id, Number(state.activeId) === Number(id) ? state.messages : freshMessages, freshEmojis.length ? freshEmojis : state.reactionEmojis);
            Comms.api(Comms.route(routes.read, id), { method: 'POST', body: '{}' }).catch(function () {});
            if (!opts.skipReloadList) {
                scheduleReloadConversations();
            }
        }).catch(function (err) {
            if (!hadCache) {
                Comms.showToast(err.message || 'Unable to open conversation.', 'error');
            }
        });
    }

    function appendMessage(message) {
        if (!message || Number(message.conversation_id) !== Number(state.activeId)) return;
        if (state.messages.some(function (m) { return Number(m.id) === Number(message.id); })) return;
        state.messages.push(message);
        persistActiveThreadCache();
        renderMessages();
    }

    function reloadActiveMessages() {
        if (!state.activeId) return Promise.resolve();
        return Comms.api(Comms.route(routes.messages, state.activeId)).then(function (data) {
            state.messages = data.messages || [];
            if (Array.isArray(data.reaction_emojis) && data.reaction_emojis.length) {
                state.reactionEmojis = data.reaction_emojis;
            }
            persistActiveThreadCache();
            renderMessages();
            return loadConversations();
        }).catch(function () { /* ignore */ });
    }

    function resizeComposer() {
        if (!els.input) return;
        els.input.style.height = 'auto';
        var next = Math.min(Math.max(els.input.scrollHeight, 44), 180);
        els.input.style.height = next + 'px';
        if (els.messages) {
            els.messages.scrollTop = els.messages.scrollHeight;
        }
    }

    function sendMessage(body) {
        if (state.sendInFlight || !state.activeId) return;
        var text = String(body || '').trim();
        var file = state.pendingAttachment;
        if (!text && !file) return;
        state.sendInFlight = true;
        if (els.sendBtn) els.sendBtn.disabled = true;
        if (els.attachBtn) els.attachBtn.disabled = true;
        setUploadProgress(file ? 8 : 0, !file);

        var request;
        if (file) {
            var form = new FormData();
            if (text) form.append('body', text);
            form.append('attachment', file, file.name);
            request = Comms.api(Comms.route(routes.messages, state.activeId), {
                method: 'POST',
                body: form,
                dedupe: false
            });
            setUploadProgress(55, false);
        } else {
            request = Comms.api(Comms.route(routes.messages, state.activeId), {
                method: 'POST',
                body: JSON.stringify({ body: text }),
                dedupe: false
            });
        }

        request.then(function (data) {
            setUploadProgress(100, !file);
            clearPendingAttachment();
            els.input.value = '';
            resizeComposer();
            appendMessage(data.message);
            return loadConversations();
        }).catch(function (err) {
            setUploadProgress(0, true);
            Comms.showToast(err.message || 'Failed to send message.', 'error');
        }).finally(function () {
            state.sendInFlight = false;
            if (els.sendBtn) els.sendBtn.disabled = false;
            if (els.attachBtn) els.attachBtn.disabled = false;
            window.setTimeout(function () { setUploadProgress(0, true); }, 400);
            els.input.focus();
        });
    }

    function startConversationWithUser(userId) {
        return Comms.api(routes.storeConversation, {
            method: 'POST',
            body: JSON.stringify({ user_id: Number(userId) }),
            dedupe: false
        }).then(function (data) {
            els.searchResults.hidden = true;
            els.userSearch.value = '';
            return loadConversations().then(function () {
                return openConversation(data.conversation.id);
            });
        }).catch(function (err) {
            Comms.showToast(err.message || 'Unable to start conversation.', 'error');
        });
    }

    function searchUsers(q) {
        q = String(q == null ? '' : q).trim();
        Comms.api(routes.searchUsers + (q ? '?q=' + encodeURIComponent(q) : '')).then(function (data) {
            var users = data.users || [];
            if (!users.length) {
                els.searchResults.innerHTML = '<div class="comms-search-item"><span>No SK Officials found in your barangay</span></div>';
                els.searchResults.hidden = false;
                return;
            }
            els.searchResults.innerHTML = users.map(function (u) {
                return '<button type="button" class="comms-search-item" data-user-id="' + u.id + '">' +
                    '<strong>' + Comms.escapeHtml(u.name) + '</strong>' +
                    '<span>' + Comms.escapeHtml(u.user_type_label || u.role) + '</span></button>';
            }).join('');
            els.searchResults.hidden = false;
        }).catch(function (err) {
            Comms.showToast(err.message || 'Search failed.', 'error');
        });
    }

    els.convList.addEventListener('click', function (e) {
        var btn = e.target.closest('.comms-conv-item');
        if (!btn) return;
        if (btn.dataset.userId) {
            startConversationWithUser(btn.dataset.userId);
            return;
        }
        openConversation(btn.dataset.id);
    });

    if (els.messages) {
        els.messages.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-react-toggle]');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                showPageReactionPicker(Number(toggle.getAttribute('data-react-toggle')), toggle);
                return;
            }
            var emojiBtn = e.target.closest('[data-react-emoji]');
            if (emojiBtn) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = emojiBtn.closest('.comms-bubble-wrap');
                var mid = wrap ? Number(wrap.getAttribute('data-id')) : 0;
                var emoji = emojiBtn.getAttribute('data-react-emoji');
                if (mid && emoji) toggleReaction(mid, emoji);
                return;
            }
            var saveBtn = e.target.closest('[data-edit-save]');
            if (saveBtn) {
                e.preventDefault();
                e.stopPropagation();
                saveEditedMessage(Number(saveBtn.getAttribute('data-edit-save')));
                return;
            }
            var cancelBtn = e.target.closest('[data-edit-cancel]');
            if (cancelBtn) {
                e.preventDefault();
                e.stopPropagation();
                state.editingId = null;
                renderMessages({ preserveScroll: true });
                return;
            }
            var moreBtn = e.target.closest('[data-msg-more]');
            if (moreBtn) {
                e.preventDefault();
                e.stopPropagation();
                var wrapMore = moreBtn.closest('.comms-bubble-wrap');
                var alreadyOpen = wrapMore && wrapMore.classList.contains('is-menu-open');
                hidePageMsgMenu();
                if (!alreadyOpen) {
                    showPageMsgMenu(Number(moreBtn.getAttribute('data-msg-more')), moreBtn);
                }
            }
        });
        els.messages.addEventListener('scroll', function () {
            hidePageReactionPicker();
            hidePageMsgMenu();
        }, { passive: true });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-msg-more]') && !e.target.closest('#commsPageMsgMenu')) {
            hidePageMsgMenu();
        }
        if (e.target.closest('.comms-reaction-picker') || e.target.closest('[data-react-toggle]')) return;
        hidePageReactionPicker();
    });

    if (els.directoryList) {
        els.directoryList.addEventListener('click', function (e) {
            var callBtn = e.target.closest('[data-dir-call]');
            if (callBtn) {
                openOrCallUser(callBtn.dataset.userId, callBtn.dataset.dirCall);
                return;
            }
            var openBtn = e.target.closest('[data-user-id]');
            if (openBtn) {
                openOrCallUser(openBtn.dataset.userId, null);
            }
        });
    }

    if (els.filterBar) {
        els.filterBar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-filter]');
            if (!btn) return;
            els.filterBar.querySelectorAll('.comms-filter-btn').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
            });
            var filter = btn.dataset.filter;
            if (filter === 'conversations') {
                setListMode('conversations');
                loadConversations();
                return;
            }
            state.directoryFilter = filter;
            setListMode(filter);
            loadDirectory();
        });
    }

    if (els.barangayFilter) {
        els.barangayFilter.addEventListener('change', function () {
            state.barangayId = els.barangayFilter.value || '';
            if (state.listMode !== 'conversations') {
                loadDirectory();
            }
        });
    }

    els.searchResults.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-user-id]');
        if (!btn) return;
        startConversationWithUser(btn.dataset.userId);
    });

    els.userSearch.addEventListener('input', function () {
        clearTimeout(state.searchTimer);
        var q = els.userSearch.value.trim();
        state.searchTimer = setTimeout(function () { searchUsers(q); }, 250);
    });
    els.userSearch.addEventListener('focus', function () {
        if (!els.userSearch.value.trim()) {
            searchUsers('');
        }
    });

    els.convFilter.addEventListener('input', function () {
        clearTimeout(state.filterTimer);
        state.filterTimer = setTimeout(function () {
            renderConversations(els.convFilter.value);
        }, 120);
    });

    els.composer.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(els.input.value);
    });

    if (els.attachBtn && els.attachInput) {
        els.attachBtn.addEventListener('click', function () {
            els.attachInput.click();
        });
        els.attachInput.addEventListener('change', function () {
            var file = els.attachInput.files && els.attachInput.files[0];
            if (!file) return;
            setPendingAttachment(file);
        });
    }
    if (els.attachClear) {
        els.attachClear.addEventListener('click', function () {
            clearPendingAttachment();
        });
    }

    els.input.addEventListener('input', resizeComposer);

    els.input.addEventListener('keydown', function (e) {
        // Enter = new line only. Ctrl/Cmd+Enter sends.
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            sendMessage(els.input.value);
            return;
        }
        if (window.CommsRealtime && typeof window.CommsRealtime.broadcastTyping === 'function' && state.activeId) {
            window.CommsRealtime.broadcastTyping(state.activeId);
        }
    });

    els.backBtn.addEventListener('click', function () {
        setThreadOpen(false);
        state.activeId = null;
        els.threadActive.hidden = true;
        els.threadEmpty.hidden = false;
        renderConversations(els.convFilter.value);
    });

    els.voiceBtn.addEventListener('click', function () {
        if (window.CommsWebRTC && state.activeId) {
            window.CommsWebRTC.startCall(state.activeId, 'voice', state.activeConversation);
        }
    });

    els.videoBtn.addEventListener('click', function () {
        if (window.CommsWebRTC && state.activeId) {
            window.CommsWebRTC.startCall(state.activeId, 'video', state.activeConversation);
        }
    });

    document.addEventListener('click', function (e) {
        if (!els.searchResults.contains(e.target) && e.target !== els.userSearch) {
            els.searchResults.hidden = true;
        }
    });

    window.CommsChat = {
        appendMessage: appendMessage,
        reloadConversations: loadConversations,
        reloadActiveMessages: reloadActiveMessages,
        noteInboxActivity: noteInboxActivity,
        onReactionChange: onReactionChange,
        onMessageChange: function (row) {
            if (!row || !state.activeId || Number(row.conversation_id) !== Number(state.activeId)) return;
            var idx = state.messages.findIndex(function (m) { return Number(m.id) === Number(row.id); });
            if (idx === -1) return;
            var skipToast = !!state.msgChangeSkipToast[String(row.id)];
            if (row.deleted_for_all_at) {
                state.messages[idx].deleted_for_all = true;
                state.messages[idx].message_type = 'deleted';
                state.messages[idx].body = null;
                state.messages[idx].attachments = [];
                state.messages[idx].reactions = [];
                if (!skipToast) Comms.showToast('Message deleted.', 'info');
            } else if (row.body != null) {
                var prevBody = state.messages[idx].body;
                state.messages[idx].body = row.body;
                state.messages[idx].edited = true;
                if (!skipToast && String(prevBody || '') !== String(row.body || '')) {
                    Comms.showToast('Message edited.', 'info');
                }
            }
            persistActiveThreadCache();
            renderMessages({ preserveScroll: true });
        },
        getActiveId: function () { return state.activeId; },
        setTyping: function (visible) {
            els.typing.hidden = !visible;
        },
        routes: routes,
        state: state
    };

    hydrateFromCache();
    Promise.all([loadConversations(), loadBarangayOfficials()]).then(function () {
        var initial = root.dataset.initialConversation;
        if (initial) openConversation(initial);
    });
})();
