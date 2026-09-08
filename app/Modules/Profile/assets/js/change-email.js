document.addEventListener('DOMContentLoaded', () => {
    const FLASH_KEY = 'kabataan_change_email_error';
    const form = document.getElementById('ceForm');
    const submitBtn = document.getElementById('ceSubmitBtn');
    const btnText = document.getElementById('ceBtnText');
    const clientFlash = document.getElementById('ceClientFlashError');
    const newEmailInput = document.getElementById('ceNewEmail');
    const currentEmailInput = document.getElementById('ceCurrentEmail');

    function showClientFlash(message) {
        if (!clientFlash || !message) return;
        // Prefer field-level error for new email; avoid a second top alert.
        if (newEmailInput) {
            setFieldError('ceNewEmail', 'ceNewEmailError', message);
            return;
        }
        clientFlash.innerHTML = `
            <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span></span>
        `;
        const span = clientFlash.querySelector('span');
        if (span) span.textContent = message;
        clientFlash.hidden = false;
        clientFlash.style.display = 'flex';
    }

    function forceLowercase(input) {
        if (!input) return;
        input.addEventListener('input', () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const lower = input.value.toLowerCase();
            if (input.value !== lower) {
                input.value = lower;
                if (typeof start === 'number' && typeof end === 'number') {
                    input.setSelectionRange(start, end);
                }
            }
        });
        input.addEventListener('blur', () => {
            input.value = input.value.trim().toLowerCase();
        });
        if (input.value) {
            input.value = input.value.toLowerCase();
        }
    }

    function setFieldError(inputId, errorId, msg) {
        const input = document.getElementById(inputId);
        const err = document.getElementById(errorId);
        if (input) input.classList.add('error');
        if (err) {
            err.textContent = msg;
            err.hidden = false;
            err.style.display = 'block';
        }
    }

    function clearFieldError(inputId, errorId) {
        const input = document.getElementById(inputId);
        const err = document.getElementById(errorId);
        if (input) {
            input.classList.remove('error');
            input.removeAttribute('aria-invalid');
        }
        if (err) {
            err.textContent = '';
            err.hidden = true;
            err.style.display = 'none';
        }
    }

    function clearTopAlerts() {
        document.getElementById('ceFlashError')?.remove();
        if (clientFlash) {
            clientFlash.hidden = true;
            clientFlash.style.display = 'none';
            clientFlash.textContent = '';
        }
    }

    try {
        const flash = sessionStorage.getItem(FLASH_KEY);
        if (flash) {
            sessionStorage.removeItem(FLASH_KEY);
            if (!document.getElementById('ceFlashError') && !document.getElementById('ceNewEmailError')?.textContent?.trim()) {
                showClientFlash(flash);
            }
        }
    } catch (e) {
        // ignore storage errors
    }

    forceLowercase(currentEmailInput);
    forceLowercase(newEmailInput);

    if (newEmailInput) {
        newEmailInput.addEventListener('input', () => {
            clearFieldError('ceNewEmail', 'ceNewEmailError');
            clearTopAlerts();
        });
    }

    if (!form) return;

    document.querySelectorAll('.pw-toggle-btn[data-target]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById(btn.dataset.target || '');
            if (!target) return;

            const isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            btn.classList.toggle('pw-visible', isPassword);
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    form.addEventListener('submit', (e) => {
        const currentEmail = document.getElementById('ceCurrentEmail')?.value.trim().toLowerCase() || '';
        const newEmail = document.getElementById('ceNewEmail')?.value.trim().toLowerCase() || '';
        const password = document.getElementById('cePassword')?.value || '';

        if (currentEmailInput) currentEmailInput.value = currentEmail;
        if (newEmailInput) newEmailInput.value = newEmail;

        let valid = true;

        clearFieldError('ceCurrentEmail', 'ceCurrentEmailError');
        clearFieldError('ceNewEmail', 'ceNewEmailError');
        clearFieldError('cePassword', 'cePasswordError');

        if (!currentEmail) {
            setFieldError('ceCurrentEmail', 'ceCurrentEmailError', 'Current email is required.');
            valid = false;
        }

        if (!newEmail) {
            setFieldError('ceNewEmail', 'ceNewEmailError', 'New email address is required.');
            valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
            setFieldError('ceNewEmail', 'ceNewEmailError', 'Please enter a valid email address.');
            valid = false;
        } else if (newEmail === currentEmail) {
            setFieldError('ceNewEmail', 'ceNewEmailError', 'New email must be different from current email.');
            valid = false;
        }

        if (!password) {
            setFieldError('cePassword', 'cePasswordError', 'Current password is required.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            return;
        }

        if (submitBtn) submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Sending…';
    });
});
