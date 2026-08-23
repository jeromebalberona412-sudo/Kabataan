/**
 * Sports Application Form — dynamic questions from SK Officials schedule program
 */
(function (global) {
    'use strict';

    const MAX_FILE_BYTES = 5 * 1024 * 1024;
    const PDF_MIME = 'application/pdf';

    let program = {};
    let scheduleProgramId = 0;
    let kkFieldLabels = {};
    let uploadedDocuments = {};
    let form = null;

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

    function formatFileSize(bytes) {
        if (!bytes || bytes <= 0) return '0 Bytes';
        const units = ['Bytes', 'KB', 'MB', 'GB'];
        const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return `${(bytes / 1024 ** power).toFixed(1)} ${units[power]}`;
    }

    function formatIsoDateDisplay(iso) {
        if (!iso) return '';
        const date = new Date(`${iso}T00:00:00`);
        if (Number.isNaN(date.getTime())) return String(iso);
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function isEmptyDisplayValue(value) {
        const v = String(value ?? '').trim();
        if (!v) return true;
        const lower = v.toLowerCase();
        return ['none', 'n/a', 'na', '—', '-', 'null'].includes(lower);
    }

    function getKkProfileValue(field) {
        return program.kk_profile?.[field] ?? '';
    }

    function getSportLabel() {
        const details = program.sports_details || {};
        const custom = program.sport_label || details.sport_label || details.other_sport_name;
        if (custom && String(custom).trim() && String(custom).toLowerCase() !== 'other') {
            return String(custom).trim();
        }

        const key = String(details.sport_key || '').toLowerCase();
        if (key === 'basketball') return 'Basketball';
        if (key === 'volleyball') return 'Volleyball';
        if (key === 'other') {
            return details.other_sport_name || details.sport_label || program.program_name || 'Sports Program';
        }

        return program.program_name || 'Sports Program';
    }

    function renderBanner() {
        const banner = document.getElementById('sportsDynamicBanner');
        if (!banner) return;

        const periodEnd = program.end_date_display || formatIsoDateDisplay(program.end_date);
        banner.innerHTML = `
            <div class="gf-header">
                <div class="gf-banner" style="background:linear-gradient(135deg,#0d9488 0%,#0f766e 100%);">
                    <span class="gf-banner-badge">${escapeHtml(getSportLabel())}</span>
                    <h1 class="gf-banner-title">${escapeHtml(program.program_name || 'Sports Application')}</h1>
                    <p class="gf-banner-subtitle">${escapeHtml(program.announcement || program.program_type || '')}</p>
                    ${periodEnd ? `<p class="gf-banner-deadline">Application closes: <strong>${escapeHtml(periodEnd)}</strong></p>` : ''}
                </div>
            </div>`;
    }

    function personalFieldHtml(label, value) {
        if (isEmptyDisplayValue(value)) return '';
        return `
            <div class="sch-wizard-field">
                <label class="sch-wizard-field-label">${escapeHtml(label)}</label>
                <div class="sch-wizard-field-value">${escapeHtml(String(value).trim())}</div>
            </div>`;
    }

    function renderKkProfileSection() {
        const container = document.getElementById('sportsKkProfileFields');
        if (!container) return;

        const fields = [
            ['First Name', getKkProfileValue('first_name')],
            ['Middle Name', getKkProfileValue('middle_name')],
            ['Last Name', getKkProfileValue('last_name')],
            ['Suffix', getKkProfileValue('suffix')],
            ['Birthday', getKkProfileValue('birthday')],
            ['Age', getKkProfileValue('age')],
            ['Sex', getKkProfileValue('sex')],
            ['Civil Status', getKkProfileValue('civil_status')],
            ['Contact Number', getKkProfileValue('contact_number')],
            ['Email', getKkProfileValue('email')],
            ['Region', getKkProfileValue('region')],
            ['Province', getKkProfileValue('province')],
            ['City/Municipality', getKkProfileValue('city')],
            ['Barangay', getKkProfileValue('barangay')],
            ['Purok/Zone', getKkProfileValue('purok_zone')],
            ['Youth Classification', getKkProfileValue('youth_classification')],
            ['Youth Age Group', getKkProfileValue('youth_age_group')],
        ];

        const skipKeys = new Set([
            'first_name', 'middle_name', 'last_name', 'suffix', 'birthday', 'age', 'sex',
            'civil_status', 'contact_number', 'email', 'region', 'province', 'city',
            'barangay', 'purok_zone', 'youth_classification', 'youth_age_group', 'full_name',
        ]);
        const shownLabels = new Set(fields.map(([label]) => label.toLowerCase()));

        let html = fields.map(([label, value]) => personalFieldHtml(label, value)).join('');

        const kkProfile = program.kk_profile || {};
        const selectedFields = program.kk_profiling_fields || Object.keys(kkProfile);

        selectedFields.forEach((field) => {
            if (skipKeys.has(field)) return;
            const value = kkProfile[field];
            const label = kkFieldLabels[field] || field.replace(/_/g, ' ');
            if (isEmptyDisplayValue(value) || shownLabels.has(label.toLowerCase())) return;
            html += personalFieldHtml(label, value);
        });

        container.innerHTML = html || '<p class="sch-preview-empty-section">No KK Profiling data available. Please complete your KK Profile first.</p>';
    }

    function renderSportsInfoSection() {
        const container = document.getElementById('sportsProgramInfo');
        if (!container) return;

        const matched = program.matched_classification;
        const details = program.sports_details || {};
        const maxTeam = details.max_team_members ?? 12;
        const classifications = Array.isArray(details.age_classifications) ? details.age_classifications : [];

        let divisionHtml = '';
        if (matched) {
            divisionHtml = `<p><strong>Your Division:</strong> ${escapeHtml(matched.name)} (Ages ${escapeHtml(String(matched.min_age))}–${escapeHtml(String(matched.max_age))})</p>`;
        } else if (program.eligibility_message) {
            divisionHtml = `<p class="sports-eligibility-warning">${escapeHtml(program.eligibility_message)}</p>`;
        }

        const classList = classifications.length
            ? `<ul class="sports-class-list">${classifications.map((item) => {
                const open = details.open_all || item.is_open;
                return `<li><strong>${escapeHtml(item.name)}</strong> — Ages ${escapeHtml(String(item.min_age))}–${escapeHtml(String(item.max_age))} (${open ? 'Open' : 'Closed'})</li>`;
            }).join('')}</ul>`
            : '<p>No age classifications configured.</p>';

        container.innerHTML = `
            <div class="gf-card">
                <h2 class="gf-section-title">Program Details</h2>
                <p><strong>Sport:</strong> ${escapeHtml(getSportLabel())}</p>
                <p><strong>Committee:</strong> ${escapeHtml(program.committee || '—')}</p>
                <p><strong>Application Period:</strong> ${escapeHtml(program.start_date_display || '—')} – ${escapeHtml(program.end_date_display || '—')}</p>
                <p><strong>Available Slots:</strong> ${escapeHtml(String(program.available_slots ?? program.participation_quantity ?? '—'))}</p>
                <p><strong>Max Team Members:</strong> ${escapeHtml(String(maxTeam))}</p>
                ${divisionHtml}
                <h3 class="gf-subsection-title">Age Classifications</h3>
                ${classList}
            </div>`;
    }

    function renderFileCard(documentMeta) {
        const questionId = escapeHtml(documentMeta.question_id);
        const sizeText = documentMeta.size_display || formatFileSize(documentMeta.size);

        return `
            <div class="gf-file-uploaded-card">
                <div class="gf-file-card">
                    <span class="gf-file-card-icon-wrap" aria-hidden="true">
                        <svg class="gf-file-card-icon-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="gf-file-card-badge">PDF</span>
                    </span>
                    <span class="gf-file-card-body">
                        <span class="gf-file-card-name">${escapeHtml(documentMeta.original_name)}</span>
                        <span class="gf-file-card-meta-row">
                            <span class="gf-file-card-meta">${escapeHtml(sizeText)}</span>
                            <span class="gf-file-card-status">Uploaded</span>
                        </span>
                    </span>
                </div>
                <button type="button" class="gf-file-replace-btn" data-replace-document="${questionId}">Replace file</button>
            </div>`;
    }

    function renderQuestionField(question, index) {
        const required = question.required ? '<span class="gf-required">*</span>' : '';
        const requiredAttr = question.required ? 'required' : '';
        const label = escapeHtml(question.label || `Question ${index + 1}`);
        const questionId = escapeHtml(String(question.id));
        const name = `question_${question.id}`;

        if (question.type === 'file') {
            return `
                <div class="gf-card gf-question gf-question-file" data-file-question-id="${question.id}">
                    <label class="gf-question-label">${label} ${required}</label>
                    <div class="gf-file-upload" data-file-upload="${question.id}">
                        <input type="file" class="gf-file-input" accept="application/pdf,.pdf" data-question-id="${questionId}" data-question-label="${label}" ${requiredAttr}>
                        <div class="gf-file-drop-zone" data-drop-zone="${questionId}">
                            <svg class="gf-file-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <p class="gf-file-text">Drag and drop PDF files here or click to browse</p>
                            <p class="gf-file-hint">Accepted: PDF only, max 5 MB</p>
                        </div>
                        <div class="gf-file-progress" data-file-progress="${questionId}" hidden>
                            <div class="gf-file-progress-bar" data-file-progress-bar="${questionId}"></div>
                            <span class="gf-file-progress-text">Uploading...</span>
                        </div>
                        <p class="gf-file-error" data-file-error="${questionId}" hidden></p>
                        <div class="gf-file-card-list" data-file-card-list="${questionId}"></div>
                    </div>
                </div>`;
        }

        if (question.type === 'paragraph') {
            return `
                <div class="gf-card gf-question">
                    <label class="gf-question-label">${label} ${required}</label>
                    <textarea name="${name}" class="gf-input" rows="4" placeholder="Your answer" data-question-id="${questionId}" data-question-type="paragraph" data-question-label="${label}" ${requiredAttr}></textarea>
                </div>`;
        }

        if (question.type === 'number') {
            return `
                <div class="gf-card gf-question">
                    <label class="gf-question-label">${label} ${required}</label>
                    <input type="number" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="number" data-question-label="${label}" ${requiredAttr}>
                </div>`;
        }

        if (question.type === 'checkbox') {
            return `
                <div class="gf-card gf-question">
                    <label class="gf-question-label">${label} ${required}</label>
                    <div class="gf-options">
                        ${(question.options || []).map((option) => `
                            <label class="gf-option">
                                <input type="checkbox" name="${name}[]" value="${escapeHtml(option)}" data-question-id="${questionId}" data-question-type="checkbox" data-question-label="${label}">
                                <span>${escapeHtml(option)}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>`;
        }

        if (question.type === 'radio') {
            return `
                <div class="gf-card gf-question">
                    <label class="gf-question-label">${label} ${required}</label>
                    <div class="gf-options">
                        ${(question.options || []).map((option) => `
                            <label class="gf-option">
                                <input type="radio" name="${name}" value="${escapeHtml(option)}" data-question-id="${questionId}" data-question-type="radio" data-question-label="${label}" ${requiredAttr}>
                                <span>${escapeHtml(option)}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>`;
        }

        if (question.type === 'dropdown') {
            return `
                <div class="gf-card gf-question">
                    <label class="gf-question-label">${label} ${required}</label>
                    <select name="${name}" class="gf-input" data-question-id="${questionId}" data-question-type="dropdown" data-question-label="${label}" ${requiredAttr}>
                        <option value="">Select an option</option>
                        ${(question.options || []).map((option) => `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`).join('')}
                    </select>
                </div>`;
        }

        return `
            <div class="gf-card gf-question">
                <label class="gf-question-label">${label} ${required}</label>
                <input type="text" name="${name}" class="gf-input" placeholder="Your answer" data-question-id="${questionId}" data-question-type="text" data-question-label="${label}" ${requiredAttr}>
            </div>`;
    }

    function renderQuestions() {
        const container = document.getElementById('sportsQuestionsContainer');
        if (!container) return;

        const questions = program.custom_questions || [];
        if (!questions.length) {
            container.innerHTML = '<div class="gf-card"><p style="color:#64748b;">No application questions were configured by SK Officials for this program.</p></div>';
            return;
        }

        container.innerHTML = questions.map((question, index) => renderQuestionField(question, index)).join('');

        questions.filter((q) => q.type === 'file').forEach((question) => {
            const doc = uploadedDocuments[question.id];
            if (doc) {
                const list = form?.querySelector(`[data-file-card-list="${question.id}"]`);
                const uploadWrapper = form?.querySelector(`[data-file-upload="${question.id}"] .gf-file-drop-zone`);
                if (list) {
                    list.innerHTML = renderFileCard({ ...doc, question_id: question.id });
                    bindReplaceButtons(list);
                }
                if (uploadWrapper) uploadWrapper.hidden = true;
            }
        });

        setupFileUploads();
    }

    function setUploadState(questionId, state, message = '') {
        const progress = form?.querySelector(`[data-file-progress="${questionId}"]`);
        const progressBar = form?.querySelector(`[data-file-progress-bar="${questionId}"]`);
        const error = form?.querySelector(`[data-file-error="${questionId}"]`);
        const dropZone = form?.querySelector(`[data-drop-zone="${questionId}"]`);

        if (progress) progress.hidden = state !== 'uploading';
        if (progressBar) progressBar.style.width = state === 'uploading' ? '65%' : '0%';
        if (error) {
            error.hidden = state !== 'error';
            error.textContent = message;
        }
        if (dropZone) {
            dropZone.classList.toggle('is-uploading', state === 'uploading');
            dropZone.classList.toggle('is-error', state === 'error');
        }
    }

    async function uploadPdfFile(questionId, file, questionLabel) {
        if (file.size > MAX_FILE_BYTES) {
            setUploadState(questionId, 'error', 'PDF file must not exceed 5 MB.');
            return;
        }
        if (file.type !== PDF_MIME && !file.name.toLowerCase().endsWith('.pdf')) {
            setUploadState(questionId, 'error', 'Only PDF files are allowed.');
            return;
        }

        setUploadState(questionId, 'uploading');
        const formData = new FormData();
        formData.append('schedule_program_id', String(scheduleProgramId));
        formData.append('question_id', questionId);
        formData.append('file', file);

        try {
            const response = await fetch('/api/kabataan/programs/documents/upload', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                credentials: 'same-origin',
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Upload failed.');
            }

            const documentMeta = { ...data.document, question_id: questionId };
            uploadedDocuments[questionId] = documentMeta;

            const list = form?.querySelector(`[data-file-card-list="${questionId}"]`);
            const dropZone = form?.querySelector(`[data-drop-zone="${questionId}"]`);
            if (list) {
                list.innerHTML = renderFileCard(documentMeta);
                bindReplaceButtons(list);
            }
            if (dropZone) dropZone.hidden = true;
            setUploadState(questionId, 'success');
        } catch (error) {
            setUploadState(questionId, 'error', error.message || 'Unable to upload PDF.');
        }
    }

    function showUploadArea(questionId) {
        const dropZone = form?.querySelector(`[data-drop-zone="${questionId}"]`);
        const list = form?.querySelector(`[data-file-card-list="${questionId}"]`);
        if (dropZone) dropZone.hidden = false;
        if (list) list.innerHTML = '';
        setUploadState(questionId, 'idle');
    }

    function bindReplaceButtons(root) {
        root?.querySelectorAll('[data-replace-document]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const questionId = button.getAttribute('data-replace-document');
                if (!questionId) return;
                delete uploadedDocuments[questionId];
                showUploadArea(questionId);
            });
        });
    }

    function setupFileUploads() {
        form?.querySelectorAll('[data-file-upload]').forEach((wrapper) => {
            const input = wrapper.querySelector('.gf-file-input');
            const questionId = wrapper.dataset.fileUpload;
            const dropZone = wrapper.querySelector(`[data-drop-zone="${questionId}"]`);
            if (!input || !questionId) return;

            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;
                uploadPdfFile(questionId, file, input.dataset.questionLabel || 'Document');
                input.value = '';
            });

            if (!dropZone) return;
            ['dragenter', 'dragover'].forEach((eventName) => {
                dropZone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropZone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach((eventName) => {
                dropZone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropZone.classList.remove('is-dragover');
                });
            });
            dropZone.addEventListener('drop', (event) => {
                const file = event.dataTransfer?.files?.[0];
                if (file) uploadPdfFile(questionId, file, input.dataset.questionLabel || 'Document');
            });
        });
    }

    function collectAnswers() {
        const answers = [];
        const questions = program.custom_questions || [];

        questions.forEach((question) => {
            const questionId = String(question.id);
            const type = question.type || 'text';

            if (type === 'file') {
                answers.push({
                    question_id: questionId,
                    question_label: question.label,
                    question_type: type,
                    answer: uploadedDocuments[questionId] || null,
                });
                return;
            }

            if (type === 'checkbox') {
                const checked = Array.from(form.querySelectorAll(`[data-question-id="${questionId}"][type="checkbox"]:checked`))
                    .map((input) => input.value);
                answers.push({
                    question_id: questionId,
                    question_label: question.label,
                    question_type: type,
                    answer: checked,
                });
                return;
            }

            if (type === 'radio') {
                const selected = form.querySelector(`[data-question-id="${questionId}"]:checked`);
                answers.push({
                    question_id: questionId,
                    question_label: question.label,
                    question_type: type,
                    answer: selected ? selected.value : '',
                });
                return;
            }

            const field = form.querySelector(`[data-question-id="${questionId}"]`);
            answers.push({
                question_id: questionId,
                question_label: question.label,
                question_type: type,
                answer: field ? field.value : '',
            });
        });

        return answers;
    }

    function validateForm() {
        const questions = program.custom_questions || [];

        for (const question of questions) {
            if (!question.required) continue;

            const questionId = String(question.id);
            if (question.type === 'file') {
                if (!uploadedDocuments[questionId]) {
                    setUploadState(questionId, 'error', 'Please upload the required PDF document.');
                    return false;
                }
                continue;
            }

            if (question.type === 'checkbox') {
                const checked = form.querySelectorAll(`[data-question-id="${questionId}"][type="checkbox"]:checked`);
                if (!checked.length) {
                    alert(`Please answer: ${question.label || 'required question'}`);
                    return false;
                }
                continue;
            }

            const field = question.type === 'radio'
                ? form.querySelector(`[data-question-id="${questionId}"]:checked`)
                : form.querySelector(`[data-question-id="${questionId}"]`);

            if (!field || String(field.value || '').trim() === '') {
                alert(`Please answer: ${question.label || 'required question'}`);
                if (field && typeof field.focus === 'function') field.focus();
                return false;
            }
        }

        const agree = form.querySelector('#sportsAgreeTerms');
        if (!agree?.checked) {
            alert('Please agree to the terms before submitting.');
            return false;
        }

        return true;
    }

    async function submitApplication() {
        const response = await fetch('/api/kabataan/programs/applications', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                schedule_program_id: scheduleProgramId,
                answers: collectAnswers(),
                system_field_answers: {},
            }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Submission failed.');
        }

        return data;
    }

    function showSuccessModal() {
        const modal = document.getElementById('sportsSuccessModal');
        if (modal) {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
    }

    async function handleSubmit(event) {
        event.preventDefault();
        if (!validateForm()) return;

        const submitBtn = form.querySelector('#sportsSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }

        try {
            await submitApplication();
            showSuccessModal();
        } catch (error) {
            alert(error.message || 'Unable to submit application.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Application';
            }
        }
    }

    function bindEvents() {
        form?.addEventListener('submit', handleSubmit);

        document.getElementById('sportsSuccessClose')?.addEventListener('click', () => {
            window.location.href = global.__sportsBackUrl || '/sports/apply';
        });

        document.getElementById('sportsAgreeTerms')?.addEventListener('change', (event) => {
            const submitBtn = form?.querySelector('#sportsSubmitBtn');
            if (submitBtn) submitBtn.disabled = !event.target.checked;
        });
    }

    function renderSubmittedView() {
        const application = program.application || {};
        const answers = application.answers || [];
        const formEl = document.getElementById('sportsApplyForm');
        if (formEl) formEl.hidden = true;

        renderBanner();
        renderSportsInfoSection();

        const container = document.getElementById('sportsApplyFormShell') || document.querySelector('.sports-apply-page');
        if (!container) return;

        let answersHtml = answers.map((answer) => {
            let display = answer.answer;
            if (answer.question_type === 'file' && display && typeof display === 'object') {
                display = display.original_name || 'Uploaded file';
            } else if (Array.isArray(display)) {
                display = display.join(', ');
            }
            return `<div class="sports-submitted-answer"><strong>${escapeHtml(answer.question_label || 'Question')}</strong><p>${escapeHtml(String(display ?? '—'))}</p></div>`;
        }).join('');

        if (!answersHtml) {
            answersHtml = '<p class="sports-card-muted">No answers on file.</p>';
        }

        const statusBlock = document.createElement('div');
        statusBlock.className = 'gf-card sports-submitted-card';
        const isApproved = application.status === 'approved';
        const statusLabel = application.status_display || application.status || 'Pending';
        const statusMessage = isApproved
            ? '<p class="sports-card-meta sports-card-success">Your sports application is complete and has been approved.</p>'
            : '';
        statusBlock.innerHTML = `
            <h2 class="gf-section-title">${isApproved ? 'Application Approved' : 'Application Submitted'}</h2>
            <p class="sports-card-meta"><strong>Status:</strong> ${escapeHtml(statusLabel)}</p>
            <p class="sports-card-meta"><strong>Submitted:</strong> ${escapeHtml(application.submitted_at || '—')}</p>
            ${statusMessage}
            <h3 class="gf-subsection-title">Your Answers</h3>
            ${answersHtml}
            <div class="gf-submit-row">
                <a href="${escapeHtml(global.__sportsBackUrl || '/sports/apply')}" class="gf-btn gf-btn-primary">Back to Sports Programs</a>
            </div>`;

        const insertBefore = document.querySelector('.sports-form-history');
        if (insertBefore) {
            container.insertBefore(statusBlock, insertBefore);
        } else {
            container.appendChild(statusBlock);
        }
    }

    function init(programData) {
        program = programData || global.__sportsProgram || {};
        scheduleProgramId = Number(program.id || global.__scheduleProgramId || 0);
        kkFieldLabels = global.__kkFieldLabels || {};
        uploadedDocuments = { ...(program.uploaded_documents || {}) };

        if (!scheduleProgramId) {
            alert('Invalid sports program.');
            window.location.href = '/sports/apply';
            return;
        }

        if (program.can_apply === false) {
            alert(program.eligibility_message || 'You are not eligible to apply for this sports program.');
            window.location.href = '/sports/apply';
            return;
        }

        if (program.has_applied && program.application_status !== 'cancelled') {
            renderSubmittedView();
            return;
        }

        form = document.getElementById('sportsApplyForm');
        renderBanner();
        renderSportsInfoSection();
        renderKkProfileSection();
        renderQuestions();
        bindEvents();
    }

    global.SportsApplyWizard = { init };

    document.addEventListener('DOMContentLoaded', () => {
        if (global.SportsApplicationsHistory) {
            global.SportsApplicationsHistory.init({
                containerId: 'sportsApplicationsHistory',
            });
        }

        if (global.__sportsProgram && global.SportsApplyWizard) {
            global.SportsApplyWizard.init(global.__sportsProgram);
        }
    });
})(window);
