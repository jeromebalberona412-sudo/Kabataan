/**
 * Reusable Kabataan header — user menu, overlay exclusivity, logout modal & mobile helpers
 */
(function () {
    'use strict';

    const userWrap = document.getElementById('kabataanHeaderUser');
    const avatarBtn = userWrap?.querySelector('.kabataan-header__avatar-btn');

    function closeProfileMenu() {
        if (!userWrap) {
            return;
        }

        userWrap.classList.remove('is-open');
        avatarBtn?.setAttribute('aria-expanded', 'false');
    }

    function closeHeaderOverlays(except) {
        if (except !== 'profile') {
            closeProfileMenu();
        }

        if (except !== 'chatbot' && typeof window.closeChatbotPopover === 'function') {
            window.closeChatbotPopover();
        }

        if (except !== 'notif' && typeof window.closeNotifPopover === 'function') {
            window.closeNotifPopover();
        }
    }

    window.kabataanCloseProfileMenu = closeProfileMenu;
    window.kabataanCloseHeaderOverlays = closeHeaderOverlays;

    if (avatarBtn && userWrap) {
        avatarBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const willOpen = !userWrap.classList.contains('is-open');
            if (willOpen) {
                closeHeaderOverlays('profile');
                userWrap.classList.add('is-open');
                avatarBtn.setAttribute('aria-expanded', 'true');
            } else {
                closeProfileMenu();
            }
        });

        document.addEventListener('click', function (e) {
            if (userWrap.classList.contains('is-open') && !userWrap.contains(e.target)) {
                closeProfileMenu();
            }
        });
    }

    const menuToggle = document.getElementById('kabataanHeaderMenuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            document.body.classList.toggle('kabataan-mobile-nav-open');
        });
    }

    const drawerBtn = document.getElementById('programsDrawerBtn');
    const drawerSidebar = document.getElementById('programsDrawerSidebar');
    const drawerBackdrop = document.getElementById('programsDrawerBackdrop');

    function closeProgramsDrawer() {
        drawerSidebar?.classList.remove('drawer-open');
        drawerBackdrop?.classList.remove('drawer-open', 'active');
        drawerBackdrop?.setAttribute('aria-hidden', 'true');
        drawerBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function openProgramsDrawer() {
        drawerSidebar?.classList.add('drawer-open');
        drawerBackdrop?.classList.add('drawer-open', 'active');
        drawerBackdrop?.setAttribute('aria-hidden', 'false');
        drawerBtn?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    window.kabataanCloseProgramsDrawer = closeProgramsDrawer;
    window.kabataanOpenProgramsDrawer = openProgramsDrawer;

    if (drawerBtn && drawerSidebar) {
        drawerBtn.addEventListener('click', function () {
            if (drawerSidebar.classList.contains('drawer-open')) {
                closeProgramsDrawer();
            } else {
                openProgramsDrawer();
            }
        });
    }

    drawerBackdrop?.addEventListener('click', closeProgramsDrawer);
    document.querySelectorAll('[data-programs-drawer-close]').forEach(function (btn) {
        btn.addEventListener('click', closeProgramsDrawer);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }

        if (drawerSidebar?.classList.contains('drawer-open')) {
            closeProgramsDrawer();
            return;
        }

        closeHeaderOverlays();
    });
})();
