const FLASH_KEY = 'guestKabataanFlashToast';
const DURATION_MS = 3800;

let toastEl = null;
let hideTimer = null;

function ensureStyles() {
    if (document.getElementById('guestKabataanToastStyles')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'guestKabataanToastStyles';
    style.textContent = `
        .guest-kabataan-toast {
            position: fixed;
            top: max(16px, env(safe-area-inset-top));
            left: 50%;
            z-index: 10100;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            box-sizing: border-box;
            width: max-content;
            max-width: min(92vw, 440px);
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid transparent;
            background: #fff;
            color: #0f172a;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.45;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
            transform: translate(-50%, -12px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease, transform 180ms ease;
        }
        .guest-kabataan-toast.is-visible {
            transform: translate(-50%, 0);
            opacity: 1;
            pointer-events: auto;
        }
        .guest-kabataan-toast--success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .guest-kabataan-toast--error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .guest-kabataan-toast--info { background: #eff6ff; border-color: #bfdbfe; color: #0450a8; }
        .guest-kabataan-toast__icon { flex: 0 0 auto; width: 20px; height: 20px; margin-top: 1px; }
        .guest-kabataan-toast__text { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
        .guest-kabataan-toast__close {
            flex: 0 0 auto;
            width: 28px;
            height: 28px;
            margin: -4px -6px -4px 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: inherit;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }
        .guest-kabataan-toast__close:focus-visible { outline: 2px solid currentColor; outline-offset: 1px; }
        @media (max-width: 480px) {
            .guest-kabataan-toast { width: calc(100vw - 24px); max-width: none; font-size: 0.85rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .guest-kabataan-toast { transition: opacity 120ms ease; }
        }
    `;
    document.head.appendChild(style);
}

const ICONS = {
    success: '<path d="M20 6 9 17l-5-5"/>',
    error: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
};

function hideToast() {
    if (hideTimer) {
        window.clearTimeout(hideTimer);
        hideTimer = null;
    }
    toastEl?.classList.remove('is-visible');
}

export function showGuestToast(message, type = 'info') {
    const text = String(message || '').trim();
    if (!text) {
        return;
    }
    const kind = ICONS[type] ? type : 'info';
    ensureStyles();

    if (!toastEl) {
        toastEl = document.createElement('div');
        toastEl.className = 'guest-kabataan-toast';
        toastEl.innerHTML = '<svg class="guest-kabataan-toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>'
            + '<span class="guest-kabataan-toast__text"></span>'
            + '<button type="button" class="guest-kabataan-toast__close" aria-label="Close notification">&times;</button>';
        toastEl.querySelector('.guest-kabataan-toast__close').addEventListener('click', hideToast);
        document.body.appendChild(toastEl);
    }

    toastEl.className = `guest-kabataan-toast guest-kabataan-toast--${kind}`;
    toastEl.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    toastEl.setAttribute('aria-live', kind === 'error' ? 'assertive' : 'polite');
    toastEl.querySelector('.guest-kabataan-toast__icon').innerHTML = ICONS[kind];
    toastEl.querySelector('.guest-kabataan-toast__text').textContent = text;

    // Force a reflow so a replaced toast re-animates instead of silently swapping text.
    void toastEl.offsetWidth;
    toastEl.classList.add('is-visible');

    if (hideTimer) {
        window.clearTimeout(hideTimer);
    }
    hideTimer = window.setTimeout(hideToast, DURATION_MS);
}

/** Queue a toast for the next page (used right before a redirect). */
export function flashGuestToast(message, type = 'success') {
    try {
        window.sessionStorage.setItem(FLASH_KEY, JSON.stringify({ message, type }));
    } catch (error) {
        // Private mode / storage disabled: the next page simply shows no toast.
    }
}

export function showFlashedGuestToast() {
    let raw = null;
    try {
        raw = window.sessionStorage.getItem(FLASH_KEY);
        window.sessionStorage.removeItem(FLASH_KEY);
    } catch (error) {
        return;
    }
    if (!raw) {
        return;
    }
    try {
        const flash = JSON.parse(raw);
        showGuestToast(flash?.message, flash?.type);
    } catch (error) {
        // Ignore malformed flash data.
    }
}
