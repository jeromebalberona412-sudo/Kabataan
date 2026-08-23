/**
 * Sports Landing — sport-type tabs + program listing from SK Officials schedules
 */
(function () {
    'use strict';

    const scheduleProgramId = Number(window.__scheduleProgramId || 0);
    const SPORT_TABS = ['basketball', 'volleyball', 'other'];
    let activeSportTab = 'basketball';
    let sportsSchedules = [];
    let currentApplications = [];

    const sportsProgramsContainer = document.getElementById('sportsProgramsContainer');
    const sportsTypeTabs = document.getElementById('sportsTypeTabs');

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

    function resolveSportKey(schedule) {
        const key = schedule?.sport_key || schedule?.sports_details?.sport_key || 'other';
        const normalized = String(key).toLowerCase();
        return SPORT_TABS.includes(normalized) ? normalized : 'other';
    }

    function resolveSportLabel(schedule) {
        const details = schedule?.sports_details || {};
        const custom = schedule?.sport_label || details.sport_label || details.other_sport_name;
        if (custom && String(custom).trim() && String(custom).toLowerCase() !== 'other') {
            return String(custom).trim();
        }

        const key = resolveSportKey(schedule);
        if (key === 'basketball') return 'Basketball';
        if (key === 'volleyball') return 'Volleyball';
        if (key === 'other') {
            return details.other_sport_name
                || (details.sport_label && String(details.sport_label).toLowerCase() !== 'other' ? details.sport_label : '')
                || schedule?.program_name
                || 'Sports Program';
        }

        return schedule?.program_name || 'Sports Program';
    }

    function resolveOtherTabLabel() {
        const otherSchedules = sportsSchedules.filter((schedule) => resolveSportKey(schedule) === 'other');
        if (otherSchedules.length === 1) {
            return resolveSportLabel(otherSchedules[0]);
        }
        if (otherSchedules.length > 1) {
            return 'Other Sports';
        }
        return 'Other';
    }

    async function fetchProgramsPayload() {
        if (window.__kabataanPrograms) {
            return window.__kabataanPrograms;
        }

        const response = await fetch('/api/kabataan/programs', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load programs');
        }

        return response.json();
    }

    function updateSportTabsVisibility() {
        if (!sportsTypeTabs) return;

        const counts = { basketball: 0, volleyball: 0, other: 0 };
        sportsSchedules.forEach((schedule) => {
            counts[resolveSportKey(schedule)] += 1;
        });

        sportsTypeTabs.querySelectorAll('[data-sport-tab]').forEach((button) => {
            const tab = button.getAttribute('data-sport-tab');
            const hasPrograms = (counts[tab] || 0) > 0;
            button.hidden = tab === 'other' && !hasPrograms;
            if (tab === 'other' && hasPrograms) {
                button.textContent = resolveOtherTabLabel();
            }
        });

        if (!counts[activeSportTab]) {
            const nextTab = SPORT_TABS.find((tab) => counts[tab] > 0) || 'basketball';
            setActiveSportTab(nextTab, false);
        }
    }

    function renderSportsPrograms() {
        if (!sportsProgramsContainer) return;

        const filtered = sportsSchedules.filter((schedule) => {
            return resolveSportKey(schedule) === activeSportTab && !schedule.has_applied;
        });

        if (!filtered.length) {
            sportsProgramsContainer.innerHTML = '';
            return;
        }

        sportsProgramsContainer.innerHTML = filtered.map((schedule) => {
            const sportLabel = resolveSportLabel(schedule);
            const questions = Array.isArray(schedule.custom_questions) ? schedule.custom_questions : [];
            const questionList = questions.length
                ? `<ul class="sports-question-preview">${questions.map((q, i) => {
                    const typeLabel = q.type === 'file' ? 'PDF upload' : 'Short answer';
                    return `<li><span class="sports-question-index">${i + 1}.</span> <span class="sports-question-label">${escapeHtml(q.label || 'Question')}${q.required ? ' <span class="sch-program-required">*</span>' : ''}</span> <span class="sports-question-type">${typeLabel}</span></li>`;
                }).join('')}</ul>`
                : '<p class="sports-no-questions">No custom questions configured.</p>';

            const applyState = (function () {
                if (schedule.has_applied) {
                    return { label: 'View My Application', enabled: true, kind: 'view' };
                }
                if (schedule.status !== 'open') {
                    return { label: 'Closed', enabled: false, kind: 'closed' };
                }
                if (schedule.can_apply === false) {
                    return { label: 'Not Eligible', enabled: false, kind: 'ineligible' };
                }
                return { label: 'Apply Now', enabled: true, kind: 'apply' };
            })();
            const statusLabel = schedule.has_applied
                ? `Applied — ${schedule.application_status || 'pending'}`
                : (schedule.status === 'open' ? 'Open' : 'Closed');
            const statusClass = schedule.status === 'open' && !schedule.has_applied ? 'sl-status-open' : 'sl-status-closed';
            const periodStart = schedule.start_date_display || '—';
            const periodEnd = schedule.end_date_display || '—';
            const actionHtml = applyState.kind === 'view'
                ? `<a href="/sports/apply/form?schedule=${encodeURIComponent(schedule.id)}" class="sl-btn sl-btn-primary sports-apply-btn">${applyState.label}</a>`
                : `<a href="${applyState.enabled ? `/sports/apply/form?schedule=${encodeURIComponent(schedule.id)}` : '#'}" class="sl-btn sl-btn-primary sports-apply-btn ${applyState.enabled ? '' : 'is-disabled'}" ${!applyState.enabled ? 'aria-disabled="true" onclick="return false;"' : ''} title="${!applyState.enabled ? escapeHtml(schedule.eligibility_message || applyState.label) : ''}">${applyState.label}</a>`;

            return `
                <div class="sl-card sports-program-card">
                    <div class="sports-program-card__body">
                        <div class="sports-program-card__head">
                            <div class="sports-program-card__title-wrap">
                                <span class="sports-program-sport-badge">Sports Development</span>
                                <h3 class="sports-program-card__title">${escapeHtml(sportLabel)}</h3>
                            </div>
                            <span class="sl-status-badge ${statusClass}">${escapeHtml(statusLabel)}</span>
                        </div>
                        <div class="sports-card-meta-grid">
                            <div class="sports-card-meta-item"><span class="sports-card-meta-label">Period</span><span class="sports-card-meta-value">${escapeHtml(periodStart)} – ${escapeHtml(periodEnd)}</span></div>
                            <div class="sports-card-meta-item"><span class="sports-card-meta-label">Committee</span><span class="sports-card-meta-value">${escapeHtml(schedule.committee || '—')}</span></div>
                            <div class="sports-card-meta-item"><span class="sports-card-meta-label">Slots</span><span class="sports-card-meta-value">${escapeHtml(String(schedule.available_slots ?? schedule.participation_quantity ?? '—'))}</span></div>
                        </div>
                        ${schedule.announcement ? `<div class="sports-card-announcement-box">${escapeHtml(schedule.announcement)}</div>` : ''}
                        ${!applyState.enabled && schedule.eligibility_message ? `<p class="sports-card-eligibility ${applyState.kind === 'closed' ? 'sports-card-eligibility--closed' : ''}">${escapeHtml(schedule.eligibility_message)}</p>` : ''}
                        <div class="sports-program-questions">
                            <h4>Application Questions</h4>
                            ${questionList}
                        </div>
                        <div class="sports-program-card__actions">
                            ${actionHtml}
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    function setActiveSportTab(tab, rerender = true) {
        if (!SPORT_TABS.includes(tab)) return;
        activeSportTab = tab;

        sportsTypeTabs?.querySelectorAll('[data-sport-tab]').forEach((button) => {
            const isActive = button.getAttribute('data-sport-tab') === tab;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (window.SportsApplicationsHistory?.setSportTabFilter) {
            window.SportsApplicationsHistory.setSportTabFilter(tab);
        }

        if (rerender) {
            renderSportsPrograms();
        }
    }

    async function refreshApplicationsHistory() {
        if (!window.SportsApplicationsHistory) {
            return [];
        }

        currentApplications = await window.SportsApplicationsHistory.loadAndRender('sportsApplicationsHistory');
        renderSportsPrograms();
        return currentApplications;
    }

    async function init() {
        if (scheduleProgramId) {
            window.location.replace(`/sports/apply/form?schedule=${encodeURIComponent(scheduleProgramId)}`);
            return;
        }

        try {
            const [payload, applications] = await Promise.all([
                fetchProgramsPayload(),
                window.SportsApplicationsHistory
                    ? window.SportsApplicationsHistory.init({
                        containerId: 'sportsApplicationsHistory',
                        onRefresh: refreshApplicationsHistory,
                    })
                    : Promise.resolve([]),
            ]);

            currentApplications = applications;

            const sportsProgram = (payload?.abyip_programs || []).find((program) => program.type === 'sports');
            const programType = sportsProgram?.title || 'Sports Development';

            sportsSchedules = (payload?.schedule_programs || []).filter((schedule) => {
                return String(schedule.program_letter || '').toUpperCase() === 'I'
                    && String(schedule.program_type || '') === String(programType);
            });

            updateSportTabsVisibility();
            renderSportsPrograms();
            if (window.SportsApplicationsHistory?.setSportTabFilter) {
                window.SportsApplicationsHistory.setSportTabFilter(activeSportTab);
            }
        } catch (error) {
            if (sportsProgramsContainer) {
                sportsProgramsContainer.innerHTML = '<div class="sports-programs-empty">Unable to load sports programs. Please try again later.</div>';
            }
            console.error(error);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        init();

        sportsTypeTabs?.querySelectorAll('[data-sport-tab]').forEach((button) => {
            button.addEventListener('click', () => setActiveSportTab(button.getAttribute('data-sport-tab')));
        });
    });
})();
