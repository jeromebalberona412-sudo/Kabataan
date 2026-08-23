/**
 * Scholarship Apply Wizard — multi-step application on /scholarship/apply?schedule=
 */
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

(function (global) {
    'use strict';

    const MAX_FILE_BYTES = 5 * 1024 * 1024;
    const ACCEPTED_MIME = 'application/pdf';
    const TOTAL_STEPS = 6;
    const PDF_PREVIEW_SCALE = 1.2;

    const STEPS = [
        { num: 1, title: 'Personal Information', nav: 'Personal Information' },
        { num: 2, title: 'Educational Background', nav: 'Educational Background' },
        { num: 3, title: 'Background Information', nav: 'Background Information' },
        { num: 4, title: 'Additional Information', nav: 'Additional Information' },
        { num: 5, title: 'Uploading of Requirements', nav: 'Uploading of Requirements' },
        { num: 6, title: 'Review & Confirm', nav: 'Review' },
    ];

    let program = {};
    let scheduleProgramId = 0;
    let kkFieldLabels = {};
    let currentStep = 1;
    let uploadedDocuments = {};
    let currentPreviewDocument = null;
    let draftSavedAt = null;

    let shell = null;
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

    function draftKey() {
        return `scholarship_draft_${scheduleProgramId}`;
    }

    function getKkProfileValue(field) {
        return program.kk_profile?.[field] ?? '';
    }

    function getKkEducation() {
        return String(getKkProfileValue('education') || '').trim();
    }

    function isEmptyDisplayValue(value) {
        const v = String(value ?? '').trim();
        if (!v) return true;
        const lower = v.toLowerCase();
        return ['n/a', 'na', '—', '-', 'null'].includes(lower);
    }

    function formatPersonalDisplayValue(field, value) {
        const formatted = String(value ?? '').trim();
        if (field === 'suffix' && formatted.toLowerCase() === 'none') {
            return 'None';
        }
        if (isEmptyDisplayValue(formatted)) {
            return '—';
        }
        const SF = global.ScholarshipSystemFields;
        const toUpper = SF?.toUpperAnswer || ((v) => String(v ?? '').trim().toUpperCase());
        return toUpper(formatted);
    }

    function getFullName() {
        const fullName = String(getKkProfileValue('full_name') || '').trim();
        if (fullName) {
            return fullName.split(/\s+/).filter((part) => !isEmptyDisplayValue(part)).join(' ');
        }
        return [getKkProfileValue('first_name'), getKkProfileValue('middle_name'), getKkProfileValue('last_name'), getKkProfileValue('suffix')]
            .map((p) => String(p || '').trim())
            .filter((p) => p && !isEmptyDisplayValue(p))
            .join(' ');
    }

    function getInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function getProfileImageUrl() {
        return String(program.profile_image_url || '').trim();
    }

    function renderProfileAvatarHtml(name) {
        const imageUrl = getProfileImageUrl();
        if (imageUrl) {
            return `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(name)}" class="sch-preview-avatar sch-preview-avatar-img">`;
        }
        return `<div class="sch-preview-avatar">${escapeHtml(getInitials(name))}</div>`;
    }

    function loadDraft() {
        try {
            const raw = localStorage.getItem(draftKey());
            if (!raw) return null;
            return JSON.parse(raw);
        } catch {
            return null;
        }
    }

    function getSystemFieldAnswers() {
        const draft = loadDraft();
        const saved = { ...(draft?.system_field_answers || {}), ...(program.system_field_answers || {}) };
        if (!global.ScholarshipSystemFields || !form) return saved;
        return { ...saved, ...global.ScholarshipSystemFields.collectAnswers(form, getKkEducation()) };
    }

    function saveDraft() {
        const payload = {
            system_field_answers: getSystemFieldAnswers(),
            step: currentStep,
            savedAt: new Date().toISOString(),
        };
        localStorage.setItem(draftKey(), JSON.stringify(payload));
        draftSavedAt = payload.savedAt;
    }

    function formatIsoDateDisplay(iso) {
        if (!iso) return '';
        const date = new Date(`${iso}T00:00:00`);
        if (Number.isNaN(date.getTime())) return String(iso);
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function formatPeriodRange(period, program) {
        if (!period) return '';
        const start = period.start_display || formatIsoDateDisplay(period.start || program?.start_date);
        const end = period.end_display || formatIsoDateDisplay(period.end || program?.end_date);
        if (start && end) return `${start} - ${end}`;
        return start || end || '';
    }

    function renderShell() {
        const container = document.getElementById('scholarshipWizardShell');
        if (!container) return null;

        const name = getFullName() || 'Applicant';

        const navHtml = STEPS.map((s) => `
            <li class="sch-preview-nav-item sch-wizard-nav-item">
                <button type="button" class="sch-wizard-step-btn" data-wizard-nav="${s.num}">
                    <span class="sch-wizard-step-num">${s.num}</span>
                    <span>${escapeHtml(s.nav)}</span>
                </button>
            </li>
        `).join('');

        container.innerHTML = `
            <div class="sch-preview-shell sch-wizard-shell">
                <div class="sch-preview-top sch-wizard-top">
                    <a href="/dashboard" class="sch-wizard-back" id="schWizardBackLink">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Back to Dashboard
                    </a>
                    <div class="sch-wizard-top-row">
                        <div>
                            <h1 id="schWizardProgramTitle">${escapeHtml(program.program_name || 'Scholarship Application')}</h1>
                            <p class="sch-preview-subtitle">Complete each section below before submitting your application.</p>
                        </div>
                    </div>
                </div>
                <div class="sch-preview-layout sch-wizard-layout">
                    <aside class="sch-preview-sidebar sch-wizard-sidebar">
                        <p class="sch-wizard-sidebar-title">Application Form</p>
                        <p class="sch-wizard-sidebar-sub">Fill in your responses step by step.</p>
                        <div class="sch-preview-profile">
                            ${renderProfileAvatarHtml(name)}
                            <p class="sch-preview-profile-name">${escapeHtml(name)}</p>
                        </div>
                        <ul class="sch-preview-nav sch-wizard-nav" id="schWizardSideNav">${navHtml}</ul>
                    </aside>
                    <main class="sch-wizard-main">
                        <form id="scholarshipWizardForm" class="sch-wizard-form" novalidate>
                            <div class="sch-wizard-content-card">
                                <div class="sch-wizard-step-panel is-active" data-step="1">
                                    <h2 class="sch-wizard-section-title">Personal Information</h2>
                                    <div id="schWizardPersonalFields" class="sch-wizard-personal-grid"></div>
                                    <div class="sch-wizard-kk-note">These information are automatically taken from your approved KK Profiling. To update incorrect details, please visit your profile section.</div>
                                </div>
                                <div class="sch-wizard-step-panel" data-step="2" hidden>
                                    <h2 class="sch-wizard-section-title">Educational Background</h2>
                                    <div id="systemEducationalBackground"></div>
                                </div>
                                <div class="sch-wizard-step-panel" data-step="3" hidden>
                                    <h2 class="sch-wizard-section-title">Background Information</h2>
                                    <div id="systemBackgroundInformation"></div>
                                </div>
                                <div class="sch-wizard-step-panel" data-step="4" hidden>
                                    <h2 class="sch-wizard-section-title">Additional Information</h2>
                                    <div id="systemAdditionalInformation"></div>
                                </div>
                                <div class="sch-wizard-step-panel" data-step="5" hidden>
                                    <h2 class="sch-wizard-section-title">Uploading of Requirements</h2>
                                    <p class="sch-wizard-upload-intro">Upload clear PDF copies only. Each file must not exceed 5 MB.</p>
                                    <div id="customQuestionsContainer"></div>
                                </div>
                                <div class="sch-wizard-step-panel" data-step="6" hidden>
                                    <h2 class="sch-wizard-section-title">Review &amp; Confirm</h2>
                                    <div id="reviewStatusList" class="gf-review-status-list"></div>
                                    <div id="reviewStepContainer" class="gf-review-content"></div>
                                    <div class="gf-confirm-checklist sch-wizard-confirm-block">
                                        <p class="sch-wizard-upload-intro">Please confirm the following before submitting your application.</p>
                                        <label class="gf-confirm-item">
                                            <input type="checkbox" id="confirmInfoTrue">
                                            <span class="gf-confirm-box"></span>
                                            <span>I confirm that all information provided is true and correct.</span>
                                        </label>
                                        <label class="gf-confirm-item">
                                            <input type="checkbox" id="confirmDocsValid">
                                            <span class="gf-confirm-box"></span>
                                            <span>I confirm that all uploaded documents are clear and valid PDF files.</span>
                                        </label>
                                        <label class="gf-confirm-item">
                                            <input type="checkbox" id="confirmFalseInfo">
                                            <span class="gf-confirm-box"></span>
                                            <span>I understand that false information may result in rejection.</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="sch-wizard-footer">
                                    <button type="button" class="sch-wizard-btn sch-wizard-btn-secondary" id="schWizardPrevBtn" hidden>Previous</button>
                                    <button type="button" class="sch-wizard-btn sch-wizard-btn-primary" id="schWizardNextBtn">Next: Educational Background →</button>
                                </div>
                            </div>
                        </form>
                    </main>
                </div>
            </div>
        `;

        const backLink = container.querySelector('#schWizardBackLink');
        if (backLink) {
            backLink.href = global.__dashboardUrl || '/dashboard';
        }

        container.hidden = false;
        return container;
    }

    function personalFieldHtml(label, value, { alwaysShow = false, fieldKey = '' } = {}) {
        const SF = global.ScholarshipSystemFields;
        const formatValue = SF?.formatProfileValue || ((v) => {
            if (Array.isArray(v)) return v.map((p) => String(p ?? '').trim()).filter(Boolean).join(', ');
            return String(v ?? '').trim();
        });
        const formatted = formatValue(value);
        if (!alwaysShow && isEmptyDisplayValue(formatted) && !(fieldKey === 'suffix' && String(value ?? '').trim().toLowerCase() === 'none')) return '';
        const display = formatPersonalDisplayValue(fieldKey, formatted !== '' ? formatted : value);
        return `
            <div class="sch-wizard-field">
                <label class="sch-wizard-field-label">${escapeHtml(label)}</label>
                <div class="sch-wizard-field-value">${escapeHtml(display)}</div>
            </div>`;
    }

    function loadPersonalStep() {
        const container = shell?.querySelector('#schWizardPersonalFields');
        if (!container) return;

        const SF = global.ScholarshipSystemFields;
        const defaultFields = SF?.DEFAULT_KK_FIELDS || [];
        const fieldLabels = { ...(SF?.KK_FIELD_LABELS || {}), ...kkFieldLabels };
        const alwaysShowKeys = SF?.PERSONAL_ALWAYS_SHOW_KEYS || new Set(['first_name', 'middle_name', 'last_name', 'suffix']);
        const excludedKeys = new Set(['work_status', 'sk_voter', 'sk_voted', 'home_address', 'full_name']);
        const selectedFields = (program.kk_profiling_fields?.length ? program.kk_profiling_fields : defaultFields)
            .filter((field) => !excludedKeys.has(field));

        const resolveValue = (field) => {
            if (field === 'city') {
                return getKkProfileValue('city') || getKkProfileValue('city_municipality');
            }
            return getKkProfileValue(field);
        };

        const html = selectedFields.map((field) => {
            const label = fieldLabels[field] || field.replace(/_/g, ' ');
            const value = resolveValue(field);
            return personalFieldHtml(label, value, { alwaysShow: alwaysShowKeys.has(field), fieldKey: field });
        }).filter(Boolean).join('');

        container.innerHTML = html || '<p class="sch-preview-empty-section">No KK Profiling data available. Please complete your KK Profile first.</p>';
    }

    function loadSystemFields() {
        const SF = global.ScholarshipSystemFields;
        if (!SF) return;
        SF.setApplicantContext?.(program.kk_profile || {});
        SF.setProgramContext?.(program);

        const draft = loadDraft();
        const saved = { ...(draft?.system_field_answers || {}), ...(program.system_field_answers || {}) };
        const kkEducation = getKkEducation();

        SF.renderApplicantSectionById(shell.querySelector('#systemEducationalBackground'), 'educational_background', saved, kkEducation);
        SF.renderApplicantSectionById(shell.querySelector('#systemBackgroundInformation'), 'background_information', saved, kkEducation);
        SF.renderApplicantSectionById(shell.querySelector('#systemAdditionalInformation'), 'additional_information', saved, kkEducation);
    }

    function renderFileCard(documentMeta) {
        const questionId = escapeHtml(documentMeta.question_id);
        const statusText = documentMeta.status === 'uploaded' ? 'Uploaded Successfully' : (documentMeta.status || 'Uploaded');
        const sizeText = documentMeta.size_display || formatFileSize(documentMeta.size);

        return `
            <div class="gf-file-uploaded-card">
                <button type="button" class="gf-file-card" data-preview-document="${questionId}">
                    <span class="gf-file-card-icon-wrap" aria-hidden="true">
                        <svg class="gf-file-card-icon-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span class="gf-file-card-badge">PDF</span>
                    </span>
                    <span class="gf-file-card-body">
                        <span class="gf-file-card-name">${escapeHtml(documentMeta.original_name)}</span>
                        <span class="gf-file-card-meta-row">
                            <span class="gf-file-card-meta">${escapeHtml(sizeText)}</span>
                            <span class="gf-file-card-status">${escapeHtml(statusText)}</span>
                        </span>
                    </span>
                    <span class="gf-file-card-preview-label">Preview</span>
                </button>
                <button type="button" class="gf-file-replace-btn" data-replace-document="${questionId}">Replace file</button>
            </div>`;
    }

    function renderQuestionField(question, index) {
        const required = question.required ? '<span class="gf-required">*</span>' : '';
        const requiredAttr = question.required ? 'required' : '';
        const label = escapeHtml(question.label || `Requirement ${index + 1}`);
        const questionId = escapeHtml(question.id);

        return `
            <div class="gf-question gf-question-file" data-file-question-id="${question.id}">
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
                </div>
                <div class="gf-file-card-list" data-file-card-list="${questionId}"></div>
            </div>`;
    }

    function renderCustomQuestions() {
        const container = shell?.querySelector('#customQuestionsContainer');
        if (!container) return;

        const questions = (program.custom_questions || []).filter((q) => q.type === 'file');
        if (!questions.length) {
            container.innerHTML = '<p class="sch-preview-empty-section">No file upload requirements configured for this program yet.</p>';
            return;
        }

        container.innerHTML = questions.map((q, i) => `
            <div class="gf-card gf-requirement-card">${renderQuestionField(q, i)}</div>
        `).join('');

        setupFileUploads();
        Object.values(uploadedDocuments).forEach((doc) => renderDocumentCard(doc.question_id, doc));
    }

    function renderDocumentCard(questionId, documentMeta) {
        const list = shell?.querySelector(`[data-file-card-list="${questionId}"]`);
        const uploadWrapper = shell?.querySelector(`[data-file-upload="${questionId}"]`);
        if (!list || !documentMeta) return;
        list.innerHTML = renderFileCard(documentMeta);
        if (uploadWrapper) uploadWrapper.hidden = true;
        bindPreviewButtons(list);
        bindReplaceButtons(list);
    }

    function showUploadArea(questionId) {
        const uploadWrapper = shell?.querySelector(`[data-file-upload="${questionId}"]`);
        const list = shell?.querySelector(`[data-file-card-list="${questionId}"]`);
        if (uploadWrapper) uploadWrapper.hidden = false;
        if (list) list.innerHTML = '';
        setUploadState(questionId, 'idle');
    }

    function setUploadState(questionId, state, message = '') {
        const progress = shell?.querySelector(`[data-file-progress="${questionId}"]`);
        const progressBar = shell?.querySelector(`[data-file-progress-bar="${questionId}"]`);
        const error = shell?.querySelector(`[data-file-error="${questionId}"]`);
        const dropZone = shell?.querySelector(`[data-drop-zone="${questionId}"]`);

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
        if (file.type !== ACCEPTED_MIME && !file.name.toLowerCase().endsWith('.pdf')) {
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
            const documentMeta = { ...data.document, question_label: questionLabel };
            uploadedDocuments[questionId] = documentMeta;
            renderDocumentCard(questionId, documentMeta);
            setUploadState(questionId, 'success');
            saveDraft();
        } catch (error) {
            setUploadState(questionId, 'error', error.message || 'Unable to upload PDF.');
        }
    }

    function setupFileUploads() {
        shell?.querySelectorAll('[data-file-upload]').forEach((wrapper) => {
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
            ['dragenter', 'dragover'].forEach((e) => dropZone.addEventListener(e, (ev) => { ev.preventDefault(); dropZone.classList.add('is-dragover'); }));
            ['dragleave', 'drop'].forEach((e) => dropZone.addEventListener(e, (ev) => { ev.preventDefault(); dropZone.classList.remove('is-dragover'); }));
            dropZone.addEventListener('drop', (ev) => {
                const file = ev.dataTransfer?.files?.[0];
                if (file) uploadPdfFile(questionId, file, input.dataset.questionLabel || 'Document');
            });
        });
        bindPreviewButtons(shell);
    }

    function bindPreviewButtons(root) {
        root?.querySelectorAll('[data-preview-document]').forEach((button) => {
            button.addEventListener('click', () => {
                const questionId = button.getAttribute('data-preview-document');
                const doc = uploadedDocuments[questionId];
                if (doc) openPdfPreview(doc);
            });
        });
    }

    function bindReplaceButtons(root) {
        root?.querySelectorAll('[data-replace-document]').forEach((button) => {
            button.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const questionId = button.getAttribute('data-replace-document');
                if (!questionId) return;
                delete uploadedDocuments[questionId];
                showUploadArea(questionId);
            });
        });
    }

    async function openPdfPreview(documentMeta) {
        const modal = document.getElementById('pdfPreviewModal');
        const pages = document.getElementById('pdfPreviewPages');
        const title = document.getElementById('pdfPreviewTitle');
        if (!modal || !pages) return;

        currentPreviewDocument = documentMeta;
        if (title) title.textContent = documentMeta.original_name || 'PDF Preview';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        pages.innerHTML = '<p class="gf-pdf-loading">Loading PDF preview...</p>';

        try {
            const pdf = await pdfjsLib.getDocument({ url: documentMeta.preview_url, withCredentials: true }).promise;
            pages.innerHTML = '';
            for (let i = 1; i <= pdf.numPages; i += 1) {
                const page = await pdf.getPage(i);
                const viewport = page.getViewport({ scale: PDF_PREVIEW_SCALE });
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.className = 'gf-pdf-page';
                pages.appendChild(canvas);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            }
        } catch {
            pages.innerHTML = '<p class="gf-pdf-error">Unable to preview this PDF right now.</p>';
        }
    }

    function closePdfPreview() {
        const modal = document.getElementById('pdfPreviewModal');
        if (!modal) return;
        modal.hidden = true;
        document.body.style.overflow = '';
        const pages = document.getElementById('pdfPreviewPages');
        if (pages) pages.innerHTML = '';
        currentPreviewDocument = null;
    }

    function getFileQuestions() {
        return (program.custom_questions || []).filter((q) => q.type === 'file');
    }

    function collectAnswers() {
        return getFileQuestions().map((question) => ({
            question_id: question.id,
            question_label: question.label,
            question_type: question.type,
            answer: uploadedDocuments[question.id] || null,
        }));
    }

    function validateRequirements() {
        let ok = true;
        getFileQuestions().forEach((question) => {
            if (!question.required) return;
            if (!uploadedDocuments[question.id]) {
                ok = false;
                const error = shell?.querySelector(`[data-file-error="${question.id}"]`);
                if (error) {
                    error.hidden = false;
                    error.textContent = 'Please upload the required PDF document.';
                }
            }
        });
        return ok;
    }

    function isPersonalInfoComplete() {
        const SF = global.ScholarshipSystemFields;
        if (!SF?.validatePersonalProfile) {
            return Boolean(getFullName() && getKkProfileValue('contact_number'));
        }
        return SF.validatePersonalProfile(program.kk_profile || {}).ok;
    }

    function getPersonalInfoErrors() {
        const SF = global.ScholarshipSystemFields;
        if (!SF?.validatePersonalProfile) return [];
        return SF.validatePersonalProfile(program.kk_profile || {}).errors || [];
    }

    function isSystemSectionComplete(sectionId) {
        const SF = global.ScholarshipSystemFields;
        if (!SF) return true;
        const map = {
            educational_background: '#systemEducationalBackground',
            background_information: '#systemBackgroundInformation',
            additional_information: '#systemAdditionalInformation',
        };
        const container = shell?.querySelector(map[sectionId]);
        return container ? SF.validateAnswers(container, getKkEducation()).ok : true;
    }

    function getCompletionItems() {
        const items = [
            { label: 'Personal Information', complete: isPersonalInfoComplete() },
            { label: 'Educational Background', complete: isSystemSectionComplete('educational_background') },
            { label: 'Background Information', complete: isSystemSectionComplete('background_information') },
            { label: 'Additional Information', complete: isSystemSectionComplete('additional_information') },
        ];
        getFileQuestions().forEach((q) => {
            items.push({ label: q.label || 'Document', complete: !q.required || Boolean(uploadedDocuments[q.id]) });
        });
        return items;
    }

    function isApplicationComplete() {
        return getCompletionItems().every((i) => i.complete);
    }

    function renderReviewStep() {
        const reviewStatusList = shell?.querySelector('#reviewStatusList');
        const reviewStepContainer = shell?.querySelector('#reviewStepContainer');
        if (!reviewStatusList || !reviewStepContainer) return;

        reviewStatusList.innerHTML = getCompletionItems().map((item) => `
            <div class="gf-review-status-item ${item.complete ? '' : 'is-missing'}">
                <span>${escapeHtml(item.label)}</span>
                <span>${item.complete ? 'Complete' : 'Missing'}</span>
            </div>
        `).join('');

        const systemAnswers = getSystemFieldAnswers();
        const SF = global.ScholarshipSystemFields;
        const systemHtml = SF
            ? SF.getAllFields()
                .filter((f) => SF.isFieldVisible(f, systemAnswers, getKkEducation()))
                .filter((f) => !f.id.endsWith('_occupation_other'))
                .filter((f) => !f.id.endsWith('_suffix_other'))
                .map((f) => {
                    const rawAnswer = systemAnswers[f.id] || '';
                    let displayAnswer = rawAnswer || '—';
                    if (f.id.endsWith('_occupation')) {
                        const prefix = f.id.replace('_occupation', '');
                        displayAnswer = SF.resolveOccupationFromValues?.(prefix, systemAnswers) || rawAnswer || '—';
                    } else if (f.id === 'annual_family_gross_income') {
                        displayAnswer = rawAnswer || '—';
                    } else if (['text', 'name', 'suffix', 'suffix_select', 'suffix_other'].includes(f.type)) {
                        displayAnswer = SF.toUpperAnswer?.(rawAnswer) || String(rawAnswer).toUpperCase() || '—';
                    }
                    return `
                    <div class="gf-review-field">
                        <span class="gf-review-field-label">${escapeHtml(global.ScholarshipSystemFields?.getFieldDisplayLabel?.(f, getKkEducation()) || f.label)}</span>
                        <span class="gf-review-field-value">${escapeHtml(displayAnswer)}</span>
                    </div>`;
                }).join('')
            : '';

        const docsHtml = getFileQuestions().length
            ? getFileQuestions().map((q) => {
                const doc = uploadedDocuments[q.id];
                return `
                    <div class="gf-review-doc">
                        <div class="gf-review-doc-header">
                            <span class="gf-review-doc-title">${escapeHtml(q.label || 'Document')}</span>
                            <span class="gf-review-doc-status ${doc ? '' : 'is-missing'}">${doc ? 'Uploaded' : 'Missing'}</span>
                        </div>
                        ${doc ? `<button type="button" class="sch-wizard-preview-link" data-preview-document="${escapeHtml(q.id)}">Preview document</button>` : ''}
                    </div>`;
            }).join('')
            : '<p class="sch-preview-empty-section">No document uploads required.</p>';

        reviewStepContainer.innerHTML = `
            <div class="gf-review-section">
                <h3>Personal Information</h3>
                <div class="gf-review-grid">
                    <div class="gf-review-field"><span class="gf-review-field-label">Full Name</span><span class="gf-review-field-value">${escapeHtml((global.ScholarshipSystemFields?.toUpperAnswer?.(getFullName()) || getFullName()).toUpperCase())}</span></div>
                    <div class="gf-review-field"><span class="gf-review-field-label">Contact</span><span class="gf-review-field-value">${escapeHtml(getKkProfileValue('contact_number') || '—')}</span></div>
                </div>
            </div>
            ${systemHtml ? `<div class="gf-review-section"><h3>Application Details</h3><div class="gf-review-grid">${systemHtml}</div></div>` : ''}
            <div class="gf-review-section"><h3>Uploaded Requirements</h3>${docsHtml}</div>`;

        bindPreviewButtons(reviewStepContainer);
    }

    function getNextLabel() {
        if (currentStep >= TOTAL_STEPS) return 'Submit Application';
        const next = STEPS[currentStep];
        return next ? `Next: ${next.title} →` : 'Continue';
    }

    function updateStepUI() {
        shell?.querySelectorAll('.sch-wizard-step-panel').forEach((panel) => {
            const step = Number(panel.dataset.step);
            panel.hidden = step !== currentStep;
            panel.classList.toggle('is-active', step === currentStep);
        });

        shell?.querySelectorAll('[data-wizard-nav]').forEach((btn) => {
            const step = Number(btn.dataset.wizardNav);
            const item = btn.closest('.sch-preview-nav-item');
            btn.classList.toggle('is-active', step === currentStep);
            if (item) item.classList.toggle('is-active', step === currentStep);
            const numEl = btn.querySelector('.sch-wizard-step-num');
            if (numEl) numEl.textContent = String(step);
        });

        const prevBtn = shell?.querySelector('#schWizardPrevBtn');
        const nextBtn = shell?.querySelector('#schWizardNextBtn');

        if (prevBtn) prevBtn.hidden = currentStep <= 1;

        if (nextBtn) {
            if (currentStep === TOTAL_STEPS) {
                nextBtn.textContent = 'Submit Application';
                updateSubmitButtonState();
            } else {
                nextBtn.textContent = getNextLabel();
                nextBtn.disabled = false;
            }
        }

        if (currentStep === 6) {
            renderReviewStep();
            updateSubmitButtonState();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateSubmitButtonState() {
        const nextBtn = shell?.querySelector('#schWizardNextBtn');
        if (!nextBtn || currentStep !== TOTAL_STEPS) return;
        const checks = ['confirmInfoTrue', 'confirmDocsValid', 'confirmFalseInfo'].map((id) => shell?.querySelector(`#${id}`)?.checked);
        nextBtn.disabled = !checks.every(Boolean) || !isApplicationComplete();
    }

    function goToStep(step) {
        currentStep = Math.max(1, Math.min(TOTAL_STEPS, step));
        updateStepUI();
    }

    function validateCurrentStep() {
        const SF = global.ScholarshipSystemFields;
        const kkEducation = getKkEducation();

        if (currentStep === 1) {
            const personalErrors = getPersonalInfoErrors();
            if (personalErrors.length) {
                alert(`Please complete your KK Profiling information before applying:\n${personalErrors.map((e) => e.message).join('\n')}`);
                return false;
            }
            return true;
        }
        if (currentStep === 2) {
            const c = shell?.querySelector('#systemEducationalBackground');
            const r = SF?.validateAnswers(c, kkEducation) || { ok: true };
            if (!r.ok) { alert(`Please complete Educational Background:\n${r.errors.map((e) => e.message).join('\n')}`); return false; }
            return true;
        }
        if (currentStep === 3) {
            const c = shell?.querySelector('#systemBackgroundInformation');
            const r = SF?.validateAnswers(c, kkEducation) || { ok: true };
            if (!r.ok) { alert(`Please complete Background Information:\n${r.errors.map((e) => e.message).join('\n')}`); return false; }
            return true;
        }
        if (currentStep === 4) {
            const c = shell?.querySelector('#systemAdditionalInformation');
            const r = SF?.validateAnswers(c, kkEducation) || { ok: true };
            if (!r.ok) { alert(`Please complete Additional Information:\n${r.errors.map((e) => e.message).join('\n')}`); return false; }
            return true;
        }
        if (currentStep === 5) return validateRequirements();
        if (currentStep === 6) {
            if (!isApplicationComplete()) {
                alert('Please complete all required sections before submitting.');
                renderReviewStep();
                return false;
            }
            const checks = ['confirmInfoTrue', 'confirmDocsValid', 'confirmFalseInfo'].map((id) => shell?.querySelector(`#${id}`)?.checked);
            if (!checks.every(Boolean)) {
                alert('Please confirm all statements before submitting your application.');
                updateSubmitButtonState();
                return false;
            }
            return true;
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
                system_field_answers: getSystemFieldAnswers(),
            }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Submission failed.');
        }
        return data;
    }

    function openConfirmModal() {
        const modal = document.getElementById('confirmSubmitModal');
        if (modal) { modal.hidden = false; document.body.style.overflow = 'hidden'; }
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirmSubmitModal');
        if (modal) {
            modal.hidden = true;
            const success = document.getElementById('successModal');
            if (!success || success.hidden) document.body.style.overflow = '';
        }
    }

    function showSuccessModal() {
        const modal = document.getElementById('successModal');
        if (modal) { modal.hidden = false; document.body.style.overflow = 'hidden'; }
        localStorage.removeItem(draftKey());
    }

    async function handleFinalSubmit() {
        const confirmBtn = document.getElementById('confirmSubmitBtn');
        if (confirmBtn) confirmBtn.disabled = true;

        try {
            await submitApplication();
            closeConfirmModal();
            showSuccessModal();
        } catch (error) {
            alert(error.message || 'Unable to submit application.');
            if (confirmBtn) confirmBtn.disabled = false;
            updateSubmitButtonState();
        }
    }

    function bindEvents() {
        shell?.querySelector('#schWizardPrevBtn')?.addEventListener('click', () => goToStep(currentStep - 1));

        shell?.querySelector('#schWizardNextBtn')?.addEventListener('click', () => {
            if (currentStep === TOTAL_STEPS) {
                if (!validateCurrentStep() || !isApplicationComplete()) {
                    alert('Please complete all required sections and confirmations before submitting.');
                    updateSubmitButtonState();
                    return;
                }
                openConfirmModal();
                return;
            }
            if (!validateCurrentStep()) return;
            goToStep(currentStep + 1);
        });

        shell?.querySelectorAll('[data-wizard-nav]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const step = Number(btn.dataset.wizardNav);
                if (step >= 1 && step <= TOTAL_STEPS) goToStep(step);
            });
        });

        ['confirmInfoTrue', 'confirmDocsValid', 'confirmFalseInfo'].forEach((id) => {
            shell?.querySelector(`#${id}`)?.addEventListener('change', updateSubmitButtonState);
        });

        document.getElementById('pdfPreviewClose')?.addEventListener('click', closePdfPreview);
        document.getElementById('pdfPreviewModal')?.querySelector('.gf-pdf-modal-overlay')?.addEventListener('click', closePdfPreview);
        document.getElementById('backToReviewBtn')?.addEventListener('click', closeConfirmModal);
        document.getElementById('confirmSubmitModal')?.querySelector('[data-close-confirm-modal]')?.addEventListener('click', closeConfirmModal);
        document.getElementById('confirmSubmitBtn')?.addEventListener('click', handleFinalSubmit);

        document.getElementById('goToDashboardBtn')?.addEventListener('click', () => {
            window.location.reload();
        });
    }

    function init(programData) {
        program = programData || {};
        scheduleProgramId = Number(program.id || global.__scheduleProgramId || 0);
        kkFieldLabels = global.__kkFieldLabels || {};
        uploadedDocuments = { ...(program.uploaded_documents || {}) };
        global.ScholarshipSystemFields?.setApplicantContext?.(program.kk_profile || {});
        global.ScholarshipSystemFields?.setProgramContext?.(program);

        const draft = loadDraft();
        if (draft?.savedAt) draftSavedAt = draft.savedAt;
        if (draft?.step && draft.step >= 1) {
            currentStep = Math.min(TOTAL_STEPS, draft.step);
        }

        if (program.can_apply === false) {
            const message = program.eligibility_message || 'You are not eligible to apply for this scholarship program.';
            alert(message);
            window.location.href = global.__dashboardUrl || '/dashboard';
            return;
        }

        const landing = document.getElementById('scholarshipLandingContent');
        if (landing) landing.hidden = true;

        shell = renderShell();
        form = shell?.querySelector('#scholarshipWizardForm');
        loadPersonalStep();
        loadSystemFields();
        renderCustomQuestions();
        bindEvents();
        updateStepUI();
    }

    global.ScholarshipApplyWizard = { init };
})(window);
