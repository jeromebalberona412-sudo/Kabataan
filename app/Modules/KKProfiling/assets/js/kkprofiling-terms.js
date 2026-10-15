/**
 * KK Profiling legal modal. Same scroll-to-end checkbox as Kabataan sign-in.
 * Checking the box at the bottom of the document closes the modal.
 */
(function () {
    const ACK_MESSAGE = 'You must agree to the Terms and Conditions and Privacy Policy before submitting.';
    const SCROLL_THRESHOLD = 12;

    function pageCheckbox() {
        return document.getElementById('kkpProfilingAcknowledgment');
    }

    function modalCheckbox() {
        return document.getElementById('kkpTermsModalAck');
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

    function termsScroll() {
        return document.getElementById('kkpTermsScroll');
    }

    function isScrolledToBottom(element) {
        if (!element || element.clientHeight < 40) {
            return false;
        }
        return element.scrollHeight - element.scrollTop - element.clientHeight <= SCROLL_THRESHOLD;
    }

    function scrollPercent(element) {
        if (!element) {
            return 0;
        }
        const scrollable = element.scrollHeight - element.clientHeight;
        if (scrollable <= 0) {
            return 0;
        }
        return Math.min(100, Math.max(0, Math.round((element.scrollTop / scrollable) * 100)));
    }

    function scrollMessage(percent, atBottom) {
        if (atBottom) {
            return 'You have reached the end of the document. Please check the checkbox below to acknowledge.';
        }
        if (percent <= 0) {
            return 'Please scroll down to read the entire document before acknowledging.';
        }
        if (percent < 50) {
            return 'Keep scrolling. Please read the full document before acknowledging.';
        }
        if (percent < 80) {
            return 'You are halfway there. Keep scrolling to finish reading.';
        }
        return 'Almost done. Scroll a little more to reach the end of the document.';
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
        pageCheckbox()?.classList.add('kkp-input-err');
        pageCheckbox()?.setAttribute('aria-invalid', 'true');
        wrap?.classList.add('is-invalid');
    };

    window.kkpClearProfilingAckError = function () {
        const errorEl = acknowledgmentError();
        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
        pageCheckbox()?.classList.remove('kkp-input-err');
        pageCheckbox()?.removeAttribute('aria-invalid');
        acknowledgmentWrap()?.classList.remove('is-invalid');
    };

    function syncScrollGate() {
        const scroll = termsScroll();
        const checkbox = modalCheckbox();
        const label = document.getElementById('kkpTermsModalAckLabel');
        const notice = termsModal()?.querySelector('[data-legal-scroll-notice]');
        const noticeText = termsModal()?.querySelector('[data-legal-scroll-text]');
        const noticePercent = termsModal()?.querySelector('[data-legal-scroll-percent]');
        if (!scroll || !checkbox) {
            return;
        }

        const atBottom = isScrolledToBottom(scroll);
        const percent = scrollPercent(scroll);
        checkbox.disabled = !atBottom;
        label?.classList.toggle('auth-legal-modal-ack--locked', !atBottom);
        if (!atBottom && checkbox.checked) {
            checkbox.checked = false;
        }
        if (notice) {
            notice.classList.toggle('is-complete', atBottom);
        }
        if (noticeText) {
            noticeText.textContent = scrollMessage(percent, atBottom);
        }
        if (noticePercent) {
            noticePercent.hidden = false;
            noticePercent.textContent = `${percent}%`;
        }
    }

    function resetScrollGate() {
        const scroll = termsScroll();
        const modalAck = modalCheckbox();
        const pageAck = pageCheckbox();
        if (scroll) {
            scroll.scrollTop = 0;
        }
        if (modalAck) {
            modalAck.checked = false;
            modalAck.disabled = true;
        }
        if (pageAck) {
            pageAck.checked = false;
            pageAck.disabled = true;
        }
        window.requestAnimationFrame(syncScrollGate);
    }

    window.kkpOpenTermsModal = function (options) {
        const modal = termsModal();
        if (modal) {
            modal.dataset.continueAfter = options?.continueAfter ? '1' : '0';
        }
        setTermsModalOpen(true);
    };

    function setTermsModalOpen(open) {
        const modal = termsModal();
        if (!modal) {
            return;
        }

        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('kkp-terms-modal-open', open);
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            resetScrollGate();
        }
    }

    function acceptAndClose() {
        const pageAck = pageCheckbox();
        if (pageAck) {
            pageAck.disabled = false;
            pageAck.checked = true;
        }
        window.kkpClearProfilingAckError();
        const modal = termsModal();
        if (modal) {
            modal.dataset.continueAfter = '0';
        }
        setTermsModalOpen(false);
        if (typeof window.kkpContinueAfterTerms === 'function') {
            window.kkpContinueAfterTerms();
            return;
        }
        pageAck?.focus();
    }

    function bindTermsModal() {
        const modal = termsModal();
        const pageAck = pageCheckbox();
        const modalAck = modalCheckbox();
        const scroll = termsScroll();
        if (!modal || modal.dataset.bound === '1') {
            return;
        }
        modal.dataset.bound = '1';

        if (pageAck) {
            pageAck.checked = false;
            pageAck.disabled = true;
        }

        document.getElementById('kkpTermsOpenBtn')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            modal.dataset.continueAfter = '0';
            setTermsModalOpen(true);
        });

        scroll?.addEventListener('scroll', syncScrollGate, { passive: true });
        if (scroll && typeof ResizeObserver === 'function') {
            new ResizeObserver(syncScrollGate).observe(scroll);
        }

        modalAck?.addEventListener('change', () => {
            if (modalAck.checked && !modalAck.disabled) {
                acceptAndClose();
            }
        });

        pageAck?.addEventListener('click', (event) => {
            if (pageAck.disabled) {
                event.preventDefault();
                setTermsModalOpen(true);
            }
        });

        pageAck?.addEventListener('change', () => {
            if (!pageAck.checked) {
                pageAck.disabled = true;
            }
        });

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTermsModal);
    } else {
        bindTermsModal();
    }
})();
