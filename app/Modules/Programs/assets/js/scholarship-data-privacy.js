/**
 * Scholarship Data Privacy Notice — consent gate before starting an application.
 */
(function (global) {
    'use strict';

    const CONSENT_PREFIX = 'sch_privacy_agreed_';

    let modalEl = null;
    let agreeCheckbox = null;
    let proceedBtn = null;
    let errorEl = null;
    let pendingScheduleId = null;
    let onAgreeCallback = null;
    let onCancelCallback = null;

    function consentKey(scheduleId) {
        return `${CONSENT_PREFIX}${scheduleId}`;
    }

    function hasConsent(scheduleId) {
        if (!scheduleId) return false;
        try {
            return sessionStorage.getItem(consentKey(scheduleId)) === '1';
        } catch {
            return false;
        }
    }

    function setConsent(scheduleId) {
        if (!scheduleId) return;
        try {
            sessionStorage.setItem(consentKey(scheduleId), '1');
        } catch {
            // ignore storage errors
        }
    }

    function ensureModal() {
        if (modalEl) return modalEl;
        modalEl = document.getElementById('schDataPrivacyModal');
        return modalEl;
    }

    function resetForm() {
        if (agreeCheckbox) agreeCheckbox.checked = false;
        if (proceedBtn) proceedBtn.disabled = true;
        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = 'Please read and agree to the Data Privacy Notice to continue.';
        }
    }

    function close() {
        if (!modalEl) return;
        modalEl.hidden = true;
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        pendingScheduleId = null;
        onAgreeCallback = null;
        onCancelCallback = null;
        document.removeEventListener('keydown', onKeyDown);
    }

    function handleCancel() {
        const callback = onCancelCallback;
        close();
        if (typeof callback === 'function') callback();
    }

    function handleProceed() {
        if (!agreeCheckbox?.checked) {
            if (errorEl) errorEl.hidden = false;
            return;
        }

        const scheduleId = pendingScheduleId;
        const callback = onAgreeCallback;
        setConsent(scheduleId);
        close();
        if (typeof callback === 'function') callback();
    }

    function onKeyDown(event) {
        if (event.key === 'Escape' && modalEl && !modalEl.hidden) {
            handleCancel();
        }
    }

    function bindEvents() {
        if (!modalEl || modalEl.dataset.schDpBound === '1') return;
        modalEl.dataset.schDpBound = '1';

        agreeCheckbox = modalEl.querySelector('#schDpAgreeCheckbox');
        proceedBtn = modalEl.querySelector('#schDpProceedBtn');
        errorEl = modalEl.querySelector('#schDpError');

        agreeCheckbox?.addEventListener('change', () => {
            if (proceedBtn) proceedBtn.disabled = !agreeCheckbox.checked;
            if (errorEl && agreeCheckbox.checked) errorEl.hidden = true;
        });

        proceedBtn?.addEventListener('click', handleProceed);

        modalEl.querySelectorAll('[data-close-sch-dp]').forEach((el) => {
            el.addEventListener('click', handleCancel);
        });
    }

    function open({ scheduleId, onAgree, onCancel, mode = 'apply' }) {
        const modal = ensureModal();
        if (!modal) {
            if (typeof onAgree === 'function') onAgree();
            return;
        }

        bindEvents();
        resetForm();
        pendingScheduleId = scheduleId;
        onAgreeCallback = onAgree;
        onCancelCallback = onCancel;

        if (proceedBtn) {
            proceedBtn.textContent = mode === 'view'
                ? 'I Agree, View Application'
                : 'Proceed to Application';
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeyDown);
        agreeCheckbox?.focus();
    }

    function requestConsent(scheduleId, onAgree, onCancel, options = {}) {
        if (!options.force && hasConsent(scheduleId)) {
            if (typeof onAgree === 'function') onAgree();
            return;
        }

        open({
            scheduleId,
            onAgree,
            onCancel,
            mode: options.mode || 'apply',
        });
    }

    global.ScholarshipDataPrivacy = {
        open,
        close,
        requestConsent,
        hasConsent,
        setConsent,
    };
})(window);
