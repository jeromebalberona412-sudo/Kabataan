/**
 * Program Survey Landing — info + history before answering
 */
(function () {
    'use strict';

    const abyipProgramId = Number(window.__abyipProgramId || 0);
    const startBtn = document.getElementById('pslStartSurveyBtn');
    const historyTable = document.getElementById('pslHistoryTable');
    const viewModal = document.getElementById('pslViewModal');
    const viewClose = document.getElementById('pslViewClose');
    const viewTitle = document.getElementById('pslViewTitle');
    const viewMeta = document.getElementById('pslViewMeta');
    const viewAnswers = document.getElementById('pslViewAnswers');

    let currentSurvey = null;
    let surveyHistory = [];

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

    async function fetchSurveyByProgram() {
        if (!abyipProgramId) return null;
        const response = await fetch(`/api/kabataan/programs/surveys/by-program/${abyipProgramId}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) return null;
        const data = await response.json();
        return data.survey || null;
    }

    async function fetchHistory() {
        const query = abyipProgramId ? `?program=${encodeURIComponent(abyipProgramId)}` : '';
        const response = await fetch(`/api/kabataan/programs/survey-responses${query}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) return [];
        const data = await response.json();
        return data.responses || [];
    }

    async function fetchResponse(responseId) {
        const response = await fetch(`/api/kabataan/programs/survey-responses/${responseId}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || 'Unable to load survey response.');
        }
        return data.response;
    }

    function renderSurveyInfo(survey) {
        currentSurvey = survey;
        const nameEl = document.getElementById('pslProgramName');
        const instructionsEl = document.getElementById('pslInstructions');
        const periodEl = document.getElementById('pslSurveyPeriod');
        const announcementEl = document.getElementById('pslAnnouncement');
        const statusBadge = document.getElementById('pslSurveyStatusBadge');

        if (!survey) {
            if (nameEl) nameEl.textContent = 'No survey available';
            if (instructionsEl) instructionsEl.textContent = 'SK Officials has not created a survey for this program in your barangay yet.';
            if (periodEl) periodEl.textContent = '—';
            if (announcementEl) announcementEl.textContent = '—';
            if (statusBadge) {
                statusBadge.textContent = 'Unavailable';
                statusBadge.className = 'sl-status-badge sl-status-closed';
            }
            updateStartButton(null);
            return;
        }

        if (nameEl) nameEl.textContent = survey.program_name || 'Program Survey';
        if (instructionsEl) instructionsEl.textContent = survey.instructions || survey.announcement || 'Please answer all required questions honestly.';
        if (periodEl) periodEl.textContent = `${survey.open_date_display || '—'} - ${survey.close_date_display || '—'}`;
        if (announcementEl) announcementEl.textContent = survey.announcement || '—';
        if (statusBadge) {
            const badgeOpen = Boolean(survey.can_respond || survey.is_open);
            statusBadge.textContent = badgeOpen ? 'Open' : (survey.status === 'scheduled' ? 'Scheduled' : 'Closed');
            statusBadge.className = `sl-status-badge ${badgeOpen ? 'sl-status-open' : 'sl-status-closed'}`;
        }

        updateStartButton(survey);
    }

    function updateStartButton(survey) {
        if (!startBtn) return;

        if (!survey) {
            startBtn.disabled = true;
            startBtn.style.opacity = '0.5';
            startBtn.style.cursor = 'not-allowed';
            startBtn.querySelector('span').textContent = 'Survey Not Available';
            return;
        }

        if (survey.has_responded) {
            startBtn.disabled = false;
            startBtn.style.opacity = '';
            startBtn.style.cursor = 'pointer';
            startBtn.querySelector('span').textContent = 'View My Response';
            return;
        }

        if (!survey.can_respond) {
            startBtn.disabled = true;
            startBtn.style.opacity = '0.5';
            startBtn.style.cursor = 'not-allowed';
            startBtn.querySelector('span').textContent = 'Survey Not Open';
            return;
        }

        startBtn.disabled = false;
        startBtn.style.opacity = '';
        startBtn.style.cursor = 'pointer';
        startBtn.querySelector('span').textContent = 'Start Survey';
    }

    function setSurveyHistoryVisibility(hasHistory) {
        const historyCard = document.querySelector('.sl-card-history');
        if (historyCard) {
            historyCard.hidden = !hasHistory;
        }
    }

    function renderHistory(responses) {
        if (!historyTable) return;

        setSurveyHistoryVisibility(responses.length > 0);

        if (!responses.length) {
            historyTable.innerHTML = '';
            return;
        }

        historyTable.innerHTML = responses.map((row) => `
            <tr>
                <td>${escapeHtml(row.program_name || 'Program Survey')}</td>
                <td>${escapeHtml(row.survey_period || '—')}</td>
                <td>${escapeHtml(row.submitted_at || '—')}</td>
                <td>
                    <button type="button" class="sl-btn-action sl-btn-view" data-view-response="${row.id}">View</button>
                </td>
            </tr>
        `).join('');

        historyTable.querySelectorAll('[data-view-response]').forEach((button) => {
            button.addEventListener('click', () => {
                openResponseView(Number(button.getAttribute('data-view-response')));
            });
        });
    }

    function renderAnswerItems(answers) {
        if (!answers?.length) {
            return '<p class="sl-view-empty">No answers found.</p>';
        }

        return answers.map((answer) => {
            const displayAnswer = Array.isArray(answer.answer)
                ? answer.answer.join(', ')
                : (answer.answer ?? '—');

            return `
                <div class="sl-answer-item">
                    <p class="sl-info-label">${escapeHtml(answer.question_label || 'Question')}</p>
                    <p class="sl-info-value">${escapeHtml(String(displayAnswer))}</p>
                </div>
            `;
        }).join('');
    }

    function openResponseView(responseId) {
        if (viewAnswers) viewAnswers.innerHTML = '<p class="sl-view-empty">Loading…</p>';
        if (viewModal) {
            viewModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        fetchResponse(responseId)
            .then((response) => {
                if (viewTitle) viewTitle.textContent = response.program_name || 'Survey Response';
                if (viewMeta) viewMeta.textContent = `${response.survey_period || '—'} • Submitted: ${response.submitted_at || '—'}`;
                if (viewAnswers) viewAnswers.innerHTML = renderAnswerItems(response.answers || []);
            })
            .catch((error) => {
                closeResponseView();
                alert(error.message || 'Unable to load response.');
            });
    }

    function closeResponseView() {
        if (viewModal) viewModal.hidden = true;
        document.body.style.overflow = '';
    }

    function scrollToHistory() {
        const historyCard = document.querySelector('.sl-card-history');
        if (historyCard) {
            historyCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function handleStartSurvey() {
        if (!currentSurvey?.id) return;

        if (currentSurvey.has_responded) {
            const latestResponse = surveyHistory[0];
            if (latestResponse?.id) {
                openResponseView(latestResponse.id);
                return;
            }
            scrollToHistory();
            return;
        }

        if (!currentSurvey.can_respond) return;
        window.location.href = `/programs/survey/form?survey=${encodeURIComponent(currentSurvey.id)}`;
    }

    async function init() {
        const [survey, history] = await Promise.all([
            fetchSurveyByProgram(),
            fetchHistory(),
        ]);

        surveyHistory = history;
        renderSurveyInfo(survey);
        renderHistory(history);
    }

    document.addEventListener('DOMContentLoaded', () => {
        init();
        startBtn?.addEventListener('click', handleStartSurvey);
        viewClose?.addEventListener('click', closeResponseView);
        viewModal?.querySelector('.sl-view-modal-overlay')?.addEventListener('click', closeResponseView);
    });
})();
