/**
 * SK OnePortal - Kabataan Interactive Guided Tour Engine
 * Zero dependencies, pure vanilla JS + CSS spotlight highlight engine.
 *
 * First login: mandatory — Next/Finish only (no Skip / X / Back).
 * After COMPLETED once: auto-start stops; header ? can relaunch with Skip/X/Back allowed.
 */

(function () {
    'use strict';

    // ── Static Step Configurations ──────────────────────────────────────────
    const TOUR_STEPS = [
        {
            key: 'home',
            target: '[data-tour="home"]',
            icon: 'fa-home',
            title: 'Home',
            description: 'Go back to your Kabataan dashboard anytime from this Home button.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'programs-menu',
            target: '[data-tour="programs-menu"]',
            icon: 'fa-graduation-cap',
            title: 'Programs Menu',
            description: 'On mobile and tablet, open Programs in Your Barangay and Barangay SK Profiles from this button.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'programs',
            target: '[data-tour="programs"]',
            icon: 'fa-list',
            title: 'Programs in Your Barangay',
            description: 'Browse available barangay programs such as Education, Sports, Health, and more. Tap a category to view and apply.',
            placement: 'right',
            isSidebar: true
        },
        {
            key: 'community-feed',
            target: '[data-tour="community-feed"]',
            icon: 'fa-rss',
            title: 'Community Feed',
            description: 'Read announcements, events, activities, and program updates from your barangay SK and the SK Federation.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'barangay-profiles',
            target: '[data-tour="barangay-profiles"]',
            icon: 'fa-building',
            title: 'Barangay SK Profiles',
            description: 'Browse SK officials from each barangay. Open a barangay to see officers, term dates, and their posts.',
            placement: 'left',
            isSidebar: true
        },
        {
            key: 'messages',
            target: '[data-tour="messages"]',
            icon: 'fa-comments',
            title: 'Messages',
            description: 'Chat with your barangay SK Officials, view conversations, and open the full Messages page.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'notifications',
            target: '[data-tour="notifications"]',
            icon: 'fa-bell',
            title: 'Notifications',
            description: 'Check important updates and alerts about programs, profiling, and portal activity.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'tutorial-guide',
            target: '[data-tour="tutorial-guide"]',
            icon: 'fa-question-circle',
            title: 'Tutorial Guide',
            description: 'Click this question mark anytime you wish to restart or review this interactive guided tour.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'account-menu',
            target: '[data-tour="account-menu"]',
            icon: 'fa-user-circle',
            title: 'Account Menu',
            description: 'Open View Profile, Account Settings, or Logout from this menu.',
            placement: 'bottom',
            isSidebar: false
        },
        {
            key: 'finish',
            target: null,
            icon: 'fa-check-circle',
            title: "You're all set!",
            description: 'You now know the main Kabataan features. You can relaunch this tutorial anytime using the question mark button in the header, to the left of Messages.',
            placement: 'center',
            isSidebar: false
        }
    ];

    // ── Engine State ─────────────────────────────────────────────────────────
    let currentStepIndex = 0;
    let isActive = false;
    let canSkip = false;
    let isMandatory = true;
    let finishInFlight = false;
    let autoOpenedMobileSidebar = false;
    let currentHighlightedEl = null;
    let windowResizeDebounce = null;

    // ── DOM Element Cache ────────────────────────────────────────────────────
    let rootEl = null;
    let backdropEl = null;
    let spotlightEl = null;
    let cardEl = null;
    let badgeEl = null;
    let iconEl = null;
    let titleEl = null;
    let descEl = null;
    let skipBtn = null;
    let backBtn = null;
    let nextBtn = null;
    let nextLabel = null;
    let nextIcon = null;
    let closeBtn = null;
    let confirmOverlay = null;
    let confirmCancelBtn = null;
    let confirmSkipBtn = null;

    function getCsrfToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenMeta ? tokenMeta.getAttribute('content') : '';
    }

    function isMobileViewport() {
        // Match programs-drawer / header CSS (drawer button appears ≤1200px).
        return window.matchMedia('(max-width: 1200px)').matches;
    }

    function isElementUsable(el) {
        if (!el || !el.getBoundingClientRect) return false;
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
            return false;
        }
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function resolveTargetEl(step) {
        if (!step || !step.target) return null;

        if (step.isSidebar && isMobileViewport()) {
            const inDrawer = document.querySelectorAll('#programsDrawerSidebar ' + step.target);
            for (let i = 0; i < inDrawer.length; i += 1) {
                if (isElementUsable(inDrawer[i]) || inDrawer[i].closest('#programsDrawerSidebar')) {
                    return inDrawer[i];
                }
            }
            if (inDrawer.length) return inDrawer[0];
        }

        const matches = document.querySelectorAll(step.target);
        for (let i = 0; i < matches.length; i += 1) {
            if (isElementUsable(matches[i])) return matches[i];
        }
        return matches.length ? matches[0] : null;
    }

    function isProgramsDrawerOpen() {
        const drawer = document.getElementById('programsDrawerSidebar');
        return !!(drawer && (drawer.classList.contains('drawer-open') || drawer.classList.contains('active')));
    }

    function waitForLayoutSettle(ms) {
        return new Promise(function (resolve) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    window.setTimeout(resolve, ms || 0);
                });
            });
        });
    }

    function openProgramsDrawerForTour() {
        if (!isMobileViewport()) return Promise.resolve(false);
        if (isProgramsDrawerOpen()) {
            document.body.classList.add('sk-tour-active-sidebar');
            return Promise.resolve(false);
        }

        if (typeof window.kabataanOpenProgramsDrawer === 'function') {
            window.kabataanOpenProgramsDrawer();
            autoOpenedMobileSidebar = true;
        } else {
            var drawer = document.getElementById('programsDrawerSidebar');
            var drawerBtn = document.getElementById('programsDrawerBtn');
            if (drawer && drawerBtn && !drawer.classList.contains('drawer-open') && !drawer.classList.contains('active')) {
                drawerBtn.click();
                autoOpenedMobileSidebar = true;
            }
        }

        document.body.classList.add('sk-tour-active-sidebar');
        return waitForLayoutSettle(340);
    }

    function closeProgramsDrawerForTour() {
        document.body.classList.remove('sk-tour-active-sidebar');
        if (!autoOpenedMobileSidebar) return;
        autoOpenedMobileSidebar = false;
        if (typeof window.kabataanCloseProgramsDrawer === 'function') {
            window.kabataanCloseProgramsDrawer();
            return;
        }
        var closeBtn = document.querySelector('[data-programs-drawer-close]');
        if (closeBtn) closeBtn.click();
    }

    function cacheDom() {
        rootEl = document.getElementById('skTourRoot');
        if (!rootEl) return false;

        backdropEl = document.getElementById('skTourBackdrop');
        spotlightEl = document.getElementById('skTourSpotlight');
        cardEl = document.getElementById('skTourCard');
        badgeEl = document.getElementById('skTourBadge');
        iconEl = document.getElementById('skTourStepIcon');
        titleEl = document.getElementById('skTourTitle');
        descEl = document.getElementById('skTourDescription');
        skipBtn = document.getElementById('skTourSkipBtn');
        backBtn = document.getElementById('skTourBackBtn');
        nextBtn = document.getElementById('skTourNextBtn');
        nextLabel = document.getElementById('skTourNextLabel');
        nextIcon = document.getElementById('skTourNextIcon');
        closeBtn = document.getElementById('skTourCloseBtn');
        confirmOverlay = document.getElementById('skTourConfirmOverlay');
        confirmCancelBtn = document.getElementById('skTourConfirmCancelBtn');
        confirmSkipBtn = document.getElementById('skTourConfirmSkipBtn');

        return true;
    }

    function applyTutorialPayload(tutorial) {
        if (!tutorial || typeof tutorial !== 'object') return;
        canSkip = tutorial.can_skip === true;
        isMandatory = tutorial.is_mandatory !== false && !canSkip;
        applyDismissControls();
    }

    function applyDismissControls() {
        const allowDismiss = canSkip && !isMandatory;

        if (skipBtn) {
            skipBtn.hidden = !allowDismiss;
            skipBtn.style.display = allowDismiss ? 'inline-flex' : 'none';
            skipBtn.setAttribute('aria-hidden', allowDismiss ? 'false' : 'true');
        }

        if (closeBtn) {
            closeBtn.hidden = !allowDismiss;
            closeBtn.style.display = allowDismiss ? 'inline-flex' : 'none';
            closeBtn.setAttribute('aria-hidden', allowDismiss ? 'false' : 'true');
        }

        if (rootEl) {
            rootEl.classList.toggle('sk-tour-mandatory', !allowDismiss);
        }

        if (backBtn && isActive) {
            // First-run: Next/Finish only. Replay: Back allowed.
            const showBack = allowDismiss && currentStepIndex > 0;
            backBtn.style.display = showBack ? 'inline-flex' : 'none';
        }
    }

    // ── API Communications ───────────────────────────────────────────────────
    async function apiRequest(endpoint, data = null, forcePost = false) {
        try {
            const usePost = forcePost || data !== null;
            const options = {
                method: usePost ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            };
            if (usePost) {
                options.body = JSON.stringify(data && typeof data === 'object' ? data : {});
            }

            const response = await fetch('/api/tutorial-guide/' + endpoint, options);
            let payload = null;
            try {
                payload = await response.json();
            } catch (_) {
                payload = null;
            }

            if (payload?.tutorial) {
                applyTutorialPayload(payload.tutorial);
            }

            if (!response.ok) {
                return { success: false, message: payload?.message || 'Request failed.', status: response.status };
            }

            return payload;
        } catch (err) {
            console.warn('Tutorial guide API call failed:', err);
            return null;
        }
    }

    // ── Step Resolution ──────────────────────────────────────────────────────
    function getValidSteps() {
        return TOUR_STEPS.filter(step => {
            if (!step.target) return true;
            return resolveTargetEl(step) !== null;
        });
    }

    function findNextValidIndex(fromIndex, forward = true) {
        const stepCount = TOUR_STEPS.length;
        let idx = fromIndex;

        while (true) {
            idx = forward ? idx + 1 : idx - 1;
            if (idx < 0 || idx >= stepCount) return -1;

            const step = TOUR_STEPS[idx];
            if (!step.target) return idx;

            if (resolveTargetEl(step)) return idx;
        }
    }

    function isFinalStep() {
        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return false;
        const validSteps = getValidSteps();
        const stepNumber = validSteps.findIndex(s => s.key === step.key) + 1;
        return currentStepIndex === TOUR_STEPS.length - 1 || stepNumber === validSteps.length;
    }

    // ── Position Spotlight & Card ────────────────────────────────────────────
    function updatePosition() {
        if (!isActive || !cardEl || !spotlightEl) return;

        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return;

        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        if (!step.target) {
            document.body.classList.remove('sk-tour-active-sidebar');
            spotlightEl.style.opacity = '0';
            spotlightEl.style.width = '0px';
            spotlightEl.style.height = '0px';
            spotlightEl.style.top = '-9999px';
            spotlightEl.style.left = '-9999px';

            const cardWidth = cardEl.offsetWidth || 380;
            const cardHeight = cardEl.offsetHeight || 220;
            const left = Math.max(16, (window.innerWidth - cardWidth) / 2);
            const top = Math.max(16, (window.innerHeight - cardHeight) / 2);

            cardEl.style.left = `${left}px`;
            cardEl.style.top = `${top}px`;
            return;
        }

        const prepareAndMeasure = function () {
            if (!isActive) return;
            const targetEl = resolveTargetEl(step);
            if (!targetEl) {
                goToNextStep();
                return;
            }

            const rect = targetEl.getBoundingClientRect();
            const inView = rect.top >= 72
                && rect.bottom <= window.innerHeight - 12
                && rect.left >= 0
                && rect.right <= window.innerWidth;
            if (!inView) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }

            waitForLayoutSettle(inView ? 40 : 220).then(function () {
                if (!isActive) return;
                const fresh = resolveTargetEl(step);
                if (!fresh) {
                    goToNextStep();
                    return;
                }

                fresh.classList.add('sk-tour-highlighted-element');
                currentHighlightedEl = fresh;

                const spotRect = fresh.getBoundingClientRect();
                const padding = 6;
                const spotLeft = Math.max(0, spotRect.left - padding);
                const spotTop = Math.max(0, spotRect.top - padding);
                const spotWidth = Math.min(window.innerWidth - spotLeft, spotRect.width + (padding * 2));
                const spotHeight = Math.min(window.innerHeight - spotTop, spotRect.height + (padding * 2));

                spotlightEl.style.opacity = '1';
                spotlightEl.style.left = `${spotLeft}px`;
                spotlightEl.style.top = `${spotTop}px`;
                spotlightEl.style.width = `${spotWidth}px`;
                spotlightEl.style.height = `${spotHeight}px`;

                positionTourCard(spotLeft, spotTop, spotWidth, spotHeight, step.placement || 'bottom');
            });
        };

        if (step.isSidebar && isMobileViewport()) {
            openProgramsDrawerForTour().then(prepareAndMeasure);
            return;
        }

        document.body.classList.remove('sk-tour-active-sidebar');
        prepareAndMeasure();
    }

    function positionTourCard(spotLeft, spotTop, spotWidth, spotHeight, preferredPlacement) {
        const cardWidth = cardEl.offsetWidth || 380;
        const cardHeight = cardEl.offsetHeight || 220;
        const margin = 14;
        const pad = 16;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let placement = preferredPlacement;
        if (isMobileViewport() && (placement === 'right' || placement === 'left')) {
            placement = 'bottom';
        }

        const targetTooLarge = (spotWidth * spotHeight) > (vw * vh * 0.35) || spotHeight > vh * 0.5;
        if (targetTooLarge) {
            placement = 'viewport-bottom';
        }

        let left = pad;
        let top = pad;

        if (placement === 'right') {
            left = spotLeft + spotWidth + margin;
            top = spotTop + (spotHeight - cardHeight) / 2;
            if (left + cardWidth > vw - pad) {
                placement = 'left';
            }
        }

        if (placement === 'left') {
            left = spotLeft - cardWidth - margin;
            top = spotTop + (spotHeight - cardHeight) / 2;
            if (left < pad) {
                placement = 'bottom';
            }
        }

        if (placement === 'top') {
            top = spotTop - cardHeight - margin;
            left = spotLeft + (spotWidth - cardWidth) / 2;
            if (top < pad) {
                placement = 'bottom';
            }
        }

        if (placement === 'bottom') {
            top = spotTop + spotHeight + margin;
            left = spotLeft + (spotWidth - cardWidth) / 2;
            if (top + cardHeight > vh - pad) {
                top = spotTop - cardHeight - margin;
            }
            if (top < pad || cardOverlapsSpotlight(left, top, cardWidth, cardHeight, spotLeft, spotTop, spotWidth, spotHeight)) {
                placement = 'viewport-bottom';
            }
        }

        if (placement === 'viewport-bottom') {
            left = (vw - cardWidth) / 2;
            top = vh - cardHeight - pad;
        }

        left = Math.max(pad, Math.min(vw - cardWidth - pad, left));
        top = Math.max(pad, Math.min(vh - cardHeight - pad, top));

        if (cardOverlapsSpotlight(left, top, cardWidth, cardHeight, spotLeft, spotTop, spotWidth, spotHeight)) {
            top = Math.max(pad, vh - cardHeight - pad);
            left = Math.max(pad, Math.min(vw - cardWidth - pad, (vw - cardWidth) / 2));
        }

        cardEl.style.left = `${left}px`;
        cardEl.style.top = `${top}px`;
    }

    function cardOverlapsSpotlight(left, top, cardWidth, cardHeight, spotLeft, spotTop, spotWidth, spotHeight) {
        const overlapX = left < spotLeft + spotWidth && left + cardWidth > spotLeft;
        const overlapY = top < spotTop + spotHeight && top + cardHeight > spotTop;
        return overlapX && overlapY;
    }

    // ── Render Step Content ──────────────────────────────────────────────────
    function renderStep() {
        const step = TOUR_STEPS[currentStepIndex];
        if (!step) return;

        const validSteps = getValidSteps();
        const stepNumber = validSteps.findIndex(s => s.key === step.key) + 1;
        const totalSteps = validSteps.length;

        badgeEl.textContent = `Step ${stepNumber} of ${totalSteps}`;
        titleEl.textContent = step.title;
        descEl.textContent = step.description;
        iconEl.className = `fas ${step.icon || 'fa-question-circle'}`;

        applyDismissControls();

        if (isFinalStep()) {
            nextLabel.textContent = 'Finish';
            nextIcon.className = 'fas fa-check';
            nextBtn.classList.add('sk-tour-btn--finish');
            nextBtn.disabled = false;
        } else {
            nextLabel.textContent = 'Next';
            nextIcon.className = 'fas fa-arrow-right';
            nextBtn.classList.remove('sk-tour-btn--finish');
            nextBtn.disabled = false;
        }

        window.requestAnimationFrame(() => {
            updatePosition();
        });

        apiRequest('progress', { step: currentStepIndex + 1 });
    }

    function setFinishLoading(loading) {
        if (!nextBtn || !nextLabel) return;
        nextBtn.disabled = !!loading;
        nextBtn.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (loading) {
            nextLabel.textContent = 'Saving...';
            nextIcon.className = 'fas fa-spinner fa-spin';
        } else if (isFinalStep()) {
            nextLabel.textContent = 'Finish';
            nextIcon.className = 'fas fa-check';
        }
    }

    // ── Navigation Handlers ──────────────────────────────────────────────────
    function goToNextStep() {
        if (finishInFlight) return;

        if (isFinalStep()) {
            finishTutorial();
            return;
        }

        const nextIdx = findNextValidIndex(currentStepIndex, true);
        if (nextIdx === -1) {
            finishTutorial();
            return;
        }

        if (TOUR_STEPS[currentStepIndex].isSidebar && !TOUR_STEPS[nextIdx].isSidebar) {
            closeProgramsDrawerForTour();
        }

        currentStepIndex = nextIdx;
        renderStep();
    }

    function goToPrevStep() {
        if (!canSkip || isMandatory || finishInFlight) return;

        const prevIdx = findNextValidIndex(currentStepIndex, false);
        if (prevIdx === -1) return;

        currentStepIndex = prevIdx;
        renderStep();
    }

    function showSkipConfirmation() {
        if (!canSkip || isMandatory) return;
        if (confirmOverlay) {
            confirmOverlay.style.display = 'flex';
        }
    }

    function hideSkipConfirmation() {
        if (confirmOverlay) {
            confirmOverlay.style.display = 'none';
        }
    }

    async function skipTutorial() {
        if (!canSkip || isMandatory) {
            hideSkipConfirmation();
            return;
        }

        hideSkipConfirmation();
        // Close immediately; persist skip in the background.
        endTour();
        apiRequest('skip', {}, true);
    }

    async function finishTutorial() {
        if (finishInFlight) return;
        finishInFlight = true;

        // Close UI immediately (0s); persist completion without blocking.
        canSkip = true;
        isMandatory = false;
        applyDismissControls();
        endTour();

        try {
            const res = await apiRequest('complete', {}, true);
            if (!res || res.success !== true) {
                // Keep tour closable on replay even if the network write failed.
                canSkip = true;
                isMandatory = false;
            }
        } finally {
            finishInFlight = false;
        }
    }

    function endTour() {
        isActive = false;
        finishInFlight = false;

        if (currentHighlightedEl) {
            currentHighlightedEl.classList.remove('sk-tour-highlighted-element');
            currentHighlightedEl = null;
        }

        if (autoOpenedMobileSidebar || document.body.classList.contains('sk-tour-active-sidebar')) {
            closeProgramsDrawerForTour();
        }

        if (rootEl) {
            rootEl.style.display = 'none';
            rootEl.setAttribute('aria-hidden', 'true');
            rootEl.classList.remove('sk-tour-mandatory');
        }

        hideSkipConfirmation();
        document.removeEventListener('keydown', handleKeydown);
    }

    function handleKeydown(e) {
        if (!isActive) return;

        if (e.key === 'ArrowRight' || e.key === 'Enter') {
            e.preventDefault();
            goToNextStep();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (canSkip && !isMandatory) {
                goToPrevStep();
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            if (canSkip && !isMandatory) {
                showSkipConfirmation();
            }
        }
    }

    // ── Launching Tour ───────────────────────────────────────────────────────
    async function startTour(fromScratch = false, initialStep = 1) {
        if (!cacheDom()) return;
        if (isActive) {
            endTour();
        }

        // Show the tour immediately (0s). Persist start/reset in the background.
        currentStepIndex = fromScratch
            ? 0
            : Math.max(0, Math.min(TOUR_STEPS.length - 1, (initialStep || 1) - 1));

        isActive = true;
        finishInFlight = false;
        rootEl.style.display = 'block';
        rootEl.setAttribute('aria-hidden', 'false');
        applyDismissControls();
        document.addEventListener('keydown', handleKeydown);
        renderStep();

        const persist = fromScratch
            ? apiRequest('reset', {}, true)
            : apiRequest('start', {}, true);
        persist.then((res) => {
            if (res?.tutorial) {
                applyTutorialPayload(res.tutorial);
            }
        });
    }

    // ── Setup Event Listeners ────────────────────────────────────────────────
    function bindUiEvents() {
        if (!cacheDom()) return;

        nextBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            goToNextStep();
        });

        backBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            goToPrevStep();
        });

        skipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            showSkipConfirmation();
        });

        closeBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            showSkipConfirmation();
        });

        confirmCancelBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            hideSkipConfirmation();
        });

        confirmSkipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            skipTutorial();
        });

        window.addEventListener('resize', () => {
            if (!isActive) return;
            window.clearTimeout(windowResizeDebounce);
            windowResizeDebounce = window.setTimeout(updatePosition, 100);
        });
        window.addEventListener('orientationchange', () => {
            if (!isActive) return;
            window.clearTimeout(windowResizeDebounce);
            windowResizeDebounce = window.setTimeout(updatePosition, 200);
        });
    }

    // ── Auto-Start Detection on First Login ──────────────────────────────────
    async function checkFirstLoginStatus() {
        const res = await apiRequest('status');
        if (!res || !res.success || !res.tutorial) return;

        applyTutorialPayload(res.tutorial);

        if (res.tutorial.should_auto_start) {
            const stepToStart = res.tutorial.current_step || 1;
            startTour(false, stepToStart);
        }
    }

    function launchTutorialFromHeader() {
        // Instant open on header click — no await / no artificial delay.
        startTour(true, 1);
    }

    function bindLaunchButton() {
        const btn = document.getElementById('tutorialGuideBtn');
        if (!btn || btn.dataset.tourBound === '1') {
            return;
        }

        btn.dataset.tourBound = '1';
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            launchTutorialFromHeader();
        });
    }

    // ── Global Interface ─────────────────────────────────────────────────────
    window.startKabataanTutorial = function (forceRestart = false) {
        startTour(forceRestart !== false, 1);
    };
    window.startSkOfficialTutorial = window.startKabataanTutorial;
    window.startSkFedTutorial = window.startKabataanTutorial;

    // ── Boot ─────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            bindUiEvents();
            bindLaunchButton();
            checkFirstLoginStatus();
        });
    } else {
        bindUiEvents();
        bindLaunchButton();
        checkFirstLoginStatus();
    }
})();
