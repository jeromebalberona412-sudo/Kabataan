/**
 * KK Profiling Terms and Conditions modal + acknowledgment helpers.
 */
(function () {
    const ACK_MESSAGE = 'You must agree to the KK Profiling Terms and Conditions before submitting.';
    const ACK_MIN_PX = 9;
    const ACK_MAX_PX = 13;

    function acknowledgmentCheckbox() {
        return document.getElementById('kkpProfilingAcknowledgment');
    }

    function acknowledgmentError() {
        return document.getElementById('kkpProfilingAcknowledgmentError');
    }

    function acknowledgmentWrap() {
        return document.getElementById('kkpProfilingAcknowledgmentWrap');
    }

    function termsModal() {
        return document.getElementById('kkpTermsModal');
    }

    function hasAgreed() {
        return acknowledgmentCheckbox()?.dataset.termsAgreed === '1';
    }

    function setAgreed(agreed) {
        const checkbox = acknowledgmentCheckbox();
        if (!checkbox) {
            return;
        }
        checkbox.dataset.termsAgreed = agreed ? '1' : '0';
        checkbox.checked = Boolean(agreed);
    }

    window.kkpProfilingAckMessage = ACK_MESSAGE;

    window.kkpSetProfilingAckError = function (message) {
        const wrap = acknowledgmentWrap();
        let errorEl = acknowledgmentError();
        if (!errorEl && wrap) {
            errorEl = document.createElement('div');
            errorEl.className = 'kkp-field-error';
            errorEl.id = 'kkpProfilingAcknowledgmentError';
            wrap.appendChild(errorEl);
        }
        if (errorEl) {
            errorEl.hidden = false;
            errorEl.textContent = message || ACK_MESSAGE;
        }
        acknowledgmentCheckbox()?.classList.add('kkp-input-err');
        acknowledgmentCheckbox()?.setAttribute('aria-invalid', 'true');
        wrap?.classList.add('is-invalid');
    };

    window.kkpClearProfilingAckError = function () {
        const errorEl = acknowledgmentError();
        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
        acknowledgmentCheckbox()?.classList.remove('kkp-input-err');
        acknowledgmentCheckbox()?.removeAttribute('aria-invalid');
        acknowledgmentWrap()?.classList.remove('is-invalid');
    };

    function fitAcknowledgmentOneLine() {
        const wrap = acknowledgmentWrap();
        const text = wrap?.querySelector('.kkp-ack-text');
        if (!wrap || !text || wrap.offsetParent === null) {
            return;
        }

        text.style.fontSize = `${ACK_MAX_PX}px`;
        let size = ACK_MAX_PX;
        const limit = Math.max(0, wrap.clientWidth - 28);
        while (size > ACK_MIN_PX && text.scrollWidth > Math.max(limit, text.clientWidth)) {
            size -= 0.25;
            text.style.fontSize = `${size}px`;
        }
    }

    function setTermsModalOpen(open) {
        const modal = termsModal();
        if (!modal) {
            return;
        }

        if (!open) {
            const active = document.activeElement;
            if (active && modal.contains(active) && typeof active.blur === 'function') {
                active.blur();
            }
        }

        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('kkp-terms-modal-open', open);

        if (open) {
            const scroll = modal.querySelector('.kkp-info-modal-scroll');
            if (scroll) {
                scroll.scrollTop = 0;
            }
            document.getElementById('kkpTermsAgreeBtn')?.focus();
        }
    }

    function agreeToTerms() {
        setAgreed(true);
        window.kkpClearProfilingAckError();
        setTermsModalOpen(false);
        acknowledgmentCheckbox()?.focus();
    }

    function bindTermsModal() {
        const modal = termsModal();
        const checkbox = acknowledgmentCheckbox();
        if (!modal || modal.dataset.bound === '1') {
            return;
        }
        modal.dataset.bound = '1';

        if (checkbox?.checked) {
            checkbox.dataset.termsAgreed = '1';
        }

        document.getElementById('kkpTermsOpenBtn')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setTermsModalOpen(true);
        });

        document.getElementById('kkpTermsAgreeBtn')?.addEventListener('click', (event) => {
            event.preventDefault();
            agreeToTerms();
        });

        ['kkpTermsCloseBtn', 'kkpTermsDismissBtn', 'kkpTermsBackdrop'].forEach((id) => {
            document.getElementById(id)?.addEventListener('click', (event) => {
                event.preventDefault();
                setTermsModalOpen(false);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                setTermsModalOpen(false);
            }
        });

        checkbox?.addEventListener('click', (event) => {
            if (hasAgreed() && checkbox.checked) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            checkbox.checked = hasAgreed();
            if (!hasAgreed()) {
                setTermsModalOpen(true);
            }
        });

        checkbox?.addEventListener('change', () => {
            if (!checkbox.checked) {
                checkbox.dataset.termsAgreed = '0';
                return;
            }
            if (!hasAgreed()) {
                checkbox.checked = false;
                setTermsModalOpen(true);
            }
        });

        fitAcknowledgmentOneLine();
        window.addEventListener('resize', fitAcknowledgmentOneLine);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', fitAcknowledgmentOneLine);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTermsModal);
    } else {
        bindTermsModal();
    }
})();
