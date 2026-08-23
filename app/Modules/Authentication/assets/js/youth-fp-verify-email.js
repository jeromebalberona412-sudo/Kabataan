/**
 * SK OnePortal Kabataan — Forgot Password / Verify Email
 *
 * Timer rules:
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ Source of truth: backend `resend_available_at` ISO timestamp            │
 * │ Client-side localStorage stores the absolute deadline (Unix ms)         │
 * │ so the countdown continues correctly across page refreshes.             │
 * │                                                                         │
 * │ On first arrival  → seed localStorage from backend timestamp            │
 * │ On refresh        → resume from localStorage (not reset)                │
 * │ On resend click   → POST to backend, get new timestamp, update storage  │
 * │ Backend always enforces cooldown; frontend timer is UI only             │
 * └─────────────────────────────────────────────────────────────────────────┘
 */
(function () {
    'use strict';

    const FALLBACK_COOLDOWN_MS = 60_000;

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const dataEl        = document.getElementById('fp-verify-data');
        const statusEl      = document.getElementById('fpve-status');
        const cooldownEl    = document.getElementById('fpve-cooldown');
        const countdownEl   = document.getElementById('fpve-countdown');
        const resendBtn     = document.getElementById('fpve-resend-btn');
        const resendLabel   = document.getElementById('fpve-resend-label');
        const resendSpinner = document.getElementById('fpve-resend-spinner');

        if (!dataEl || !resendBtn) return;

        const email           = dataEl.dataset.email        || '';
        const resendUrl       = dataEl.dataset.resendUrl    || '';
        const csrfToken       = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const backendDeadline = dataEl.dataset.resendAvailableAt || '';

        // localStorage key scoped per email address
        const STORAGE_KEY = 'kabataan_fpve_until_' + simpleHash(email);

        let tickInterval   = null;
        let resendInFlight = false;

        bootstrapTimer();

        resendBtn.addEventListener('click', handleResend);

        // ─────────────────────────────────────────────────────────────────────

        function bootstrapTimer() {
            const stored    = getStoredDeadline();
            const backendMs = backendDeadline ? new Date(backendDeadline).getTime() : 0;
            const now       = Date.now();

            let deadlineMs;

            if (stored > now) {
                deadlineMs = stored;
            } else if (backendMs > now) {
                deadlineMs = backendMs;
                persistDeadline(deadlineMs);
            } else {
                clearStoredDeadline();
                enableResend();
                return;
            }

            startTicking(deadlineMs);
        }

        function startTicking(deadlineMs) {
            if (tickInterval) {
                clearInterval(tickInterval);
                tickInterval = null;
            }

            function tick() {
                const remaining = Math.ceil((deadlineMs - Date.now()) / 1000);

                if (remaining <= 0) {
                    clearInterval(tickInterval);
                    tickInterval = null;
                    clearStoredDeadline();
                    enableResend();
                    return;
                }

                disableResend(remaining);
            }

            tick();
            tickInterval = setInterval(tick, 500);
        }

        function disableResend(remainingSecs) {
            resendBtn.disabled = true;
            // Always hide spinner during cooldown — only show it during active fetch
            if (resendSpinner) resendSpinner.hidden = true;
            if (cooldownEl)  cooldownEl.hidden = false;
            if (countdownEl) countdownEl.textContent = formatTime(remainingSecs);
        }

        function enableResend() {
            resendBtn.disabled = false;
            // Always ensure spinner is hidden when button is available
            if (resendSpinner) resendSpinner.hidden = true;
            if (cooldownEl)  cooldownEl.hidden = true;
            if (countdownEl) countdownEl.textContent = '';
            setLabel('Resend Reset Link');
        }

        async function handleResend() {
            if (resendInFlight || resendBtn.disabled) return;

            resendInFlight     = true;
            resendBtn.disabled = true;
            // Only show spinner here — during the active network request
            if (resendSpinner) resendSpinner.hidden = false;
            setLabel('Sending…');
            clearStatus();

            try {
                let turnstileToken = '';
                if (window.kabataanTurnstileChallenge) {
                    try {
                        turnstileToken = await window.kabataanTurnstileChallenge();
                    } catch {
                        enableResend();
                        return;
                    }
                } else if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                    try {
                        turnstileToken = await window.KabataanTurnstileGate.challenge();
                    } catch {
                        enableResend();
                        return;
                    }
                }

                const response = await fetch(resendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept':           'application/json',
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email,
                        'cf-turnstile-response': turnstileToken,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.status === 410 || data.expired) {
                    if (resendSpinner) resendSpinner.hidden = true;
                    showStatus('Your session has expired. Redirecting…', 'error');
                    setTimeout(() => {
                        window.location.href = dataEl.dataset.signinUrl || '/forgot-password';
                    }, 1500);
                    return;
                }

                if (!response.ok || !data.ok) {
                    const msg = data.message || 'Unable to resend. Please try again.';
                    if (resendSpinner) resendSpinner.hidden = true;
                    showStatus(msg, 'error');

                    if (data.resend_available_at) {
                        const newDeadline = new Date(data.resend_available_at).getTime();
                        if (newDeadline > Date.now()) {
                            persistDeadline(newDeadline);
                            setLabel('Resend Reset Link');
                            startTicking(newDeadline);
                            return;
                        }
                    }

                    enableResend();
                    return;
                }

                // Success
                if (resendSpinner) resendSpinner.hidden = true;
                showStatus(data.message || 'Reset link resent. Check your inbox.', 'success');
                setLabel('Resend Reset Link');

                const newDeadline = data.resend_available_at
                    ? new Date(data.resend_available_at).getTime()
                    : Date.now() + FALLBACK_COOLDOWN_MS;

                persistDeadline(newDeadline);
                startTicking(newDeadline);

            } catch {
                if (resendSpinner) resendSpinner.hidden = true;
                showStatus('Network error. Please check your connection and try again.', 'error');
                enableResend();
            } finally {
                resendInFlight = false;
            }
        }

        // ── UI helpers ────────────────────────────────────────────────────────

        function setLabel(text) {
            if (resendLabel) resendLabel.textContent = text;
        }

        function showStatus(msg, type) {
            if (!statusEl) return;
            statusEl.hidden      = false;
            statusEl.textContent = msg;
            statusEl.className   = 'fpve-status fpve-status-' + type;
        }

        function clearStatus() {
            if (!statusEl) return;
            statusEl.hidden      = true;
            statusEl.textContent = '';
            statusEl.className   = 'fpve-status';
        }

        // ── Storage helpers ───────────────────────────────────────────────────

        function getStoredDeadline() {
            const v = localStorage.getItem(STORAGE_KEY);
            if (!v) return 0;
            const n = Number(v);
            return Number.isFinite(n) ? n : 0;
        }

        function persistDeadline(ms) {
            localStorage.setItem(STORAGE_KEY, String(ms));
        }

        function clearStoredDeadline() {
            localStorage.removeItem(STORAGE_KEY);
        }

        // ── Utilities ─────────────────────────────────────────────────────────

        function formatTime(totalSecs) {
            const m = Math.floor(totalSecs / 60);
            const s = totalSecs % 60;
            return m + ':' + String(s).padStart(2, '0');
        }

        function simpleHash(str) {
            let h = 0;
            for (let i = 0; i < str.length; i++) {
                h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
            }
            return Math.abs(h).toString(36);
        }
    }

    function simpleHash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
        }
        return Math.abs(h).toString(36);
    }

}());
