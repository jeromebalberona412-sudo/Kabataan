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

    const sectionToNavMap = {
        hero: 'hero',
        about: 'about',
        benefits: 'about',
        'kk-profiling': 'about',
        activities: 'about',
        transparency: 'about',
        process: 'about',
        requirements: 'about',
        participation: 'about',
        'get-started': 'about',
        faq: 'faq',
        kabataanFooter: 'kabataanFooter',
        'barangay-abyip': 'barangay-abyip',
        barangays: 'barangays',
    };

    const setActiveLink = (sectionId) => {
        const navKey = sectionToNavMap[sectionId] || sectionId;
        if (!navKey) {
            clearActiveLinks();
            return;
        }

        navLinks.forEach((link) => {
            const linkSection = link.dataset.section || '';
            link.classList.toggle('active', linkSection === navKey);
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
        : [
            'hero',
            'about',
            'benefits',
            'kk-profiling',
            'activities',
            'transparency',
            'process',
            'requirements',
            'participation',
            'get-started',
            'faq',
            'kabataanFooter',
        ]
            .map((id) => document.getElementById(id))
            .filter(Boolean);

    if (trackedSections.length > 0) {
        const updateScrollActive = () => {
            if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 60)) {
                setActiveLink('kabataanFooter');
                return;
            }
            if (window.scrollY < 80) {
                setActiveLink('hero');
                return;
            }
        };

        window.addEventListener('scroll', updateScrollActive, { passive: true });

        if ('IntersectionObserver' in window) {
            const visibility = new Map();

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    visibility.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
                });

                if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 60)) {
                    setActiveLink('kabataanFooter');
                    return;
                }

                if (window.scrollY < 80) {
                    setActiveLink('hero');
                    return;
                }

                let activeId = '';
                let bestRatio = 0;

                visibility.forEach((ratio, id) => {
                    if (ratio > bestRatio) {
                        bestRatio = ratio;
                        activeId = id;
                    }
                });

                if (bestRatio >= 0.15 && activeId) {
                    setActiveLink(activeId);
                }
            }, {
                root: null,
                threshold: [0, 0.15, 0.3, 0.5, 0.7],
                rootMargin: '-15% 0px -45% 0px',
            });

            trackedSections.forEach((section) => observer.observe(section));
        }
    }

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const sectionId = link.dataset.section || '';
            const linkHref = link.getAttribute('href') || '';
            const linkPath = linkHref.startsWith('/')
                ? linkHref.split('#')[0]
                : (linkHref ? (new URL(linkHref, window.location.origin)).pathname : '');
            const currentPath = window.location.pathname;
            const isHomepage = currentPath === '/homepage' || currentPath === '/';
            const targetsHomepage = linkPath === '/homepage' || linkPath === '/' || linkPath === '';

            if (isHomepage && targetsHomepage && sectionId) {
                const targetId = sectionId === 'hero' ? 'hero' : sectionId;
                const target = document.getElementById(targetId);
                if (target) {
                    event.preventDefault();
                    setActiveLink(sectionId);
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if (history.pushState) {
                        if (sectionId === 'hero') {
                            history.pushState(null, '', window.location.pathname);
                        } else {
                            history.pushState(null, '', '#' + sectionId);
                        }
                    }
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

    if (initialSection === 'barangay-abyip' || initialSection === 'barangays') {
        setActiveLink(initialSection);
    } else if (initialSection && initialSection !== 'hero') {
        requestAnimationFrame(() => scrollToSection(initialSection));
        setActiveLink(initialSection);
    } else {
        setActiveLink('hero');
    }

    applyFilters();
});
