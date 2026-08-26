/**
 * Kabataan inactivity session timeout UX + AJAX 401 handling.
 * Server-side SessionTimeout middleware remains authoritative.
 */
(function () {
    'use strict';

    if (window.__kabataanSessionTimeoutInit) {
        return;
    }
    window.__kabataanSessionTimeoutInit = true;

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function signInUrl() {
        return window.kabataanSignInRoute || '/sign-in';
    }

    function redirectToSignIn() {
        window.location.replace(signInUrl());
    }

    function initFetchGuard() {
        if (window.__kabataanSessionFetchPatched || typeof window.fetch !== 'function') {
            return;
        }
        window.__kabataanSessionFetchPatched = true;

        var originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            return originalFetch(input, init).then(function (response) {
                if (response.status !== 401) {
                    return response;
                }

                var requestUrl = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                if (requestUrl && (requestUrl.indexOf('/sign-in') !== -1 || requestUrl.indexOf('/login') !== -1)) {
                    return response;
                }

                return response.clone().json().then(function (payload) {
                    if (payload && payload.session_expired) {
                        redirectToSignIn();
                    }
                    return response;
                }).catch(function () {
                    return response;
                });
            });
        };
    }

    function forceServerSessionCheck() {
        var continueUrl = window.kabataanSessionContinueRoute || '/session/continue';
        fetch(continueUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken() || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).catch(function () {
            // Ignore network errors; redirect anyway.
        }).finally(function () {
            redirectToSignIn();
        });
    }

    function initWarning() {
        var config = window.kabataanSessionTimeoutConfig || {};
        var timeoutMinutes = Number(config.timeoutMinutes || 0);
        var warningMinutes = Number(config.warningMinutes || 0);
        var lastActivityAt = Number(config.lastActivityAt || 0);
        var modal = document.getElementById('kabataanSessionTimeoutModal');

        if (!modal || !timeoutMinutes || warningMinutes <= 0 || warningMinutes >= timeoutMinutes || !lastActivityAt) {
            return;
        }

        var warningTimer = null;
        var expireTimer = null;
        var warningShown = false;
        var continueBtn = document.getElementById('kabataanSessionTimeoutContinueBtn');
        var logoutBtn = document.getElementById('kabataanSessionTimeoutLogoutBtn');
        var messageEl = document.getElementById('kabataanSessionTimeoutMessage');

        function clearTimers() {
            if (warningTimer) {
                clearTimeout(warningTimer);
                warningTimer = null;
            }
            if (expireTimer) {
                clearTimeout(expireTimer);
                expireTimer = null;
            }
        }

        function hideWarning() {
            warningShown = false;
            modal.hidden = true;
            document.body.style.overflow = '';
        }

        function showWarning(secondsRemaining) {
            if (warningShown) {
                return;
            }
            warningShown = true;
            var minutesLeft = Math.max(1, Math.ceil(secondsRemaining / 60));
            if (messageEl) {
                messageEl.textContent = 'Your session will expire in ' + minutesLeft + ' minute' + (minutesLeft === 1 ? '' : 's') + ' due to inactivity.';
            }
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function scheduleFrom(activityTimestamp) {
            clearTimers();
            hideWarning();

            var nowSec = Math.floor(Date.now() / 1000);
            var timeoutAt = activityTimestamp + (timeoutMinutes * 60);
            var warnAt = timeoutAt - (warningMinutes * 60);
            var msUntilWarn = (warnAt - nowSec) * 1000;
            var msUntilExpire = (timeoutAt - nowSec) * 1000;

            if (msUntilExpire <= 0) {
                forceServerSessionCheck();
                return;
            }

            if (msUntilWarn <= 0) {
                showWarning(Math.max(1, Math.floor(msUntilExpire / 1000)));
            } else {
                warningTimer = setTimeout(function () {
                    showWarning(warningMinutes * 60);
                }, msUntilWarn);
            }

            expireTimer = setTimeout(function () {
                forceServerSessionCheck();
            }, msUntilExpire);
        }

        function continueSession() {
            var continueUrl = window.kabataanSessionContinueRoute || '/session/continue';
            if (continueBtn) {
                continueBtn.disabled = true;
            }

            fetch(continueUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken() || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('continue_failed');
                }
                return response.json();
            }).then(function (data) {
                var refreshed = Number(data && data.last_activity_at ? data.last_activity_at : Math.floor(Date.now() / 1000));
                window.kabataanSessionTimeoutConfig.lastActivityAt = refreshed;
                scheduleFrom(refreshed);
                try {
                    localStorage.setItem('kabataan_session_activity', String(refreshed));
                } catch (e) {
                    // Ignore storage errors.
                }
            }).catch(function () {
                redirectToSignIn();
            }).finally(function () {
                if (continueBtn) {
                    continueBtn.disabled = false;
                }
            });
        }

        if (continueBtn) {
            continueBtn.addEventListener('click', continueSession);
        }
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
                var form = document.querySelector('.kabataan-header form[action*="logout"], .kkpu-lock-bar form[action*="logout"]');
                if (form) {
                    form.submit();
                    return;
                }
                redirectToSignIn();
            });
        }

        window.addEventListener('storage', function (event) {
            if (event.key !== 'kabataan_session_activity' || !event.newValue) {
                return;
            }
            var synced = Number(event.newValue);
            if (!synced) {
                return;
            }
            window.kabataanSessionTimeoutConfig.lastActivityAt = synced;
            scheduleFrom(synced);
        });

        scheduleFrom(lastActivityAt);
    }

    function boot() {
        initFetchGuard();
        initWarning();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
