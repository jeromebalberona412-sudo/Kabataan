/**
 * Program Evaluation Form — post-program feedback from SK Officials
 */
(function () {
    'use strict';

    const evaluation = window.__programEvaluation || {};
    const evaluationId = Number(window.__evaluationId || evaluation.id || 0);
    const form = document.getElementById('programEvaluationForm');
    const questionsContainer = document.getElementById('evaluationQuestionsContainer');
    const submitBtn = document.getElementById('evaluationSubmitBtn');
    const cancelBtn = document.getElementById('evaluationCancelBtn');
    const successModal = document.getElementById('evaluationSuccessModal');
    const successClose = document.getElementById('evaluationSuccessClose');

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

    function renderQuestions() {
        if (!questionsContainer) return;

        const questions = evaluation.questions || [];
        if (!questions.length) {
            questionsContainer.innerHTML = '<p style="text-align:center;color:#64748b;padding:24px;">No evaluation questions available.</p>';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        questionsContainer.innerHTML = questions.map((question, index) => renderQuestionField(question, index)).join('');
    }

    function renderQuestionField(question, index) {
        const required = question.required ? '<span class="gf-required">*</span>' : '';
        const requiredAttr = question.required ? 'required' : '';
        const name = `question_${question.id}`;
        const label = escapeHtml(question.label || `Question ${index + 1}`);
        const questionId = escapeHtml(String(question.id));

        switch (question.type) {
            case 'paragraph':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <textarea name="${name}" class="gf-input" rows="4" placeholder="Your answer" data-question-id="${questionId}" data-question-type="paragraph" ${requiredAttr}></textarea>
                    </div>`;
            case 'number':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="number" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="number" ${requiredAttr}>
                    </div>`;
            case 'date':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="date" name="${name}" class="gf-input" data-question-id="${questionId}" data-question-type="date" ${requiredAttr}>
                    </div>`;
            case 'dropdown':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <select name="${name}" class="gf-input" data-question-id="${questionId}" data-question-type="dropdown" ${requiredAttr}>
                            <option value="">Select an option</option>
                            ${(question.options || []).map((option) => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join('')}
                        </select>
                    </div>`;
            case 'file':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="text" name="${name}" class="gf-input" placeholder="Enter file name or note" data-question-id="${questionId}" data-question-type="file" ${requiredAttr}>
                    </div>`;
            default:
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="text" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="text" ${requiredAttr}>
                    </div>`;
        }
    }

    function collectAnswers() {
        const answers = [];
        const questions = evaluation.questions || [];

        questions.forEach((question) => {
            const questionId = String(question.id);
            const field = form.querySelector(`[data-question-id="${questionId}"]`);
            if (!field) return;
            answers.push({ question_id: questionId, answer: field.value });
        });

        return answers;
    }

    function setSubmitLoading(isLoading) {
        if (!submitBtn) return;
        submitBtn.disabled = isLoading;
        submitBtn.textContent = isLoading ? 'Submitting…' : 'Submit Evaluation';
    }

    async function handleSubmit(event) {
        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        setSubmitLoading(true);

        try {
            const response = await fetch('/api/kabataan/programs/evaluation-responses', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    evaluation_id: evaluationId,
                    answers: collectAnswers(),
                }),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to submit evaluation.');
            }

            if (successModal) {
                successModal.hidden = false;
                document.body.style.overflow = 'hidden';
            }
        } catch (error) {
            alert(error.message || 'Failed to submit evaluation.');
        } finally {
            setSubmitLoading(false);
        }
    }

    cancelBtn?.addEventListener('click', () => {
        window.location.href = '/dashboard';
    });

    successClose?.addEventListener('click', () => {
        window.location.href = '/dashboard';
    });

    form?.addEventListener('submit', handleSubmit);
    renderQuestions();
})();
