document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('barangaySearch');
    const barangayGrid = document.getElementById('barangayGrid');
    const noResults = document.getElementById('noResults');
    const filterButtons = Array.from(document.querySelectorAll('.kk-signup-filter'));
    const jsErrorAlert = document.getElementById('jsErrorAlert');
    const jsErrorAlertText = document.getElementById('jsErrorAlertText');

    let activeFilter = 'all';
    const scheduleMap = new Map();

    const statusBadgeClass = {
        Ongoing: 'kk-signup-badge-ongoing',
        Upcoming: 'kk-signup-badge-upcoming',
        Rescheduled: 'kk-signup-badge-rescheduled',
        Completed: 'kk-signup-badge-completed',
        Cancelled: 'kk-signup-badge-cancelled',
    };

    function showError(message) {
        if (!jsErrorAlert || !jsErrorAlertText) return;
        jsErrorAlertText.innerHTML = '';
        const div = document.createElement('div');
        div.textContent = message;
        jsErrorAlertText.appendChild(div);
        jsErrorAlert.style.display = 'flex';
        jsErrorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function getNoScheduleMessage(barangayName) {
        return 'This barangay (' + barangayName + ') has no scheduled KK Profiling yet. Please contact your barangay SK officials for more information.';
    }

    function getClosedScheduleMessage(barangayName, sched) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const startDate = sched.date_start ? new Date(sched.date_start + 'T00:00:00') : null;
        const endDate = sched.date_expiry ? new Date(sched.date_expiry + 'T00:00:00') : null;

        switch (sched.status) {
            case 'Upcoming':
                return 'KK Profiling sign-up for ' + barangayName + ' is not yet open. The schedule will start soon.';
            case 'Completed':
                return 'KK Profiling sign-up for ' + barangayName + ' has already closed. Please wait for the next schedule.';
            case 'Cancelled':
                return 'KK Profiling sign-up for ' + barangayName + ' has been cancelled. Please contact your barangay SK officials.';
            case 'Rescheduled':
                return 'KK Profiling sign-up for ' + barangayName + ' has been rescheduled. Please check the new schedule dates.';
            case 'Ongoing':
                if (startDate && startDate > today) {
                    return 'KK Profiling sign-up for ' + barangayName + ' is not yet open. Sign-up will start on ' + fmtDate(sched.date_start) + '.';
                }
                if (endDate && endDate < today) {
                    return 'KK Profiling sign-up for ' + barangayName + ' has closed as of ' + fmtDate(sched.date_expiry) + '. Please wait for the next schedule.';
                }
                return 'KK Profiling sign-up for ' + barangayName + ' is not currently open. Please wait for the next schedule.';
            default:
                return 'KK Profiling sign-up for ' + barangayName + ' is not currently open.';
        }
    }

    function handleBarangayClick(btn) {
        const barangayName = btn.dataset.name || 'this barangay';
        const barangayId = Number(btn.dataset.barangayId);
        const sched = scheduleMap.get(barangayId);

        if (sched && Boolean(sched.is_open)) {
            window.location.href = '/kkprofiling/signup/' + btn.dataset.slug;
            return;
        }

        const message = sched
            ? getClosedScheduleMessage(barangayName, sched)
            : getNoScheduleMessage(barangayName);

        showError(message);
    }

    function fmtDate(str) {
        if (!str) return '';
        return new Date(str + 'T00:00:00').toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    function fmtShortDate(str) {
        if (!str) return '';
        return new Date(str + 'T00:00:00').toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
        });
    }

    function buildPeriodMarkup(sched) {
        const start = fmtDate(sched.date_start);
        const end = fmtDate(sched.date_expiry);
        const shortStart = fmtShortDate(sched.date_start);
        const shortEnd = fmtShortDate(sched.date_expiry);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const startDate = new Date(sched.date_start + 'T00:00:00');
        const endDate = new Date(sched.date_expiry + 'T00:00:00');

        let periodHtml = `<span class="kk-signup-period"><strong>Open:</strong> ${shortStart} – ${shortEnd}</span>`;

        if (sched.is_open) {
            const daysLeft = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
            periodHtml += `<span class="kk-signup-open-hint">Sign up open${daysLeft > 0 ? ` · ${daysLeft} day${daysLeft === 1 ? '' : 's'} left` : ''}</span>`;
        } else if (startDate > today) {
            periodHtml += `<span class="kk-signup-period">Opens ${start}</span>`;
        } else if (endDate < today) {
            periodHtml += `<span class="kk-signup-period">Closed ${end}</span>`;
        } else if (sched.status === 'Ongoing') {
            periodHtml += `<span class="kk-signup-period">Grace period: ${start} – ${end}</span>`;
        }

        return periodHtml;
    }

    function buildStatusBadge(sched) {
        const status = sched.status || 'none';
        const badgeClass = statusBadgeClass[status] || 'kk-signup-badge-none';
        return `<span class="kk-signup-badge ${badgeClass}">${status}</span>`;
    }

    function applyCardSchedule(btn, sched) {
        const meta = btn.querySelector('.kk-signup-barangay-meta');
        const isOpen = Boolean(sched.is_open);

        btn.dataset.status = sched.status;
        btn.classList.add('has-schedule');

        if (meta) {
            meta.innerHTML = `${buildStatusBadge(sched)}${buildPeriodMarkup(sched)}`;
        }

        if (isOpen) {
            btn.classList.add('is-open');
        } else {
            btn.classList.remove('is-open');
        }
    }

    function sortCards() {
        const cards = Array.from(barangayGrid.querySelectorAll('.kk-signup-barangay'));
        cards.sort((a, b) => {
            const aOpen = a.classList.contains('is-open') ? 0 : 1;
            const bOpen = b.classList.contains('is-open') ? 0 : 1;
            if (aOpen !== bOpen) return aOpen - bOpen;

            const aHas = a.classList.contains('has-schedule') ? 0 : 1;
            const bHas = b.classList.contains('has-schedule') ? 0 : 1;
            if (aHas !== bHas) return aHas - bHas;

            return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        });

        cards.forEach((card) => barangayGrid.appendChild(card));
    }

    function applyFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        barangayGrid.querySelectorAll('.kk-signup-barangay').forEach((btn) => {
            const nameMatch = (btn.dataset.name || '').toLowerCase().includes(query);
            const status = btn.dataset.status || 'none';
            const statusMatch = activeFilter === 'all'
                || (activeFilter === 'none' ? status === 'none' : status === activeFilter);

            const show = nameMatch && statusMatch;
            btn.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (noResults) {
            noResults.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.filter || 'all';
            filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', applyFilters);

    barangayGrid.querySelectorAll('.kk-signup-barangay').forEach((btn) => {
        btn.addEventListener('click', () => handleBarangayClick(btn));
    });

    fetch('/api/kkprofiling/open-barangays')
        .then((response) => response.json())
        .then(({ schedules }) => {
            schedules.forEach((item) => scheduleMap.set(item.barangay_id, item));

            barangayGrid.querySelectorAll('.kk-signup-barangay').forEach((btn) => {
                const sched = scheduleMap.get(Number(btn.dataset.barangayId));
                if (sched) {
                    applyCardSchedule(btn, sched);
                }
            });

            sortCards();
            applyFilters();
        })
        .catch(() => {
            applyFilters();
        });
});
