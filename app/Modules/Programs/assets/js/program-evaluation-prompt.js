/**
 * Program evaluation prompt — shown on dashboard login when SK Officials published a form.
 */
(function () {
    'use strict';

    const STORAGE_PREFIX = 'kab_eval_dismissed_';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getPendingEvaluations() {
        const fromWindow = window.__kabataanPrograms?.pending_evaluations;
        if (Array.isArray(fromWindow)) return fromWindow;
        return [];
    }

    function isDismissed(evaluationId) {
        try {
            return localStorage.getItem(`${STORAGE_PREFIX}${evaluationId}`) === '1';
        } catch (error) {
            return false;
        }
    }

    function dismissEvaluation(evaluationId) {
        try {
            localStorage.setItem(`${STORAGE_PREFIX}${evaluationId}`, '1');
        } catch (error) {
            // ignore
        }
    }

    function goToEvaluation(evaluationId) {
        window.location.href = `/programs/evaluation/form?evaluation=${encodeURIComponent(evaluationId)}`;
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    function showPrompt(evaluation) {
        const modal = document.getElementById('programEvaluationPromptModal');
        if (!modal) return;

        const titleEl = modal.querySelector('[data-eval-title]');
        const programEl = modal.querySelector('[data-eval-program]');
        const periodEl = modal.querySelector('[data-eval-period]');

        if (titleEl) titleEl.textContent = 'Program Evaluation Available';
        if (programEl) programEl.textContent = evaluation.program_name || 'Barangay Program';
        if (periodEl) {
            periodEl.textContent = `${evaluation.start_date_display || '—'} – ${evaluation.end_date_display || '—'}`;
        }

        modal.dataset.evaluationId = String(evaluation.id);
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function bindModal() {
        const modal = document.getElementById('programEvaluationPromptModal');
        if (!modal || modal.dataset.bound === '1') return;
        modal.dataset.bound = '1';

        modal.querySelector('[data-eval-start]')?.addEventListener('click', () => {
            const evaluationId = modal.dataset.evaluationId;
            if (evaluationId) goToEvaluation(evaluationId);
        });

        modal.querySelector('[data-eval-later]')?.addEventListener('click', () => {
            const evaluationId = modal.dataset.evaluationId;
            if (evaluationId) dismissEvaluation(evaluationId);
            closeModal(modal);
        });

        modal.querySelector('[data-eval-overlay]')?.addEventListener('click', () => {
            const evaluationId = modal.dataset.evaluationId;
            if (evaluationId) dismissEvaluation(evaluationId);
            closeModal(modal);
        });
    }

    function init() {
        if (window.__SHOW_KK_UPDATE_MODAL) return;

        bindModal();

        const pending = getPendingEvaluations().filter((item) => item?.can_respond && !isDismissed(item.id));
        if (!pending.length) return;

        showPrompt(pending[0]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.KabataanProgramEvaluation = {
        goToEvaluation,
        escapeHtml,
    };
})();
