/**
 * Programs Pre-Survey — single attendance question (frontend only)
 */
(function () {
    const form = document.getElementById('ppsForm');
    const snackbar = document.getElementById('ppsSnackbar');
    const snackbarText = document.getElementById('ppsSnackbarText');
    const successModal = document.getElementById('ppsSuccessModal');
    const successClose = document.getElementById('ppsSuccessClose');
    const submitBtn = document.getElementById('ppsSubmitBtn');
    const dashboardUrl = document.querySelector('.pps-btn--ghost')?.getAttribute('href') || '/dashboard';

    function showSnackbar(message) {
        if (!snackbar || !snackbarText) return;
        snackbarText.textContent = message;
        snackbar.hidden = false;
        snackbar.classList.add('pps-snackbar--visible');
        setTimeout(() => {
            snackbar.classList.remove('pps-snackbar--visible');
            setTimeout(() => { snackbar.hidden = true; }, 300);
        }, 3200);
    }

    function validateForm() {
        if (!form) return false;
        const fieldset = form.querySelector('.pps-fieldset');
        const checked = form.querySelector('[name="attendance"]:checked');
        fieldset?.classList.remove('pps-fieldset--error');

        if (!checked) {
            fieldset?.classList.add('pps-fieldset--error');
            showSnackbar('Please select your attendance answer.');
            fieldset?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        return true;
    }

    form?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        if (submitBtn) submitBtn.disabled = true;

        setTimeout(() => {
            if (successModal) successModal.hidden = false;
            if (submitBtn) submitBtn.disabled = false;
        }, 900);
    });

    successClose?.addEventListener('click', () => {
        window.location.href = dashboardUrl;
    });

    successModal?.querySelector('.pps-modal__backdrop')?.addEventListener('click', () => {
        successModal.hidden = true;
    });

    form?.querySelectorAll('[name="attendance"]').forEach((input) => {
        input.addEventListener('change', () => {
            input.closest('.pps-fieldset')?.classList.remove('pps-fieldset--error');
        });
    });
})();
