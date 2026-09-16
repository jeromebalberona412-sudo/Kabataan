/**
 * Comment spam guard + 1k character limit helpers for Community Feed.
 */
(function () {
    const BURST_LIMIT = 3;
    const COMMENT_MAX_CHARS = 1000;
    const COMMENT_LIMIT_MSG = 'Comments and replies are limited to 1,000 characters.';
    const COOLDOWN_MSG = (seconds) => (
        `Too many comments. Please wait ${seconds} second(s) before commenting again.`
    );

    let cooldownUntil = 0;
    let timerId = null;
    const originalPlaceholders = new WeakMap();

    function nowSec() {
        return Math.floor(Date.now() / 1000);
    }

    function remaining() {
        return Math.max(0, cooldownUntil - nowSec());
    }

    function isCoolingDown() {
        return remaining() > 0;
    }

    function composerInputs() {
        return document.querySelectorAll(
            '#cpCommentInput, #editCommentBody, .comment-input, [data-reply-input]'
        );
    }

    function normalizeCommentText(text) {
        return String(text || '')
            .replace(/[\r\n\u2028\u2029]+/g, ' ')
            .replace(/[ \t\f\v]+/g, ' ')
            .trim();
    }

    function setInputsDisabled(disabled) {
        composerInputs().forEach((el) => {
            el.disabled = disabled;
            if (disabled) {
                el.setAttribute('aria-disabled', 'true');
            } else {
                el.removeAttribute('aria-disabled');
            }
        });
        document.querySelectorAll('#cpSendBtn, .send-comment-btn, [data-reply-send]').forEach((el) => {
            el.disabled = disabled;
        });
    }

    function updateCooldownPlaceholders(left) {
        composerInputs().forEach((el) => {
            if (!originalPlaceholders.has(el)) {
                originalPlaceholders.set(el, el.getAttribute('placeholder') || '');
            }
            if (left > 0) {
                el.setAttribute('placeholder', `Wait ${left}s before commenting again...`);
            } else {
                el.setAttribute('placeholder', originalPlaceholders.get(el) || '');
            }
        });
    }

    function notify(message, type) {
        if (typeof window.showFeedToast === 'function') {
            window.showFeedToast(message, type || 'error');
            return;
        }
        if (typeof window.notifyFeed === 'function') {
            window.notifyFeed(message, type || 'error');
            return;
        }
        if (typeof window.notifyPreview === 'function') {
            window.notifyPreview(message, type || 'error');
        }
    }

    function tick() {
        const left = remaining();
        updateCooldownPlaceholders(left);
        if (left <= 0) {
            stopTimer(true);
            return;
        }
    }

    function stopTimer(announceReady) {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
        cooldownUntil = 0;
        setInputsDisabled(false);
        updateCooldownPlaceholders(0);
        if (announceReady) {
            notify('You can comment again.', 'success');
        }
    }

    function startCooldown(seconds, options = {}) {
        const secs = Math.max(1, Number(seconds) || 60);
        const until = nowSec() + secs;
        if (until <= cooldownUntil && isCoolingDown()) {
            return;
        }
        cooldownUntil = until;
        setInputsDisabled(true);
        updateCooldownPlaceholders(secs);
        if (options.notify !== false && !options.silent) {
            notify(COOLDOWN_MSG(secs), 'error');
        }
        if (timerId) clearInterval(timerId);
        timerId = setInterval(tick, 1000);
    }

    function applyFromPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        const retry = Number(payload.retry_after ?? payload.rate_limit?.retry_after);
        const locked = Boolean(payload.locked ?? payload.rate_limit?.locked);
        if (locked && retry > 0) {
            startCooldown(retry);
        }
    }

    function assertCanComment() {
        const left = remaining();
        if (left > 0) {
            return COOLDOWN_MSG(left);
        }
        return null;
    }

    function assertBodyLength(text) {
        if (String(text || '').length > COMMENT_MAX_CHARS) {
            return COMMENT_LIMIT_MSG;
        }
        return null;
    }

    function applyNormalizedValue(el, next) {
        const cleaned = String(next || '')
            .replace(/[\r\n\u2028\u2029]+/g, ' ')
            .replace(/[ \t\f\v]+/g, ' ');
        const clipped = cleaned.slice(0, COMMENT_MAX_CHARS);
        if (el.value !== clipped) {
            const start = el.selectionStart;
            el.value = clipped;
            if (typeof start === 'number') {
                const pos = Math.min(start, clipped.length);
                try { el.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
            }
        }
        return clipped;
    }

    function bindLengthGuard(el) {
        if (!el || el.dataset.commentLenBound === '1') return;
        el.dataset.commentLenBound = '1';
        if (!el.getAttribute('maxlength')) {
            el.setAttribute('maxlength', String(COMMENT_MAX_CHARS));
        }

        el.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        el.addEventListener('beforeinput', (event) => {
            if (event.isComposing) return;
            const type = event.inputType || '';
            if (type.startsWith('delete') || type === 'historyUndo' || type === 'historyRedo') {
                return;
            }
            if (type === 'insertLineBreak' || type === 'insertParagraph') {
                event.preventDefault();
                return;
            }
            let insert = '';
            if (typeof event.data === 'string') {
                insert = event.data.replace(/[\r\n\u2028\u2029]+/g, ' ').replace(/[ \t\f\v]+/g, ' ');
            } else if (type === 'insertFromPaste') {
                return;
            } else {
                return;
            }

            const value = String(el.value || '');
            const start = el.selectionStart ?? value.length;
            const end = el.selectionEnd ?? start;
            const next = value.slice(0, start) + insert + value.slice(end);
            if (next.length > COMMENT_MAX_CHARS) {
                event.preventDefault();
                notify(COMMENT_LIMIT_MSG, 'error');
            }
        });

        el.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = event.clipboardData?.getData('text') ?? '';
            const cleaned = pasted
                .replace(/[\r\n\u2028\u2029]+/g, ' ')
                .replace(/[ \t\f\v]+/g, ' ');
            const value = String(el.value || '');
            const start = el.selectionStart ?? value.length;
            const end = el.selectionEnd ?? start;
            const next = (value.slice(0, start) + cleaned + value.slice(end)).slice(0, COMMENT_MAX_CHARS);
            el.value = next;
            const pos = Math.min(start + cleaned.length, next.length);
            try { el.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
            el.dispatchEvent(new Event('input', { bubbles: true }));
            if ((value.slice(0, start) + cleaned + value.slice(end)).length > COMMENT_MAX_CHARS) {
                notify(COMMENT_LIMIT_MSG, 'error');
            }
        });

        el.addEventListener('input', () => {
            applyNormalizedValue(el, el.value);
        });
    }

    function bindAllLengthGuards(root) {
        const scope = root || document;
        scope.querySelectorAll('#cpCommentInput, #editCommentBody, .comment-input, [data-reply-input]')
            .forEach((el) => bindLengthGuard(el));
        if (isCoolingDown()) {
            setInputsDisabled(true);
            updateCooldownPlaceholders(remaining());
        }
    }

    window.FeedCommentGuard = {
        BURST_LIMIT,
        COMMENT_MAX_CHARS,
        COMMENT_LIMIT_MSG,
        remaining,
        isCoolingDown,
        startCooldown,
        applyFromPayload,
        assertCanComment,
        assertBodyLength,
        normalizeCommentText,
        bindLengthGuard,
        bindAllLengthGuards,
        setInputsDisabled,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bindAllLengthGuards());
    } else {
        bindAllLengthGuards();
    }
})();
