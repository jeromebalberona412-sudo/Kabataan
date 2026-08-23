/**
 * Optional-email Step 1: dynamic submit button, Turnstile, and no-email submit.
 */
(function () {
    const root = document.getElementById('kkpRegistrationWizard');
    if (!root) {
        return;
    }

    const emailInput = document.getElementById('kkpEmail');
    const nextLabelEl = document.getElementById('kkpWizardNextLabel');
    const nextBtn = document.getElementById('kkpWizardNextBtn');
    const form = document.getElementById('kkProfilingForm');
    const noEmailModal = document.getElementById('kkpNoEmailModal');
    const noEmailAgreeBtn = document.getElementById('kkpNoEmailAgreeBtn');
    const noEmailAgreeCheck = document.getElementById('kkpNoEmailAgreeCheck');
    const noEmailScroll = document.getElementById('kkpNoEmailScroll');
    const noEmailScrollHint = document.getElementById('kkpNoEmailScrollHint');
    const successModal = document.getElementById('kkpRegSuccessModal');
    const successLoginBtn = document.getElementById('kkpRegSuccessLoginBtn');
    const turnstileEnabled = root.dataset.turnstileEnabled === '1';
    const turnstileSiteKey = root.dataset.turnstileSitekey || '';
    const slug = root.dataset.barangaySlug || '';
    const apiBase = `/api/kkprofiling/${slug}/wizard`;

    let turnstileToken = null;
    let isSubmitting = false;
    let submittedWithoutEmail = false;
    let saveStep1Fn = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function emailValue() {
        return (emailInput?.value || '').trim();
    }

    function hasEmailValue() {
        return emailValue() !== '';
    }

    function syncStep1Button() {
        if (!nextLabelEl) {
            return;
        }

        const step1Active = document.getElementById('kkpWizardStep1') && !document.getElementById('kkpWizardStep1').hidden;
        if (!step1Active) {
            return;
        }

        nextLabelEl.textContent = hasEmailValue() ? 'Save & Continue' : 'Submit KK Profiling';
    }

    function showOverlay(el) {
        if (!el) {
            return;
        }
        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
    }

    function hideOverlay(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
    }

    function hasScrolledNoEmailToBottom() {
        if (!noEmailScroll) {
            return true;
        }
        const remaining = noEmailScroll.scrollHeight - noEmailScroll.clientHeight - noEmailScroll.scrollTop;
        return remaining <= 12;
    }

    function syncNoEmailScrollLock() {
        const reachedBottom = hasScrolledNoEmailToBottom();
        if (noEmailAgreeCheck) {
            noEmailAgreeCheck.disabled = !reachedBottom;
            if (!reachedBottom) {
                noEmailAgreeCheck.checked = false;
            }
        }
        if (noEmailScrollHint) {
            noEmailScrollHint.hidden = reachedBottom;
        }
        syncNoEmailAgreeButton();
    }

    function resetNoEmailConfirm() {
        if (noEmailAgreeCheck) {
            noEmailAgreeCheck.checked = false;
            noEmailAgreeCheck.disabled = true;
        }
        if (noEmailScroll) {
            noEmailScroll.scrollTop = 0;
        }
        syncNoEmailScrollLock();
        window.requestAnimationFrame(() => {
            syncNoEmailScrollLock();
        });
    }

    function syncNoEmailAgreeButton() {
        if (!noEmailAgreeBtn) {
            return;
        }
        noEmailAgreeBtn.disabled = !noEmailAgreeCheck?.checked || isSubmitting;
    }

    function closeNoEmailConfirm() {
        hideOverlay(noEmailModal);
        resetNoEmailConfirm();
    }

    function showSubmitSuccess() {
        const title = document.getElementById('kkpRegSuccessTitle');
        const message = document.getElementById('kkpRegSuccessMessage');
        if (title) {
            title.textContent = 'KK Profiling Submitted';
        }
        if (message) {
            message.textContent = 'Your KK Profiling has been recorded. You can now go to Sign in.';
        }
        if (successLoginBtn) {
            successLoginBtn.textContent = 'Go to Sign in';
        }
        if (successModal) {
            successModal.hidden = false;
            successModal.setAttribute('aria-hidden', 'false');
        }
    }

    function waitForTurnstileGate(maxWaitMs = 8000) {
        return new Promise((resolve, reject) => {
            const start = Date.now();
            const check = () => {
                if (window.KabataanTurnstileGate?.challenge) {
                    resolve(window.KabataanTurnstileGate);
                    return;
                }
                if (Date.now() - start > maxWaitMs) {
                    reject(new Error('Security check failed to load. Please refresh the page.'));
                    return;
                }
                window.setTimeout(check, 50);
            };
            check();
        });
    }

    function runTurnstileChallenge() {
        if (!turnstileEnabled || !turnstileSiteKey) {
            return Promise.resolve('');
        }
        if (typeof window.kabataanTurnstileChallenge === 'function') {
            return window.kabataanTurnstileChallenge();
        }
        return waitForTurnstileGate().then((gate) => gate.challenge());
    }

    function requestTurnstileThen(action) {
        return runTurnstileChallenge().then((token) => {
            turnstileToken = token || '';
            if (action) {
                action(turnstileToken);
            }
            return turnstileToken;
        });
    }

    window.kkpChallengeTurnstile = function () {
        return requestTurnstileThen(null);
    };

    function setBusy(busy) {
        isSubmitting = busy;
        if (nextBtn) {
            nextBtn.disabled = busy;
            nextBtn.classList.toggle('is-submitting', busy);
        }
        syncNoEmailAgreeButton();
    }

    async function postForm(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Request failed.');
            error.errors = data.errors || {};
            throw error;
        }

        return data;
    }

    function applyFieldErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return;
        }
        Object.entries(errors).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const input = form?.querySelector(`[name="${field}"]`);
            if (input && typeof window.showFieldError === 'function') {
                window.showFieldError(input, message);
            }
        });
        const firstErr = document.querySelector('.kkp-field-error');
        firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function submitWithoutEmail(token) {
        if (submittedWithoutEmail || isSubmitting) {
            return;
        }

        setBusy(true);

        try {
            const assemblyHidden = document.getElementById('kkpKkAssembly');
            const timesHidden = document.getElementById('kkpKkTimes');
            const reasonHidden = document.getElementById('kkpKkReason');
            const checkedAssembly = document.querySelector('input[name="kk_assemblyChk"]:checked');
            const checkedTimes = document.querySelector('input[name="kk_timesChk"]:checked');
            const checkedReason = document.querySelector('input[name="kk_reasonChk"]:checked');
            if (assemblyHidden && checkedAssembly) {
                assemblyHidden.disabled = false;
                assemblyHidden.value = checkedAssembly.value;
            }
            if (timesHidden) {
                timesHidden.disabled = false;
                timesHidden.value = checkedTimes ? checkedTimes.value : (assemblyHidden?.value === 'Yes' ? timesHidden.value : '');
            }
            if (reasonHidden) {
                reasonHidden.disabled = false;
                reasonHidden.value = checkedReason ? checkedReason.value : (assemblyHidden?.value === 'No' ? reasonHidden.value : '');
            }

            const formData = new FormData(form);
            formData.append('respondent_number', root.dataset.respondentNumber || '');
            formData.set('email', '');
            if (token) {
                formData.append('cf-turnstile-response', token);
            }

            await postForm(`${apiBase}/submit-without-email`, formData);
            submittedWithoutEmail = true;
            hideOverlay(noEmailModal);
            if (nextBtn) {
                nextBtn.disabled = true;
            }
            showSubmitSuccess();
        } catch (error) {
            applyFieldErrors(error.errors);
            if (!error.errors || Object.keys(error.errors).length === 0) {
                window.alert(error.message);
            }
            turnstileToken = null;
        } finally {
            setBusy(false);
            if (submittedWithoutEmail && nextBtn) {
                nextBtn.disabled = true;
            }
        }
    }

    async function continueWithEmail(token) {
        if (isSubmitting) {
            return;
        }

        setBusy(true);

        try {
            if (typeof saveStep1Fn === 'function') {
                if (token) {
                    window.__kkpTurnstileToken = token;
                }
                await saveStep1Fn();
            }
        } finally {
            window.__kkpTurnstileToken = '';
            setBusy(false);
        }
    }

    window.kkpHandleStep1PrimaryAction = async function (helpers) {
        if (submittedWithoutEmail || isSubmitting) {
            return false;
        }

        saveStep1Fn = helpers?.saveStep1 || saveStep1Fn;

        if (typeof window.validateKkProfilingForm !== 'function') {
            return false;
        }

        const valid = await window.validateKkProfilingForm({
            skipEmailExistenceCheck: !hasEmailValue(),
        });

        if (!valid) {
            return false;
        }

        if (!hasEmailValue()) {
            resetNoEmailConfirm();
            showOverlay(noEmailModal);
            window.requestAnimationFrame(() => {
                syncNoEmailScrollLock();
            });
            return true;
        }

        await continueWithEmail('');
        return true;
    };

    window.kkpConsumeTurnstileToken = function () {
        const token = window.__kkpTurnstileToken || turnstileToken || '';
        window.__kkpTurnstileToken = '';
        turnstileToken = null;
        return token;
    };

    window.kkpSyncStep1Button = syncStep1Button;

    emailInput?.addEventListener('input', syncStep1Button);
    emailInput?.addEventListener('blur', syncStep1Button);

    noEmailAgreeCheck?.addEventListener('click', (event) => {
        if (!hasScrolledNoEmailToBottom()) {
            event.preventDefault();
            noEmailAgreeCheck.checked = false;
            noEmailAgreeCheck.disabled = true;
            syncNoEmailScrollLock();
        }
    });

    noEmailAgreeCheck?.addEventListener('change', () => {
        if (!hasScrolledNoEmailToBottom()) {
            noEmailAgreeCheck.checked = false;
            noEmailAgreeCheck.disabled = true;
        }
        syncNoEmailAgreeButton();
    });

    noEmailScroll?.addEventListener('scroll', syncNoEmailScrollLock, { passive: true });
    window.addEventListener('resize', () => {
        if (noEmailModal && !noEmailModal.hidden) {
            syncNoEmailScrollLock();
        }
    });

    noEmailAgreeBtn?.addEventListener('click', () => {
        if (!noEmailAgreeCheck?.checked || submittedWithoutEmail || isSubmitting) {
            return;
        }
        hideOverlay(noEmailModal);
        requestTurnstileThen((token) => {
            submitWithoutEmail(token);
        }).catch((error) => {
            if (error?.message !== 'Verification cancelled.') {
                window.alert(error.message);
            }
        });
    });

    document.getElementById('kkpNoEmailCancelBtn')?.addEventListener('click', () => {
        closeNoEmailConfirm();
    });

    document.getElementById('kkpNoEmailCloseBtn')?.addEventListener('click', () => {
        closeNoEmailConfirm();
    });

    document.getElementById('kkpNoEmailBackdrop')?.addEventListener('click', () => {
        closeNoEmailConfirm();
    });

    syncStep1Button();
})();
