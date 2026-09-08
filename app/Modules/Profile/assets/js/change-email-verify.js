document.addEventListener('DOMContentLoaded', function () {
    const COOLDOWN_SECONDS = 60;
    const POLL_INTERVAL_MS = 3000;

    const verifySection = document.getElementById('ceVerifySection');
    const statusUrl = verifySection?.dataset.statusUrl || '';
    const pendingEmail = document.getElementById('cePendingEmail')?.textContent?.trim() || 'default';
    const cooldownKey = `kabataan_email_change_resend_${pendingEmail}`;

    const timerElement = document.getElementById('ceTimer');
    const timerCountElement = document.getElementById('ceTimerCount');
    const resendBtn = document.getElementById('ceResendBtn');
    const resendForm = document.getElementById('ceResendForm');
    const statusTitle = document.getElementById('ceStatusTitle');
    const statusSub = document.getElementById('ceStatusSub');
    const statusBadge = document.getElementById('ceStatusBadge');

    let timerInterval = null;
    let confirmationHandled = false;
    const serverCooldown = Number(window.ceResendCooldown || 0);

    function formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function clearCooldown() {
        localStorage.removeItem(cooldownKey);
    }

    function setCooldownExpiry(seconds) {
        localStorage.setItem(cooldownKey, String(Date.now() + Math.max(1, seconds) * 1000));
    }

    function getRemainingSeconds() {
        const expiry = Number.parseInt(localStorage.getItem(cooldownKey) || '0', 10);
        if (expiry > Date.now()) {
            return Math.max(0, Math.ceil((expiry - Date.now()) / 1000));
        }

        return serverCooldown > 0 ? serverCooldown : 0;
    }

    function updateTimerDisplay(seconds) {
        if (timerCountElement) timerCountElement.textContent = formatCountdown(seconds);
    }

    function timerExpired() {
        if (timerElement) timerElement.style.display = 'none';
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend Verification';
        }
        clearCooldown();
    }

    function startTimer(seconds) {
        let remaining = seconds;
        if (remaining <= 0) {
            timerExpired();
            return;
        }

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend Verification';
        }
        if (timerElement) timerElement.style.display = 'block';
        updateTimerDisplay(remaining);

        if (timerInterval) clearInterval(timerInterval);

        timerInterval = setInterval(function () {
            remaining = getRemainingSeconds();
            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerExpired();
            } else {
                updateTimerDisplay(remaining);
            }
        }, 1000);
    }

    function bootstrapTimer() {
        let remaining = getRemainingSeconds();

        if (remaining <= 0 && serverCooldown > 0) {
            remaining = serverCooldown;
            setCooldownExpiry(remaining);
        }

        if (remaining > 0) {
            if (!localStorage.getItem(cooldownKey)) {
                setCooldownExpiry(remaining);
            }
            startTimer(remaining);
        } else {
            timerExpired();
        }
    }

    function markAwaitingPasswordUI(message, pendingEmail) {
        if (statusTitle) statusTitle.textContent = 'Email Verified!';
        if (statusSub) {
            statusSub.textContent = message || 'Set your new password on the other tab to finish the email change.';
        }
        if (statusBadge) {
            statusBadge.textContent = 'Awaiting password';
            statusBadge.style.background = '#fef3c7';
            statusBadge.style.color = '#92400e';
        }
        if (pendingEmail) {
            const pendingEmailVal = document.getElementById('cePendingEmailVal');
            if (pendingEmailVal) pendingEmailVal.textContent = pendingEmail;
        }
    }

    function markCompletedUI(message) {
        confirmationHandled = true;
        if (statusTitle) statusTitle.textContent = 'All Done!';
        if (statusSub) statusSub.textContent = message || 'Signing you out so you can log in with your new credentials.';
        if (statusBadge) {
            statusBadge.textContent = 'Completed';
            statusBadge.style.background = '#dcfce7';
            statusBadge.style.color = '#166534';
        }
    }

    function redirectToLogin(message, redirectUrl) {
        clearCooldown();
        if (timerInterval) clearInterval(timerInterval);
        markCompletedUI(message);
        setTimeout(function () {
            window.location.replace(redirectUrl || '/login');
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

    async function checkConfirmationStatus() {
        if (confirmationHandled || !statusUrl) return;

        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.status === 401 || response.status === 419) {
                return;
            }

            if (!response.ok) {
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS + 2000);
                return;
            }

            const payload = await response.json();

            if (payload.state === 'pending') {
                if (payload.resend_cooldown > 0) {
                    const localRemaining = getRemainingSeconds();
                    if (localRemaining <= 0) {
                        setCooldownExpiry(payload.resend_cooldown);
                        startTimer(payload.resend_cooldown);
                    }
                }
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS);
                return;
            }

            if (payload.state === 'awaiting_password') {
                markAwaitingPasswordUI(payload.message, payload.pending_email);
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS);
                return;
            }

            if (payload.state === 'completed') {
                redirectToLogin(
                    payload.message || 'Email and password updated. Please sign in with your new credentials.',
                    payload.redirect || '/login',
                );
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

    bootstrapTimer();

    if (window.ceAwaitingPassword) {
        markAwaitingPasswordUI(
            'Set your new password on the other tab to finish the email change.',
            document.getElementById('cePendingEmailVal')?.textContent?.trim() || '',
        );
    }

    checkConfirmationStatus();

    if (resendForm) {
        resendForm.addEventListener('submit', function () {
            if (resendBtn) {
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending…';
            }
            setCooldownExpiry(COOLDOWN_SECONDS);
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
