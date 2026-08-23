/**
 * Shared sports applications history — year-grouped table + view/cancel modals
 */
(function (global) {
    'use strict';

    const CANCEL_REASON_MAX = 500;
    let activeCancelApplicationId = null;
    let onRefresh = null;
    let allApplications = [];
    let historyContainerId = 'sportsApplicationsHistory';
    let activeSportTabFilter = 'basketball';

    const applicationViewModal = document.getElementById('applicationViewModal');
    const applicationViewContainer = document.getElementById('applicationViewContainer');
    const applicationViewMaximize = document.getElementById('applicationViewMaximize');
    const applicationViewClose = document.getElementById('applicationViewClose');
    const applicationCancelModal = document.getElementById('applicationCancelModal');
    const applicationCancelDismissBtn = document.getElementById('applicationCancelDismissBtn');
    const applicationCancelBtn = document.getElementById('applicationCancelBtn');
    const applicationCancelReason = document.getElementById('applicationCancelReason');
    const applicationCancelConfirm = document.getElementById('applicationCancelConfirm');
    const applicationCancelCharCount = document.getElementById('applicationCancelCharCount');
    const applicationCancelError = document.getElementById('applicationCancelError');

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getStatusClass(status) {
        switch (String(status || '').toLowerCase()) {
            case 'pending': return 'sl-status-pending';
            case 'approved': return 'sl-status-approved';
            case 'rejected': return 'sl-status-rejected';
            case 'cancelled': return 'sl-status-cancelled';
            default: return 'sl-status-pending';
        }
    }

    function resolveApplicationYear(app) {
        if (app?.application_year) {
            return String(app.application_year);
        }

        if (app?.submitted_at_iso) {
            const date = new Date(app.submitted_at_iso);
            if (!Number.isNaN(date.getTime())) {
                return String(date.getFullYear());
            }
        }

        return 'Unknown';
    }

    function groupApplicationsByYear(applications) {
        const groups = {};

        applications.forEach((app) => {
            const year = resolveApplicationYear(app);
            if (!groups[year]) {
                groups[year] = [];
            }
            groups[year].push(app);
        });

        return Object.keys(groups)
            .sort((a, b) => {
                const yearA = Number(a);
                const yearB = Number(b);
                if (!Number.isNaN(yearA) && !Number.isNaN(yearB)) {
                    return yearB - yearA;
                }
                return String(b).localeCompare(String(a));
            })
            .map((year) => ({
                year,
                applications: groups[year].sort((left, right) => {
                    const leftTime = Date.parse(left.submitted_at_iso || '') || 0;
                    const rightTime = Date.parse(right.submitted_at_iso || '') || 0;
                    return rightTime - leftTime;
                }),
            }));
    }

    function resolveTeamName(app) {
        const direct = String(app?.team_name || '').trim();
        if (direct) {
            return direct;
        }

        const preview = Array.isArray(app?.answers_preview) ? app.answers_preview : [];
        const teamItem = preview.find((item) => {
            const label = String(item?.label || '').toLowerCase();
            return label === 'team name' || label.includes('team name');
        });

        const value = String(teamItem?.value || '').trim();
        return value && value !== '—' ? value : '—';
    }

    function setViewModalMaximized(isMaximized) {
        if (!applicationViewContainer) return;
        applicationViewContainer.classList.toggle('is-fullscreen', isMaximized);
        if (applicationViewMaximize) {
            applicationViewMaximize.title = isMaximized ? 'Restore Down' : 'Maximize';
            applicationViewMaximize.setAttribute('aria-label', isMaximized ? 'Restore Down' : 'Maximize');
            const maximizeIcon = applicationViewMaximize.querySelector('.sl-modal-icon-maximize');
            const restoreIcon = applicationViewMaximize.querySelector('.sl-modal-icon-restore');
            if (maximizeIcon) maximizeIcon.hidden = isMaximized;
            if (restoreIcon) restoreIcon.hidden = !isMaximized;
        }
    }

    function resolveSportDisplayLabel(app) {
        const label = String(app?.sport_label || '').trim();
        if (label && label.toLowerCase() !== 'other') {
            return label;
        }

        const key = String(app?.sport_key || '').toLowerCase();
        if (key === 'basketball') return 'Basketball';
        if (key === 'volleyball') return 'Volleyball';

        return label || '—';
    }

    function resolveApplicationSportKey(app) {
        const key = String(app?.sport_key || '').toLowerCase();
        if (key === 'basketball' || key === 'volleyball' || key === 'other') {
            return key;
        }

        const label = String(app?.sport_label || resolveSportDisplayLabel(app)).toLowerCase();
        if (label.includes('basketball')) return 'basketball';
        if (label.includes('volleyball')) return 'volleyball';

        return 'other';
    }

    function resolveSportTabLabel(tab) {
        if (tab === 'basketball') return 'Basketball';
        if (tab === 'volleyball') return 'Volleyball';
        const otherTab = document.querySelector('#sportsTypeTabs [data-sport-tab="other"]');
        if (otherTab && !otherTab.hidden) {
            return otherTab.textContent.trim() || 'Other';
        }
        return 'Other';
    }

    function applicationSearchText(app) {
        return [
            resolveSportDisplayLabel(app),
            app.program_name,
            app.program_period,
            app.submitted_at,
            app.status_display || app.status,
            resolveTeamName(app),
        ].join(' ').toLowerCase();
    }

    function filterApplications(applications) {
        const yearFilter = document.getElementById('sportsHistoryYearFilter')?.value || '';
        const searchQuery = (document.getElementById('sportsHistorySearch')?.value || '').trim().toLowerCase();

        return applications.filter((app) => {
            if (activeSportTabFilter && resolveApplicationSportKey(app) !== activeSportTabFilter) {
                return false;
            }
            if (yearFilter && resolveApplicationYear(app) !== yearFilter) {
                return false;
            }
            if (searchQuery && !applicationSearchText(app).includes(searchQuery)) {
                return false;
            }
            return true;
        });
    }

    function setSportTabFilter(tab) {
        activeSportTabFilter = tab || 'basketball';
        renderFilteredHistory();
    }

    function updateHistorySectionVisibility(applications) {
        const hasApplications = Array.isArray(applications) && applications.length > 0;
        const historySection = document.querySelector('.sports-applications-history-section');
        const toolbar = document.getElementById('sportsHistoryToolbar');

        if (historySection) {
            historySection.hidden = !hasApplications;
        }
        if (toolbar) {
            toolbar.hidden = !hasApplications;
        }
    }

    function populateYearFilter(applications) {
        const select = document.getElementById('sportsHistoryYearFilter');
        const toolbar = document.getElementById('sportsHistoryToolbar');
        if (!select) return;

        const years = [...new Set(applications.map(resolveApplicationYear))]
            .filter((year) => year && year !== 'Unknown')
            .sort((a, b) => Number(b) - Number(a));

        const current = select.value;
        select.innerHTML = '<option value="">All Years</option>'
            + years.map((year) => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`).join('');

        if (current && years.includes(current)) {
            select.value = current;
        }

        if (toolbar) {
            toolbar.hidden = applications.length === 0;
        }
        updateHistorySectionVisibility(applications);
    }

    function renderFilteredHistory() {
        const container = document.getElementById(historyContainerId);
        if (!container) return;

        const filtered = filterApplications(allApplications);
        if (!filtered.length) {
            const hasYearOrSearch = Boolean(
                document.getElementById('sportsHistoryYearFilter')?.value
                || document.getElementById('sportsHistorySearch')?.value?.trim()
            );
            const hasAnyApplications = allApplications.length > 0;
            const hasSportApplications = allApplications.some(
                (app) => resolveApplicationSportKey(app) === activeSportTabFilter
            );

            let message = 'No previous sports applications found.';
            if (hasYearOrSearch) {
                message = 'No applications match your filters.';
            } else if (hasAnyApplications && !hasSportApplications) {
                message = `No ${resolveSportTabLabel(activeSportTabFilter)} applications found.`;
            }

            container.innerHTML = `<p class="sports-history-empty">${escapeHtml(message)}</p>`;
            return;
        }

        renderHistoryTable(container, filtered);
    }

    function bindHistoryFilterEvents() {
        const yearFilter = document.getElementById('sportsHistoryYearFilter');
        const searchInput = document.getElementById('sportsHistorySearch');

        if (yearFilter && yearFilter.dataset.bound !== '1') {
            yearFilter.dataset.bound = '1';
            yearFilter.addEventListener('change', renderFilteredHistory);
        }

        if (searchInput && searchInput.dataset.bound !== '1') {
            searchInput.dataset.bound = '1';
            searchInput.addEventListener('input', renderFilteredHistory);
        }
    }

    function resetCancelConfirmButton() {
        if (!applicationCancelBtn) return;
        applicationCancelBtn.disabled = true;
        applicationCancelBtn.classList.remove('is-enabled');
        applicationCancelBtn.classList.add('is-disabled');
    }

    function syncCancelConfirmButton() {
        if (!applicationCancelBtn) return;
        const matched = (applicationCancelConfirm?.value?.trim() || '') === 'Confirm';
        applicationCancelBtn.disabled = !matched;
        applicationCancelBtn.classList.toggle('is-enabled', matched);
        applicationCancelBtn.classList.toggle('is-disabled', !matched);
    }

    function updateCancelReasonCharCount() {
        if (!applicationCancelCharCount || !applicationCancelReason) return;
        const length = applicationCancelReason.value.length;
        applicationCancelCharCount.textContent = `${length} / ${CANCEL_REASON_MAX} characters`;
        applicationCancelCharCount.classList.toggle('is-limit', length >= CANCEL_REASON_MAX);
    }

    function resetCancelReasonForm() {
        if (applicationCancelReason) applicationCancelReason.value = '';
        if (applicationCancelConfirm) applicationCancelConfirm.value = '';
        updateCancelReasonCharCount();
        resetCancelConfirmButton();
        if (applicationCancelError) {
            applicationCancelError.hidden = true;
            applicationCancelError.textContent = '';
        }
    }

    function openCancelApplicationModal(applicationId) {
        activeCancelApplicationId = applicationId;
        resetCancelReasonForm();
        if (applicationCancelModal) {
            applicationCancelModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
    }

    function closeCancelReasonModal() {
        activeCancelApplicationId = null;
        resetCancelReasonForm();
        if (applicationCancelModal) {
            applicationCancelModal.hidden = true;
        }
        if (!applicationViewModal || applicationViewModal.hidden) {
            document.body.style.overflow = '';
        }
    }

    async function fetchApplications() {
        const response = await fetch('/api/kabataan/programs/applications?letter=I', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return [];
        }

        const data = await response.json();
        return data.applications || [];
    }

    async function fetchApplication(applicationId) {
        const response = await fetch(`/api/kabataan/programs/applications/${applicationId}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || 'Unable to load application.');
        }
        return data.application;
    }

    function renderHistoryTable(container, applications) {
        if (!container) {
            return;
        }

        if (!applications.length) {
            container.innerHTML = '<p class="sports-history-empty">No previous sports applications found.</p>';
            return;
        }

        const grouped = groupApplicationsByYear(applications);

        container.innerHTML = grouped.map(({ year, applications: yearApps }) => `
            <div class="sports-history-year-group">
                <h3 class="sports-history-year-heading">${escapeHtml(year)}</h3>
                <div class="sl-table-wrapper sports-history-table-wrap">
                    <table class="sl-table sports-history-table">
                        <thead>
                            <tr>
                                <th class="col-sport">Sport</th>
                                <th class="col-program">Program</th>
                                <th class="col-period">Period</th>
                                <th class="col-submitted">Submitted</th>
                                <th class="col-status">Status</th>
                                <th class="col-team">Team Name</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${yearApps.map((app) => {
                                const canCancel = app.can_cancel && String(app.status || '').toLowerCase() === 'pending';
                                return `
                                <tr>
                                    <td data-label="Sport" class="col-sport">${escapeHtml(resolveSportDisplayLabel(app))}</td>
                                    <td data-label="Program" class="col-program">${escapeHtml(app.program_name || 'Program')}</td>
                                    <td data-label="Period" class="col-period">${escapeHtml(app.program_period || '—')}</td>
                                    <td data-label="Submitted" class="col-submitted">${escapeHtml(app.submitted_at || '—')}</td>
                                    <td data-label="Status" class="col-status">
                                        <span class="sl-status-badge ${getStatusClass(app.status)}">${escapeHtml(app.status_display || app.status)}</span>
                                    </td>
                                    <td data-label="Team Name" class="col-team">
                                        <span class="sports-history-team-name">${escapeHtml(resolveTeamName(app))}</span>
                                    </td>
                                    <td data-label="Actions" class="col-actions">
                                        <div class="sl-table-actions">
                                            <button type="button" class="sl-btn-action sl-btn-view" data-view-application="${app.id}">View</button>
                                            ${canCancel ? `<button type="button" class="sl-btn-action sl-btn-cancel" data-cancel-application="${app.id}">Cancel</button>` : ''}
                                        </div>
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `).join('');

        container.querySelectorAll('[data-view-application]').forEach((button) => {
            button.addEventListener('click', () => {
                openApplicationView(Number(button.getAttribute('data-view-application')));
            });
        });

        container.querySelectorAll('[data-cancel-application]').forEach((button) => {
            button.addEventListener('click', () => {
                openCancelApplicationModal(Number(button.getAttribute('data-cancel-application')));
            });
        });
    }

    function renderPersonalInfoItems(items) {
        if (!items?.length) {
            return '<p class="sl-view-empty">No personal information available.</p>';
        }

        return items.map((item) => `
            <div class="sl-view-personal-item sports-view-field">
                <span class="sl-view-personal-label">${escapeHtml(item.label)}</span>
                <span class="sl-view-personal-value">${escapeHtml(item.value || '—')}</span>
            </div>
        `).join('');
    }

    function renderAnswerItems(answers) {
        if (!answers?.length) {
            return '<p class="sl-view-empty">No answers submitted.</p>';
        }

        return answers.map((answer) => {
            if (answer.question_type === 'file' && answer.answer && typeof answer.answer === 'object') {
                const file = answer.answer;
                const fileUrl = file.preview_url || file.download_url || '#';
                return `
                    <div class="sl-answer-item sports-view-answer-card">
                        <p class="sports-view-answer-label">${escapeHtml(answer.question_label || 'Document')}</p>
                        <a href="${escapeHtml(fileUrl)}" class="sports-view-file-link" target="_blank" rel="noopener">
                            <span class="sports-view-file-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </span>
                            <span class="sports-view-file-meta">
                                <span class="sports-view-file-name">${escapeHtml(file.original_name || 'Uploaded Document')}</span>
                                <span class="sports-view-file-action">View document</span>
                            </span>
                        </a>
                    </div>`;
            }

            const displayAnswer = Array.isArray(answer.answer)
                ? answer.answer.join(', ')
                : (typeof answer.answer === 'object' ? JSON.stringify(answer.answer) : (answer.answer || '—'));

            return `
                <div class="sl-answer-item sports-view-answer-card">
                    <p class="sports-view-answer-label">${escapeHtml(answer.question_label || 'Question')}</p>
                    <p class="sports-view-answer-value">${escapeHtml(displayAnswer)}</p>
                </div>`;
        }).join('');
    }

    function openApplicationView(applicationId) {
        setViewModalMaximized(false);

        if (applicationViewModal) {
            applicationViewModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        const applicationViewAnswers = document.getElementById('applicationViewAnswers');
        const applicationViewPersonalInfo = document.getElementById('applicationViewPersonalInfo');

        if (applicationViewPersonalInfo) {
            applicationViewPersonalInfo.innerHTML = '';
        }
        if (applicationViewAnswers) {
            applicationViewAnswers.innerHTML = '<p class="sl-view-empty">Loading application details…</p>';
        }

        fetchApplication(applicationId)
            .then((application) => {
                const applicationViewTitle = document.getElementById('applicationViewTitle');
                const applicationViewCancelledInfo = document.getElementById('applicationViewCancelledInfo');
                const applicationViewCancelledReason = document.getElementById('applicationViewCancelledReason');

                if (applicationViewTitle) {
                    applicationViewTitle.textContent = application.program_name || 'Application Details';
                }
                if (applicationViewPersonalInfo) {
                    applicationViewPersonalInfo.innerHTML = renderPersonalInfoItems(application.personal_info || []);
                }
                if (applicationViewAnswers) {
                    applicationViewAnswers.innerHTML = renderAnswerItems(application.answers || []);
                }
                if (applicationViewCancelledInfo) {
                    applicationViewCancelledInfo.hidden = application.status !== 'cancelled';
                    if (applicationViewCancelledReason) {
                        applicationViewCancelledReason.textContent = application.cancel_reason || 'No reason provided.';
                    }
                }
            })
            .catch((error) => {
                closeApplicationView();
                alert(error.message || 'Unable to load application.');
            });
    }

    function closeApplicationView() {
        setViewModalMaximized(false);
        if (applicationViewModal) {
            applicationViewModal.hidden = true;
        }
        if (!applicationCancelModal || applicationCancelModal.hidden) {
            document.body.style.overflow = '';
        }
    }

    async function cancelApplication(applicationId, cancelReason) {
        const response = await fetch(`/api/kabataan/programs/applications/${applicationId}/cancel`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ cancel_reason: cancelReason }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || 'Unable to cancel application.');
        }
        return data;
    }

    async function handleCancelApplication() {
        if (!activeCancelApplicationId || !applicationCancelReason) {
            return;
        }

        const reason = applicationCancelReason.value.trim();
        const confirmText = applicationCancelConfirm?.value?.trim() || '';

        if (reason.length < 3) {
            if (applicationCancelError) {
                applicationCancelError.textContent = 'Please type your cancel reason (at least 3 characters).';
                applicationCancelError.hidden = false;
            }
            return;
        }

        if (reason.length > CANCEL_REASON_MAX) {
            if (applicationCancelError) {
                applicationCancelError.textContent = `Cancel reason must not exceed ${CANCEL_REASON_MAX} characters.`;
                applicationCancelError.hidden = false;
            }
            return;
        }

        if (confirmText !== 'Confirm') {
            if (applicationCancelError) {
                applicationCancelError.textContent = 'Please type Confirm to cancel your sports application.';
                applicationCancelError.hidden = false;
            }
            return;
        }

        if (applicationCancelBtn) {
            applicationCancelBtn.disabled = true;
            applicationCancelBtn.textContent = 'Cancelling...';
        }

        try {
            await cancelApplication(activeCancelApplicationId, reason);
            closeCancelReasonModal();
            if (typeof onRefresh === 'function') {
                await onRefresh();
            }
        } catch (error) {
            if (applicationCancelError) {
                applicationCancelError.textContent = error.message;
                applicationCancelError.hidden = false;
            }
        } finally {
            if (applicationCancelBtn) {
                applicationCancelBtn.textContent = 'Confirm Cancel';
                syncCancelConfirmButton();
            }
        }
    }

    function bindModalEvents() {
        if (applicationViewModal?.dataset.historyBound === '1') {
            return;
        }

        applicationViewClose?.addEventListener('click', closeApplicationView);
        applicationViewModal?.querySelector('.sl-view-modal-overlay')?.addEventListener('click', closeApplicationView);

        applicationViewMaximize?.addEventListener('click', (event) => {
            event.stopPropagation();
            const isMaximized = applicationViewContainer?.classList.contains('is-fullscreen');
            setViewModalMaximized(!isMaximized);
        });

        applicationCancelBtn?.addEventListener('click', handleCancelApplication);
        applicationCancelDismissBtn?.addEventListener('click', closeCancelReasonModal);
        applicationCancelModal?.addEventListener('click', (event) => {
            if (event.target === applicationCancelModal) {
                closeCancelReasonModal();
            }
        });

        applicationCancelReason?.addEventListener('input', updateCancelReasonCharCount);
        applicationCancelConfirm?.addEventListener('input', () => {
            if (applicationCancelError) {
                applicationCancelError.hidden = true;
                applicationCancelError.textContent = '';
            }
            syncCancelConfirmButton();
        });

        resetCancelConfirmButton();

        if (applicationViewModal) {
            applicationViewModal.dataset.historyBound = '1';
        }
    }

    async function loadAndRender(containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            return [];
        }

        historyContainerId = containerId;
        container.innerHTML = '<p class="sports-history-loading">Loading your sports applications…</p>';

        try {
            allApplications = await fetchApplications();
            populateYearFilter(allApplications);
            bindHistoryFilterEvents();
            renderFilteredHistory();
            return allApplications;
        } catch (error) {
            container.innerHTML = '<p class="sports-history-empty">Unable to load sports applications. Please try again later.</p>';
            console.error(error);
            return [];
        }
    }

    async function init(options = {}) {
        const containerId = options.containerId || 'sportsApplicationsHistory';
        historyContainerId = containerId;
        onRefresh = options.onRefresh || (async () => loadAndRender(containerId));
        bindModalEvents();
        bindHistoryFilterEvents();
        return loadAndRender(containerId);
    }

    global.SportsApplicationsHistory = {
        init,
        fetchApplications,
        renderHistoryTable,
        loadAndRender,
        setSportTabFilter,
        openApplicationView,
        openCancelApplicationModal,
        closeApplicationView,
        closeCancelReasonModal,
    };
})(window);
