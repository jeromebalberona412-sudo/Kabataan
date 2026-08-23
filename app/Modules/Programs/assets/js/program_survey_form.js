/**
 * Program Survey Form — dynamic questions from SK Officials
 */
(function () {
    'use strict';

    const survey = window.__programSurvey || {};
    const surveyId = Number(window.__surveyId || survey.id || 0);
    const form = document.getElementById('programSurveyForm');
    const questionsContainer = document.getElementById('surveyQuestionsContainer');
    const submitBtn = document.getElementById('surveySubmitBtn');
    const cancelBtn = document.getElementById('surveyCancelBtn');
    const successModal = document.getElementById('surveySuccessModal');
    const successClose = document.getElementById('surveySuccessClose');

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

        const questions = survey.questions || [];
        if (!questions.length) {
            questionsContainer.innerHTML = '<p style="text-align:center;color:#64748b;padding:24px;">No survey questions available.</p>';
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
                    </div>
                `;
            case 'number':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="number" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="number" ${requiredAttr}>
                    </div>
                `;
            case 'date':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="date" name="${name}" class="gf-input" data-question-id="${questionId}" data-question-type="date" ${requiredAttr}>
                    </div>
                `;
            case 'checkbox':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <div class="gf-options">
                            ${(question.options || []).map((option, optionIndex) => `
                                <label class="gf-option">
                                    <input type="checkbox" name="${name}[]" value="${escapeHtml(option)}" data-question-id="${questionId}" data-question-type="checkbox">
                                    <span>${escapeHtml(option)}</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                `;
            case 'radio':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <div class="gf-options">
                            ${(question.options || []).map((option) => `
                                <label class="gf-option">
                                    <input type="radio" name="${name}" value="${escapeHtml(option)}" data-question-id="${questionId}" data-question-type="radio" ${requiredAttr}>
                                    <span>${escapeHtml(option)}</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                `;
            case 'dropdown':
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <select name="${name}" class="gf-input" data-question-id="${questionId}" data-question-type="dropdown" ${requiredAttr}>
                            <option value="">Select an option</option>
                            ${(question.options || []).map((option) => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join('')}
                        </select>
                    </div>
                `;
            default:
                return `
                    <div class="gf-card gf-question">
                        <label class="gf-question-label">${label} ${required}</label>
                        <input type="text" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="text" ${requiredAttr}>
                    </div>
                `;
        }
    }

    function collectAnswers() {
        const answers = [];
        const questions = survey.questions || [];

        questions.forEach((question) => {
            const questionId = String(question.id);
            const type = question.type;

            if (type === 'checkbox') {
                const checked = Array.from(form.querySelectorAll(`[data-question-id="${questionId}"][type="checkbox"]:checked`))
                    .map((input) => input.value);
                answers.push({ question_id: Number(questionId), answer: checked });
                return;
            }

            const field = form.querySelector(`[data-question-id="${questionId}"]`);
            if (!field) return;

            if (type === 'radio') {
                const selected = form.querySelector(`[data-question-id="${questionId}"]:checked`);
                answers.push({ question_id: Number(questionId), answer: selected ? selected.value : '' });
                return;
            }

            answers.push({ question_id: Number(questionId), answer: field.value });
        });

        return answers;
    }

    function setSubmitLoading(isLoading) {
        if (!submitBtn) return;
        submitBtn.disabled = isLoading;
        submitBtn.textContent = isLoading ? 'Submitting…' : 'Submit Survey';
    }

    async function handleSubmit(event) {
        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        setSubmitLoading(true);

        try {
            const response = await fetch('/api/kabataan/programs/survey-responses', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    survey_id: surveyId,
                    answers: collectAnswers(),
                }),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to submit survey.');
            }

            if (successModal) {
                successModal.hidden = false;
                document.body.style.overflow = 'hidden';
            }
        } catch (error) {
            alert(error.message || 'Failed to submit survey.');
        } finally {
            setSubmitLoading(false);
        }
    }

    function handleCancel() {
        const programId = survey.abyip_program_id;
        window.location.href = programId
            ? `/programs/survey?program=${encodeURIComponent(programId)}`
            : '/dashboard';
    }

    function handleSuccessClose() {
        const programId = survey.abyip_program_id;
        window.location.href = programId
            ? `/programs/survey?program=${encodeURIComponent(programId)}`
            : '/dashboard';
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderQuestions();
        form?.addEventListener('submit', handleSubmit);
        cancelBtn?.addEventListener('click', handleCancel);
        successClose?.addEventListener('click', handleSuccessClose);
    });
})();
