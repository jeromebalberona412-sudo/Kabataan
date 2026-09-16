document.addEventListener('DOMContentLoaded', function () {
    const COOLDOWN_SECONDS = 60;
    const POLL_INTERVAL_MS = 3000;
    const BTN_LABEL = 'Resend Verification';

    const verifySection = document.getElementById('ceVerifySection');
    const statusUrl = verifySection?.dataset.statusUrl || '';
    const resendUrl = verifySection?.dataset.resendUrl || document.getElementById('ceResendForm')?.action || '';
    const signInUrl = verifySection?.dataset.signinUrl || '/sign-in';
    const dashboardUrl = verifySection?.dataset.dashboardUrl || '/dashboard';
    const pendingEmail = document.getElementById('cePendingEmail')?.textContent?.trim() || 'default';
    const cooldownKey = `kabataan_email_change_resend_${pendingEmail}`;

    const timerElement = document.getElementById('ceTimer');
    const timerCountElement = document.getElementById('ceTimerCount');
    const resendBtn = document.getElementById('ceResendBtn');
    const resendForm = document.getElementById('ceResendForm');
    const statusTitle = document.getElementById('ceStatusTitle');
    const statusSub = document.getElementById('ceStatusSub');
    const statusBadge = document.getElementById('ceStatusBadge');
    const infoBox = document.getElementById('ceInfoBox');

    let timerInterval = null;
    let confirmationHandled = false;
    let resendInFlight = false;
    const serverCooldown = Number(window.ceResendCooldown || 0);

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || resendForm?.querySelector('input[name="_token"]')?.value
            || '';
    }

    function jsonHeaders() {
        return {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    function formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function clearCooldown() {
        localStorage.removeItem(cooldownKey);
    }

    function setCooldownExpiry(seconds) {
        localStorage.setItem(cooldownKey, String(Date.now() + Math.max(1, seconds || COOLDOWN_SECONDS) * 1000));
    }

    function storedRemaining() {
        const expiry = Number.parseInt(localStorage.getItem(cooldownKey) || '0', 10);
        return expiry > Date.now() ? Math.max(0, Math.ceil((expiry - Date.now()) / 1000)) : 0;
    }

    function updateTimerDisplay(seconds) {
        if (timerCountElement) timerCountElement.textContent = formatCountdown(seconds);
    }

    function timerExpired() {
        if (timerElement) timerElement.style.display = 'none';
        if (resendBtn && !resendInFlight) {
            resendBtn.disabled = false;
            resendBtn.textContent = BTN_LABEL;
        }
        clearCooldown();
    }

    function startTimer(seconds) {
        const remainingStart = Math.max(0, seconds);
        if (remainingStart <= 0) {
            timerExpired();
            return;
        }

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = BTN_LABEL;
        }
        if (timerElement) timerElement.style.display = 'block';
        updateTimerDisplay(remainingStart);

        if (timerInterval) clearInterval(timerInterval);

        timerInterval = setInterval(function () {
            const remaining = storedRemaining();
            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                timerExpired();
            } else {
                updateTimerDisplay(remaining);
            }
        }, 1000);
    }

    function bootstrapTimer() {
        let remaining = storedRemaining();

        if (remaining <= 0 && serverCooldown > 0) {
            remaining = serverCooldown;
            setCooldownExpiry(remaining);
        }

        if (remaining > 0) {
            startTimer(remaining);
        } else {
            timerExpired();
        }
    }

    function markCompletedUI(message) {
        confirmationHandled = true;
        if (statusTitle) statusTitle.textContent = 'Email Changed';
        if (statusSub) statusSub.textContent = message || 'Email changed successfully.';
        if (statusBadge) {
            statusBadge.textContent = 'Completed';
            statusBadge.style.background = '#dcfce7';
            statusBadge.style.color = '#166534';
        }
        if (infoBox) {
            infoBox.textContent = message || 'Email changed successfully.';
        }
    }

    function isLoggedOutResponse(response, payload) {
        if (!response) return false;
        if (response.status === 401 || response.status === 419) return true;
        if (response.redirected) return true;
        const contentType = response.headers.get('content-type') || '';
        if (contentType && !contentType.includes('application/json')) return true;
        return payload && (payload.state === 'completed' || payload.state === 'confirmed');
    }

    function redirectAfterComplete(message, redirectUrl) {
        clearCooldown();
        if (timerInterval) clearInterval(timerInterval);
        markCompletedUI(message);
        setTimeout(function () {
            window.location.replace(redirectUrl || dashboardUrl || signInUrl);
        }, 900);
    }

    function showLiveError(message) {
        const liveError = document.getElementById('ceVerifyLiveError');
        if (!liveError || !message) return;

        liveError.innerHTML = `
            <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span></span>
        `;
        const span = liveError.querySelector('span');
        if (span) span.textContent = message;
        liveError.hidden = false;
        liveError.style.display = 'flex';
        liveError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideLiveError() {
        const liveError = document.getElementById('ceVerifyLiveError');
        if (!liveError) return;
        liveError.hidden = true;
        liveError.style.display = 'none';
        liveError.textContent = '';
    }

    function redirectToChangeEmail(message, redirectUrl, isInvalidEmail) {
        clearCooldown();
        if (timerInterval) clearInterval(timerInterval);

        if (message && isInvalidEmail) {
            try {
                sessionStorage.setItem('kabataan_change_email_error', message);
            } catch (e) {
                // ignore
            }
            showLiveError(message);
            setTimeout(function () {
                window.location.replace(redirectUrl || '/change-email');
            }, 1600);
            return;
        }

        window.location.replace(redirectUrl || '/change-email');
    }

    async function parseJson(response) {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    }

    async function checkConfirmationStatus() {
        if (confirmationHandled || !statusUrl) return;

        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await parseJson(response);

            if (isLoggedOutResponse(response, payload) || payload.state === 'completed' || payload.state === 'confirmed') {
                redirectAfterComplete(
                    payload.message || 'Email changed successfully.',
                    payload.redirect || (response.status === 401 || response.status === 419 ? signInUrl : dashboardUrl),
                );
                return;
            }

            if (!response.ok) {
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS + 2000);
                return;
            }

            if (payload.state === 'pending') {
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS);
                return;
            }

            if (payload.state === 'cancelled') {
                confirmationHandled = true;
                redirectToChangeEmail(
                    payload.message || 'Email change request is no longer active.',
                    payload.redirect || '/change-email',
                    Boolean(payload.invalid_email),
                );
            }
        } catch (error) {
            setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS + 2000);
        }
    }

    async function submitResend() {
        if (confirmationHandled || resendInFlight || storedRemaining() > 0 || !resendUrl) {
            return;
        }

        resendInFlight = true;
        hideLiveError();
        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending…';
        }

        try {
            const response = await fetch(resendUrl, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ _token: csrfToken() }),
            });

            const payload = await parseJson(response);

            if (isLoggedOutResponse(response, payload) || payload.state === 'completed' || payload.state === 'confirmed') {
                redirectAfterComplete(
                    payload.message || 'Email changed successfully.',
                    payload.redirect || (response.status === 401 || response.status === 419 ? signInUrl : dashboardUrl),
                );
                return;
            }

            if (!response.ok || payload.ok === false) {
                resendInFlight = false;
                if (resendBtn) {
                    resendBtn.textContent = BTN_LABEL;
                }

                const message = payload.message || 'Unable to resend verification email. Please try again.';
                if (/no pending email change/i.test(message)) {
                    redirectToChangeEmail(message, '/change-email', false);
                    return;
                }
                showLiveError(message);

                const cooldown = Number(payload.resend_cooldown || payload.cooldown || 0);
                if (cooldown > 0) {
                    setCooldownExpiry(cooldown);
                    startTimer(cooldown);
                } else {
                    timerExpired();
                }
                return;
            }

            const cooldown = Number(payload.resend_cooldown || payload.cooldown || COOLDOWN_SECONDS);
            setCooldownExpiry(cooldown);
            resendInFlight = false;
            startTimer(cooldown);
            if (infoBox) {
                infoBox.textContent = payload.message || 'Verification email resent. Check your inbox.';
            }
        } catch (error) {
            resendInFlight = false;
            if (resendBtn) {
                resendBtn.textContent = BTN_LABEL;
                resendBtn.disabled = false;
            }
            showLiveError('Unable to resend verification email. Please try again.');
        }
    }

    bootstrapTimer();

    checkConfirmationStatus();

    if (resendBtn) {
        resendBtn.addEventListener('click', function (event) {
            event.preventDefault();
            submitResend();
        });
    }

    if (resendForm) {
        resendForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitResend();
        });
    }

    const cancelForm = document.getElementById('ceCancelForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function () {
            clearCooldown();
            if (timerInterval) clearInterval(timerInterval);
        });
    }
});
