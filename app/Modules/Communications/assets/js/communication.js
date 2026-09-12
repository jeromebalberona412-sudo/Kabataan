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
        var existing = document.getElementById('commsToast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'commsToast';
        el.className = 'comms-toast comms-toast--' + type;
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.textContent = message;
        var bg = '#0450a8';
        if (type === 'error') bg = '#b42318';
        else if (type === 'success') bg = '#15803d';
        var isTop = type === 'success' || type === 'error' || type === 'info';
        el.style.cssText = 'position:fixed;z-index:100500;left:50%;transform:translateX(-50%);' +
            (isTop
                ? 'top:max(0.85rem, calc(var(--kab-nav-h, 68px) + 0.55rem));bottom:auto;'
                : 'bottom:1rem;top:auto;') +
            'background:' + bg +
            ';color:#fff;padding:0.8rem 1.15rem;border-radius:10px;max-width:min(92vw,380px);' +
            'box-shadow:0 10px 28px rgba(0,0,0,.2);font-size:0.9rem;font-weight:700;text-align:center;' +
            'pointer-events:none;';
        document.body.appendChild(el);
        setTimeout(function () {
            if (el && el.parentNode) el.remove();
        }, 3200);
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

    function currentUserAvatarUrl() {
        var root = document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
        if (!root) return '';
        return String(root.getAttribute('data-current-user-avatar') || root.dataset.currentUserAvatar || '').trim();
    }

    function cloneReactions(reactions) {
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

    function optimisticToggleReactions(reactions, emoji) {
        var list = cloneReactions(reactions).filter(function (r) { return r.count > 0; });
        var mineEntry = null;
        var target = null;
        var meUser = {
            id: 0,
            user_type: '',
            name: 'You',
            mine: true,
            profile_image_url: currentUserAvatarUrl()
        };
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
                    target.users.push(meUser);
                }
            } else {
                list.push({
                    emoji: emoji,
                    count: 1,
                    mine: true,
                    users: [meUser]
                });
            }
        }
        return list.filter(function (r) { return r.count > 0; });
    }

    function reactorDisplayName(user) {
        if (!user) return 'Someone';
        if (user.mine) return 'You';
        var name = String(user.name || '').trim();
        return name !== '' ? name : 'Someone';
    }

    var reactionPeopleEl = null;
    var reactionPeopleHideTimer = null;
    var reactionPeoplePinned = false;
    var reactionModalEl = null;
    var reactionModalState = { reactions: [], filterEmoji: '' };

    function ensureReactionPeopleStyles() {
        if (document.getElementById('commsReactionPeopleStyles')) return;
        var style = document.createElement('style');
        style.id = 'commsReactionPeopleStyles';
        style.textContent =
            '.comms-reaction-people{position:fixed;z-index:100100;min-width:148px;max-width:min(260px,calc(100vw - 16px));background:#1c1e21;color:#fff;border-radius:10px;padding:8px 10px;box-shadow:0 12px 28px rgba(15,23,42,.32);font-size:12px;line-height:1.35;}' +
            '.comms-reaction-people[hidden]{display:none!important;}' +
            '.comms-reaction-people-group{display:flex;gap:8px;align-items:flex-start;}' +
            '.comms-reaction-people-group+.comms-reaction-people-group{margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.12);}' +
            '.comms-reaction-people-emoji{font-size:1.05rem;line-height:1.2;flex:0 0 auto;}' +
            '.comms-reaction-people-list{margin:0;padding:0;list-style:none;}' +
            '.comms-reaction-people-list li{font-weight:600;word-break:break-word;}' +
            'button.comms-reaction-summary{cursor:pointer;font:inherit;color:inherit;}' +
            '.comms-reaction-modal{position:fixed;inset:0;z-index:100120;display:flex;align-items:center;justify-content:center;padding:1rem;}' +
            '.comms-reaction-modal[hidden]{display:none!important;}' +
            '.comms-reaction-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45);}' +
            '.comms-reaction-modal-panel{position:relative;z-index:1;width:min(100%,420px);max-height:min(84vh,560px);background:#fff;border-radius:14px;box-shadow:0 18px 48px rgba(15,23,42,.28);display:flex;flex-direction:column;overflow:hidden;}' +
            '.comms-reaction-modal-header{display:flex;align-items:center;justify-content:center;position:relative;padding:1rem 3rem;border-bottom:1px solid #e5e7eb;}' +
            '.comms-reaction-modal-title{margin:0;font-size:1.05rem;font-weight:750;color:#111827;text-align:center;}' +
            '.comms-reaction-modal-close{position:absolute;right:.7rem;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;border-radius:999px;background:#f3f4f6;color:#111827;font-size:1.25rem;line-height:1;cursor:pointer;}' +
            '.comms-reaction-modal-tabs{display:flex;gap:.15rem;overflow-x:auto;padding:.55rem .75rem;border-bottom:1px solid #eef2f7;justify-content:center;}' +
            '.comms-reaction-modal-tab{border:0;background:transparent;color:#6b7280;font-weight:700;font-size:.88rem;padding:.4rem .65rem;border-bottom:2px solid transparent;white-space:nowrap;cursor:pointer;}' +
            '.comms-reaction-modal-tab.is-active{color:#0450a8;border-bottom-color:#0450a8;}' +
            '.comms-reaction-modal-list{overflow:auto;padding:.35rem 0;flex:1;}' +
            '.comms-reaction-modal-row{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;}' +
            '.comms-reaction-modal-avatar{width:40px;height:40px;border-radius:999px;background:#e8f1fb;color:#0450a8;display:inline-flex;align-items:center;justify-content:center;font-weight:750;flex:0 0 auto;overflow:hidden;}' +
            '.comms-reaction-modal-avatar img{width:100%;height:100%;object-fit:cover;display:block;}' +
            '.comms-reaction-modal-meta{flex:1;min-width:0;}' +
            '.comms-reaction-modal-name{font-weight:700;color:#111827;word-break:break-word;}' +
            '.comms-reaction-modal-hint{font-size:.78rem;color:#6b7280;margin-top:.1rem;}' +
            '.comms-reaction-modal-emoji{font-size:1.25rem;flex:0 0 auto;}' +
            '.comms-reaction-modal-empty{padding:1.5rem 1rem;text-align:center;color:#6b7280;font-weight:600;}' +
            '.comms-image-lightbox{position:fixed;inset:0;z-index:100200;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0;}' +
            '.comms-image-lightbox[hidden]{display:none!important;}' +
            '.comms-image-lightbox-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.92);}' +
            '.comms-image-lightbox-toolbar{position:fixed;top:max(.65rem,env(safe-area-inset-top));left:50%;transform:translateX(-50%);z-index:3;display:flex;align-items:center;gap:.35rem;padding:.35rem;border-radius:999px;background:rgba(0,0,0,.35);}' +
            '.comms-image-lightbox-tool{width:40px;height:40px;border:0;border-radius:999px;background:rgba(255,255,255,.12);color:#fff;font-size:1.15rem;font-weight:700;cursor:pointer;line-height:1;}' +
            '.comms-image-lightbox-tool:hover,.comms-image-lightbox-tool:focus-visible{background:rgba(255,255,255,.22);outline:none;}' +
            '.comms-image-lightbox-zoom-label{min-width:3.2rem;text-align:center;color:#e2e8f0;font-size:.8rem;font-weight:700;}' +
            '.comms-image-lightbox-close{position:fixed;top:max(.65rem,env(safe-area-inset-top));left:max(.65rem,env(safe-area-inset-left));z-index:3;width:42px;height:42px;border:0;border-radius:999px;background:rgba(255,255,255,.14);color:#fff;font-size:1.6rem;line-height:1;cursor:pointer;}' +
            '.comms-image-lightbox-nav{position:fixed;top:50%;transform:translateY(-50%);z-index:3;width:46px;height:46px;border:0;border-radius:999px;background:rgba(255,255,255,.14);color:#fff;font-size:1.45rem;cursor:pointer;}' +
            '.comms-image-lightbox-nav.is-prev{left:max(.75rem,env(safe-area-inset-left));}' +
            '.comms-image-lightbox-nav.is-next{right:max(.75rem,env(safe-area-inset-right));}' +
            '.comms-image-lightbox-stage{position:relative;z-index:1;flex:1;width:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4.5rem 4rem 6.5rem;}' +
            '.comms-image-lightbox-img{max-width:min(96vw,1200px);max-height:calc(100vh - 11rem);width:auto;height:auto;object-fit:contain;border-radius:8px;transform-origin:center center;transition:transform .12s ease;user-select:none;-webkit-user-drag:none;}' +
            '.comms-image-lightbox-counter{position:fixed;top:max(.85rem,env(safe-area-inset-top));right:max(.85rem,env(safe-area-inset-right));z-index:3;color:#e2e8f0;font-size:.85rem;font-weight:700;background:rgba(0,0,0,.35);padding:.35rem .65rem;border-radius:999px;}' +
            '.comms-image-lightbox-thumbs{position:fixed;left:50%;bottom:max(.75rem,env(safe-area-inset-bottom));transform:translateX(-50%);z-index:3;display:flex;gap:.4rem;max-width:min(96vw,920px);overflow-x:auto;padding:.45rem .55rem;border-radius:14px;background:rgba(0,0,0,.42);-webkit-overflow-scrolling:touch;scrollbar-width:thin;}' +
            '.comms-image-lightbox-thumbs[hidden]{display:none!important;}' +
            '.comms-image-lightbox-thumb{flex:0 0 auto;width:56px;height:56px;padding:0;border:2px solid transparent;border-radius:8px;overflow:hidden;background:#0f172a;cursor:pointer;opacity:.72;}' +
            '.comms-image-lightbox-thumb.is-active{border-color:#fff;opacity:1;}' +
            '.comms-image-lightbox-thumb img{display:block;width:100%;height:100%;object-fit:cover;}' +
            '.comms-image-lightbox-nav:disabled{opacity:.35;cursor:default;}';
        document.head.appendChild(style);
    }

    function ensureReactionPeopleEl() {
        ensureReactionPeopleStyles();
        if (reactionPeopleEl) return reactionPeopleEl;
        reactionPeopleEl = document.createElement('div');
        reactionPeopleEl.id = 'commsReactionPeople';
        reactionPeopleEl.className = 'comms-reaction-people';
        reactionPeopleEl.hidden = true;
        reactionPeopleEl.setAttribute('role', 'tooltip');
        document.body.appendChild(reactionPeopleEl);
        reactionPeopleEl.addEventListener('pointerenter', function () {
            if (reactionPeopleHideTimer) {
                window.clearTimeout(reactionPeopleHideTimer);
                reactionPeopleHideTimer = null;
            }
        });
        reactionPeopleEl.addEventListener('pointerleave', function () {
            if (!reactionPeoplePinned) hideReactionPeople();
        });
        document.addEventListener('click', function (e) {
            if (!reactionPeopleEl || reactionPeopleEl.hidden) return;
            if (e.target.closest('#commsReactionPeople') || e.target.closest('[data-react-people]')) return;
            hideReactionPeople();
        });
        return reactionPeopleEl;
    }

    function hideReactionPeople() {
        reactionPeoplePinned = false;
        if (reactionPeopleHideTimer) {
            window.clearTimeout(reactionPeopleHideTimer);
            reactionPeopleHideTimer = null;
        }
        if (reactionPeopleEl) reactionPeopleEl.hidden = true;
    }

    function positionReactionPeople(anchor) {
        var el = ensureReactionPeopleEl();
        if (!anchor) return;
        var rect = anchor.getBoundingClientRect();
        var pad = 8;
        var w = el.offsetWidth || 180;
        var h = el.offsetHeight || 48;
        var top = rect.top - h - 8;
        var left = rect.left + (rect.width / 2) - (w / 2);
        if (top < pad) top = rect.bottom + 8;
        left = Math.max(pad, Math.min(left, window.innerWidth - w - pad));
        top = Math.max(pad, Math.min(top, window.innerHeight - h - pad));
        el.style.top = top + 'px';
        el.style.left = left + 'px';
    }

    function renderReactionPeopleHtml(reactions, filterEmoji) {
        var items = cloneReactions(reactions).filter(function (r) {
            if (Number(r.count || 0) <= 0) return false;
            if (filterEmoji && r.emoji !== filterEmoji) return false;
            return true;
        });
        if (!items.length) {
            return '<div class="comms-reaction-people-group"><ul class="comms-reaction-people-list"><li>No reactions yet</li></ul></div>';
        }
        return items.map(function (r) {
            var names = (r.users && r.users.length)
                ? r.users.map(reactorDisplayName)
                : (r.mine ? ['You'] : []);
            if (!names.length) {
                names = [String(r.count) + (Number(r.count) === 1 ? ' reaction' : ' reactions')];
            }
            return '<div class="comms-reaction-people-group">' +
                '<span class="comms-reaction-people-emoji">' + escapeHtml(r.emoji) + '</span>' +
                '<ul class="comms-reaction-people-list">' +
                names.map(function (n) { return '<li>' + escapeHtml(n) + '</li>'; }).join('') +
                '</ul></div>';
        }).join('');
    }

    function showReactionPeople(anchor, reactions, options) {
        options = options || {};
        if (!anchor) return;
        var el = ensureReactionPeopleEl();
        if (reactionPeopleHideTimer) {
            window.clearTimeout(reactionPeopleHideTimer);
            reactionPeopleHideTimer = null;
        }
        reactionPeoplePinned = !!options.pinned;
        el.innerHTML = renderReactionPeopleHtml(reactions, options.emoji || '');
        el.hidden = false;
        positionReactionPeople(anchor);
        requestAnimationFrame(function () { positionReactionPeople(anchor); });
    }

    function toggleReactionPeople(anchor, reactions, options) {
        options = options || {};
        openReactionModal(reactions, options.emoji || '');
    }

    function scheduleHideReactionPeople() {
        if (reactionPeoplePinned) return;
        if (reactionPeopleHideTimer) window.clearTimeout(reactionPeopleHideTimer);
        reactionPeopleHideTimer = window.setTimeout(hideReactionPeople, 160);
    }

    function ensureReactionModal() {
        ensureReactionPeopleStyles();
        if (reactionModalEl) return reactionModalEl;
        reactionModalEl = document.createElement('div');
        reactionModalEl.id = 'commsReactionModal';
        reactionModalEl.className = 'comms-reaction-modal';
        reactionModalEl.hidden = true;
        reactionModalEl.setAttribute('role', 'dialog');
        reactionModalEl.setAttribute('aria-modal', 'true');
        reactionModalEl.setAttribute('aria-labelledby', 'commsReactionModalTitle');
        reactionModalEl.innerHTML =
            '<div class="comms-reaction-modal-backdrop" data-reaction-modal-close tabindex="-1"></div>' +
            '<div class="comms-reaction-modal-panel">' +
            '<div class="comms-reaction-modal-header">' +
            '<h3 class="comms-reaction-modal-title" id="commsReactionModalTitle">Message reactions</h3>' +
            '<button type="button" class="comms-reaction-modal-close" data-reaction-modal-close aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="comms-reaction-modal-tabs" id="commsReactionModalTabs" role="tablist"></div>' +
            '<div class="comms-reaction-modal-list" id="commsReactionModalList"></div>' +
            '</div>';
        document.body.appendChild(reactionModalEl);
        reactionModalEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-reaction-modal-close]')) {
                closeReactionModal();
                return;
            }
            var tab = e.target.closest('[data-reaction-tab]');
            if (tab) {
                reactionModalState.filterEmoji = tab.getAttribute('data-reaction-tab') || '';
                renderReactionModalBody();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && reactionModalEl && !reactionModalEl.hidden) {
                closeReactionModal();
            }
        });
        return reactionModalEl;
    }

    function renderReactionModalBody() {
        var tabs = document.getElementById('commsReactionModalTabs');
        var list = document.getElementById('commsReactionModalList');
        if (!tabs || !list) return;
        var items = cloneReactions(reactionModalState.reactions).filter(function (r) {
            return Number(r.count || 0) > 0;
        });
        var total = 0;
        items.forEach(function (r) { total += Number(r.count || 0); });
        var filter = reactionModalState.filterEmoji || '';
        tabs.innerHTML =
            '<button type="button" class="comms-reaction-modal-tab' + (filter === '' ? ' is-active' : '') + '" data-reaction-tab="" role="tab">All ' + total + '</button>' +
            items.map(function (r) {
                return '<button type="button" class="comms-reaction-modal-tab' + (filter === r.emoji ? ' is-active' : '') + '" data-reaction-tab="' +
                    escapeHtml(r.emoji) + '" role="tab">' + escapeHtml(r.emoji) + ' ' + Number(r.count || 0) + '</button>';
            }).join('');

        var rows = [];
        items.forEach(function (r) {
            if (filter && r.emoji !== filter) return;
            var users = (r.users && r.users.length) ? r.users : [];
            if (!users.length && r.mine) {
                users = [{ name: 'You', mine: true }];
            }
            if (!users.length) {
                rows.push('<div class="comms-reaction-modal-row"><div class="comms-reaction-modal-meta"><div class="comms-reaction-modal-name">' +
                    Number(r.count || 0) + ' reaction' + (Number(r.count) === 1 ? '' : 's') +
                    '</div></div><span class="comms-reaction-modal-emoji">' + escapeHtml(r.emoji) + '</span></div>');
                return;
            }
            users.forEach(function (u) {
                var name = reactorDisplayName(u);
                var initial = String(name).charAt(0).toUpperCase() || '?';
                var avatarUrl = String(u.profile_image_url || '').trim();
                if (!avatarUrl && u.mine) avatarUrl = currentUserAvatarUrl();
                if (!avatarUrl) avatarUrl = defaultAvatar(name);
                rows.push(
                    '<div class="comms-reaction-modal-row">' +
                    '<span class="comms-reaction-modal-avatar" aria-hidden="true">' +
                    '<img src="' + escapeHtml(avatarUrl) + '" alt="">' +
                    '</span>' +
                    '<div class="comms-reaction-modal-meta">' +
                    '<div class="comms-reaction-modal-name">' + escapeHtml(name) + '</div>' +
                    '</div>' +
                    '<span class="comms-reaction-modal-emoji">' + escapeHtml(r.emoji) + '</span>' +
                    '</div>'
                );
            });
        });
        list.innerHTML = rows.length ? rows.join('') : '<p class="comms-reaction-modal-empty">No reactions yet</p>';
    }

    function openReactionModal(reactions, filterEmoji) {
        hideReactionPeople();
        ensureReactionModal();
        reactionModalState.reactions = cloneReactions(reactions);
        reactionModalState.filterEmoji = filterEmoji || '';
        renderReactionModalBody();
        reactionModalEl.hidden = false;
        document.body.classList.add('comms-reaction-modal-open');
    }

    function closeReactionModal() {
        if (!reactionModalEl) return;
        reactionModalEl.hidden = true;
        document.body.classList.remove('comms-reaction-modal-open');
    }

    var imageLightboxEl = null;
    var imageLightboxState = { images: [], index: 0, zoom: 1 };

    function ensureImageLightbox() {
        ensureReactionPeopleStyles();
        if (imageLightboxEl) return imageLightboxEl;
        imageLightboxEl = document.createElement('div');
        imageLightboxEl.id = 'commsImageLightbox';
        imageLightboxEl.className = 'comms-image-lightbox';
        imageLightboxEl.hidden = true;
        imageLightboxEl.setAttribute('role', 'dialog');
        imageLightboxEl.setAttribute('aria-modal', 'true');
        imageLightboxEl.setAttribute('aria-label', 'Image preview');
        imageLightboxEl.innerHTML =
            '<div class="comms-image-lightbox-backdrop" data-comms-lightbox-close tabindex="-1"></div>' +
            '<button type="button" class="comms-image-lightbox-close" data-comms-lightbox-close aria-label="Close">&times;</button>' +
            '<div class="comms-image-lightbox-toolbar" role="toolbar" aria-label="Zoom controls">' +
            '<button type="button" class="comms-image-lightbox-tool" data-comms-lightbox-zoom-out aria-label="Zoom out">−</button>' +
            '<span class="comms-image-lightbox-zoom-label" data-comms-lightbox-zoom-label>100%</span>' +
            '<button type="button" class="comms-image-lightbox-tool" data-comms-lightbox-zoom-in aria-label="Zoom in">+</button>' +
            '<button type="button" class="comms-image-lightbox-tool" data-comms-lightbox-zoom-reset aria-label="Reset zoom">⟲</button>' +
            '</div>' +
            '<div class="comms-image-lightbox-counter" data-comms-lightbox-counter hidden></div>' +
            '<button type="button" class="comms-image-lightbox-nav is-prev" data-comms-lightbox-prev aria-label="Previous">&#10094;</button>' +
            '<div class="comms-image-lightbox-stage">' +
            '<img class="comms-image-lightbox-img" alt="Full size image" draggable="false">' +
            '</div>' +
            '<button type="button" class="comms-image-lightbox-nav is-next" data-comms-lightbox-next aria-label="Next">&#10095;</button>' +
            '<div class="comms-image-lightbox-thumbs" data-comms-lightbox-thumbs hidden role="listbox" aria-label="Image thumbnails"></div>';
        document.body.appendChild(imageLightboxEl);
        imageLightboxEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-comms-lightbox-close]')) {
                closeImageLightbox();
                return;
            }
            if (e.target.closest('[data-comms-lightbox-prev]')) {
                stepImageLightbox(-1);
                return;
            }
            if (e.target.closest('[data-comms-lightbox-next]')) {
                stepImageLightbox(1);
                return;
            }
            if (e.target.closest('[data-comms-lightbox-zoom-in]')) {
                setImageLightboxZoom(imageLightboxState.zoom + 0.25);
                return;
            }
            if (e.target.closest('[data-comms-lightbox-zoom-out]')) {
                setImageLightboxZoom(imageLightboxState.zoom - 0.25);
                return;
            }
            if (e.target.closest('[data-comms-lightbox-zoom-reset]')) {
                setImageLightboxZoom(1);
                return;
            }
            var thumb = e.target.closest('[data-comms-lightbox-thumb-index]');
            if (thumb) {
                var idx = Number(thumb.getAttribute('data-comms-lightbox-thumb-index'));
                if (!Number.isNaN(idx)) {
                    imageLightboxState.index = idx;
                    setImageLightboxZoom(1);
                    renderImageLightbox();
                }
            }
        });
        imageLightboxEl.addEventListener('wheel', function (e) {
            if (imageLightboxEl.hidden) return;
            e.preventDefault();
            if (e.deltaY < 0) setImageLightboxZoom(imageLightboxState.zoom + 0.15);
            else setImageLightboxZoom(imageLightboxState.zoom - 0.15);
        }, { passive: false });
        document.addEventListener('keydown', function (e) {
            if (!imageLightboxEl || imageLightboxEl.hidden) return;
            if (e.key === 'Escape') closeImageLightbox();
            else if (e.key === 'ArrowLeft') stepImageLightbox(-1);
            else if (e.key === 'ArrowRight') stepImageLightbox(1);
            else if (e.key === '+' || e.key === '=') setImageLightboxZoom(imageLightboxState.zoom + 0.25);
            else if (e.key === '-') setImageLightboxZoom(imageLightboxState.zoom - 0.25);
            else if (e.key === '0') setImageLightboxZoom(1);
        });
        return imageLightboxEl;
    }

    function setImageLightboxZoom(next) {
        imageLightboxState.zoom = Math.max(0.5, Math.min(4, Number(next) || 1));
        var img = imageLightboxEl && imageLightboxEl.querySelector('.comms-image-lightbox-img');
        var label = imageLightboxEl && imageLightboxEl.querySelector('[data-comms-lightbox-zoom-label]');
        if (img) img.style.transform = 'scale(' + imageLightboxState.zoom + ')';
        if (label) label.textContent = Math.round(imageLightboxState.zoom * 100) + '%';
    }

    function uniqueLightboxSources(list) {
        var out = [];
        var seen = {};
        (list || []).forEach(function (src) {
            var value = String(src || '').trim();
            if (!value || seen[value]) return;
            seen[value] = true;
            out.push(value);
        });
        return out;
    }

    function renderImageLightbox() {
        var el = ensureImageLightbox();
        var img = el.querySelector('.comms-image-lightbox-img');
        var counter = el.querySelector('[data-comms-lightbox-counter]');
        var prev = el.querySelector('[data-comms-lightbox-prev]');
        var next = el.querySelector('[data-comms-lightbox-next]');
        var thumbs = el.querySelector('[data-comms-lightbox-thumbs]');
        var list = imageLightboxState.images;
        var idx = imageLightboxState.index;
        if (!img || !list.length) return;
        img.src = list[idx];
        setImageLightboxZoom(imageLightboxState.zoom || 1);
        if (counter) {
            if (list.length > 1) {
                counter.hidden = false;
                counter.textContent = (idx + 1) + ' / ' + list.length;
            } else {
                counter.hidden = true;
                counter.textContent = '';
            }
        }
        if (prev) prev.hidden = list.length < 2;
        if (next) next.hidden = list.length < 2;
        if (thumbs) {
            if (list.length > 1) {
                thumbs.hidden = false;
                thumbs.innerHTML = list.map(function (src, i) {
                    return '<button type="button" class="comms-image-lightbox-thumb' + (i === idx ? ' is-active' : '') +
                        '" data-comms-lightbox-thumb-index="' + i + '" role="option" aria-selected="' + (i === idx ? 'true' : 'false') + '">' +
                        '<img src="' + escapeHtml(src) + '" alt=""></button>';
                }).join('');
                var activeThumb = thumbs.querySelector('.comms-image-lightbox-thumb.is-active');
                if (activeThumb && typeof activeThumb.scrollIntoView === 'function') {
                    activeThumb.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            } else {
                thumbs.hidden = true;
                thumbs.innerHTML = '';
            }
        }
    }

    function openImageLightbox(src, images) {
        var list = uniqueLightboxSources(Array.isArray(images) ? images : []);
        var start = String(src || '').trim();
        if (!list.length && start) list = [start];
        if (!list.length) return;
        var index = list.indexOf(start);
        if (index < 0) index = 0;
        imageLightboxState.images = list;
        imageLightboxState.index = index;
        imageLightboxState.zoom = 1;
        ensureImageLightbox();
        renderImageLightbox();
        imageLightboxEl.hidden = false;
        document.body.classList.add('comms-image-lightbox-open');
    }

    function closeImageLightbox() {
        if (!imageLightboxEl) return;
        imageLightboxEl.hidden = true;
        document.body.classList.remove('comms-image-lightbox-open');
        var img = imageLightboxEl.querySelector('.comms-image-lightbox-img');
        if (img) {
            img.removeAttribute('src');
            img.style.transform = '';
        }
        imageLightboxState.zoom = 1;
    }

    function stepImageLightbox(delta) {
        var list = imageLightboxState.images;
        if (list.length < 2) return;
        imageLightboxState.index = (imageLightboxState.index + delta + list.length) % list.length;
        imageLightboxState.zoom = 1;
        renderImageLightbox();
    }

    function collectLightboxGallery(thumb, root) {
        var start = '';
        if (thumb.tagName === 'IMG') {
            start = thumb.getAttribute('data-comms-lightbox-src') || thumb.currentSrc || thumb.src || '';
        } else {
            start = thumb.getAttribute('data-comms-lightbox-src') || thumb.getAttribute('href') || '';
            var nested = thumb.querySelector('img');
            if (!start && nested) start = nested.currentSrc || nested.src || '';
        }
        start = String(start || '').trim();
        if (!start) return { src: '', images: [] };

        // Prefer every image in this conversation thread (history strip + left/right nav).
        var nodes = [];
        if (root) {
            nodes = Array.prototype.slice.call(root.querySelectorAll('[data-comms-lightbox-src]'));
        }
        if (!nodes.length) {
            var wrap = thumb.closest('[data-batch-id]');
            var batchId = wrap ? String(wrap.getAttribute('data-batch-id') || '') : '';
            if (batchId && root) {
                nodes = Array.prototype.slice.call(
                    root.querySelectorAll('[data-batch-id="' + batchId.replace(/"/g, '') + '"] [data-comms-lightbox-src]')
                );
            }
        }
        if (!nodes.length) {
            var grid = thumb.closest('.comms-chat-attachment-grid, .comms-bubble-attachment-grid, .comms-bubble, .comms-chat-bubble');
            nodes = grid
                ? Array.prototype.slice.call(grid.querySelectorAll('[data-comms-lightbox-src]'))
                : [thumb.closest('[data-comms-lightbox-src]') || thumb];
        }
        var images = uniqueLightboxSources(nodes.map(function (node) {
            if (!node) return '';
            return node.getAttribute('data-comms-lightbox-src') ||
                (node.tagName === 'IMG' ? (node.currentSrc || node.src) : '') ||
                node.getAttribute('href') || '';
        }));
        return { src: start, images: images };
    }

    function wireChatImageLightbox(root) {
        if (!root || root.dataset.commsLightboxWired === 'true') return;
        root.addEventListener('click', function (e) {
            var thumb = e.target.closest('[data-comms-lightbox-src], .comms-chat-attachment-grid img, .comms-bubble-image img, a.comms-bubble-image, button.comms-bubble-image');
            if (!thumb || !root.contains(thumb)) return;
            var gallery = collectLightboxGallery(thumb, root);
            if (!gallery.src) return;
            e.preventDefault();
            e.stopPropagation();
            openImageLightbox(gallery.src, gallery.images);
        });
        root.dataset.commsLightboxWired = 'true';
    }

    var deleteModalEl = null;
    var deleteModalResolve = null;

    function ensureDeleteModalStyles() {
        if (document.getElementById('commsDeleteModalStyles')) return;
        var style = document.createElement('style');
        style.id = 'commsDeleteModalStyles';
        style.textContent =
            '.comms-unsend-modal{position:fixed;inset:0;z-index:100080;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:auto;}' +
            '.comms-unsend-modal[hidden]{display:none!important;}' +
            '.comms-unsend-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55);}' +
            '.comms-unsend-card{position:relative;z-index:1;width:min(100%,500px);background:#fff;border-radius:8px;box-shadow:0 12px 28px rgba(0,0,0,.22);overflow:hidden;box-sizing:border-box;}' +
            '.comms-unsend-close{position:absolute;top:12px;right:12px;width:36px;height:36px;border:0;border-radius:999px;background:#e4e6eb;color:#050505;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;}' +
            '.comms-unsend-close:hover,.comms-unsend-close:focus-visible{background:#d8dadf;outline:2px solid rgba(8,102,255,.35);outline-offset:2px;}' +
            '.comms-unsend-title{margin:0;padding:18px 52px 8px 16px;font-size:1.25rem;line-height:1.25;font-weight:700;color:#080809;text-align:center;font-family:inherit;}' +
            '.comms-unsend-options{display:flex;flex-direction:column;padding:8px 0 4px;}' +
            '.comms-unsend-option{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;cursor:pointer;margin:0;}' +
            '.comms-unsend-option[hidden]{display:none!important;}' +
            '.comms-unsend-option:hover{background:#f2f3f5;}' +
            '.comms-unsend-option input{appearance:none;-webkit-appearance:none;flex:0 0 auto;width:20px;height:20px;margin:2px 0 0;border:2px solid #ccd0d5;border-radius:50%;background:#fff;cursor:pointer;box-sizing:border-box;}' +
            '.comms-unsend-option input:checked{border-color:#0866FF;background:#0866FF;box-shadow:inset 0 0 0 3px #fff;}' +
            '.comms-unsend-option input:focus-visible{outline:2px solid rgba(8,102,255,.4);outline-offset:2px;}' +
            '.comms-unsend-option-copy{min-width:0;display:flex;flex-direction:column;gap:2px;}' +
            '.comms-unsend-option-label{font-size:.9375rem;font-weight:700;color:#080809;line-height:1.3;}' +
            '.comms-unsend-option-hint{font-size:.8125rem;font-weight:400;color:#65676b;line-height:1.35;}' +
            '.comms-unsend-footer{display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:12px 16px 16px;border-top:1px solid #e4e6eb;}' +
            '.comms-unsend-cancel{border:0;background:transparent;color:#0866FF;font-size:.9375rem;font-weight:600;padding:.55rem .9rem;border-radius:6px;cursor:pointer;font-family:inherit;}' +
            '.comms-unsend-cancel:hover,.comms-unsend-cancel:focus-visible{background:#e7f3ff;outline:none;}' +
            '.comms-unsend-remove{border:0;background:#0866FF;color:#fff;font-size:.9375rem;font-weight:600;padding:.55rem 1.15rem;border-radius:6px;cursor:pointer;font-family:inherit;min-width:88px;}' +
            '.comms-unsend-remove:hover,.comms-unsend-remove:focus-visible{background:#0759dd;outline:2px solid rgba(8,102,255,.4);outline-offset:2px;}' +
            '@media (max-width:414px){.comms-unsend-card{border-radius:10px;width:100%;}.comms-unsend-title{font-size:1.05rem;padding:16px 48px 8px 12px;}.comms-unsend-option{padding:12px;}}';
        document.head.appendChild(style);
    }

    function ensureDeleteModal() {
        ensureDeleteModalStyles();
        if (deleteModalEl) return deleteModalEl;
        deleteModalEl = document.createElement('div');
        deleteModalEl.id = 'commsDeleteModal';
        deleteModalEl.className = 'comms-unsend-modal';
        deleteModalEl.hidden = true;
        deleteModalEl.setAttribute('role', 'dialog');
        deleteModalEl.setAttribute('aria-modal', 'true');
        deleteModalEl.setAttribute('aria-labelledby', 'commsDeleteModalTitle');
        deleteModalEl.innerHTML =
            '<div class="comms-unsend-backdrop" data-delete-cancel tabindex="-1"></div>' +
            '<div class="comms-unsend-card">' +
            '<button type="button" class="comms-unsend-close" data-delete-cancel aria-label="Close">' +
            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
            '</button>' +
            '<h3 class="comms-unsend-title" id="commsDeleteModalTitle">Who do you want to unsend this message for?</h3>' +
            '<div class="comms-unsend-options" role="radiogroup" aria-labelledby="commsDeleteModalTitle">' +
            '<label class="comms-unsend-option" id="commsDeleteModalAll">' +
            '<input type="radio" name="commsDeleteScope" value="all">' +
            '<span class="comms-unsend-option-copy">' +
            '<span class="comms-unsend-option-label">Unsend for everyone</span>' +
            '<span class="comms-unsend-option-hint">This message will be unsent for everyone in the chat. Others may have already seen or forwarded it.</span>' +
            '</span></label>' +
            '<label class="comms-unsend-option" id="commsDeleteModalMe">' +
            '<input type="radio" name="commsDeleteScope" value="me">' +
            '<span class="comms-unsend-option-copy">' +
            '<span class="comms-unsend-option-label">Unsend for you</span>' +
            '<span class="comms-unsend-option-hint">This message will be removed for you. Others in the chat will still be able to see it.</span>' +
            '</span></label>' +
            '</div>' +
            '<div class="comms-unsend-footer">' +
            '<button type="button" class="comms-unsend-cancel" data-delete-cancel>Cancel</button>' +
            '<button type="button" class="comms-unsend-remove" data-delete-confirm>Remove</button>' +
            '</div></div>';
        document.body.appendChild(deleteModalEl);

        deleteModalEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-delete-cancel]')) {
                closeDeleteMessageDialog(null);
                return;
            }
            if (e.target.closest('[data-delete-confirm]')) {
                var selected = deleteModalEl.querySelector('input[name="commsDeleteScope"]:checked');
                closeDeleteMessageDialog(selected ? selected.value : 'me');
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
        var allOpt = document.getElementById('commsDeleteModalAll');
        var allInput = allOpt ? allOpt.querySelector('input') : null;
        var meInput = modal.querySelector('#commsDeleteModalMe input');

        if (title) {
            title.textContent = options.canDeleteAll
                ? 'Who do you want to unsend this message for?'
                : 'Remove this message?';
        }
        if (allOpt) {
            allOpt.hidden = !options.canDeleteAll;
        }
        if (allInput) {
            allInput.disabled = !options.canDeleteAll;
            allInput.checked = !!options.canDeleteAll;
        }
        if (meInput) {
            meInput.checked = !options.canDeleteAll;
        }

        if (deleteModalResolve) {
            deleteModalResolve(null);
            deleteModalResolve = null;
        }

        modal.hidden = false;
        window.setTimeout(function () {
            var confirmBtn = modal.querySelector('[data-delete-confirm]');
            if (confirmBtn) confirmBtn.focus();
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

    function truncateText(value, maxChars) {
        var text = String(value == null ? '' : value).trim().replace(/\s+/g, ' ');
        var max = Math.max(8, Number(maxChars) || 32);
        if (text.length <= max) return text;
        return text.slice(0, Math.max(1, max - 1)).replace(/\s+$/, '') + '…';
    }

    function isUserOnline(user) {
        if (!user || typeof user !== 'object') return false;
        if (user.is_online === true) return true;
        if (user.is_online === false) return false;
        if (String(user.online_status || '').toLowerCase() === 'online') return true;
        if (String(user.online_status || '').toLowerCase() === 'offline') return false;
        // Prefer last_seen so cached presence does not stay green forever.
        var seenMs = user.last_seen ? Date.parse(user.last_seen) : NaN;
        if (!Number.isNaN(seenMs)) {
            return (Date.now() - seenMs) < (2 * 60 * 1000);
        }
        return false;
    }

    function formatPresenceLabel(user) {
        return isUserOnline(user) ? 'Online' : 'Offline';
    }

    function renderTypingIndicatorHtml(meta) {
        var who = meta && meta.name ? String(meta.name).trim() : '';
        var label = who ? (who + ' is typing...') : 'Typing...';
        return '<div class="comms-typing-row">' +
            '<span class="visually-hidden">' + escapeHtml(label) + '</span>' +
            '<div class="comms-typing-bubble" aria-hidden="true">' +
            '<span class="comms-typing-dot"></span>' +
            '<span class="comms-typing-dot"></span>' +
            '<span class="comms-typing-dot"></span>' +
            '</div>' +
            '<span class="comms-typing-label" aria-hidden="true">' + escapeHtml(label) + '</span>' +
            '</div>';
    }

    function paintTypingEl(el, visible, meta) {
        if (!el) return;
        if (!visible) {
            el.hidden = true;
            el.innerHTML = '';
            el.textContent = '';
            return;
        }
        el.innerHTML = renderTypingIndicatorHtml(meta || null);
        el.hidden = false;
    }

    /**
     * Visual-line weighted length: a newline is not "1 char free".
     * Completed lines count as at least one full textarea line width.
     */
    function estimateComposerCharsPerLine(el) {
        if (!el || !el.clientWidth) return 36;
        var style = window.getComputedStyle(el);
        var fontSize = parseFloat(style.fontSize) || 14;
        var pad = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
        var width = Math.max(0, el.clientWidth - pad);
        return Math.max(16, Math.floor(width / Math.max(fontSize * 0.55, 7)));
    }

    function measureMessageLength(text, charsPerLine) {
        var cpl = Math.max(8, Number(charsPerLine) || 36);
        var lines = String(text == null ? '' : text).replace(/\r\n/g, '\n').split('\n');
        var total = 0;
        for (var i = 0; i < lines.length; i += 1) {
            var len = lines[i].length;
            if (i < lines.length - 1) {
                total += Math.max(cpl, len);
            } else {
                total += len;
            }
        }
        return total;
    }

    function resizeGrowTextarea(el, maxVisibleLines, minVisibleLines) {
        if (!el) return;
        var maxLines = Math.max(1, Number(maxVisibleLines) || 5);
        var minLines = Math.max(1, Math.min(maxLines, Number(minVisibleLines) || 1));
        var styles = window.getComputedStyle(el);
        var fontSize = parseFloat(styles.fontSize) || 15;
        var lineHeight = parseFloat(styles.lineHeight);
        // Unitless / "normal" line-height can under-report; use font metric * 1.45.
        if (!Number.isFinite(lineHeight) || lineHeight <= 0 || String(styles.lineHeight) === 'normal') {
            lineHeight = fontSize * 1.45;
        }
        var padY = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
        var borderY = (parseFloat(styles.borderTopWidth) || 0) + (parseFloat(styles.borderBottomWidth) || 0);
        var minH = Math.ceil((lineHeight * minLines) + padY + borderY);
        var maxH = Math.ceil((lineHeight * maxLines) + padY + borderY);
        el.style.fieldSizing = 'fixed';
        el.style.minHeight = minH + 'px';
        el.style.maxHeight = maxH + 'px';
        el.style.height = 'auto';
        el.style.overflowY = 'hidden';
        var contentH = el.scrollHeight;
        var next = Math.min(Math.max(contentH, minH), maxH);
        el.style.height = next + 'px';
        el.style.overflowY = contentH > maxH + 1 ? 'auto' : 'hidden';
    }

    var faqSuggestionsMemory = {};
    var FAQ_CACHE_TTL_MS = 30 * 60 * 1000;
    var FAQ_CACHE_FRESH_MS = 15 * 1000;

    function faqSuggestionsCacheKey(conversationId) {
        return 'comms_faq_sugg_v8_' + String(conversationId || '0');
    }

    function readFaqSuggestionsCache(conversationId) {
        var cid = String(conversationId || '');
        if (!cid) return null;
        var mem = faqSuggestionsMemory[cid];
        if (mem && Array.isArray(mem.faqs) && (Date.now() - Number(mem.at || 0) <= FAQ_CACHE_TTL_MS)) {
            return { faqs: mem.faqs, at: mem.at, fresh: (Date.now() - Number(mem.at || 0) <= FAQ_CACHE_FRESH_MS) };
        }
        try {
            var raw = localStorage.getItem(faqSuggestionsCacheKey(cid));
            if (!raw) raw = sessionStorage.getItem(faqSuggestionsCacheKey(cid));
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.faqs)) return null;
            var at = Number(parsed.at || 0);
            if (Date.now() - at > FAQ_CACHE_TTL_MS) return null;
            faqSuggestionsMemory[cid] = { faqs: parsed.faqs, at: at };
            return { faqs: parsed.faqs, at: at, fresh: (Date.now() - at <= FAQ_CACHE_FRESH_MS) };
        } catch (e) {
            return null;
        }
    }

    function writeFaqSuggestionsCache(conversationId, faqs) {
        var cid = String(conversationId || '');
        if (!cid) return;
        var payload = { at: Date.now(), faqs: Array.isArray(faqs) ? faqs : [] };
        faqSuggestionsMemory[cid] = payload;
        try {
            localStorage.setItem(faqSuggestionsCacheKey(cid), JSON.stringify(payload));
        } catch (e1) {
            try {
                sessionStorage.setItem(faqSuggestionsCacheKey(cid), JSON.stringify(payload));
            } catch (e2) { /* ignore */ }
        }
    }

    function prefetchFaqSuggestions(conversationId, urlTemplate) {
        var cid = String(conversationId || '');
        if (!cid || !urlTemplate) return Promise.resolve(null);
        var cached = readFaqSuggestionsCache(cid);
        if (cached && cached.fresh) return Promise.resolve(cached.faqs);
        var url = String(urlTemplate).replace('__ID__', cid);
        return api(url).then(function (data) {
            var faqs = (data && Array.isArray(data.faqs)) ? data.faqs : [];
            writeFaqSuggestionsCache(cid, faqs);
            return faqs;
        }).catch(function () {
            return cached ? cached.faqs : null;
        });
    }

    window.Comms = {
        __booted: true,
        csrfToken: csrfToken,
        escapeHtml: escapeHtml,
        defaultAvatar: defaultAvatar,
        formatTime: formatTime,
        truncateText: truncateText,
        isUserOnline: isUserOnline,
        formatPresenceLabel: formatPresenceLabel,
        renderTypingIndicatorHtml: renderTypingIndicatorHtml,
        paintTypingEl: paintTypingEl,
        estimateComposerCharsPerLine: estimateComposerCharsPerLine,
        measureMessageLength: measureMessageLength,
        resizeGrowTextarea: resizeGrowTextarea,
        route: route,
        api: api,
        showToast: showToast,
        playReactionSound: playReactionSound,
        ensureReactionAudio: ensureReactionAudio,
        cloneReactions: cloneReactions,
        optimisticToggleReactions: optimisticToggleReactions,
        showReactionPeople: showReactionPeople,
        toggleReactionPeople: toggleReactionPeople,
        hideReactionPeople: hideReactionPeople,
        scheduleHideReactionPeople: scheduleHideReactionPeople,
        openReactionModal: openReactionModal,
        closeReactionModal: closeReactionModal,
        openImageLightbox: openImageLightbox,
        closeImageLightbox: closeImageLightbox,
        wireChatImageLightbox: wireChatImageLightbox,
        openDeleteMessageDialog: openDeleteMessageDialog,
        readLocalCache: readLocalCache,
        writeLocalCache: writeLocalCache,
        readInboxCache: readInboxCache,
        writeInboxCache: writeInboxCache,
        readOfficialsCache: readOfficialsCache,
        writeOfficialsCache: writeOfficialsCache,
        readThreadCache: readThreadCache,
        writeThreadCache: writeThreadCache,
        readFaqSuggestionsCache: readFaqSuggestionsCache,
        writeFaqSuggestionsCache: writeFaqSuggestionsCache,
        prefetchFaqSuggestions: prefetchFaqSuggestions
    };
})(window);
