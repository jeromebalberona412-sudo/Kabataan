/**
 * Shared Communications helpers (Federations / Officials / Kabataan).
 */
(function (window) {
    'use strict';

    if (window.Comms && window.Comms.__booted) {
        return;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (ch) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[ch];
        });
    }

    function defaultAvatar(name) {
        var label = encodeURIComponent(String(name || 'User').slice(0, 40));
        return 'https://ui-avatars.com/api/?name=' + label + '&background=0450A8&color=fff';
    }

    function formatTime(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        var now = new Date();
        var sameDay = d.toDateString() === now.toDateString();
        if (sameDay) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function route(template, id) {
        return String(template || '').replace('__ID__', String(id));
    }

    var inflight = Object.create(null);

    function api(url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        var isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
        var key = method + ':' + url + ':' + (isFormData ? '[formdata]' : (options.body || ''));
        if (options.dedupe !== false && inflight[key]) {
            return inflight[key];
        }

        var headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken()
        }, options.headers || {});

        if (options.body && !isFormData && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        if (isFormData && headers['Content-Type']) {
            delete headers['Content-Type'];
        }

        var promise = fetch(url, {
            method: method,
            headers: headers,
            credentials: 'same-origin',
            body: options.body || undefined
        }).then(async function (res) {
            var data = null;
            var text = await res.text();
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = { message: text }; }
            if (!res.ok) {
                var msg = (data && (data.message || data.error)) || 'Request failed';
                if (data && data.errors) {
                    var first = Object.keys(data.errors)[0];
                    if (first && data.errors[first] && data.errors[first][0]) {
                        msg = data.errors[first][0];
                    }
                }
                var err = new Error(msg);
                err.status = res.status;
                err.data = data;
                throw err;
            }
            return data;
        }).finally(function () {
            delete inflight[key];
        });

        if (options.dedupe !== false) {
            inflight[key] = promise;
        }
        return promise;
    }

    function showToast(message, type) {
        type = type || 'info';
        if (typeof window.showToast === 'function' && window.showToast !== showToast) {
            window.showToast(message, type);
            return;
        }
        var existing = document.getElementById('commsToast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'commsToast';
        el.setAttribute('role', 'status');
        el.textContent = message;
        el.style.cssText = 'position:fixed;z-index:3000;right:1rem;bottom:1rem;background:' +
            (type === 'error' ? '#b42318' : '#0450a8') +
            ';color:#fff;padding:0.75rem 1rem;border-radius:8px;max-width:min(90vw,360px);box-shadow:0 8px 24px rgba(0,0,0,.18);';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3200);
    }

    var REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
    var reactionAudio = null;

    function ensureReactionAudio() {
        if (!reactionAudio) {
            reactionAudio = new Audio(REACTION_SOUND_URL);
            reactionAudio.preload = 'auto';
            reactionAudio.volume = 0.75;
            try { reactionAudio.load(); } catch (e) {}
        }
        return reactionAudio;
    }

    function playReactionSound() {
        var audio = ensureReactionAudio();
        audio.muted = false;
        audio.volume = 0.75;
        try {
            if (audio.readyState >= 2) {
                try { audio.currentTime = 0; } catch (e) {}
            }
            var playPromise = audio.play();
            if (playPromise && playPromise.catch) {
                playPromise.catch(function () {
                    var oneShot = new Audio(REACTION_SOUND_URL);
                    oneShot.volume = 0.75;
                    oneShot.play().catch(function () {});
                });
            }
        } catch (e) {
            try {
                var fallback = new Audio(REACTION_SOUND_URL);
                fallback.volume = 0.75;
                fallback.play().catch(function () {});
            } catch (err) {}
        }
    }

    ensureReactionAudio();
    document.addEventListener('pointerdown', ensureReactionAudio, { once: true, capture: true });

    var deleteModalEl = null;
    var deleteModalResolve = null;

    function ensureDeleteModalStyles() {
        if (document.getElementById('commsDeleteModalStyles')) return;
        var style = document.createElement('style');
        style.id = 'commsDeleteModalStyles';
        style.textContent =
            '.comms-delete-modal{position:fixed;inset:0;z-index:100050;display:flex;align-items:flex-end;justify-content:center;padding:max(.75rem,env(safe-area-inset-top)) .75rem max(.75rem,env(safe-area-inset-bottom));}' +
            '.comms-delete-modal[hidden]{display:none!important;}' +
            '.comms-delete-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);}' +
            '.comms-delete-modal-panel{position:relative;z-index:1;width:min(100%,380px);max-height:min(90dvh,560px);overflow:auto;display:flex;flex-direction:column;gap:8px;}' +
            '.comms-delete-sheet,.comms-delete-cancel-sheet{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.22);}' +
            '.comms-delete-modal-title{margin:1.05rem 1.1rem .2rem;font-size:.82rem;font-weight:700;color:#8e8e93;letter-spacing:.01em;line-height:1.3;text-align:center;}' +
            '.comms-delete-modal-text{margin:0 1.15rem 1rem;font-size:.92rem;color:#3c3c43;line-height:1.4;text-align:center;font-weight:500;}' +
            '.comms-delete-choices{display:flex;flex-direction:column;}' +
            '.comms-delete-choice{border:0;border-top:1px solid #e5e5ea;background:#fff;padding:.95rem 1rem;width:100%;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:.2rem;}' +
            '.comms-delete-choice[hidden]{display:none!important;}' +
            '.comms-delete-choice:focus-visible{outline:2px solid rgba(4,80,168,.35);outline-offset:-2px;}' +
            '.comms-delete-choice-label{font-size:1.05rem;font-weight:600;line-height:1.2;}' +
            '.comms-delete-choice-hint{font-size:.75rem;font-weight:400;color:#8e8e93;line-height:1.35;text-align:center;max-width:20rem;}' +
            '.comms-delete-modal-btn-all{color:#ff3b30;}' +
            '.comms-delete-modal-btn-me{color:#1c1c1e;}' +
            '.comms-delete-modal-btn-all:hover,.comms-delete-modal-btn-all:focus-visible{background:#fff5f5;}' +
            '.comms-delete-modal-btn-me:hover,.comms-delete-modal-btn-me:focus-visible{background:#f2f2f7;}' +
            '.comms-delete-modal-btn-cancel{border:0;background:#fff;color:#007aff;padding:1rem;width:100%;font-size:1.05rem;font-weight:700;cursor:pointer;font-family:inherit;}' +
            '.comms-delete-modal-btn-cancel:hover,.comms-delete-modal-btn-cancel:focus-visible{background:#f2f2f7;outline:2px solid rgba(0,122,255,.28);outline-offset:-2px;}' +
            '@media (min-width:768px){.comms-delete-modal{align-items:center;padding:1.25rem;}.comms-delete-sheet,.comms-delete-cancel-sheet{border-radius:16px;}}';
        document.head.appendChild(style);
    }

    function ensureDeleteModal() {
        ensureDeleteModalStyles();
        if (deleteModalEl) return deleteModalEl;
        deleteModalEl = document.createElement('div');
        deleteModalEl.id = 'commsDeleteModal';
        deleteModalEl.className = 'comms-delete-modal';
        deleteModalEl.hidden = true;
        deleteModalEl.setAttribute('role', 'dialog');
        deleteModalEl.setAttribute('aria-modal', 'true');
        deleteModalEl.setAttribute('aria-labelledby', 'commsDeleteModalTitle');
        deleteModalEl.innerHTML =
            '<div class="comms-delete-modal-backdrop" data-delete-cancel tabindex="-1"></div>' +
            '<div class="comms-delete-modal-panel">' +
            '<div class="comms-delete-sheet">' +
            '<h3 class="comms-delete-modal-title" id="commsDeleteModalTitle">Delete message?</h3>' +
            '<p class="comms-delete-modal-text" id="commsDeleteModalText">Who do you want to remove this message for?</p>' +
            '<div class="comms-delete-choices">' +
            '<button type="button" class="comms-delete-choice comms-delete-modal-btn-all" id="commsDeleteModalAll" hidden>' +
            '<span class="comms-delete-choice-label">Delete for everyone</span>' +
            '<span class="comms-delete-choice-hint">This message will be unsent for everyone in the chat.</span>' +
            '</button>' +
            '<button type="button" class="comms-delete-choice comms-delete-modal-btn-me" id="commsDeleteModalMe">' +
            '<span class="comms-delete-choice-label">Delete for me</span>' +
            '<span class="comms-delete-choice-hint">This removes the message from your chat only.</span>' +
            '</button>' +
            '</div></div>' +
            '<div class="comms-delete-cancel-sheet">' +
            '<button type="button" class="comms-delete-modal-btn-cancel" data-delete-cancel>Cancel</button>' +
            '</div></div>';
        document.body.appendChild(deleteModalEl);

        deleteModalEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-delete-cancel]')) {
                closeDeleteMessageDialog(null);
                return;
            }
            if (e.target.closest('#commsDeleteModalMe')) {
                closeDeleteMessageDialog('me');
                return;
            }
            if (e.target.closest('#commsDeleteModalAll')) {
                closeDeleteMessageDialog('all');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && deleteModalEl && !deleteModalEl.hidden) {
                closeDeleteMessageDialog(null);
            }
        });

        return deleteModalEl;
    }

    function closeDeleteMessageDialog(result) {
        if (!deleteModalEl) return;
        deleteModalEl.hidden = true;
        var resolve = deleteModalResolve;
        deleteModalResolve = null;
        if (typeof resolve === 'function') resolve(result);
    }

    function openDeleteMessageDialog(options) {
        options = options || {};
        var modal = ensureDeleteModal();
        var title = document.getElementById('commsDeleteModalTitle');
        var text = document.getElementById('commsDeleteModalText');
        var allBtn = document.getElementById('commsDeleteModalAll');
        var meBtn = document.getElementById('commsDeleteModalMe');

        if (title) title.textContent = options.title || 'Delete message?';
        if (text) {
            text.textContent = options.canDeleteAll
                ? 'Who do you want to remove this message for?'
                : 'This message will only be removed from your chat.';
        }
        if (allBtn) {
            allBtn.hidden = !options.canDeleteAll;
            allBtn.setAttribute('aria-hidden', options.canDeleteAll ? 'false' : 'true');
        }
        if (meBtn) {
            var meLabel = meBtn.querySelector('.comms-delete-choice-label');
            if (meLabel) meLabel.textContent = 'Delete for me';
        }

        if (deleteModalResolve) {
            deleteModalResolve(null);
            deleteModalResolve = null;
        }

        modal.hidden = false;
        window.setTimeout(function () {
            var cancelBtn = modal.querySelector('.comms-delete-modal-btn-cancel');
            if (cancelBtn) cancelBtn.focus();
        }, 0);

        return new Promise(function (resolve) {
            deleteModalResolve = resolve;
        });
    }

    var CACHE_NS = 'kabataan:comms:';
    var THREAD_CACHE_PREFIX = 'comms:thread:';

    function cacheUserId() {
        var root = document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
        return String((root && root.dataset.currentUserId) || '0');
    }

    function scopedCacheKey(name) {
        return CACHE_NS + cacheUserId() + ':' + name;
    }

    function readCacheEntry(key) {
        if (window.SkLocalCache && typeof window.SkLocalCache.get === 'function') {
            var entry = window.SkLocalCache.get(key);
            return entry && Object.prototype.hasOwnProperty.call(entry, 'payload') ? entry.payload : null;
        }
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && Object.prototype.hasOwnProperty.call(parsed, 'payload')) {
                return parsed.payload;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeCacheEntry(key, payload) {
        if (window.SkLocalCache && typeof window.SkLocalCache.set === 'function') {
            window.SkLocalCache.set(key, payload);
            return;
        }
        try {
            localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), payload: payload }));
        } catch (e) { /* quota / private mode */ }
    }

    function readLocalCache(name) {
        return readCacheEntry(scopedCacheKey(name));
    }

    function writeLocalCache(name, payload) {
        if (payload == null) return;
        writeCacheEntry(scopedCacheKey(name), payload);
    }

    function readInboxCache() {
        var payload = readLocalCache('inbox');
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.conversations)) return payload.conversations;
        return null;
    }

    function writeInboxCache(conversations) {
        if (!Array.isArray(conversations)) return;
        writeLocalCache('inbox', conversations);
    }

    function readOfficialsCache() {
        var payload = readLocalCache('officials');
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.users)) return payload.users;
        return null;
    }

    function writeOfficialsCache(users) {
        if (!Array.isArray(users)) return;
        writeLocalCache('officials', users);
    }

    function threadCacheKey(conversationId) {
        return THREAD_CACHE_PREFIX + cacheUserId() + ':' + String(conversationId);
    }

    function readThreadCache(conversationId) {
        if (!conversationId) return null;
        return readCacheEntry(threadCacheKey(conversationId));
    }

    function writeThreadCache(conversationId, payload) {
        if (!conversationId || payload == null) return;
        writeCacheEntry(threadCacheKey(conversationId), payload);
    }

    window.Comms = {
        __booted: true,
        csrfToken: csrfToken,
        escapeHtml: escapeHtml,
        defaultAvatar: defaultAvatar,
        formatTime: formatTime,
        route: route,
        api: api,
        showToast: showToast,
        playReactionSound: playReactionSound,
        ensureReactionAudio: ensureReactionAudio,
        openDeleteMessageDialog: openDeleteMessageDialog,
        readLocalCache: readLocalCache,
        writeLocalCache: writeLocalCache,
        readInboxCache: readInboxCache,
        writeInboxCache: writeInboxCache,
        readOfficialsCache: readOfficialsCache,
        writeOfficialsCache: writeOfficialsCache,
        readThreadCache: readThreadCache,
        writeThreadCache: writeThreadCache
    };
})(window);
