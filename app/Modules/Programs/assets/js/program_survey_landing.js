/**
 * Program Survey Landing — info + history before answering
 */
(function () {
    'use strict';

    const abyipProgramId = Number(window.__abyipProgramId || 0);
    const surveyByProgramUrl = window.__surveyByProgramUrl || null;
    const surveyResponsesUrl = window.__surveyResponsesUrl || '/api/kabataan/programs/survey-responses';
    const surveyAnswerBaseUrl = String(window.__surveyAnswerBaseUrl || '/programs/survey').replace(/\/$/, '');
    const surveyResponseBaseUrl = String(window.__surveyResponseBaseUrl || '/programs/survey/responses').replace(/\/$/, '');
    const startBtn = document.getElementById('pslStartSurveyBtn');
    const historyTable = document.getElementById('pslHistoryTable');

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

    function answerUrl(surveyId) {
        return `${surveyAnswerBaseUrl}/${encodeURIComponent(surveyId)}/answer`;
    }

    function responseUrl(responseId) {
        return `${surveyResponseBaseUrl}/${encodeURIComponent(responseId)}`;
    }

    async function fetchSurveyByProgram() {
        if (!abyipProgramId || !surveyByProgramUrl) return null;
        const response = await fetch(surveyByProgramUrl, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) return null;
        const data = await response.json();
        return data.survey || null;
    }

    async function fetchHistory() {
        const response = await fetch(surveyResponsesUrl, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) return [];
        const data = await response.json();
        return data.responses || [];
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
                    <a class="sl-btn-action sl-btn-view" href="${escapeHtml(responseUrl(row.id))}">View</a>
                </td>
            </tr>
        `).join('');
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
                window.location.href = responseUrl(latestResponse.id);
                return;
            }
            scrollToHistory();
            return;
        }

        if (!currentSurvey.can_respond) return;
        window.location.href = answerUrl(currentSurvey.id);
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
    });
})();
