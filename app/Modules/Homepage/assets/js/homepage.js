document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.style.scrollBehavior = 'smooth';

    const navToggle = document.getElementById('kabataanNavToggle');
    const drawer = document.getElementById('kabataanDrawer');
    const navLinks = Array.from(document.querySelectorAll('.kabataan-nav-link, .kabataan-drawer-link'));
    const tabs = Array.from(document.querySelectorAll('.kabataan-tab'));
    const cards = Array.from(document.querySelectorAll('.kabataan-barangay-card'));
    const searchInput = document.getElementById('barangaySearch');
    const resultLabel = document.getElementById('barangayResultLabel');

    const state = {
        filter: 'all',
        query: '',
    };

    const scrollToSection = (sectionId) => {
        const target = document.getElementById(sectionId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const setDrawerOpen = (open) => {
        if (!navToggle || !drawer) {
            return;
        }

        drawer.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navToggle.classList.toggle('is-active', open);

        if (!open) {
            navToggle.blur();
            document.activeElement?.blur();
        }
    };

    const clearActiveLinks = () => {
        navLinks.forEach((link) => link.classList.remove('active'));
    };

    const setActiveLink = (sectionId) => {
        if (!sectionId || sectionId === 'kabataanFooter') {
            clearActiveLinks();
            return;
        }

        navLinks.forEach((link) => {
            const linkSection = link.dataset.section || '';
            link.classList.toggle('active', linkSection === sectionId);
        });
    };

    const applyFilters = () => {
        let visible = 0;

        cards.forEach((card) => {
            const barangay = (card.dataset.barangay || '').toLowerCase();
            const haystack = card.innerText.toLowerCase();
            const matchesFilter = state.filter === 'all' || barangay === state.filter;
            const matchesSearch = state.query === '' || haystack.includes(state.query);
            const show = matchesFilter && matchesSearch;

            card.hidden = !show;
            if (show) {
                visible += 1;
            }
        });

        if (resultLabel) {
            resultLabel.textContent = `${visible} highlight${visible === 1 ? '' : 's'} showing`;
        }
    };

    navToggle?.addEventListener('click', () => {
        const isOpen = drawer?.classList.contains('open');
        setDrawerOpen(!isOpen);
    });

    drawer?.addEventListener('click', (event) => {
        const target = event.target;
        if (target instanceof HTMLElement && target.closest('a')) {
            setDrawerOpen(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (!drawer || !navToggle || !drawer.classList.contains('open')) {
            return;
        }

        const target = event.target;
        if (target instanceof HTMLElement && !drawer.contains(target) && !navToggle.contains(target)) {
            setDrawerOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setDrawerOpen(false);
        }
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((item) => item.classList.remove('active'));
            tab.classList.add('active');
            state.filter = tab.dataset.filter || 'all';
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', () => {
        state.query = searchInput.value.trim().toLowerCase();
        applyFilters();
    });

    const isBarangayListPage = window.location.pathname.includes('barangay-accomplishments')
        || window.location.pathname.includes('barangay-abyip');

    const trackedSections = isBarangayListPage
        ? []
        : ['hero', 'about', 'faq', 'kabataanFooter']
            .map((id) => document.getElementById(id))
            .filter(Boolean);

    if ('IntersectionObserver' in window && trackedSections.length > 0) {
        const visibility = new Map();

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                visibility.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
            });

            let activeId = '';
            let bestRatio = 0;

            visibility.forEach((ratio, id) => {
                if (ratio > bestRatio) {
                    bestRatio = ratio;
                    activeId = id;
                }
            });

            if (bestRatio >= 0.2) {
                setActiveLink(activeId);
            } else {
                clearActiveLinks();
            }
        }, {
            root: null,
            threshold: [0, 0.15, 0.3, 0.5, 0.7],
            rootMargin: '-20% 0px -55% 0px',
        });

        trackedSections.forEach((section) => observer.observe(section));
    }

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const sectionId = link.dataset.section;
            if (sectionId === 'kabataanFooter') {
                clearActiveLinks();
            } else if (sectionId) {
                setActiveLink(sectionId);
            }

            const linkHref = link.getAttribute('href') || '';
            const linkPath = linkHref.startsWith('/')
                ? linkHref
                : (linkHref ? (new URL(linkHref, window.location.origin)).pathname : '');
            const currentPath = window.location.pathname;
            const isHomepage = currentPath === '/homepage' || currentPath === '/';
            const targetsHomepage = linkPath === '/homepage' || linkPath === '/';

            if (isHomepage && targetsHomepage && sectionId) {
                const target = document.getElementById(sectionId);
                if (target) {
                    event.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            link.blur();
            setDrawerOpen(false);
        });
    });

    const initialSection = (() => {
        if (window.location.pathname.includes('barangay-abyip')) {
            return 'barangay-abyip';
        }

        if (window.location.pathname.includes('barangay-accomplishments')) {
            return 'barangays';
        }

        const hashId = window.location.hash.replace('#', '');
        if (hashId && document.getElementById(hashId)) {
            return hashId;
        }

        const dataScroll = document.body.dataset.scrollTo;
        if (dataScroll && document.getElementById(dataScroll)) {
            return dataScroll;
        }
        return 'hero';
    })();

    if (initialSection === 'barangays') {
        setActiveLink('barangays');
    } else if (initialSection && initialSection !== 'hero') {
        requestAnimationFrame(() => scrollToSection(initialSection));
        setActiveLink(initialSection === 'kabataanFooter' ? '' : initialSection);
    } else {
        setActiveLink('hero');
    }

    applyFilters();
});
