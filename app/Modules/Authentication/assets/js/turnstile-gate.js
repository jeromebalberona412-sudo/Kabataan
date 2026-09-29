/**
 * Shared Cloudflare Turnstile challenge for Kabataan auth forms.
 * Same flow as SK Federations / SK Officials: renders a fresh widget only while
 * the modal is visible, resolves the token once, then closes so the caller continues.
 */
(function () {
    'use strict';

    var widgetId = null;
    var rendered = false;
    var errorRetries = 0;
    var pending = null;
    var mountTimer = null;
    var watchdogTimer = null;
    var successHandled = false;
    var completing = false;

    // Cloudflare may never call back when its frame is blocked or the network drops mid-challenge.
    var WATCHDOG_MS = 30000;

    var MSG = {
        missingToken: 'Please complete the Cloudflare verification first.',
        failed: 'Cloudflare verification failed. Please try again.',
        timedOut: 'Cloudflare verification timed out. Please try again.',
        cancelled: 'Verification cancelled.',
        loadFailed: 'Cloudflare verification failed to load. Please check your connection and try again.',
        unsupported: 'This browser is not supported by Cloudflare verification. Please use an updated Chrome, Safari, Edge, or Firefox.',
        clientHint: 'If this keeps happening, turn off VPN, ad blockers, or browser device emulation, or try another browser.',
    };

    function config() {
        return document.getElementById('turnstile-gate-config');
    }

    function isEnabled() {
        var el = config();
        return Boolean(el && el.dataset.enabled === '1' && el.dataset.sitekey);
    }

    function siteKey() {
        var el = config();
        return el ? (el.dataset.sitekey || '') : '';
    }

    function modal() {
        return document.getElementById('turnstile-modal');
    }

    function isModalOpen() {
        var modalEl = modal();
        return Boolean(modalEl && modalEl.classList.contains('turnstile-modal-visible'));
    }

    function isSmallViewport() {
        return window.matchMedia('(max-width: 480px)').matches;
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function afterModalPaint(callback) {
        var delay = 80;
        if (isSmallViewport()) {
            delay = 520;
        } else if (isMobileViewport()) {
            delay = 400;
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setTimeout(callback, delay);
            });
        });
    }

    function waitForApi(maxWaitMs) {
        maxWaitMs = maxWaitMs || 10000;
        return new Promise(function (resolve, reject) {
            if (typeof window.turnstile !== 'undefined') {
                resolve();
                return;
            }
            var start = Date.now();
            var iv = setInterval(function () {
                if (typeof window.turnstile !== 'undefined') {
                    clearInterval(iv);
                    resolve();
                } else if (Date.now() - start > maxWaitMs) {
                    clearInterval(iv);
                    reject(new Error(MSG.loadFailed));
                }
            }, 100);
        });
    }

    function stopWatchdog() {
        if (watchdogTimer !== null) {
            clearTimeout(watchdogTimer);
            watchdogTimer = null;
        }
    }

    function startWatchdog() {
        stopWatchdog();
        watchdogTimer = setTimeout(function () {
            watchdogTimer = null;
            if (!isModalOpen() || !pending || successHandled || completing) {
                return;
            }
            console.warn('[Turnstile] no response from Cloudflare within ' + (WATCHDOG_MS / 1000) + 's');
            setError(MSG.timedOut, true, MSG.clientHint);
        }, WATCHDOG_MS);
    }

    function setError(message, withRetry, hint) {
        var modalEl = modal();
        if (!modalEl || !isModalOpen()) {
            return;
        }
        stopWatchdog();
        var errEl = modalEl.querySelector('.turnstile-modal-error');
        if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'turnstile-modal-error';
            errEl.setAttribute('role', 'alert');
            var body = modalEl.querySelector('.turnstile-modal-body');
            if (body) {
                body.appendChild(errEl);
            }
        }
        errEl.textContent = message;
        if (hint) {
            var hintEl = document.createElement('span');
            hintEl.className = 'turnstile-modal-error-hint';
            hintEl.textContent = hint;
            errEl.appendChild(hintEl);
        }
        if (withRetry) {
            var retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'turnstile-retry-btn';
            retryBtn.textContent = 'Try Again';
            retryBtn.addEventListener('click', retryWidget, { once: true });
            errEl.appendChild(retryBtn);
        }
        errEl.style.display = 'block';
    }

    function clearError() {
        var modalEl = modal();
        var errEl = modalEl ? modalEl.querySelector('.turnstile-modal-error') : null;
        if (errEl) {
            errEl.remove();
        }
    }

    function configErrorMessage(code) {
        if (code === '110200') {
            return 'Domain not authorized in Cloudflare Turnstile dashboard. Please add this domain in your Cloudflare widget settings.';
        }
        if (code === '110100' || code === '110110') {
            return 'Invalid Turnstile site key. Please check your configuration.';
        }
        return null;
    }

    function showModal() {
        var modalEl = modal();
        if (!modalEl) {
            return;
        }
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        modalEl.hidden = false;
        modalEl.removeAttribute('hidden');
        modalEl.classList.add('turnstile-modal-visible');
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        var modalEl = modal();
        if (!modalEl) {
            return;
        }
        modalEl.classList.remove('turnstile-modal-visible');
        modalEl.hidden = true;
        modalEl.setAttribute('hidden', '');
        document.body.style.overflow = '';
        clearError();
    }

    function recreateContainer() {
        var modalEl = modal();
        if (!modalEl) {
            return null;
        }
        var body = modalEl.querySelector('.turnstile-modal-body');
        if (!body) {
            return null;
        }
        var existing = body.querySelector('#turnstile-container');
        if (existing) {
            existing.remove();
        }
        var fresh = document.createElement('div');
        fresh.id = 'turnstile-container';
        body.insertBefore(fresh, body.firstChild);
        return fresh;
    }

    function clearWidget() {
        stopWatchdog();
        if (mountTimer !== null) {
            clearTimeout(mountTimer);
            mountTimer = null;
        }
        if (rendered && widgetId !== null && typeof window.turnstile !== 'undefined') {
            try {
                window.turnstile.remove(widgetId);
            } catch (err) {
                console.warn('[Turnstile] remove failed:', err);
            }
        }
        widgetId = null;
        rendered = false;
    }

    function renderWidget() {
        if (rendered || !isModalOpen()) {
            return;
        }

        var mount = recreateContainer();
        var key = siteKey();
        if (!mount || !key || typeof window.turnstile === 'undefined') {
            throw new Error('Verification config missing. Please refresh the page.');
        }

        widgetId = window.turnstile.render(mount, {
            sitekey: key,
            theme: 'light',
            size: 'normal',
            retry: 'never',
            'refresh-expired': 'manual',
            callback: onSuccess,
            'error-callback': onError,
            'expired-callback': onExpired,
            'timeout-callback': onTimeout,
            'unsupported-callback': onUnsupported,
            'before-interactive-callback': stopWatchdog,
            'after-interactive-callback': startWatchdog,
        });
        rendered = true;
        startWatchdog();
    }

    function safeRender() {
        try {
            renderWidget();
        } catch (err) {
            console.warn('[Turnstile] render failed:', err);
            clearWidget();
            setError(MSG.failed, true);
        }
    }

    function mountWidget() {
        if (!isModalOpen()) {
            return;
        }

        clearWidget();

        waitForApi(10000).then(function () {
            if (!isModalOpen() || !pending) {
                return;
            }
            afterModalPaint(function () {
                if (isModalOpen() && pending) {
                    safeRender();
                }
            });
        }).catch(function () {
            setError(MSG.loadFailed, true, MSG.clientHint);
        });
    }

    // reset() re-runs the challenge inside the same iframe; removing and re-rendering
    // a widget whose challenge is still executing is itself a source of 300xxx errors.
    function resetWidget() {
        if (!isModalOpen() || !pending) {
            return;
        }
        clearError();
        if (rendered && widgetId !== null && typeof window.turnstile !== 'undefined') {
            try {
                window.turnstile.reset(widgetId);
                startWatchdog();
                return;
            } catch (err) {
                console.warn('[Turnstile] reset failed, re-rendering:', err);
            }
        }
        mountWidget();
    }

    function retryWidget() {
        errorRetries = 0;
        resetWidget();
    }

    function scheduleReset(delayMs) {
        if (mountTimer !== null) {
            clearTimeout(mountTimer);
        }
        mountTimer = setTimeout(function () {
            mountTimer = null;
            resetWidget();
        }, delayMs);
    }

    function preloadTurnstileApi() {
        if (!isEnabled()) {
            return;
        }
        waitForApi(15000).catch(function () {
            // mountWidget() will surface the error when the modal opens.
        });
    }

    function rejectPending(message) {
        if (completing) {
            return;
        }
        if (pending && pending.reject) {
            pending.reject(new Error(message || MSG.cancelled));
        }
        pending = null;
        successHandled = false;
        hideModal();
        clearWidget();
    }

    function onSuccess(token) {
        if (successHandled || completing || !pending) {
            return;
        }
        if (!token || typeof token !== 'string' || token.trim() === '') {
            setError(MSG.missingToken, true);
            return;
        }

        successHandled = true;
        completing = true;
        stopWatchdog();

        var resolve = pending.resolve;
        pending = null;

        if (resolve) {
            resolve(token);
        }

        hideModal();

        window.setTimeout(function () {
            clearWidget();
            completing = false;
        }, 120);
    }

    function onError(errorCode) {
        if (!isModalOpen() || successHandled || completing) {
            return true;
        }

        var code = String(errorCode || '');
        console.warn('[Turnstile] error:', code);
        stopWatchdog();
        clearError();

        var configMessage = configErrorMessage(code);
        if (configMessage) {
            clearWidget();
            setError(configMessage, false);
            return true;
        }

        // One silent reset covers transient failures; repeated 300xxx/600xxx means the
        // visitor's browser environment is being rejected, so hand control to the user.
        if (errorRetries < 1) {
            errorRetries += 1;
            scheduleReset(800);
            return true;
        }

        var family = code.slice(0, 3);
        var clientSide = family === '300' || family === '600' || family === '200';
        setError(MSG.failed, true, clientSide ? MSG.clientHint : null);
        return true;
    }

    function onExpired() {
        if (!isModalOpen() || successHandled || completing) {
            return;
        }
        resetWidget();
    }

    function onTimeout() {
        if (!isModalOpen() || successHandled || completing) {
            return;
        }
        console.warn('[Turnstile] interactive challenge timed out');
        setError(MSG.timedOut, true);
    }

    function onUnsupported() {
        console.warn('[Turnstile] browser not supported');
        setError(MSG.unsupported, false);
    }

    function challenge() {
        if (!isEnabled()) {
            return Promise.resolve('');
        }

        if (pending && !completing) {
            rejectPending(MSG.cancelled);
        }

        return new Promise(function (resolve, reject) {
            pending = { resolve: resolve, reject: reject };
            errorRetries = 0;
            successHandled = false;
            completing = false;
            showModal();
            clearError();
            mountWidget();
        });
    }

    function challengeIfRequired(required) {
        if (!isEnabled() || !required) {
            return Promise.resolve('');
        }
        return challenge();
    }

    function injectToken(form, token) {
        if (!form) {
            return;
        }
        form.querySelectorAll('input[name="cf-turnstile-response"]').forEach(function (el) {
            el.remove();
        });
        if (!token) {
            return;
        }
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'cf-turnstile-response';
        hidden.value = token;
        form.appendChild(hidden);
    }

    function submitForm(form) {
        return challenge().then(function (token) {
            if (isEnabled() && (!token || String(token).trim() === '')) {
                return Promise.reject(new Error(MSG.missingToken));
            }
            injectToken(form, token);
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    function submitFormIfRequired(form) {
        var required = Boolean(form && form.getAttribute('data-turnstile-required') === '1');
        if (!isEnabled() || !required) {
            injectToken(form, '');
            HTMLFormElement.prototype.submit.call(form);
            return Promise.resolve();
        }
        return challenge().then(function (token) {
            injectToken(form, token);
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    function hasValidToken(form) {
        if (!form) {
            return false;
        }
        var field = form.querySelector('input[name="cf-turnstile-response"]');
        return Boolean(field && String(field.value || '').trim() !== '');
    }

    function bindClose() {
        var closeBtn = document.getElementById('turnstile-close-btn');
        var cancelBtn = document.getElementById('turnstile-cancel-btn');
        var backdrop = document.getElementById('turnstile-modal-backdrop');
        function onClose() {
            if (completing) {
                return;
            }
            rejectPending(MSG.cancelled);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', onClose);
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', onClose);
        }
        if (backdrop) {
            backdrop.addEventListener('click', onClose);
        }
        var card = document.querySelector('.turnstile-modal-card');
        if (card) {
            card.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isModalOpen()) {
                onClose();
            }
        });
    }

    bindClose();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preloadTurnstileApi);
    } else {
        preloadTurnstileApi();
    }

    window.KabataanTurnstileGate = {
        isEnabled: isEnabled,
        isOpen: isModalOpen,
        challenge: challenge,
        challengeIfRequired: challengeIfRequired,
        cancel: function () {
            rejectPending(MSG.cancelled);
        },
        injectToken: injectToken,
        submitForm: submitForm,
        submitFormIfRequired: submitFormIfRequired,
        setFormRequired: function (form, required) {
            if (!form) {
                return;
            }
            form.setAttribute('data-turnstile-required', required ? '1' : '0');
        },
        hasValidToken: hasValidToken,
        messages: MSG,
    };

    window.kabataanTurnstileChallenge = function () {
        if (!isEnabled()) {
            return Promise.resolve('');
        }

        return new Promise(function (resolve, reject) {
            var started = Date.now();
            var wait = function () {
                if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                    window.KabataanTurnstileGate.challenge().then(resolve).catch(reject);
                    return;
                }
                if (Date.now() - started > 8000) {
                    reject(new Error(MSG.loadFailed));
                    return;
                }
                window.setTimeout(wait, 50);
            };
            wait();
        });
    };

    window.kabataanTurnstileChallengeIfRequired = function (required) {
        if (!isEnabled() || !required) {
            return Promise.resolve('');
        }
        return window.kabataanTurnstileChallenge();
    };

    window.kabataanTurnstileSubmitForm = function (form) {
        if (form && form.hasAttribute('data-turnstile-required')) {
            return window.KabataanTurnstileGate.submitFormIfRequired(form);
        }
        return window.KabataanTurnstileGate.submitForm(form);
    };
}());
