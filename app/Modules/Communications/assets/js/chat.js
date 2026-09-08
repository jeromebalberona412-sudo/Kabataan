/**
 * Messenger UI: conversation list, thread, send/read, user search.
 */
import './communication.js';

(function () {
    'use strict';

    var root = document.getElementById('commsApp');
    if (!root || !window.Comms) return;

    var routes = {};
    try { routes = JSON.parse(root.dataset.routes || '{}'); } catch (e) { routes = {}; }

    var state = {
        currentUserId: Number(root.dataset.currentUserId || 0),
        portalUserType: root.dataset.portalUserType || '',
        messageMaxLength: Math.max(1, Number(root.dataset.messageMaxLength || 1000) || 1000),
        charLimitToastShown: false,
        conversations: [],
        barangayOfficials: [],
        activeId: null,
        activeConversation: null,
        messages: [],
        sendInFlight: false,
        searchTimer: null,
        loadingOlder: false,
        listMode: 'conversations',
        directoryFilter: 'officials',
        barangayId: '',
        reactionEmojis: ['👍', '❤️', '😆', '😮', '😢', '🙏'],
        openReactionPickerId: null,
        reactInFlight: {},
        reactSkipRealtime: {},
        reactSeq: {},
        reactLocalUntil: {},
        pendingAttachment: null,
        pendingPreviewUrl: null,
        pendingAttachments: [],
        pendingPreviewUrls: [],
        messageCache: {},
        editingId: null,
        msgChangeSkipToast: {},
        faqSuggestions: [],
        faqLoading: false,
        faqKnownEmpty: false,
        faqCooldownUntil: {},
        faqCooldownTimer: null,
        peerRefreshTimer: null
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
        attachMenu: document.getElementById('commsAttachMenu'),
        pickPhotos: document.getElementById('commsPickPhotos'),
        pickFiles: document.getElementById('commsPickFiles'),
        photoInput: document.getElementById('commsPhotoInput'),
        fileInput: document.getElementById('commsFileInput'),
        attachInput: document.getElementById('commsAttachInput'),
        attachPreview: document.getElementById('commsAttachPreview'),
        attachPreviewInner: document.getElementById('commsAttachPreviewInner'),
        attachClear: document.getElementById('commsAttachClear'),
        uploadProgress: document.getElementById('commsUploadProgress'),
        scrollBottom: document.getElementById('commsScrollBottom'),
        uploadProgressBar: document.getElementById('commsUploadProgressBar'),
        faqSuggestions: document.getElementById('commsFaqSuggestions'),
        faqSuggestionsList: document.getElementById('commsFaqSuggestionsList'),
        faqMenu: document.getElementById('commsFaqMenu'),
        faqMenuToggle: document.getElementById('commsFaqMenuToggle'),
        composerEnd: document.querySelector('.comms-composer-end')
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
            var fullName = peer.name || 'User';
            var displayName = Comms.truncateText ? Comms.truncateText(fullName, 30) : fullName;
            var avatar = peer.profile_image_url || Comms.defaultAvatar(fullName);
            var previewText = Comms.truncateText ? Comms.truncateText(preview, 40) : preview;
            return '<button type="button" class="comms-conv-item' + active + '" data-id="' + c.id + '" role="listitem" title="' + Comms.escapeHtml(fullName) + '">' +
                '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                '<div class="comms-conv-main">' +
                '<div class="comms-conv-name">' + Comms.escapeHtml(displayName) + '</div>' +
                '<div class="comms-conv-preview">' + Comms.escapeHtml(previewText) + '</div>' +
                '</div>' +
                '<div class="comms-conv-meta">' +
                '<span class="comms-conv-time">' + Comms.escapeHtml(Comms.formatTime(c.updated_at)) + '</span>' +
                (unread > 0 ? '<span class="comms-unread">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
                '</div></button>';
        }).join('') + starters.map(function (user) {
            var fullName = user.name || 'User';
            var displayName = Comms.truncateText ? Comms.truncateText(fullName, 30) : fullName;
            var avatar = user.profile_image_url || Comms.defaultAvatar(fullName);
            var preview = user.position || user.user_type_label || 'SK Official';
            var previewText = Comms.truncateText ? Comms.truncateText(preview, 40) : preview;
            return '<button type="button" class="comms-conv-item" data-user-id="' + user.id + '" role="listitem" title="' + Comms.escapeHtml(fullName) + '">' +
                '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                '<div class="comms-conv-main">' +
                '<div class="comms-conv-name">' + Comms.escapeHtml(displayName) + '</div>' +
                '<div class="comms-conv-preview">' + Comms.escapeHtml(previewText) + '</div>' +
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

    function formatFullDateTime(iso) {
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

    function formatDaySeparator(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        var now = new Date();
        var time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
        if (d.toDateString() === now.toDateString()) return time;
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        var weekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
        if (d >= weekAgo) {
            return d.toLocaleDateString([], { weekday: 'short' }) + ' ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ', ' + time;
    }

    function shouldInsertDateSeparator(prevIso, currIso) {
        if (!currIso) return false;
        if (!prevIso) return true;
        var prev = new Date(prevIso);
        var curr = new Date(currIso);
        if (Number.isNaN(prev.getTime()) || Number.isNaN(curr.getTime())) return true;
        if (prev.toDateString() !== curr.toDateString()) return true;
        return Math.abs(curr.getTime() - prev.getTime()) >= 45 * 60 * 1000;
    }

    function renderDateSeparator(iso) {
        var label = formatDaySeparator(iso);
        if (!label) return '';
        return '<div class="comms-date-sep" role="separator">' +
            '<span class="comms-date-sep-text">' + Comms.escapeHtml(label) + '</span>' +
            '</div>';
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

    function renderReactionChips(reactions, messageId) {
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
        return '<div class="comms-reaction-chips" role="group" aria-label="Reactions">' +
            '<button type="button" class="comms-reaction-summary' + (anyMine ? ' is-mine' : '') + '"' +
            ' data-react-people="' + Number(messageId || 0) + '"' +
            ' aria-haspopup="true" aria-label="See who reacted">' +
            '<span class="comms-reaction-stack">' +
            items.map(function (r) {
                return '<span class="comms-reaction-stack-btn' + (r.mine ? ' is-mine' : '') + '" data-react-people-emoji="' + Comms.escapeHtml(r.emoji) + '">' +
                    Comms.escapeHtml(r.emoji) +
                    '</span>';
            }).join('') +
            '</span>' +
            (total > 1 ? '<span class="comms-reaction-count">' + total + '</span>' : '') +
            '</button></div>';
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
        var msg = state.messages.find(function (m) { return Number(m.id) === id; });
        var mineEmoji = '';
        if (msg && Array.isArray(msg.reactions)) {
            msg.reactions.forEach(function (r) {
                if (r && r.mine) mineEmoji = String(r.emoji || '');
            });
        }
        var el = getPageReactionPickerEl();
        el.setAttribute('data-react-picker', String(id));
        el.innerHTML = state.reactionEmojis.map(function (emoji) {
            var active = mineEmoji && emoji === mineEmoji ? ' is-active' : '';
            return '<button type="button" class="comms-reaction-pick' + active + '" data-react-emoji="' + Comms.escapeHtml(emoji) + '" role="menuitem" aria-pressed="' + (active ? 'true' : 'false') + '">' +
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
        var canEdit = !deleted && !!msg.mine && msg.message_type === 'text' && String(msg.body || '').trim() !== '';
        var items = '';
        if (canEdit) {
            items += '<button type="button" role="menuitem" data-msg-edit="' + msg.id + '">Edit</button>';
        }
        items += '<button type="button" role="menuitem" data-msg-delete="' + msg.id + '">Delete</button>';
        var el = getPageMsgMenuEl();
        el.innerHTML = items;
        el.setAttribute('data-msg-menu', String(msg.id));
        el.hidden = false;
        if (els.messages) {
            els.messages.querySelectorAll('.comms-bubble-wrap').forEach(function (wrap) {
                var isOpen = String(wrap.getAttribute('data-id')) === String(msg.id);
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
        if (body.length > state.messageMaxLength) {
            Comms.showToast('Message exceeds the ' + state.messageMaxLength + ' character limit.', 'error');
            return;
        }
        var saveBtn = els.messages.querySelector('[data-edit-save="' + messageId + '"]');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('is-loading');
            saveBtn.setAttribute('aria-busy', 'true');
            if (!saveBtn.dataset.label) saveBtn.dataset.label = saveBtn.textContent || 'Save';
            saveBtn.textContent = 'Saving';
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
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('is-loading');
                saveBtn.removeAttribute('aria-busy');
                saveBtn.textContent = saveBtn.dataset.label || 'Save';
            }
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

    function fileKindClass(att) {
        var name = String((att && att.file_name) || '');
        var mime = String((att && att.mime_type) || '').toLowerCase();
        if (/\.pdf$/i.test(name) || mime === 'application/pdf') return ' is-pdf';
        if (/\.docx?$/i.test(name) || mime.indexOf('msword') !== -1 || mime.indexOf('wordprocessingml') !== -1) return ' is-word';
        return '';
    }

    function isImageAttachment(att) {
        if (!att) return false;
        return att.file_type === 'image'
            || att.storage_provider === 'cloudinary'
            || (att.url && /^image\//i.test(String(att.mime_type || '')))
            || /\.(jpe?g|png|gif|webp|bmp|heic)$/i.test(String(att.file_name || ''));
    }

    function isImageOnlyMessage(m) {
        if (!m || m.message_type === 'call' || m.message_type === 'system' || m.message_type === 'deleted' || m.deleted_for_all) {
            return false;
        }
        var hasAttachments = (m.message_type === 'image' || m.message_type === 'file')
            || (Array.isArray(m.attachments) && m.attachments.length > 0);
        if (!hasAttachments || String(m.body || '').trim()) return false;
        var atts = m.attachments || [];
        return atts.length > 0 && atts.every(isImageAttachment);
    }

    function sameImageBatch(a, b) {
        if (!isImageOnlyMessage(a) || !isImageOnlyMessage(b)) return false;
        if (!!a.mine !== !!b.mine) return false;
        if (a.batch_id && b.batch_id) return String(a.batch_id) === String(b.batch_id);
        if (a.batch_id || b.batch_id) return false;
        var t1 = Date.parse(a.created_at || '') || 0;
        var t2 = Date.parse(b.created_at || '') || 0;
        return !!(t1 && t2 && Math.abs(t2 - t1) <= 4000);
    }

    function imageBatchEndIndex(items, start) {
        var end = start;
        while (end + 1 < items.length && sameImageBatch(items[start], items[end + 1])) end += 1;
        return end;
    }

    function collectImageAttachments(messages) {
        var out = [];
        (messages || []).forEach(function (m) {
            (m.attachments || []).forEach(function (att) {
                if (!isImageAttachment(att)) return;
                var src = att.url || att.download_url || '';
                if (!src) return;
                out.push({ src: src, file_name: att.file_name || 'Image', message: m, attachment: att });
            });
        });
        return out;
    }

    function renderAttachments(attachments) {
        var items = Array.isArray(attachments) ? attachments : [];
        if (!items.length) return '';
        var images = [];
        var files = [];
        items.forEach(function (att) {
            if (isImageAttachment(att)) images.push(att);
            else files.push(att);
        });
        var html = '';
        if (images.length) {
            var countAttr = Math.min(images.length, 9);
            html += '<div class="comms-bubble-attachment-grid" data-count="' + countAttr + '"' +
                (images.length > 9 ? ' data-extra="' + (images.length - 9) + '"' : '') + '>';
            images.forEach(function (att, i) {
                var src = att.url || att.download_url;
                if (i >= 9) {
                    html += '<button type="button" class="comms-bubble-image is-overflow-hidden" tabindex="-1" aria-hidden="true" data-comms-lightbox-src="' +
                        Comms.escapeHtml(src) + '"><img src="' + Comms.escapeHtml(src) + '" alt=""></button>';
                    return;
                }
                var extra = (i === 8 && images.length > 9)
                    ? '<span class="comms-bubble-attachment-more">+' + (images.length - 9) + '</span>'
                    : '';
                html += '<button type="button" class="comms-bubble-image" data-comms-lightbox-src="' + Comms.escapeHtml(src) + '" aria-label="View image fullscreen">' +
                    '<img src="' + Comms.escapeHtml(src) + '" alt="' + Comms.escapeHtml(att.file_name || 'Image') + '" loading="lazy">' +
                    extra + '</button>';
            });
            html += '</div>';
        }
        files.forEach(function (att) {
            html += '<a class="comms-bubble-file' + fileKindClass(att) + '" href="' + Comms.escapeHtml(att.download_url) + '" target="_blank" rel="noopener noreferrer">' +
                '<span class="comms-bubble-file-ext">' + Comms.escapeHtml(fileExtLabel(att.file_name)) + '</span>' +
                '<span class="comms-bubble-file-meta">' +
                '<span class="comms-bubble-file-name">' + Comms.escapeHtml(att.file_name || 'Document') + '</span>' +
                '<span class="comms-bubble-file-size">' + Comms.escapeHtml(formatBytes(att.file_size)) + '</span>' +
                '</span></a>';
        });
        return html;
    }

    function renderImageBatchBubble(groupMsgs) {
        var images = collectImageAttachments(groupMsgs);
        if (!images.length) return '';
        var last = groupMsgs[groupMsgs.length - 1];
        var mine = !!last.mine;
        var pickerOpen = Number(state.openReactionPickerId) === Number(last.id);
        var isLocalMsg = String(last.id || '').indexOf('local-') === 0;
        var showMsgActions = !!mine && !isLocalMsg;
        var sideCls = mine ? 'comms-bubble--mine' : 'comms-bubble--theirs';
        var batchAttr = last.batch_id ? ' data-batch-id="' + Comms.escapeHtml(String(last.batch_id)) + '"' : '';
        var countAttr = Math.min(images.length, 9);
        var grid = '<div class="comms-bubble-attachment-grid" data-count="' + countAttr + '"' +
            (images.length > 9 ? ' data-extra="' + (images.length - 9) + '"' : '') + '>';
        images.forEach(function (item, i) {
            if (i >= 9) {
                grid += '<button type="button" class="comms-bubble-image is-overflow-hidden" tabindex="-1" aria-hidden="true" data-comms-lightbox-src="' +
                    Comms.escapeHtml(item.src) + '"><img src="' + Comms.escapeHtml(item.src) + '" alt=""></button>';
                return;
            }
            var extra = (i === 8 && images.length > 9)
                ? '<span class="comms-bubble-attachment-more">+' + (images.length - 9) + '</span>'
                : '';
            grid += '<button type="button" class="comms-bubble-image" data-comms-lightbox-src="' + Comms.escapeHtml(item.src) +
                '" aria-label="View image fullscreen"><img src="' + Comms.escapeHtml(item.src) + '" alt="' +
                Comms.escapeHtml(item.file_name) + '" loading="lazy">' + extra + '</button>';
        });
        grid += '</div>';
        var html = '<div class="comms-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') +
            ' is-image-batch" data-id="' + last.id + '" data-batch-size="' + images.length + '"' + batchAttr + '>' +
            '<div class="comms-bubble-row">' +
            '<div class="comms-bubble-cluster">' +
            '<div class="comms-bubble ' + sideCls + ' has-attachment has-attachment-only">' +
            grid +
            '</div>' +
            '</div>' +
            '<div class="comms-bubble-tools">' +
            (showMsgActions ? renderMessageActions(last) : '') +
            '<button type="button" class="comms-react-btn" data-react-toggle="' + last.id +
            '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' +
            reactBtnIcon + '</button>' +
            '</div></div>' +
            renderReactionChips(last.reactions, last.id) +
            '</div>';
        groupMsgs.slice(0, -1).forEach(function (m) {
            html += '<div class="comms-batch-anchor" hidden data-id="' + m.id + '" aria-hidden="true"></div>';
        });
        return html;
    }

    function isImageFile(file) {
        return String((file && file.type) || '').indexOf('image/') === 0;
    }

    function setAttachMenuOpen(open) {
        if (!els.attachMenu || !els.attachBtn) return;
        els.attachMenu.hidden = !open;
        els.attachBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function clearPendingAttachment() {
        (state.pendingPreviewUrls || []).forEach(function (u) {
            if (u) {
                try { URL.revokeObjectURL(u); } catch (e) {}
            }
        });
        if (state.pendingPreviewUrl) {
            try { URL.revokeObjectURL(state.pendingPreviewUrl); } catch (e) {}
        }
        state.pendingAttachments = [];
        state.pendingPreviewUrls = [];
        state.pendingAttachment = null;
        state.pendingPreviewUrl = null;
        if (els.photoInput) els.photoInput.value = '';
        if (els.fileInput) els.fileInput.value = '';
        if (els.attachInput) els.attachInput.value = '';
        if (els.attachPreview) els.attachPreview.hidden = true;
        if (els.attachPreviewInner) els.attachPreviewInner.innerHTML = '';
        setUploadProgress(0, true);
        syncComposerEndControls();
        syncComposerHeightVar();
    }

    function renderAttachPreview() {
        if (!els.attachPreview || !els.attachPreviewInner) return;
        var files = state.pendingAttachments || [];
        if (!files.length) {
            els.attachPreview.hidden = true;
            els.attachPreviewInner.innerHTML = '';
            syncComposerHeightVar();
            return;
        }
        els.attachPreview.hidden = false;
        els.attachPreviewInner.innerHTML = files.map(function (file, i) {
            var url = state.pendingPreviewUrls[i];
            if (isImageFile(file) && url) {
                return '<span class="comms-attach-preview-item">' +
                    '<button type="button" class="comms-attach-preview-zoom" data-comms-lightbox-src="' +
                    Comms.escapeHtml(url) + '" aria-label="Zoom preview image">' +
                    '<img src="' + Comms.escapeHtml(url) + '" alt="' + Comms.escapeHtml(file.name || 'Image') + '">' +
                    '</button>' +
                    '<span class="comms-attach-preview-name">' + Comms.escapeHtml(file.name || 'Image') + '</span>' +
                    '</span>';
            }
            return '<span class="comms-attach-preview-item">' +
                '<span class="comms-attach-preview-file"><strong>' + Comms.escapeHtml(fileExtLabel(file.name)) + '</strong> ' +
                Comms.escapeHtml(file.name || 'File') + '</span></span>';
        }).join('');
        if (els.attachPreview.dataset.zoomWired !== 'true') {
            els.attachPreview.addEventListener('click', function (e) {
                var thumb = e.target.closest('[data-comms-lightbox-src]');
                if (!thumb || !els.attachPreview.contains(thumb)) return;
                e.preventDefault();
                if (typeof Comms.openImageLightbox === 'function') {
                    var srcs = (state.pendingPreviewUrls || []).filter(Boolean);
                    Comms.openImageLightbox(thumb.getAttribute('data-comms-lightbox-src'), srcs.length ? srcs : [
                        thumb.getAttribute('data-comms-lightbox-src')
                    ]);
                }
            });
            els.attachPreview.dataset.zoomWired = 'true';
        }
        syncComposerHeightVar();
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

    function pushPendingFiles(files, kind) {
        var maxImg = 50;
        var maxFile = 10;
        var maxBytes = 25 * 1024 * 1024;
        var list = Array.prototype.slice.call(files || []);
        if (!list.length) return;

        var selectBytes = 0;
        var selectImg = 0;
        var selectFile = 0;
        list.forEach(function (f) {
            selectBytes += f.size || 0;
            if (isImageFile(f)) selectImg++;
            else selectFile++;
        });

        var pendingBytes = 0;
        var imgCount = 0;
        var fileCount = 0;
        (state.pendingAttachments || []).forEach(function (f) {
            pendingBytes += f.size || 0;
            if (isImageFile(f)) imgCount++;
            else fileCount++;
        });

        if (selectBytes > maxBytes || (pendingBytes + selectBytes) > maxBytes) {
            Comms.showToast('Total attachment size cannot exceed 25 MB. Selection was not added.', 'error');
            return;
        }
        if (kind === 'image' && (imgCount + selectImg) > maxImg) {
            Comms.showToast('Max ' + maxImg + ' images (still within 25 MB). Selection was not added.', 'error');
            return;
        }
        if (kind !== 'image' && (fileCount + selectFile) > maxFile) {
            Comms.showToast('Max ' + maxFile + ' files. Selection was not added.', 'error');
            return;
        }

        list.forEach(function (f) {
            state.pendingAttachments.push(f);
            state.pendingPreviewUrls.push(isImageFile(f) ? URL.createObjectURL(f) : null);
        });
        state.pendingAttachment = state.pendingAttachments[0] || null;
        state.pendingPreviewUrl = state.pendingPreviewUrls[0] || null;
        renderAttachPreview();
        syncComposerEndControls();
    }

    function setPendingAttachment(file) {
        if (!file) return;
        pushPendingFiles([file], isImageFile(file) ? 'image' : 'file');
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
        var items = state.messages || [];
        var parts = [];
        var skipUntil = -1;
        var prevCreatedAt = null;
        items.forEach(function (m, index) {
            if (index <= skipUntil) return;

            if (shouldInsertDateSeparator(prevCreatedAt, m.created_at)) {
                parts.push(renderDateSeparator(m.created_at));
            }

            if (m.message_type === 'call' || m.message_type === 'system') {
                parts.push('<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + m.id + '" title="' + Comms.escapeHtml(formatFullDateTime(m.created_at)) + '">' +
                    '<div class="comms-bubble-row comms-bubble-row--call">' +
                    '<div class="comms-bubble-cluster">' +
                    '<div class="' + callBubbleClass(m.body) + '" role="status">' +
                    callEventIcon(m.body) +
                    '<span class="comms-call-text">' + Comms.escapeHtml(m.body) + '</span>' +
                    '</div>' +
                    '</div></div></div>');
                prevCreatedAt = m.created_at;
                return;
            }
            if (m.deleted_for_all || m.message_type === 'deleted') {
                parts.push('<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + '" data-id="' + m.id + '" title="' + Comms.escapeHtml(formatFullDateTime(m.created_at)) + '">' +
                    '<div class="comms-bubble-row">' +
                    '<div class="comms-bubble-cluster">' +
                    '<div class="comms-bubble comms-bubble--deleted">' +
                    '<div class="comms-bubble-text">This message was deleted</div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="comms-bubble-tools">' + renderMessageActions(m) + '</div>' +
                    '</div></div>');
                prevCreatedAt = m.created_at;
                return;
            }
            var mine = !!m.mine;
            var pickerOpen = Number(state.openReactionPickerId) === Number(m.id);
            var editing = Number(state.editingId) === Number(m.id);

            if (!editing && isImageOnlyMessage(m)) {
                var batchEnd = imageBatchEndIndex(items, index);
                var group = items.slice(index, batchEnd + 1);
                if (collectImageAttachments(group).length >= 2) {
                    parts.push(renderImageBatchBubble(group));
                    skipUntil = batchEnd;
                    prevCreatedAt = (group[group.length - 1] && group[group.length - 1].created_at) || m.created_at;
                    return;
                }
            }

            var bodyHtml = '';
            if (m.body && !editing) {
                bodyHtml = '<div class="comms-bubble-text">' + Comms.escapeHtml(m.body) +
                    (m.edited ? ' <span class="comms-edited">edited</span>' : '') +
                    '</div>';
            }
            var isAutomation = m.message_type === 'automation';
            var hasAttachments = (m.message_type === 'image' || m.message_type === 'file') ||
                (Array.isArray(m.attachments) && m.attachments.length > 0);
            var hasTextBody = !!String(m.body || '').trim() || editing;
            var attachmentOnly = hasAttachments && !hasTextBody;
            var splitAttachText = hasAttachments && hasTextBody && !editing;
            var isLocalMsg = String(m.id || '').indexOf('local-') === 0;
            var showMsgActions = !isAutomation && !!mine && !isLocalMsg && !editing;
            var sideCls = mine ? 'comms-bubble--mine' : 'comms-bubble--theirs';
            var bubbleExtra = (hasAttachments ? ' has-attachment' : '') +
                (attachmentOnly ? ' has-attachment-only' : '') +
                (isAutomation ? ' comms-bubble--automation' : '');
            var attachHtml = renderAttachments(m.attachments);
            var bubbleContent;
            if (editing) {
                bubbleContent =
                    '<div class="comms-bubble-cluster">' +
                    '<div class="comms-edit-panel" role="group" aria-label="Edit message">' +
                    '<textarea class="comms-edit-input" data-edit-input="' + m.id + '" maxlength="' + state.messageMaxLength + '" rows="3">' +
                    Comms.escapeHtml(m.body || '') +
                    '</textarea>' +
                    '<div class="comms-edit-footer">' +
                    '<div class="comms-edit-actions">' +
                    '<button type="button" class="comms-edit-cancel" data-edit-cancel="' + m.id + '">Cancel</button>' +
                    '<button type="button" class="comms-edit-save" data-edit-save="' + m.id + '">Save</button>' +
                    '</div></div></div></div>';
            } else if (splitAttachText) {
                bubbleContent =
                    '<div class="comms-bubble-cluster">' +
                    '<div class="comms-bubble ' + sideCls + ' has-attachment has-attachment-only">' + attachHtml + '</div>' +
                    '<div class="comms-bubble ' + sideCls + (isAutomation ? ' comms-bubble--automation' : '') + '">' + bodyHtml + '</div>' +
                    '</div>';
            } else {
                bubbleContent =
                    '<div class="comms-bubble-cluster">' +
                    '<div class="comms-bubble ' + sideCls + bubbleExtra + '">' +
                    attachHtml +
                    bodyHtml +
                    '</div>' +
                    '</div>';
            }
            parts.push('<div class="comms-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') + (isAutomation ? ' is-automation' : '') + '" data-id="' + m.id + '"' +
                (m.batch_id ? ' data-batch-id="' + Comms.escapeHtml(String(m.batch_id)) + '"' : '') +
                ' title="' + Comms.escapeHtml(formatFullDateTime(m.created_at)) + '">' +
                '<div class="comms-bubble-row">' +
                bubbleContent +
                '<div class="comms-bubble-tools">' +
                (showMsgActions ? renderMessageActions(m) : '') +
                '<button type="button" class="comms-react-btn" data-react-toggle="' + m.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + reactBtnIcon + '</button>' +
                '</div>' +
                '</div>' +
                renderReactionChips(m.reactions, m.id) +
                '</div>');
            prevCreatedAt = m.created_at;
        });
        els.messages.innerHTML = parts.join('');
        if (!els.messages) return;
        if (opts.preserveScroll) {
            els.messages.scrollTop = prevScroll;
        } else if (stickBottom || opts.stickBottom === true) {
            els.messages.scrollTop = els.messages.scrollHeight;
        } else {
            els.messages.scrollTop = prevScroll;
        }
        syncScrollBottomBtn();
        if (state.editingId) {
            var activeEdit = els.messages.querySelector('[data-edit-input="' + state.editingId + '"]');
            if (activeEdit) {
                activeEdit.focus();
                var val = activeEdit.value || '';
                activeEdit.setSelectionRange(val.length, val.length);
            }
        }
    }

    function syncComposerLimit() {
        if (!els.input) return;
        var len = composerMeasuredLength(els.input.value || '');
        var max = state.messageMaxLength;
        if (len >= max) {
            if (!state.charLimitToastShown) {
                state.charLimitToastShown = true;
                toastCharLimit();
            }
        } else {
            state.charLimitToastShown = false;
        }
    }

    function syncEditCount(input) {
        if (!input) return;
        var max = state.messageMaxLength;
        var cpl = Comms.estimateComposerCharsPerLine ? Comms.estimateComposerCharsPerLine(input) : 36;
        var len = Comms.measureMessageLength
            ? Comms.measureMessageLength(input.value || '', cpl)
            : String(input.value || '').length;
        if (len > max) {
            // Soft-trim raw text only when raw itself exceeds; weighted overage stays toast-only.
            if (String(input.value || '').length > max) {
                input.value = String(input.value || '').slice(0, max);
            }
            Comms.showToast('Message cannot exceed ' + max + ' characters.', 'error');
        }
    }

    function syncScrollBottomBtn() {
        if (!els.scrollBottom || !els.messages) return;
        var el = els.messages;
        var distance = el.scrollHeight - el.scrollTop - el.clientHeight;
        els.scrollBottom.hidden = distance < 80;
    }

    function jumpToLatestMessages() {
        if (!els.messages) return;
        els.messages.scrollTop = els.messages.scrollHeight;
        syncScrollBottomBtn();
    }

    function cloneReactions(reactions) {
        if (window.Comms && typeof Comms.cloneReactions === 'function') {
            return Comms.cloneReactions(reactions);
        }
        return (Array.isArray(reactions) ? reactions : []).map(function (r) {
            return {
                emoji: r.emoji,
                count: Number(r.count || 0),
                mine: !!r.mine,
                users: Array.isArray(r.users) ? r.users.slice() : []
            };
        });
    }

    function optimisticToggleReactions(reactions, emoji) {
        if (window.Comms && typeof Comms.optimisticToggleReactions === 'function') {
            return Comms.optimisticToggleReactions(reactions, emoji);
        }
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
                list.push({ emoji: emoji, count: 1, mine: true, users: [] });
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
        renderMessages({ preserveScroll: true });
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
        if (!Number(messageId) || String(messageId).indexOf('local-') === 0) return;

        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg) return;

        var previous = cloneReactions(msg.reactions);
        var next = optimisticToggleReactions(previous, emoji);
        var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
            && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

        var seq = (state.reactSeq[key] || 0) + 1;
        state.reactSeq[key] = seq;
        state.openReactionPickerId = null;
        hidePageReactionPicker();
        applyMessageReactions(messageId, next);
        if (added && typeof Comms.playReactionSound === 'function') {
            Comms.playReactionSound();
        }

        state.reactSkipRealtime[key] = true;
        state.reactLocalUntil[key] = Date.now() + 3200;

        Comms.api(Comms.route(routes.react, messageId), {
            method: 'POST',
            body: JSON.stringify({ emoji: emoji }),
            dedupe: false
        }).then(function (data) {
            if (state.reactSeq[key] !== seq) return;
            applyMessageReactions(data.message_id || messageId, data.reactions || next);
            state.reactLocalUntil[key] = Date.now() + 3200;
        }).catch(function (err) {
            if (state.reactSeq[key] !== seq) return;
            applyMessageReactions(messageId, previous);
            Comms.showToast(err.message || 'Unable to react.', 'error');
        }).finally(function () {
            if (state.reactSeq[key] !== seq) return;
            window.setTimeout(function () {
                if (state.reactSeq[key] !== seq) return;
                delete state.reactSkipRealtime[key];
            }, 2800);
        });
    }

    function updatePeerHeader(conversation) {
        var peer = (conversation && conversation.other_user) || {};
        var fullName = peer.name || 'User';
        els.peerName.textContent = Comms.truncateText ? Comms.truncateText(fullName, 36) : fullName;
        els.peerName.setAttribute('title', fullName);
        var statusBits = [];
        var position = String(peer.position || '').trim();
        var barangay = String(peer.barangay_name || '').trim();
        if (position) statusBits.push(position);
        if (barangay) statusBits.push('Brgy. ' + barangay);
        var presence = Comms.formatPresenceLabel ? Comms.formatPresenceLabel(peer) : (peer.online_status || 'Offline');
        statusBits.push(presence);
        var meta = statusBits.filter(Boolean).join(' · ');
        els.peerMeta.textContent = Comms.truncateText ? Comms.truncateText(meta, 52) : meta;
        els.peerMeta.setAttribute('title', meta);
        var online = Comms.isUserOnline ? Comms.isUserOnline(peer) : String(presence).toLowerCase() === 'online';
        els.peerMeta.classList.toggle('is-online', online);
        els.peerMeta.classList.toggle('is-offline', !online);
        els.peerAvatar.src = peer.profile_image_url || Comms.defaultAvatar(fullName);
        els.peerAvatar.alt = fullName;
    }

    function stopPeerRefresh() {
        if (state.peerRefreshTimer) {
            window.clearInterval(state.peerRefreshTimer);
            state.peerRefreshTimer = null;
        }
    }

    function refreshActivePeer() {
        if (!state.activeId || !routes.showConversation) return;
        var id = state.activeId;
        Comms.api(Comms.route(routes.showConversation, id)).then(function (data) {
            if (!data || !data.conversation) return;
            if (Number(state.activeId) !== Number(id)) return;
            state.activeConversation = data.conversation;
            updatePeerHeader(data.conversation);
            var idx = state.conversations.findIndex(function (c) {
                return Number(c.id) === Number(id);
            });
            if (idx >= 0 && data.conversation.other_user) {
                state.conversations[idx].other_user = data.conversation.other_user;
                if (Comms.writeInboxCache) Comms.writeInboxCache(state.conversations);
            }
            persistActiveThreadCache();
        }).catch(function () { /* non-fatal */ });
    }

    function startPeerRefresh(conversationId) {
        stopPeerRefresh();
        if (!conversationId) return;
        state.peerRefreshTimer = window.setInterval(function () {
            if (Number(state.activeId) !== Number(conversationId)) {
                stopPeerRefresh();
                return;
            }
            refreshActivePeer();
        }, 30000);
    }

    function updatePeerOnlineFromPresence(presenceMap) {
        if (!presenceMap) return;

        var onlineIds = {};
        Object.keys(presenceMap).forEach(function (key) {
            var metas = presenceMap[key] || [];
            metas.forEach(function (meta) {
                var uid = Number((meta && meta.user_id) || key || 0);
                if (uid) onlineIds[uid] = true;
            });
        });

        state.conversations.forEach(function (c) {
            var peer = c.other_user;
            if (!peer || !peer.id) return;
            var next = onlineIds[Number(peer.id)] ? 'online' : 'offline';
            peer.online_status = next;
            peer.is_online = next === 'online';
            if (next === 'online') peer.last_seen = new Date().toISOString();
        });

        if (!state.activeConversation || !state.activeConversation.other_user) return;
        var activePeer = state.activeConversation.other_user;
        var peerId = Number(activePeer.id || 0);
        if (!peerId) return;

        var nextStatus = onlineIds[peerId] ? 'online' : 'offline';
        activePeer.online_status = nextStatus;
        activePeer.is_online = nextStatus === 'online';
        if (nextStatus === 'online') activePeer.last_seen = new Date().toISOString();
        state.activeConversation.other_user = activePeer;
        updatePeerHeader(state.activeConversation);
    }

    function setListMode(mode) {
        state.listMode = mode;
        var isChats = mode === 'conversations';
        if (els.convList) els.convList.hidden = !isChats;
        if (els.directoryList) els.directoryList.hidden = isChats;
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
                    var fullName = u.name || 'User';
                    var displayName = Comms.truncateText ? Comms.truncateText(fullName, 30) : fullName;
                    var avatar = u.profile_image_url || Comms.defaultAvatar(fullName);
                    var metaRaw = [u.position || u.user_type_label || '', u.barangay_name || ''].filter(Boolean).join(' · ');
                    var meta = Comms.truncateText ? Comms.truncateText(metaRaw, 40) : metaRaw;
                    return '<div class="comms-dir-item" role="listitem" data-user-id="' + u.id + '" title="' + Comms.escapeHtml(fullName) + '">' +
                        '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="">' +
                        '<button type="button" class="comms-dir-open" data-user-id="' + u.id + '" style="border:0;background:transparent;text-align:left;padding:0;cursor:pointer;min-width:0;" title="' + Comms.escapeHtml(fullName) + '">' +
                        '<div class="comms-conv-name">' + Comms.escapeHtml(displayName) + '</div>' +
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
            renderConversations('');
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
        renderConversations('');
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
            renderConversations('');
            prefetchFaqForConversations(state.conversations);
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
            renderConversations('');
        }).catch(function () {
            if (!state.barangayOfficials.length) state.barangayOfficials = [];
        });
    }

    function setFaqMenuOpen(open) {
        if (!els.faqSuggestions || !els.faqMenuToggle) return;
        var isOpen = !!open;
        els.faqSuggestions.hidden = !isOpen;
        els.faqMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        els.faqMenuToggle.classList.toggle('is-open', isOpen);
    }

    function syncComposerEndControls() {
        var hasText = !!(els.input && String(els.input.value || '').trim());
        var hasFile = !!(state.pendingAttachments && state.pendingAttachments.length) || !!state.pendingAttachment;
        var showSend = hasText || hasFile;
        var faqs = state.faqSuggestions || [];
        var hasFaqs = faqs.length > 0;
        // Same end-slot: burger when empty, send when typing/attaching.
        var showFaq = !showSend && (hasFaqs || (!!state.faqLoading && !state.faqKnownEmpty));

        if (els.sendBtn) {
            els.sendBtn.hidden = !showSend;
            els.sendBtn.disabled = state.sendInFlight || !showSend;
            els.sendBtn.setAttribute('aria-hidden', showSend ? 'false' : 'true');
        }
        if (els.faqMenu) {
            if (!showFaq) {
                els.faqMenu.hidden = true;
                setFaqMenuOpen(false);
            } else {
                els.faqMenu.hidden = false;
                els.faqMenu.removeAttribute('data-no-faqs');
            }
        }
        if (els.faqMenuToggle) {
            var coolLeft = faqCooldownRemaining();
            els.faqMenuToggle.disabled = !hasFaqs && !state.faqLoading;
            els.faqMenuToggle.classList.toggle('is-loading', !!state.faqLoading && !hasFaqs);
            els.faqMenuToggle.classList.toggle('is-cooldown', coolLeft > 0);
            els.faqMenuToggle.setAttribute('aria-busy', (state.faqLoading && !hasFaqs) ? 'true' : 'false');
            els.faqMenuToggle.setAttribute('aria-hidden', showFaq ? 'false' : 'true');
            els.faqMenuToggle.title = coolLeft > 0
                ? ('Suggested questions available in ' + coolLeft + 's')
                : 'Suggested questions';
            var timerBadge = els.faqMenuToggle.querySelector('.comms-faq-toggle-timer');
            if (coolLeft > 0) {
                if (!timerBadge) {
                    timerBadge = document.createElement('span');
                    timerBadge.className = 'comms-faq-toggle-timer';
                    timerBadge.setAttribute('aria-hidden', 'true');
                    els.faqMenuToggle.appendChild(timerBadge);
                }
                timerBadge.textContent = String(coolLeft);
            } else if (timerBadge) {
                timerBadge.remove();
            }
        }
        if (els.composerEnd) {
            els.composerEnd.classList.toggle('has-send', showSend);
            els.composerEnd.classList.toggle('has-faq', showFaq);
        }
    }

    function faqCacheKey(conversationId) {
        return 'comms_faq_sugg_v6_' + String(conversationId || '0');
    }

    function readFaqCache(conversationId) {
        if (Comms.readFaqSuggestionsCache) {
            var cached = Comms.readFaqSuggestionsCache(conversationId);
            return cached && Array.isArray(cached.faqs) ? cached : null;
        }
        try {
            var raw = localStorage.getItem(faqCacheKey(conversationId));
            if (!raw) raw = sessionStorage.getItem(faqCacheKey(conversationId));
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.faqs)) return null;
            if (Date.now() - Number(parsed.at || 0) > 30 * 60 * 1000) return null;
            return { faqs: parsed.faqs, at: parsed.at, fresh: (Date.now() - Number(parsed.at || 0) <= 15 * 1000) };
        } catch (e) {
            return null;
        }
    }

    function writeFaqCache(conversationId, faqs) {
        if (Comms.writeFaqSuggestionsCache) {
            Comms.writeFaqSuggestionsCache(conversationId, faqs);
            return;
        }
        try {
            localStorage.setItem(faqCacheKey(conversationId), JSON.stringify({
                at: Date.now(),
                faqs: Array.isArray(faqs) ? faqs : []
            }));
        } catch (e) { /* ignore */ }
    }

    function prefetchFaqForConversations(list) {
        if (!routes.faqSuggestions || !Comms.prefetchFaqSuggestions) return;
        (list || []).slice(0, 8).forEach(function (c) {
            var id = c && c.id;
            if (!id) return;
            Comms.prefetchFaqSuggestions(id, routes.faqSuggestions);
        });
    }

    function matchFaqResponse(question) {
        var needle = String(question || '').trim().toLowerCase();
        if (!needle) return null;
        var faqs = state.faqSuggestions || [];
        for (var i = 0; i < faqs.length; i++) {
            if (String(faqs[i].question || '').trim().toLowerCase() === needle) {
                return faqs[i];
            }
        }
        return null;
    }

    function matchFaqById(faqId) {
        var id = Number(faqId);
        if (!id) return null;
        var faqs = state.faqSuggestions || [];
        for (var i = 0; i < faqs.length; i++) {
            if (Number(faqs[i].id) === id) return faqs[i];
        }
        return null;
    }

    function resolveFaqMatch(text, opts) {
        opts = opts || {};
        if (opts.faqResponse) {
            return {
                id: Number(opts.faqId) || 0,
                question: text,
                automated_response: String(opts.faqResponse)
            };
        }
        if (opts.faqId) {
            var byId = matchFaqById(opts.faqId);
            if (byId) return byId;
        }
        return matchFaqResponse(text);
    }

    function faqCooldownRemaining() {
        var cid = String(state.activeId || '');
        return Math.max(0, Math.ceil((Number(state.faqCooldownUntil[cid] || 0) - Date.now()) / 1000));
    }

    function stopFaqCooldownTimer() {
        if (state.faqCooldownTimer) {
            window.clearInterval(state.faqCooldownTimer);
            state.faqCooldownTimer = null;
        }
    }

    function startFaqCooldown(seconds) {
        var cid = String(state.activeId || '');
        if (!cid) return;
        var secs = Math.max(1, Number(seconds) || 10);
        state.faqCooldownUntil[cid] = Date.now() + (secs * 1000);
        stopFaqCooldownTimer();
        renderFaqSuggestions();
        state.faqCooldownTimer = window.setInterval(function () {
            if (faqCooldownRemaining() <= 0) {
                stopFaqCooldownTimer();
                delete state.faqCooldownUntil[String(state.activeId || '')];
            }
            renderFaqSuggestions();
        }, 250);
    }

    function renderFaqSuggestions() {
        if (!els.faqSuggestions || !els.faqSuggestionsList) return;
        var faqs = state.faqSuggestions || [];
        var showMenu = faqs.length > 0 || (state.faqLoading && !state.faqKnownEmpty);
        if (!showMenu) {
            els.faqSuggestionsList.innerHTML = '';
            var oldHint = els.faqSuggestions.querySelector(':scope > .comms-faq-cooldown-hint');
            if (oldHint) oldHint.remove();
            syncComposerEndControls();
            return;
        }
        var remaining = faqCooldownRemaining();
        var cooling = remaining > 0;
        els.faqSuggestionsList.innerHTML = faqs.map(function (faq) {
            var q = String(faq.question || '');
            var answer = String(faq.automated_response || '');
            var faqId = Number(faq.id) || 0;
            return '<button type="button" class="comms-faq-chip' + (cooling ? ' is-cooldown' : '') + '"' +
                (cooling ? ' disabled aria-disabled="true"' : '') +
                ' role="listitem" title="' + Comms.escapeHtml(q) + '"' +
                (faqId ? ' data-faq-id="' + faqId + '"' : '') +
                ' data-faq-question="' + Comms.escapeHtml(q) + '"' +
                (answer ? ' data-faq-response="' + Comms.escapeHtml(answer) + '"' : '') +
                '>' + Comms.escapeHtml(q) + '</button>';
        }).join('');

        var hint = els.faqSuggestions.querySelector(':scope > .comms-faq-cooldown-hint');
        if (cooling) {
            if (!hint) {
                hint = document.createElement('p');
                hint.className = 'comms-faq-cooldown-hint';
                hint.setAttribute('role', 'status');
                els.faqSuggestions.insertBefore(hint, els.faqSuggestionsList);
            }
            hint.textContent = 'Suggested questions locked — wait ' + remaining + 's';
        } else if (hint) {
            hint.remove();
        }
        syncComposerEndControls();
    }

    function loadFaqSuggestions(conversationId) {
        if (!routes.faqSuggestions || !conversationId) {
            state.faqSuggestions = [];
            state.faqLoading = false;
            state.faqKnownEmpty = true;
            renderFaqSuggestions();
            return Promise.resolve();
        }
        var cached = readFaqCache(conversationId);
        var cachedList = (cached && Array.isArray(cached.faqs)) ? cached.faqs : null;
        // Paint non-empty cache only; empty cache is not final (per-official FAQs).
        if (cachedList && cachedList.length) {
            state.faqSuggestions = cachedList;
            state.faqKnownEmpty = false;
            state.faqLoading = false;
            renderFaqSuggestions();
        } else {
            state.faqLoading = true;
            state.faqKnownEmpty = false;
            if (els.faqMenu) els.faqMenu.hidden = false;
            renderFaqSuggestions();
        }
        // Always refresh so Official FAQ edits show up quickly.
        return Comms.api(Comms.route(routes.faqSuggestions, conversationId), { dedupe: true }).then(function (data) {
            if (Number(state.activeId) !== Number(conversationId)) return;
            state.faqSuggestions = (data && Array.isArray(data.faqs)) ? data.faqs : [];
            state.faqKnownEmpty = state.faqSuggestions.length === 0;
            state.faqLoading = false;
            writeFaqCache(conversationId, state.faqSuggestions);
            renderFaqSuggestions();
        }).catch(function () {
            if (Number(state.activeId) !== Number(conversationId)) return;
            state.faqLoading = false;
            if (!state.faqSuggestions.length) {
                state.faqKnownEmpty = true;
                renderFaqSuggestions();
            }
        });
    }

    function stopLocalTyping() {
        if (!state.activeId || !window.CommsRealtime) return;
        if (typeof window.CommsRealtime.stopTyping === 'function') {
            window.CommsRealtime.stopTyping(state.activeId);
        } else if (typeof window.CommsRealtime.broadcastTyping === 'function') {
            window.CommsRealtime.broadcastTyping(state.activeId, { stop: true });
        }
    }

    function notifyComposerTyping(rawValue) {
        if (!state.activeId || !window.CommsRealtime || typeof window.CommsRealtime.broadcastTyping !== 'function') {
            return;
        }
        var hasText = !!String(rawValue == null ? '' : rawValue).replace(/\s/g, '');
        if (!hasText) {
            stopLocalTyping();
            return;
        }
        window.CommsRealtime.broadcastTyping(state.activeId);
    }

    function openConversation(id, opts) {
        opts = opts || {};
        if (state.activeId && Number(state.activeId) !== Number(id)) {
            stopLocalTyping();
        }
        state.activeId = Number(id);
        window.__COMMS_PAGE_ACTIVE_ID__ = state.activeId;
        setThreadOpen(true);
        els.threadEmpty.hidden = true;
        els.threadActive.hidden = false;
        if (els.typing) {
            els.typing.hidden = true;
            els.typing.textContent = '';
        }
        renderConversations('');
        startPeerRefresh(id);

        if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
            window.CommsRealtime.subscribeConversation(state.activeId);
        }

        var listConv = state.conversations.find(function (c) {
            return Number(c.id) === Number(id);
        });
        if (listConv && listConv.other_user) {
            updatePeerHeader(listConv);
        }
        if (els.composer) {
            els.composer.hidden = false;
            els.composer.removeAttribute('hidden');
            els.composer.style.display = 'flex';
        }
        // Show FAQ burger immediately while suggestions load (empty composer slot).
        state.faqLoading = true;
        state.faqKnownEmpty = false;
        if (els.faqMenu) {
            els.faqMenu.hidden = false;
            els.faqMenu.removeAttribute('data-no-faqs');
        }
        syncComposerEndControls();
        resizeComposer();

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
        renderFaqSuggestions();
        loadFaqSuggestions(id);

        convPromise.then(function (data) {
            if (!data || !data.conversation) return;
            if (Number(state.activeId) !== Number(id)) return;
            state.activeConversation = data.conversation;
            updatePeerHeader(data.conversation);
            persistActiveThreadCache();
        }).catch(function () { /* non-fatal */ });

        return msgPromise.then(function (data) {
            var freshMessages = mergePreservedReactions(data.messages || []);
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

    function mergePreservedReactions(incoming) {
        var localById = {};
        (state.messages || []).forEach(function (m) {
            localById[String(m.id)] = m;
        });
        var now = Date.now();
        return (incoming || []).map(function (m) {
            var key = String(m.id);
            var until = Number(state.reactLocalUntil[key] || 0);
            var skip = !!state.reactSkipRealtime[key];
            if ((!skip && until <= now) || !localById[key]) return m;
            var local = localById[key];
            if (!Array.isArray(local.reactions)) return m;
            return Object.assign({}, m, { reactions: cloneReactions(local.reactions) });
        });
    }

    function appendMessage(message) {
        if (!message || Number(message.conversation_id) !== Number(state.activeId)) return;
        var mid = String(message.id);
        if (state.messages.some(function (m) { return String(m.id) === mid; })) return;
        // Realtime automation can arrive before POST resolves — replace optimistic bubble.
        if (message.message_type === 'automation') {
            var autoTempIdx = state.messages.findIndex(function (m) {
                return String(m.id).indexOf('local-auto-') === 0;
            });
            if (autoTempIdx !== -1) {
                state.messages[autoTempIdx] = message;
                persistActiveThreadCache();
                renderMessages({ stickBottom: true });
                return;
            }
        }
        state.messages.push(message);
        persistActiveThreadCache();
        renderMessages({ stickBottom: true });
    }

    function reloadActiveMessages() {
        if (!state.activeId) return Promise.resolve();
        return Comms.api(Comms.route(routes.messages, state.activeId)).then(function (data) {
            state.messages = mergePreservedReactions(data.messages || []);
            if (Array.isArray(data.reaction_emojis) && data.reaction_emojis.length) {
                state.reactionEmojis = data.reaction_emojis;
            }
            persistActiveThreadCache();
            renderMessages();
            return loadConversations();
        }).catch(function () { /* ignore */ });
    }

    function composerMeasuredLength(text) {
        var cpl = Comms.estimateComposerCharsPerLine
            ? Comms.estimateComposerCharsPerLine(els.input)
            : 36;
        if (Comms.measureMessageLength) {
            return Comms.measureMessageLength(text, cpl);
        }
        return String(text == null ? '' : text).length;
    }

    function syncComposerHeightVar() {
        if (!els.composer || !els.app) return;
        var h = Math.ceil(els.composer.getBoundingClientRect().height || els.composer.offsetHeight || 64);
        els.app.style.setProperty('--comms-composer-h', Math.max(64, h) + 'px');
    }

    function resizeComposer() {
        if (!els.input) return;
        if (Comms.resizeGrowTextarea) {
            Comms.resizeGrowTextarea(els.input, 5);
        } else {
            els.input.style.height = 'auto';
            var next = Math.min(Math.max(els.input.scrollHeight, 44), 140);
            els.input.style.height = next + 'px';
        }
        syncComposerHeightVar();
    }

    function toastCharLimit() {
        Comms.showToast('Message cannot exceed ' + state.messageMaxLength + ' characters.', 'error');
    }

    function sendMessage(body, opts) {
        opts = opts || {};
        if (state.sendInFlight || !state.activeId) return;
        // Preserve internal spaces and blank lines; trim only the outer edges.
        var text = String(body == null ? '' : body).replace(/\r\n/g, '\n').replace(/^\s+|\s+$/g, '');
        var files = (state.pendingAttachments && state.pendingAttachments.length)
            ? state.pendingAttachments.slice()
            : (state.pendingAttachment ? [state.pendingAttachment] : []);
        var previewUrls = (state.pendingPreviewUrls && state.pendingPreviewUrls.length)
            ? state.pendingPreviewUrls.slice()
            : (state.pendingPreviewUrl ? [state.pendingPreviewUrl] : []);
        if (!text && !files.length) return;
        if (composerMeasuredLength(text) > state.messageMaxLength) {
            Comms.showToast('Message exceeds the ' + state.messageMaxLength + ' character limit.', 'error');
            return;
        }
        if (opts.fromFaq && faqCooldownRemaining() > 0) {
            Comms.showToast('Please wait ' + faqCooldownRemaining() + 's before sending another FAQ.', 'error');
            return;
        }
        stopLocalTyping();
        state.sendInFlight = true;
        if (els.sendBtn) els.sendBtn.disabled = true;
        if (els.attachBtn) els.attachBtn.disabled = true;
        setAttachMenuOpen(false);
        setUploadProgress(files.length ? 8 : 0, !files.length);

        var textTempId = text ? ('local-' + Date.now()) : null;
        var fileTempIds = [];
        var faqMatch = (text && !files.length) ? resolveFaqMatch(text, opts) : null;
        var expectAutoReply = !!(faqMatch && String(faqMatch.automated_response || '').trim());
        var faqId = faqMatch && Number(faqMatch.id) ? Number(faqMatch.id) : (Number(opts.faqId) || null);
        var jobs = [];

        if (text) {
            state.messages.push({
                id: textTempId,
                conversation_id: state.activeId,
                body: text,
                message_type: 'text',
                mine: true,
                created_at: new Date().toISOString(),
                reactions: [],
                attachments: []
            });
            if (expectAutoReply) {
                state.messages.push({
                    id: 'local-auto-' + Date.now(),
                    conversation_id: state.activeId,
                    body: String(faqMatch.automated_response),
                    message_type: 'automation',
                    mine: false,
                    created_at: new Date().toISOString(),
                    reactions: [],
                    attachments: []
                });
                startFaqCooldown(10);
            }
            persistActiveThreadCache();
            renderMessages({ stickBottom: true });
            if (els.input) els.input.value = '';
            resizeComposer();
            syncComposerEndControls();
            var payload = { body: text };
            if (faqId) payload.faq_id = faqId;
            jobs.push(Comms.api(Comms.route(routes.messages, state.activeId), {
                method: 'POST',
                body: JSON.stringify(payload),
                dedupe: false
            }).then(function (data) {
                var tempIdx = state.messages.findIndex(function (m) { return String(m.id) === String(textTempId); });
                if (tempIdx !== -1 && data.message) state.messages[tempIdx] = data.message;
                else if (data.message) appendMessage(data.message);
                if (data.automated_message) {
                    var autoTempIdx = state.messages.findIndex(function (m) {
                        return String(m.id).indexOf('local-auto-') === 0;
                    });
                    if (autoTempIdx !== -1) state.messages[autoTempIdx] = data.automated_message;
                    else appendMessage(data.automated_message);
                    renderMessages({ stickBottom: true });
                    persistActiveThreadCache();
                } else if (!expectAutoReply) {
                    state.messages = state.messages.filter(function (m) {
                        return String(m.id).indexOf('local-auto-') !== 0;
                    });
                    renderMessages({ stickBottom: true });
                }
            }));
        }

        if (files.length) {
            state.pendingPreviewUrls = [];
            state.pendingPreviewUrl = null;
            clearPendingAttachment();
            if (els.input) els.input.value = '';
            resizeComposer();
            var batchId = 'batch-' + Date.now();
            files.forEach(function (file, index) {
                var isImage = isImageFile(file);
                var blobUrl = previewUrls[index] || (isImage ? URL.createObjectURL(file) : null);
                var fileTempId = 'local-att-' + Date.now() + '-' + index;
                fileTempIds.push(fileTempId);
                appendMessage({
                    id: fileTempId,
                    conversation_id: state.activeId,
                    body: '',
                    message_type: isImage ? 'image' : 'file',
                    mine: true,
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
                var form = new FormData();
                form.append('attachment', file, file.name);
                jobs.push(Comms.api(Comms.route(routes.messages, state.activeId), {
                    method: 'POST',
                    body: form,
                    dedupe: false
                }).then(function (data) {
                    setUploadProgress(Math.min(100, 40 + ((index + 1) / files.length) * 60), false);
                    var tempIdx = state.messages.findIndex(function (m) { return String(m.id) === String(fileTempId); });
                    if (tempIdx !== -1 && data.message) {
                        var prevAtt = state.messages[tempIdx].attachments || [];
                        prevAtt.forEach(function (a) {
                            if (a && a.url && String(a.url).indexOf('blob:') === 0) {
                                try { URL.revokeObjectURL(a.url); } catch (e) { /* ignore */ }
                            }
                        });
                        state.messages[tempIdx] = Object.assign({}, data.message, { batch_id: batchId });
                    } else if (data.message) {
                        appendMessage(Object.assign({}, data.message, { batch_id: batchId }));
                    }
                }));
            });
        }

        Promise.all(jobs).then(function () {
            persistActiveThreadCache();
            renderMessages({ stickBottom: true });
            return loadConversations();
        }).catch(function (err) {
            state.messages = state.messages.filter(function (m) {
                var id = String(m.id);
                if (id === String(textTempId)) return false;
                if (id.indexOf('local-auto-') === 0) return false;
                if (fileTempIds.indexOf(id) !== -1) return false;
                return true;
            });
            renderMessages({ stickBottom: true });
            setUploadProgress(0, true);
            Comms.showToast(err.message || 'Failed to send message.', 'error');
        }).finally(function () {
            state.sendInFlight = false;
            if (els.attachBtn) els.attachBtn.disabled = false;
            window.setTimeout(function () { setUploadProgress(0, true); }, 400);
            syncComposerEndControls();
            if (els.input) els.input.focus();
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

    function renderSearchResults(users) {
        if (!els.searchResults) return;
        users = Array.isArray(users) ? users : [];
        if (!users.length) {
            els.searchResults.innerHTML = '<div class="comms-search-item"><span>No SK Officials found in your barangay</span></div>';
            els.searchResults.hidden = false;
            return;
        }
        els.searchResults.innerHTML = users.map(function (u) {
            return '<button type="button" class="comms-search-item" data-user-id="' + u.id + '">' +
                '<strong>' + Comms.escapeHtml(u.name) + '</strong>' +
                '<span>' + Comms.escapeHtml(u.user_type_label || u.position || u.role || '') + '</span></button>';
        }).join('');
        els.searchResults.hidden = false;
    }

    function filterLocalOfficials(q) {
        var needle = String(q || '').trim().toLowerCase();
        var list = state.barangayOfficials || [];
        if (!needle) return list.slice(0, 20);
        return list.filter(function (u) {
            var name = String(u.name || '').toLowerCase();
            var email = String(u.email || '').toLowerCase();
            var role = String(u.user_type_label || u.position || u.role || '').toLowerCase();
            return name.indexOf(needle) !== -1 || email.indexOf(needle) !== -1 || role.indexOf(needle) !== -1;
        }).slice(0, 20);
    }

    function searchUsers(q) {
        q = String(q == null ? '' : q);
        var trimmed = q.trim();
        var requestId = (state.searchRequestId = (state.searchRequestId || 0) + 1);
        // Instant local results while typing.
        renderSearchResults(filterLocalOfficials(trimmed));
        if (!routes.searchUsers) return;
        Comms.api(routes.searchUsers + (trimmed ? '?q=' + encodeURIComponent(trimmed) : '')).then(function (data) {
            if (requestId !== state.searchRequestId) return;
            renderSearchResults(data.users || []);
        }).catch(function (err) {
            if (requestId !== state.searchRequestId) return;
            // Keep local results visible; only toast if nothing local matched.
            if (!filterLocalOfficials(trimmed).length) {
                Comms.showToast(err.message || 'Search failed.', 'error');
            }
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
        els.messages.addEventListener('scroll', function () {
            syncScrollBottomBtn();
        }, { passive: true });
        els.messages.addEventListener('click', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (peopleBtn) {
                e.preventDefault();
                e.stopPropagation();
                var pid = Number(peopleBtn.getAttribute('data-react-people'));
                var pmsg = state.messages.find(function (m) { return Number(m.id) === pid; });
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
        els.messages.addEventListener('pointerover', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn || !els.messages.contains(peopleBtn)) return;
            var pid = Number(peopleBtn.getAttribute('data-react-people'));
            var pmsg = state.messages.find(function (m) { return Number(m.id) === pid; });
            var emojiEl = e.target.closest('[data-react-people-emoji]');
            if (window.Comms && Comms.showReactionPeople) {
                Comms.showReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                    pinned: false,
                    emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                });
            }
        });
        els.messages.addEventListener('pointerout', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn) return;
            var related = e.relatedTarget;
            if (related && (peopleBtn.contains(related) || (related.closest && related.closest('#commsReactionPeople')))) {
                return;
            }
            if (window.Comms && Comms.scheduleHideReactionPeople) Comms.scheduleHideReactionPeople();
        });
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

    if (els.faqSuggestionsList) {
        els.faqSuggestionsList.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-faq-question]');
            if (!chip || chip.disabled) return;
            if (faqCooldownRemaining() > 0) return;
            var question = chip.getAttribute('data-faq-question') || '';
            var faqId = Number(chip.getAttribute('data-faq-id') || 0) || null;
            var faqResponse = chip.getAttribute('data-faq-response') || '';
            if (question) {
                setFaqMenuOpen(false);
                sendMessage(question, {
                    fromFaq: true,
                    faqId: faqId,
                    faqResponse: faqResponse
                });
            }
        });
    }

    if (els.faqMenuToggle) {
        els.faqMenuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!els.faqMenu || els.faqMenu.hidden) return;
            var open = els.faqMenuToggle.getAttribute('aria-expanded') !== 'true';
            setFaqMenuOpen(open);
            if (open && state.activeId) loadFaqSuggestions(state.activeId);
        });
        document.addEventListener('click', function (e) {
            if (!els.faqMenu || els.faqMenu.hidden) return;
            if (els.faqMenu.contains(e.target)) return;
            setFaqMenuOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setFaqMenuOpen(false);
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
        var q = els.userSearch.value;
        renderConversations(q);
        // Paint local matches immediately, then refresh from API shortly after.
        renderSearchResults(filterLocalOfficials(q));
        state.searchTimer = setTimeout(function () { searchUsers(q); }, 120);
    });
    els.userSearch.addEventListener('focus', function () {
        searchUsers(els.userSearch.value || '');
    });

    if (els.scrollBottom) {
        els.scrollBottom.addEventListener('click', function (e) {
            e.preventDefault();
            jumpToLatestMessages();
        });
    }

    els.composer.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(els.input.value);
    });

    if (els.attachBtn) {
        els.attachBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = !(els.attachMenu && !els.attachMenu.hidden);
            setAttachMenuOpen(open);
        });
    }
    if (els.pickPhotos && els.photoInput) {
        els.pickPhotos.addEventListener('click', function (e) {
            e.preventDefault();
            setAttachMenuOpen(false);
            els.photoInput.click();
        });
        els.photoInput.addEventListener('change', function () {
            if (els.photoInput.files && els.photoInput.files.length) {
                pushPendingFiles(els.photoInput.files, 'image');
            }
            els.photoInput.value = '';
        });
    }
    if (els.pickFiles && els.fileInput) {
        els.pickFiles.addEventListener('click', function (e) {
            e.preventDefault();
            setAttachMenuOpen(false);
            els.fileInput.click();
        });
        els.fileInput.addEventListener('change', function () {
            if (els.fileInput.files && els.fileInput.files.length) {
                pushPendingFiles(els.fileInput.files, 'file');
            }
            els.fileInput.value = '';
        });
    }
    document.addEventListener('click', function (e) {
        if (!els.attachMenu || els.attachMenu.hidden) return;
        if (els.attachBtn && els.attachBtn.contains(e.target)) return;
        if (els.attachMenu.contains(e.target)) return;
        setAttachMenuOpen(false);
    });
    if (els.attachClear) {
        els.attachClear.addEventListener('click', function () {
            clearPendingAttachment();
        });
    }

    els.input.addEventListener('input', function () {
        resizeComposer();
        syncComposerEndControls();
        syncComposerLimit();
        notifyComposerTyping(els.input.value);
    });

    if (els.messages) {
        els.messages.addEventListener('input', function (e) {
            var editInput = e.target.closest('[data-edit-input]');
            if (editInput) syncEditCount(editInput);
        });
    }

    if (els.input) {
        els.input.removeAttribute('maxlength');
        syncComposerLimit();
    }

    els.input.addEventListener('keydown', function (e) {
        var max = state.messageMaxLength;
        var current = String(els.input.value || '');
        var len = composerMeasuredLength(current);
        var atLimit = len >= max;

        // Enter inserts a newline — weighted cost depends on chars already on the line.
        if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) {
            var projected = composerMeasuredLength(current + '\n');
            if (projected > max) {
                e.preventDefault();
                toastCharLimit();
                return;
            }
        }

        // Enter = new line only. Ctrl/Cmd+Enter sends.
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            if (atLimit && !String(els.input.value || '').trim()) {
                toastCharLimit();
                return;
            }
            sendMessage(els.input.value);
            return;
        }

        // Printable key at weighted max: toast every attempt.
        if (atLimit && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            var nextLen = composerMeasuredLength(current + e.key);
            if (nextLen > max) {
                e.preventDefault();
                toastCharLimit();
            }
        }
    });

    els.backBtn.addEventListener('click', function () {
        stopLocalTyping();
        stopPeerRefresh();
        setThreadOpen(false);
        state.activeId = null;
        window.__COMMS_PAGE_ACTIVE_ID__ = null;
        if (els.typing) {
            els.typing.hidden = true;
            els.typing.textContent = '';
        }
        els.threadActive.hidden = true;
        els.threadEmpty.hidden = false;
        renderConversations('');
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
                    Comms.showToast('Message edited.', 'success');
                }
            }
            persistActiveThreadCache();
            renderMessages({ preserveScroll: true });
        },
        getActiveId: function () { return state.activeId; },
        updatePeerOnlineFromPresence: updatePeerOnlineFromPresence,
        setTyping: function (visible, meta) {
            if (!els.typing) return;
            if (window.Comms && typeof window.Comms.paintTypingEl === 'function') {
                window.Comms.paintTypingEl(els.typing, !!visible, meta || null);
                return;
            }
            if (!visible) {
                els.typing.hidden = true;
                els.typing.textContent = '';
                return;
            }
            var who = meta && meta.name ? String(meta.name).trim() : '';
            els.typing.textContent = who ? (who + ' is typing...') : 'Typing...';
            els.typing.hidden = false;
        },
        routes: routes,
        state: state
    };

    hydrateFromCache();
    if (els.input) resizeComposer();
    if (els.messages && window.Comms && typeof window.Comms.wireChatImageLightbox === 'function') {
        window.Comms.wireChatImageLightbox(els.messages);
    }
    Promise.all([loadConversations(), loadBarangayOfficials()]).then(function () {
        var initial = root.dataset.initialConversation;
        if (initial) openConversation(initial);
    });
})();
