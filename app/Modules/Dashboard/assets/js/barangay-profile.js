/**
 * Barangay community feed (stalk page)
 */
(function () {
    'use strict';

    const cfg = window.BarangayProfileConfig || {};
    const canEngage = Boolean(cfg.canEngage);
    const inflight = new Set();
    const LIKE_THUMB_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>';
    const REACTION_EMOJI = { like: '👍', love: '❤️', haha: '😂', wow: '😮', sad: '😢', angry: '😡' };
    const REACTION_LABEL = { like: 'Like', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad', angry: 'Angry' };
    const REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
    let reactionAudio = null;
    let pollTimer = null;
    let pollInFlight = false;
    const reactionSeqByPost = new Map();

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    function isTouchDevice() {
        return window.matchMedia('(hover: none), (pointer: coarse)').matches;
    }

    function cachedPost(postId) {
        return (cfg.posts || []).find((item) => Number(item.id) === Number(postId)) || null;
    }

    function mergeCachedPost(fresh) {
        if (!fresh || !fresh.id) {
            return null;
        }
        if (!Array.isArray(cfg.posts)) {
            cfg.posts = [];
        }
        const merged = Object.assign({}, cachedPost(fresh.id) || {}, fresh, { comments_loaded: true });
        const idx = cfg.posts.findIndex((item) => Number(item.id) === Number(fresh.id));
        if (idx >= 0) {
            cfg.posts[idx] = merged;
        } else {
            cfg.posts.push(merged);
        }
        return merged;
    }

    function commentPreviewIsOpen(postId) {
        const shell = document.getElementById('commentPreviewShell');
        if (!shell?.classList.contains('is-open')) {
            return false;
        }
        if (postId == null) {
            return true;
        }
        return Number(shell.dataset.postId || 0) === Number(postId);
    }

    function commentComposerIsFocused() {
        return Boolean(document.querySelector('#commentPreviewShell .cp-composer-input:focus, #commentPreviewShell [data-reply-input]:focus'));
    }

    function ensureReactionAudio() {
        if (!reactionAudio) {
            reactionAudio = new Audio(REACTION_SOUND_URL);
            reactionAudio.preload = 'auto';
            reactionAudio.volume = 0.75;
            try { reactionAudio.load(); } catch (_) {}
        }
        return reactionAudio;
    }

    function playFeedReactionSound() {
        const audio = ensureReactionAudio();
        audio.muted = false;
        audio.volume = 0.75;
        try {
            if (audio.readyState >= 2) {
                try { audio.currentTime = 0; } catch (_) {}
            }
            const playPromise = audio.play();
            if (playPromise && playPromise.catch) {
                playPromise.catch(function () {
                    const oneShot = new Audio(REACTION_SOUND_URL);
                    oneShot.volume = 0.75;
                    oneShot.play().catch(function () {});
                });
            }
        } catch (_) {
            try {
                const fallback = new Audio(REACTION_SOUND_URL);
                fallback.volume = 0.75;
                fallback.play().catch(function () {});
            } catch (err) {}
        }
    }

    window.playFeedReactionSound = playFeedReactionSound;
    ensureReactionAudio();

    function resolveNextReaction(currentType, requestedType) {
        const requested = requestedType || 'like';
        const current = currentType || '';
        if (current === requested) {
            return { liked: false, type: '' };
        }
        return { liked: true, type: requested };
    }

    document.querySelectorAll('.feed-tab[data-feed-filter]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.feed-tab[data-feed-filter]').forEach((tab) => {
                tab.classList.remove('active');
            });
            btn.classList.add('active');
            const filter = btn.dataset.feedFilter || 'all';
            let visible = 0;
            document.querySelectorAll('#brgyFeed .post-card').forEach((card) => {
                const show = filter === 'all' || card.dataset.postType === filter;
                card.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });
            const empty = document.getElementById('brgyFeedEmptyFilter');
            if (empty) {
                empty.hidden = visible > 0;
            }
        });
    });

    async function fetchPostWithComments(postId) {
        const res = await fetch('/api/feed/' + postId, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            return null;
        }
        return res.json();
    }

    async function refreshOpenCommentPreview() {
        const shell = document.getElementById('commentPreviewShell');
        const postId = Number(shell?.dataset.postId || 0);
        if (!commentPreviewIsOpen(postId) || commentComposerIsFocused()) {
            return;
        }
        try {
            const fresh = await fetchPostWithComments(postId);
            const merged = mergeCachedPost(fresh);
            if (merged && commentPreviewIsOpen(postId) && typeof window.openCommentPreview === 'function') {
                window.openCommentPreview(merged, { skipUrl: true, preserveScroll: true });
            }
        } catch (_) {
            // keep current preview
        }
    }

    async function openComments(id) {
        const postId = Number(id);
        const cached = cachedPost(postId);
        if (cached && typeof window.openCommentPreview === 'function') {
            window.openCommentPreview(cached);
        }
        try {
            const fresh = await fetchPostWithComments(postId);
            const merged = mergeCachedPost(fresh);
            if (!merged || typeof window.openCommentPreview !== 'function') {
                return;
            }
            if (commentPreviewIsOpen(postId)) {
                window.openCommentPreview(merged, { skipUrl: true, preserveScroll: Boolean(cached) });
            } else if (!cached) {
                window.openCommentPreview(merged);
            }
        } catch (_) {
            // keep embedded post
        }
    }

    window.openComments = openComments;

    document.getElementById('brgyFeed')?.addEventListener('click', (event) => {
        const reactionsOpener = event.target.closest('[data-open-reactions]');
        if (reactionsOpener) {
            event.preventDefault();
            if (typeof window.openPostReactionViewer === 'function') {
                window.openPostReactionViewer(reactionsOpener.getAttribute('data-open-reactions'));
            }
            return;
        }
        const opener = event.target.closest('[data-open-comments]');
        if (!opener) {
            return;
        }
        event.preventDefault();
        openComments(opener.getAttribute('data-open-comments'));
    });

    document.getElementById('brgyFeed')?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        const opener = event.target.closest('[data-open-comments]');
        if (!opener) {
            return;
        }
        event.preventDefault();
        openComments(opener.getAttribute('data-open-comments'));
    });

    function paintCommentCount(card, count) {
        const total = Number(count || 0);
        const summaryBtn = card.querySelector('.reaction-summary-comments');
        if (summaryBtn) {
            summaryBtn.textContent = total + (total === 1 ? ' comment' : ' comments');
            summaryBtn.hidden = total < 1;
        }
        const commentLabel = card.querySelector('.comment-btn span');
        if (commentLabel) {
            const base = canEngage ? 'Comment' : 'View comments';
            commentLabel.textContent = total > 0 ? base + ' (' + total + ')' : base;
        }
    }

    function paintPostReaction(postId, liked, nextType, count) {
        const btn = document.getElementById('feed-like-btn-' + postId);
        if (btn) {
            btn.classList.toggle('liked', Boolean(liked));
            btn.dataset.type = nextType || '';
            const icon = btn.querySelector('.reaction-icon');
            if (icon) {
                icon.innerHTML = nextType
                    ? `<span class="reaction-current">${REACTION_EMOJI[nextType] || ''}</span>`
                    : LIKE_THUMB_SVG;
            }
            const label = btn.querySelector('.reaction-label');
            if (label) {
                label.textContent = REACTION_LABEL[nextType] || 'Like';
            }
            btn.closest('.reaction-wrap')?.querySelectorAll('.reaction-option').forEach((opt) => {
                opt.classList.toggle('is-active', Boolean(nextType) && opt.dataset.type === nextType);
            });
        }

        const card = document.querySelector(`#brgyFeed .post-card[data-post-id="${postId}"]`);
        const total = card?.querySelector('[data-like-count]');
        if (total) {
            total.textContent = String(count || 0);
            total.closest('.reaction-summary-left')?.toggleAttribute('hidden', Number(count || 0) < 1);
        }

        const cached = cachedPost(postId);
        if (cached) {
            cached.liked = Boolean(liked);
            cached.reaction_type = nextType || null;
            cached.likes = Number(count || 0);
        }
    }

    async function setReaction(postId, requestedType) {
        const key = String(postId);
        if (inflight.has(key)) {
            return;
        }
        const btn = document.getElementById('feed-like-btn-' + postId);
        const current = btn?.dataset.type || '';
        const next = resolveNextReaction(current, requestedType);
        const cached = cachedPost(postId);
        let count = Number(cached?.likes || 0);
        if (current && !next.liked) {
            count = Math.max(0, count - 1);
        } else if (!current && next.liked) {
            count += 1;
        }
        if (next.liked) {
            playFeedReactionSound();
        }
        paintPostReaction(postId, next.liked, next.type, count);

        inflight.add(key);
        const seq = (reactionSeqByPost.get(key) || 0) + 1;
        reactionSeqByPost.set(key, seq);
        try {
            const res = await fetch('/api/feed/' + postId + '/react', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ reaction_type: requestedType || 'like', client_seq: seq }),
            });
            if (!res.ok || reactionSeqByPost.get(key) !== seq) {
                return;
            }
            const data = await res.json();
            paintPostReaction(
                postId,
                Boolean(data.liked),
                data.liked ? (data.reaction_type || 'like') : '',
                data.count ?? data.likes
            );
        } catch (_) {
            // keep optimistic state
        } finally {
            inflight.delete(key);
        }
    }

    function bindReactionControls(root) {
        if (!root || !canEngage) {
            return;
        }
        root.querySelectorAll('.reaction-wrap[data-target="post"]').forEach((wrap) => {
            if (wrap.dataset.bound === '1') {
                return;
            }
            wrap.dataset.bound = '1';
            const btn = wrap.querySelector('.reaction-btn');
            const postId = Number(wrap.dataset.postId);
            let hideTimer = null;
            let pressTimer = null;
            let didLongPress = false;

            wrap.addEventListener('mouseenter', () => {
                if (isTouchDevice()) {
                    return;
                }
                clearTimeout(hideTimer);
                wrap.classList.add('is-open');
            });
            wrap.addEventListener('mouseleave', () => {
                if (isTouchDevice()) {
                    return;
                }
                hideTimer = setTimeout(() => wrap.classList.remove('is-open'), 80);
            });
            btn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (didLongPress) {
                    didLongPress = false;
                    return;
                }
                if (isTouchDevice() && wrap.classList.contains('is-open')) {
                    wrap.classList.remove('is-open');
                    return;
                }
                wrap.classList.remove('is-open');
                setReaction(postId, btn.dataset.type || 'like');
            });
            btn?.addEventListener('touchstart', () => {
                didLongPress = false;
                clearTimeout(pressTimer);
                pressTimer = setTimeout(() => {
                    didLongPress = true;
                    document.querySelectorAll('.reaction-wrap.is-open').forEach((other) => {
                        if (other !== wrap) {
                            other.classList.remove('is-open');
                        }
                    });
                    wrap.classList.add('is-open');
                }, 180);
            }, { passive: true });
            const cancelPress = () => clearTimeout(pressTimer);
            btn?.addEventListener('touchend', cancelPress);
            btn?.addEventListener('touchcancel', cancelPress);
            wrap.querySelectorAll('.reaction-option').forEach((opt) => {
                opt.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    wrap.classList.remove('is-open');
                    setReaction(postId, opt.dataset.type || 'like');
                });
            });
        });
    }

    function applyPolledPost(post) {
        const postId = Number(post.id);
        if (!postId || inflight.has(String(postId))) {
            return;
        }
        const card = document.querySelector(`#brgyFeed .post-card[data-post-id="${postId}"]`);
        if (!card) {
            return;
        }
        const liked = Boolean(post.liked);
        const type = liked ? (post.reaction_type || 'like') : '';
        paintPostReaction(postId, liked, type, post.likes ?? 0);
        paintCommentCount(card, post.comment_count ?? 0);
        const cached = cachedPost(postId);
        if (cached) {
            cached.liked = liked;
            cached.reaction_type = type || null;
            cached.likes = Number(post.likes || 0);
            cached.comment_count = Number(post.comment_count || 0);
        }
    }

    async function pollFeedUpdates() {
        const barangayId = Number(cfg.barangayId || 0);
        if (!barangayId || document.hidden || pollInFlight) {
            return;
        }
        pollInFlight = true;
        try {
            if (commentPreviewIsOpen()) {
                await refreshOpenCommentPreview();
            }
            const res = await fetch('/api/feed?barangay_id=' + barangayId, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            (data.data || []).forEach(applyPolledPost);
            const countEl = document.querySelector('.brgy-stat-item strong');
            if (countEl && Array.isArray(data.data)) {
                countEl.textContent = String(data.data.length);
            }
        } catch (_) {
            // keep current cards
        } finally {
            pollInFlight = false;
        }
    }

    function startFeedPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }
        const delay = Number(window.CommunityFeedConfig?.feedPollMs || 5000);
        const tick = async function () {
            await pollFeedUpdates();
            pollTimer = setTimeout(tick, delay);
        };
        pollTimer = setTimeout(tick, delay);
    }

    bindReactionControls(document.getElementById('brgyFeed'));
    document.addEventListener('click', (event) => {
        if (event.target.closest('.reaction-wrap')) {
            return;
        }
        document.querySelectorAll('#brgyFeed .reaction-wrap.is-open').forEach((wrap) => {
            wrap.classList.remove('is-open');
        });
    });
    startFeedPolling();

    function initBfpSidebarDrawer() {
        const fab = document.getElementById('bfpSidebarFab');
        const sidebar = document.getElementById('bfpSidebar');
        const backdrop = document.getElementById('bfpSidebarBackdrop');
        const closeBtn = document.getElementById('bfpSidebarClose');
        if (!fab || !sidebar || !backdrop) return;

        const openDrawer = () => {
            sidebar.classList.add('drawer-open');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        const closeDrawer = () => {
            sidebar.classList.remove('drawer-open');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
        };

        fab.addEventListener('click', openDrawer);
        closeBtn?.addEventListener('click', closeDrawer);
        backdrop.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });
    }

    initBfpSidebarDrawer();
})();
