/**
 * Shared Kabataan logout confirmation modal
 */
(function () {
    'use strict';

    const logoutBtn = document.querySelector('.kabataan-header .logout-btn, .kkpu-lock-bar .logout-btn');
    const logoutForm = logoutBtn?.closest('form');
    const modal = document.getElementById('kabataanLogoutModal');
    const confirmBtn = document.getElementById('kabataanConfirmLogoutBtn');
    let isLoggingOut = false;

    window.closeKabataanLogoutModal = function () {
        if (modal) {
            modal.hidden = true;
        }
        document.body.style.overflow = '';
    };

    window.openKabataanLogoutModal = function () {
        if (modal) {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
    };

    function performLogout() {
        if (isLoggingOut) {
            return;
        }
        isLoggingOut = true;

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Logging out…';
        }

        closeKabataanLogoutModal();

        if (logoutForm) {
            logoutForm.submit();
            return;
        }

        window.location.replace('/sign-in');
    }

    if (logoutBtn && logoutForm) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openKabataanLogoutModal();
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', performLogout);
    }

    modal?.querySelector('.kab-logout-modal__overlay')?.addEventListener('click', closeKabataanLogoutModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) {
            closeKabataanLogoutModal();
        }
    });
})();
