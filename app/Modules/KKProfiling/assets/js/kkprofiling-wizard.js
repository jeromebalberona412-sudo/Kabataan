/**
 * KK Profiling 3-step registration wizard
 */

(function () {
    const root = document.getElementById('kkpRegistrationWizard');
    if (!root) {
        return;
    }

    const slug = root.dataset.barangaySlug || '';
    const barangayName = root.dataset.barangayName || '';
    const verificationSentOnLoad = root.dataset.verificationSent === '1';

    const apiBase = `/api/kkprofiling/${slug}/wizard`;

    const STEP_META = {
        1: {
            title: 'Profiling Form',
            desc: '',
        },
        2: {
            title: 'Supporting Documents',
            desc: 'Optional: upload your Voter\'s ID, PhilHealth ID, or other valid proof of identity now, or skip and continue to email verification.',
        },
        3: {
            title: 'Check Your Email',
            desc: 'We sent a secure link to set your account password. Open your email to continue.',
        },
    };

    const panels = {
        1: document.getElementById('kkpWizardStep1'),
        2: document.getElementById('kkpWizardStep2'),
        3: document.getElementById('kkpWizardStep3'),
    };

    const progressItems = document.querySelectorAll('#kkpWizardSteps .kkp-wizard-step-item');
    const progressConnectors = document.querySelectorAll('#kkpWizardSteps .kkp-wizard-step-connector');
    const stepTitleEl = document.getElementById('kkpWizardStepTitle');
    const stepDescEl = document.getElementById('kkpWizardStepDesc');
    const barangayNameEl = document.getElementById('kkpWizardBarangayName');
    const backBtn = document.getElementById('kkpWizardBackBtn');
    const nextBtn = document.getElementById('kkpWizardNextBtn');
    const nextLabelEl = document.getElementById('kkpWizardNextLabel');
    const form = document.getElementById('kkProfilingForm');
    const navBar = document.getElementById('kkpWizardNav');
    const emailVerifyCard = document.getElementById('emailVerifyCard');
    const displayEmail = document.getElementById('displayEmail');
    const formClearAllBtn = document.getElementById('kkpFormClearAllBtn');
    const formCard = document.getElementById('kkpFormCard');
    const clearAllButtons = [formClearAllBtn].filter(Boolean);
    const STEP_STORAGE_KEY = `kkp_wizard_step_${slug}`;

    function readStoredWizardStep() {
        try {
            const stored = parseInt(sessionStorage.getItem(STEP_STORAGE_KEY) || '0', 10);
            if (stored >= 1 && stored <= 3) {
                return stored;
            }
        } catch (error) {
            // Non-blocking
        }

        return null;
    }

    function persistWizardStepLocally(step) {
        try {
            sessionStorage.setItem(STEP_STORAGE_KEY, String(Math.max(1, Math.min(3, step))));
        } catch (error) {
            // Non-blocking
        }
    }

    async function persistWizardStepRemotely(step) {
        try {
            await postJson(`${apiBase}/set-step`, { step });
        } catch (error) {
            // Non-blocking — local step UI still wins for mobile/desktop toggles
        }
    }
    const clearDraftModal = document.getElementById('kkpClearDraftModal');
    const clearDraftBackdrop = document.getElementById('kkpClearDraftBackdrop');
    const clearDraftCloseBtn = document.getElementById('kkpClearDraftCloseBtn');
    const clearDraftCancelBtn = document.getElementById('kkpClearDraftCancelBtn');
    const clearDraftConfirmBtn = document.getElementById('kkpClearDraftConfirmBtn');

    const DOC_MAX_BYTES = 10 * 1024 * 1024;
    const DOC_ALLOWED_TYPES = ['image/jpeg', 'image/png'];
    const DOC_ALLOWED_EXT = ['.jpg', '.jpeg', '.png'];
    const PHILIPPINE_OCR_DOC_TYPES = ['national_id', 'philhealth_id', 'voters_id'];

    const DOC_TYPE_LABELS = {
        national_id: 'PhilSys / National ID',
        philhealth_id: 'PhilHealth ID',
        voters_id: "Voter's ID",
        school_id: 'School ID',
        other_id: 'Other valid proof of identity',
    };

    const ocrPanel = document.getElementById('kkpWizardOcrPanel');
    const ocrStatusEl = document.getElementById('kkpWizardOcrStatus');
    const ocrFieldsEl = document.getElementById('kkpWizardOcrFields');
    const ocrNoteEl = document.getElementById('kkpWizardOcrNote');
    const docErrorEl = document.getElementById('kkpWizardDocError');
    const selfieUploadPanel = document.getElementById('kkpSelfieUploadPanel');
    const selfieInput = document.getElementById('kkpSelfie');
    const selfieVerificationEnabled = selfieUploadPanel?.dataset?.selfieEnabled === '1';

    let ocrScanToken = 0;
    let lastOcrPayload = null;
    let lastOcrBlockingError = null;

    const docTypeRadios = document.querySelectorAll('input[name="document_type"]');
    const schoolIdUploadPanel = document.getElementById('kkpSchoolIdUpload');
    const nationalIdUploadPanel = document.getElementById('kkpNationalIdUpload');
    const votersIdUploadPanel = document.getElementById('kkpVotersIdUpload');
    const philhealthIdUploadPanel = document.getElementById('kkpPhilhealthIdUpload');
    const otherIdUploadPanel = document.getElementById('kkpOtherIdUpload');

    const DOCUMENT_INPUT_IDS = {
        school_id: ['kkpSchoolIdFront', 'kkpSchoolIdBack'],
        national_id: ['kkpNationalIdFront', 'kkpNationalIdBack'],
        voters_id: ['kkpVotersIdFront', 'kkpVotersIdBack'],
        philhealth_id: ['kkpPhilhealthIdFront', 'kkpPhilhealthIdBack'],
        other_id: ['kkpOtherIdFront', 'kkpOtherIdBack'],
    };

    function buildPreviewConfig(inputId) {
        return {
            empty: document.getElementById(`${inputId}Empty`),
            preview: document.getElementById(`${inputId}Preview`),
            img: document.getElementById(`${inputId}PreviewImg`),
            fileName: document.getElementById(`${inputId}FileName`),
            dropzone: document.getElementById(`${inputId}Dropzone`),
        };
    }

    const previewConfig = {};

    Object.values(DOCUMENT_INPUT_IDS).flat().forEach((inputId) => {
        previewConfig[inputId] = buildPreviewConfig(inputId);
    });
    previewConfig.kkpSelfie = buildPreviewConfig('kkpSelfie');

    function getDocumentInput(inputId) {
        return document.getElementById(inputId);
    }

    function getDocumentInputsForType(documentType) {
        return (DOCUMENT_INPUT_IDS[documentType] || []).map((inputId) => getDocumentInput(inputId));
    }

    const previewUrls = {};

    let currentStep = 1;
    let verificationSent = verificationSentOnLoad;
    let registrationCompleted = root.dataset.registrationComplete === '1';
    let registrationAutoApproved = root.dataset.autoApproved === '1';
    const initialStep = parseInt(root.dataset.initialStep, 10) || 1;
    let restoredStep = initialStep;
    let registrationCompletionPoll = null;
    let step1DraftTimer = null;
    let step1DraftInFlight = false;
    let step1DraftQueued = false;
    let suppressStep1Autosave = true;

    if (barangayNameEl && barangayName) {
        barangayNameEl.textContent = barangayName;
    }

    function hideDocUploadError() {
        lastOcrBlockingError = null;

        if (docErrorEl) {
            docErrorEl.hidden = true;
            docErrorEl.textContent = '';
        }
    }

    function formatOcrMismatchMessage(payload, selectedDocumentType) {
        const selectedLabel = DOC_TYPE_LABELS[selectedDocumentType] || selectedDocumentType;
        const detectedType = payload?.detected_id_type || payload?.id_type;
        const detectedLabel = formatIdTypeLabel(detectedType) || 'a different ID type';

        if (payload?.message) {
            return payload.message;
        }

        if (payload?.expected_id_type && detectedType && detectedType !== 'Unknown') {
            return `You selected ${selectedLabel}, but the uploaded images appear to be ${detectedLabel}. Please upload the correct ID or change the document type.`;
        }

        if (detectedType && detectedType !== 'Unknown' && payload?.validation_error) {
            return `The uploaded images do not match ${selectedLabel}. Detected: ${detectedLabel}. Please upload the correct front and back photos.`;
        }

        if (payload?.id_type === 'Unknown' || !payload?.success) {
            return `Unable to verify ${selectedLabel} from the uploaded images. Please upload a clearer front and back photo of your selected ID.`;
        }

        return `The uploaded ID could not be verified as ${selectedLabel}. Please check your files and try again.`;
    }

    function showDocUploadError(message) {
        if (!message) {
            hideDocUploadError();
            return;
        }

        lastOcrBlockingError = message;

        if (docErrorEl) {
            docErrorEl.hidden = false;
            docErrorEl.textContent = message;
            docErrorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        alert(message);
    }

    function setOcrPanelState(state) {
        if (!ocrPanel) {
            return;
        }

        ocrPanel.classList.remove('is-error', 'is-loading');

        if (state === 'loading') {
            ocrPanel.classList.add('is-loading');
        } else if (state === 'error') {
            ocrPanel.classList.add('is-error');
        }
    }

    function formatIdTypeLabel(value) {
        const raw = String(value || '').trim();
        if (!raw || raw === 'Unknown') {
            return null;
        }

        const map = {
            national_id: 'PhilSys / National ID',
            philhealth_id: 'PhilHealth ID',
            voters_id: "Voter's ID",
            school_id: 'School ID',
            other_id: 'Other Supporting ID',
        };

        return map[raw] || raw;
    }

    function formatOcrStatusLabel(status) {
        const map = {
            ocr_success: 'Successfully processed',
            ocr_low_confidence: 'Low confidence',
            ocr_empty: 'No useful text detected',
            ocr_failed: 'Processing failed',
            tesseract_unavailable: 'OCR engine unavailable',
            invalid_image: 'Invalid image',
            invalid_upload: 'Invalid upload',
            skipped: 'Skipped',
        };

        return map[String(status || '')] || (status ? String(status) : null);
    }

    function clearOcrUiState({ hidePanel = true } = {}) {
        lastOcrPayload = null;
        lastOcrBlockingError = null;
        ocrScanToken += 1;
        hideDocUploadError();

        if (ocrFieldsEl) {
            ocrFieldsEl.innerHTML = '';
            ocrFieldsEl.hidden = true;
        }

        if (ocrStatusEl) {
            ocrStatusEl.textContent = '';
        }

        if (ocrNoteEl) {
            ocrNoteEl.hidden = true;
        }

        if (ocrPanel) {
            setOcrPanelState('ok');
            if (hidePanel) {
                ocrPanel.hidden = true;
            }
        }

        updateNavButtons(currentStep);
    }

    function renderOcrFields(payload) {
        if (!ocrFieldsEl || !ocrStatusEl || !ocrPanel) {
            return;
        }

        if (!payload) {
            clearOcrUiState();
            return;
        }

        const detectedType = payload?.detected_id_type || payload?.id_type;
        const detectedLabel = formatIdTypeLabel(detectedType);
        const confidenceValue = Number(payload?.confidence);
        const confidenceLabel = Number.isFinite(confidenceValue) && confidenceValue > 0
            ? `${Math.round(confidenceValue * 100)}%`
            : null;

        const entries = [
            ['Document detected', payload?.document_detected ? String(payload.document_detected).toUpperCase() : (payload?.success ? 'YES' : null)],
            ['ID type', detectedLabel],
            ['Confidence', confidenceLabel],
            ['OCR status', formatOcrStatusLabel(payload?.ocr_status)],
            ['Verification', payload?.needs_review ? 'Needs administrator review' : (payload?.success ? 'Ready for review' : null)],
            ['Full name', payload?.full_name],
            ['Birthdate', payload?.birthdate],
            ['Sex', payload?.sex],
            ['Address', payload?.address],
            ['ID number', payload?.id_number],
            ['Face match', payload?.face_match === true ? 'Matched' : (payload?.face_verification?.decision || null)],
        ].filter(([, value]) => value);

        ocrFieldsEl.innerHTML = '';

        entries.forEach(([label, value]) => {
            const wrap = document.createElement('div');
            const dt = document.createElement('dt');
            const dd = document.createElement('dd');
            dt.textContent = label;
            dd.textContent = String(value);
            wrap.appendChild(dt);
            wrap.appendChild(dd);
            ocrFieldsEl.appendChild(wrap);
        });

        ocrFieldsEl.hidden = entries.length === 0;
        ocrPanel.hidden = false;

        if (payload?.validation_error) {
            const mismatchMessage = formatOcrMismatchMessage(payload, getSelectedDocumentType());
            ocrStatusEl.textContent = mismatchMessage;
            setOcrPanelState('error');
            showDocUploadError(mismatchMessage);
        } else if (payload?.success || payload?.needs_review) {
            hideDocUploadError();
            if (selfieVerificationEnabled && payload?.face_match) {
                ocrStatusEl.textContent = payload?.message || 'Document appears valid for review.';
            } else if (selfieVerificationEnabled && payload?.face_verification?.decision === 'FAIL') {
                ocrStatusEl.textContent = 'We could not confirm the selfie against the ID photo. Please upload a clearer image.';
                setOcrPanelState('error');
                showDocUploadError('We could not confirm the selfie against the ID photo. Please upload a clearer image.');
                return;
            } else if (selfieVerificationEnabled && PHILIPPINE_OCR_DOC_TYPES.includes(getSelectedDocumentType()) && selfieUploadPanel) {
                ocrStatusEl.textContent = payload?.message || 'Document appears valid for review. You may upload a selfie if required.';
                selfieUploadPanel.hidden = false;
            } else {
                ocrStatusEl.textContent = payload?.message || 'Document appears valid for review. Review detected details below.';
                if (selfieUploadPanel) {
                    selfieUploadPanel.hidden = true;
                }
            }
            setOcrPanelState('ok');
        } else {
            const fallbackMessage = payload?.message || 'OCR could not identify this ID.';
            ocrStatusEl.textContent = fallbackMessage;
            setOcrPanelState('error');
            showDocUploadError(fallbackMessage);
        }
    }

    function migrateDocumentFiles(fromType, toType) {
        if (!fromType || !toType || fromType === toType) {
            return false;
        }

        const fromInputs = getDocumentInputsForType(fromType);
        const toInputs = getDocumentInputsForType(toType);
        let moved = false;

        [0, 1].forEach((index) => {
            const source = fromInputs[index];
            const target = toInputs[index];
            const file = source?.files?.[0];

            if (!file || !target || typeof DataTransfer === 'undefined') {
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            target.files = transfer.files;
            updateFilePreview(target, { skipScan: true });
            moved = true;
        });

        if (moved) {
            fromInputs.forEach((input) => {
                if (!input) {
                    return;
                }
                resetFilePreview(input.id);
                input.value = '';
            });
        }

        return moved;
    }

    function applyAutoDetectedDocumentType(detectedType, { confidence = 0 } = {}) {
        const allowed = ['national_id', 'philhealth_id', 'voters_id', 'school_id'];
        if (!allowed.includes(detectedType) || Number(confidence) < 0.45) {
            return false;
        }

        const currentType = getSelectedDocumentType();
        if (currentType === detectedType) {
            return false;
        }

        const radio = document.querySelector(`input[name="document_type"][value="${detectedType}"]`);
        if (!radio) {
            return false;
        }

        if (currentType) {
            migrateDocumentFiles(currentType, detectedType);
        }

        radio.checked = true;
        syncDocumentUploadPanels.lastType = detectedType;
        syncDocumentUploadPanels();
        return true;
    }

    function setFieldValue(fieldName, value, { onlyEmpty = true } = {}) {
        if (!value) {
            return false;
        }

        const input = form?.querySelector(`[name="${fieldName}"]`);

        if (!input) {
            return false;
        }

        if (onlyEmpty && String(input.value || '').trim() !== '') {
            return false;
        }

        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        return true;
    }

    function applySexValue(sex, { onlyEmpty = true } = {}) {
        if (!sex) {
            return false;
        }

        const hidden = document.getElementById('kkpSex');

        if (hidden && (!onlyEmpty || !hidden.value)) {
            hidden.value = sex;
        }

        document.querySelectorAll('input[name="sexChk"]').forEach((checkbox) => {
            checkbox.checked = checkbox.value === sex;
        });

        return true;
    }

    function applyFormSuggestions(suggestions, options = {}) {
        if (!suggestions || typeof suggestions !== 'object') {
            return false;
        }

        const onlyEmpty = options.onlyEmpty !== false;
        let applied = false;

        applied = setFieldValue('first_name', suggestions.first_name, { onlyEmpty }) || applied;
        applied = setFieldValue('middle_name', suggestions.middle_name, { onlyEmpty }) || applied;
        applied = setFieldValue('last_name', suggestions.last_name, { onlyEmpty }) || applied;
        applied = setFieldValue('birthday', suggestions.birthday, { onlyEmpty }) || applied;
        applied = setFieldValue('age', suggestions.age != null ? String(suggestions.age) : '', { onlyEmpty }) || applied;
        applied = setFieldValue('purok_zone', suggestions.purok_zone, { onlyEmpty }) || applied;
        applied = applySexValue(suggestions.sex, { onlyEmpty }) || applied;

        if (ocrNoteEl) {
            ocrNoteEl.hidden = !applied;
        }

        return applied;
    }

    function getSelfieFile() {
        return selfieInput?.files?.[0] || null;
    }

    async function scanPhilippineIdIfReady() {
        const documentType = getSelectedDocumentType();

        if (!PHILIPPINE_OCR_DOC_TYPES.includes(documentType) || !hasCompleteDocumentUpload()) {
            clearOcrUiState({ hidePanel: true });
            return;
        }

        const files = getActiveDocumentFiles();
        const sameSideError = await validateDistinctFrontAndBack(files);

        if (sameSideError) {
            lastOcrPayload = {
                success: false,
                validation_error: true,
                needs_review: false,
                document_detected: 'no',
                id_type: null,
                confidence: 0,
                ocr_status: 'invalid_upload',
                message: sameSideError,
            };
            renderOcrFields(lastOcrPayload);
            showDocUploadError(sameSideError);
            updateNavButtons(currentStep);
            return;
        }

        const token = ++ocrScanToken;

        hideDocUploadError();
        lastOcrBlockingError = null;
        lastOcrPayload = null;

        if (ocrPanel) {
            ocrPanel.hidden = false;
        }

        if (ocrFieldsEl) {
            ocrFieldsEl.innerHTML = '';
            ocrFieldsEl.hidden = true;
        }

        if (ocrStatusEl) {
            ocrStatusEl.textContent = 'Scanning ID with OCR...';
        }

        setOcrPanelState('loading');

        try {
            const formData = new FormData();
            formData.append('document_type', documentType);
            formData.append('front', files.front);
            formData.append('back', files.back);

            const selfie = selfieVerificationEnabled ? getSelfieFile() : null;
            if (selfie) {
                formData.append('selfie', selfie);
            }

            const response = await fetch(`${apiBase}/detect-id`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json().catch(() => ({}));

            if (token !== ocrScanToken) {
                return;
            }

            lastOcrPayload = data.ocr || data;

            if (!response.ok || lastOcrPayload?.validation_error) {
                if (!lastOcrPayload?.validation_error) {
                    lastOcrPayload = {
                        success: false,
                        validation_error: true,
                        message: data.message || formatOcrMismatchMessage({}, documentType),
                    };
                }
            } else {
                const detectedType = lastOcrPayload?.detected_id_type || lastOcrPayload?.id_type;
                if (lastOcrPayload?.auto_corrected || Number(lastOcrPayload?.confidence || 0) >= 0.45) {
                    applyAutoDetectedDocumentType(detectedType, {
                        confidence: lastOcrPayload?.confidence || 0,
                    });
                }
            }

            renderOcrFields(lastOcrPayload);

            if (lastOcrPayload?.success && data.form_suggestions) {
                applyFormSuggestions(data.form_suggestions, { onlyEmpty: true });
            }

            updateNavButtons(currentStep);
        } catch (error) {
            if (token !== ocrScanToken) {
                return;
            }

            const offlineMessage = 'We couldn\'t read this ID clearly. Please upload a clearer front and back photo and try again.';
            renderOcrFields({
                success: false,
                validation_error: true,
                message: offlineMessage,
            });
            showDocUploadError(offlineMessage);
            updateNavButtons(currentStep);
        }
    }

    function hasBlockingOcrError() {
        if (!PHILIPPINE_OCR_DOC_TYPES.includes(getSelectedDocumentType())) {
            return false;
        }

        if (!hasCompleteDocumentUpload()) {
            return false;
        }

        return Boolean(lastOcrBlockingError);
    }

    function isAllowedDocumentFile(file) {
        if (!file) {
            return false;
        }

        const name = file.name.toLowerCase();
        const hasAllowedExt = DOC_ALLOWED_EXT.some((ext) => name.endsWith(ext));

        return DOC_ALLOWED_TYPES.includes(file.type) || hasAllowedExt;
    }

    function validateDocumentFile(file) {
        if (!file) {
            return null;
        }

        if (!isAllowedDocumentFile(file)) {
            return 'Only JPG or PNG images are allowed.';
        }

        if (file.size > DOC_MAX_BYTES) {
            return 'Image must be 10MB or smaller.';
        }

        return null;
    }

    async function filesAppearIdentical(front, back) {
        if (!front || !back) {
            return false;
        }

        if (front === back) {
            return true;
        }

        if (
            front.size === back.size
            && front.name === back.name
            && front.lastModified === back.lastModified
        ) {
            return true;
        }

        if (front.size !== back.size || front.size === 0) {
            return false;
        }

        if (!window.crypto?.subtle) {
            return false;
        }

        try {
            const [frontHash, backHash] = await Promise.all([
                crypto.subtle.digest('SHA-256', await front.arrayBuffer()),
                crypto.subtle.digest('SHA-256', await back.arrayBuffer()),
            ]);

            const toHex = (buffer) => [...new Uint8Array(buffer)]
                .map((byte) => byte.toString(16).padStart(2, '0'))
                .join('');

            return toHex(frontHash) === toHex(backHash);
        } catch (error) {
            return false;
        }
    }

    async function validateDistinctFrontAndBack(files) {
        if (!files?.front || !files?.back) {
            return null;
        }

        if (await filesAppearIdentical(files.front, files.back)) {
            return 'Front and back must be different photos. You uploaded the same image for both sides. Please upload the real front and the real back of your ID.';
        }

        return null;
    }

    function getSelectedDocumentType() {
        return document.querySelector('input[name="document_type"]:checked')?.value || '';
    }

    function getActiveDocumentFiles() {
        const documentType = getSelectedDocumentType();

        if (!documentType) {
            return { front: null, back: null };
        }

        const inputs = getDocumentInputsForType(documentType);

        return {
            front: inputs[0]?.files?.[0] || null,
            back: inputs[1]?.files?.[0] || null,
        };
    }

    function hasPartialDocumentUpload() {
        const files = getActiveDocumentFiles();

        return Boolean(files.front || files.back);
    }

    function hasCompleteDocumentUpload() {
        const files = getActiveDocumentFiles();

        return Boolean(files.front && files.back);
    }

    function clearDocumentInputsForType(documentType) {
        getDocumentInputsForType(documentType).forEach((input) => clearDocumentInput(input));
    }

    function clearAllDocumentInputs() {
        Object.keys(DOCUMENT_INPUT_IDS).forEach((documentType) => clearDocumentInputsForType(documentType));
    }

    function clearDocumentInput(input) {
        if (!input) {
            return;
        }

        resetFilePreview(input.id);
        input.value = '';
        clearOcrUiState({ hidePanel: !hasCompleteDocumentUpload() });

        if (hasCompleteDocumentUpload()) {
            scanPhilippineIdIfReady();
        }
    }

    function resetFilePreview(inputId) {
        const config = previewConfig[inputId];

        if (!config) {
            return;
        }

        if (previewUrls[inputId]) {
            URL.revokeObjectURL(previewUrls[inputId]);
            delete previewUrls[inputId];
        }

        if (config.dropzone) {
            config.dropzone.hidden = false;
        }

        if (config.preview) {
            config.preview.hidden = true;
        }

        if (config.img) {
            config.img.removeAttribute('src');
        }

        if (config.fileName) {
            config.fileName.textContent = '';
        }
    }

    function updateFilePreview(input, options = {}) {
        if (!input) {
            return;
        }

        const config = previewConfig[input.id];
        const file = input.files?.[0];
        const skipScan = Boolean(options.skipScan);

        if (!config) {
            return;
        }

        if (!file) {
            resetFilePreview(input.id);
            clearOcrUiState({ hidePanel: !hasCompleteDocumentUpload() });
            updateNavButtons(currentStep);
            return;
        }

        const error = validateDocumentFile(file);

        if (error) {
            input.value = '';
            resetFilePreview(input.id);
            showDocUploadError(error);
            updateNavButtons(currentStep);
            return;
        }

        hideDocUploadError();
        resetFilePreview(input.id);

        const objectUrl = URL.createObjectURL(file);
        previewUrls[input.id] = objectUrl;

        if (config.img) {
            config.img.src = objectUrl;
        }

        if (config.fileName) {
            config.fileName.textContent = file.name;
        }

        if (config.dropzone) {
            config.dropzone.hidden = true;
        }

        if (config.preview) {
            config.preview.hidden = false;
        }

        updateNavButtons(currentStep);

        if (!skipScan) {
            // New image uploaded — clear previous OCR result before rescanning.
            if (!hasCompleteDocumentUpload()) {
                clearOcrUiState({ hidePanel: true });
            } else {
                lastOcrPayload = null;
                lastOcrBlockingError = null;
                hideDocUploadError();
                scanPhilippineIdIfReady();
            }
        }
    }

    function resetAllDocumentPreviews() {
        Object.keys(previewConfig).forEach((inputId) => resetFilePreview(inputId));
    }

    function syncDocumentUploadPanels() {
        const selectedType = getSelectedDocumentType();
        const previousType = syncDocumentUploadPanels.lastType || '';

        if (previousType && previousType !== selectedType) {
            clearDocumentInputsForType(previousType);
            clearOcrUiState({ hidePanel: true });
        } else {
            hideDocUploadError();
        }

        syncDocumentUploadPanels.lastType = selectedType;

        if (schoolIdUploadPanel) {
            schoolIdUploadPanel.hidden = selectedType !== 'school_id';
        }

        if (nationalIdUploadPanel) {
            nationalIdUploadPanel.hidden = selectedType !== 'national_id';
        }

        if (votersIdUploadPanel) {
            votersIdUploadPanel.hidden = selectedType !== 'voters_id';
        }

        if (philhealthIdUploadPanel) {
            philhealthIdUploadPanel.hidden = selectedType !== 'philhealth_id';
        }

        if (otherIdUploadPanel) {
            otherIdUploadPanel.hidden = selectedType !== 'other_id';
        }

        if (!selectedType) {
            clearAllDocumentInputs();
            if (ocrPanel) {
                ocrPanel.hidden = true;
            }
            if (selfieUploadPanel) {
                selfieUploadPanel.hidden = true;
            }
        } else {
            // Clear all other document types
            const allTypes = ['school_id', 'national_id', 'voters_id', 'philhealth_id', 'other_id'];
            allTypes.forEach(type => {
                if (type !== selectedType) {
                    clearDocumentInputsForType(type);
                }
            });
        }

        updateNavButtons(currentStep);

        if (selectedType) {
            const activePanel = [
                schoolIdUploadPanel,
                nationalIdUploadPanel,
                votersIdUploadPanel,
                philhealthIdUploadPanel,
                otherIdUploadPanel,
            ].find((panel) => panel && !panel.hidden);

            if (activePanel) {
                window.requestAnimationFrame(() => {
                    activePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        }

        scanPhilippineIdIfReady();
    }
    syncDocumentUploadPanels.lastType = '';

    function bindDropzone(dropzone, input) {
        if (!dropzone || !input) {
            return;
        }

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            const file = event.dataTransfer?.files?.[0];

            if (!file) {
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            updateFilePreview(input);
        });
    }

    function bindDocumentTypeControls() {
        docTypeRadios.forEach((radio) => {
            radio.addEventListener('change', syncDocumentUploadPanels);
        });

        Object.values(DOCUMENT_INPUT_IDS).flat().forEach((inputId) => {
            const input = getDocumentInput(inputId);
            input?.addEventListener('change', () => updateFilePreview(input));
            bindDropzone(previewConfig[inputId]?.dropzone, input);
        });

        selfieInput?.addEventListener('change', () => {
            updateFilePreview(selfieInput);
            if (hasCompleteDocumentUpload() && PHILIPPINE_OCR_DOC_TYPES.includes(getSelectedDocumentType())) {
                scanPhilippineIdIfReady();
            }
        });
        bindDropzone(previewConfig.kkpSelfie?.dropzone, selfieInput);

        document.querySelectorAll('.kkp-wizard-dropzone-remove').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const inputId = button.dataset.clearInput;
                const input = inputId ? document.getElementById(inputId) : null;

                if (input) {
                    clearDocumentInput(input);
                    hideDocUploadError();
                    updateNavButtons(currentStep);
                }
            });
        });

        syncDocumentUploadPanels();
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function getDraftEmail() {
        const fromForm = form?.querySelector('input[name="email"]')?.value?.trim();
        const fromDisplay = displayEmail?.textContent?.trim();

        if (fromForm) {
            return fromForm;
        }

        if (fromDisplay && fromDisplay !== 'your-email@example.com') {
            return fromDisplay;
        }

        return '';
    }

    function stopRegistrationCompletionPoll() {
        if (registrationCompletionPoll) {
            clearInterval(registrationCompletionPoll);
            registrationCompletionPoll = null;
        }
    }

    function showRegistrationCompleteState(autoApproved = registrationAutoApproved) {
        registrationCompleted = true;
        root.dataset.registrationComplete = '1';
        root.dataset.autoApproved = autoApproved ? '1' : '0';
        stopRegistrationCompletionPoll();

        if (window.kkpStopResendTimer) {
            window.kkpStopResendTimer();
        }

        root.classList.add('kkp-wizard-registration-complete');
        document.body.classList.add('kkp-wizard-registration-complete');

        const resendBtn = document.getElementById('resendEmailBtn');
        const verifyHelp = document.querySelector('#kkpWizardStep3 .verify-help');
        const emailErrorEl = document.getElementById('kkpWizardEmailError');

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.hidden = true;
        }

        if (verifyHelp) {
            verifyHelp.hidden = true;
        }

        if (emailErrorEl) {
            emailErrorEl.hidden = true;
            emailErrorEl.textContent = '';
        }

        if (navBar) {
            navBar.hidden = true;
        }

        const topBack = document.getElementById('kkpTopBackLink');
        if (topBack) {
            topBack.hidden = true;
        }

        const modal = document.getElementById('kkpRegSuccessModal');
        if (modal) {
            const titleEl = document.getElementById('kkpRegSuccessTitle');
            const messageEl = document.getElementById('kkpRegSuccessMessage');
            const loginBtn = modal.querySelector('.kkp-reg-success-modal-btn');

            if (autoApproved) {
                if (titleEl) {
                    titleEl.textContent = 'Registration Submitted Successfully';
                }
                if (messageEl) {
                    messageEl.textContent = 'Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.';
                }
            } else {
                if (titleEl) {
                    titleEl.textContent = 'Registration Submitted Successfully';
                }
                if (messageEl) {
                    messageEl.textContent = 'Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.';
                }
            }

            if (loginBtn) {
                loginBtn.textContent = 'Go to Sign in';
            }

            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('kkp-wizard-success-modal-open');
        }
    }

    window.kkpShowRegistrationComplete = showRegistrationCompleteState;

    async function pollRegistrationCompletion() {
        if (registrationCompleted) {
            return;
        }

        const email = getDraftEmail() || root.dataset.completedEmail || '';

        if (!email || email === 'your-email@example.com') {
            return;
        }

        try {
            const response = await fetch(
                `${apiBase}/registration-complete?email=${encodeURIComponent(email)}`,
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                },
            );
            const data = await response.json();

            if (data.completed) {
                registrationAutoApproved = Boolean(data.auto_approved);
                showRegistrationCompleteState(registrationAutoApproved);
            }
        } catch (error) {
            // Non-blocking
        }
    }

    function startRegistrationCompletionPoll() {
        if (registrationCompleted) {
            return;
        }

        stopRegistrationCompletionPoll();
        pollRegistrationCompletion();
        registrationCompletionPoll = setInterval(pollRegistrationCompletion, 4000);
    }

    function updateStepMeta(step) {
        const meta = STEP_META[step];

        if (!meta) {
            return;
        }

        if (stepTitleEl) {
            stepTitleEl.textContent = meta.title;
        }

        if (stepDescEl) {
            const desc = String(meta.desc || '').trim();
            stepDescEl.textContent = desc;
            stepDescEl.hidden = desc === '';
        }
    }

    const KKP_CHECKBOX_FIELDS = [
        { name: 'sex', chk: 'sexChk', hiddenId: 'kkpSex' },
        { name: 'civil_status', chk: 'civil_statusChk', hiddenId: 'kkpCivilStatus' },
        { name: 'youth_age_group', chk: 'youth_age_groupChk', hiddenId: 'kkpYouthAgeGroup' },
        { name: 'education', chk: 'educationChk', hiddenId: 'kkpEducation' },
        { name: 'youth_classification', chk: 'youth_classificationChk', hiddenId: 'kkpYouthClass' },
        { name: 'work_status', chk: 'work_statusChk', hiddenId: 'kkpWorkStatus' },
        { name: 'sk_voter', chk: 'sk_voterChk', hiddenId: 'kkpSkVoter' },
        { name: 'national_voter', chk: 'national_voterChk', hiddenId: 'kkpNationalVoter' },
        { name: 'kk_assembly', chk: 'kk_assemblyChk', hiddenId: 'kkpKkAssembly' },
        { name: 'kk_times', chk: 'kk_timesChk', hiddenId: 'kkpKkTimes' },
        { name: 'kk_reason', chk: 'kk_reasonChk', hiddenId: 'kkpKkReason' },
        { name: 'sk_voted', chk: 'sk_votedChk', hiddenId: 'kkpSkVoted' },
        { name: 'group_chat', chk: 'group_chatChk', hiddenId: 'kkpGroupChat' },
    ];

    function setCheckboxGroupValue(chkName, hiddenId, value) {
        if (!value || !form) return;

        const hidden = document.getElementById(hiddenId);
        if (hidden) {
            hidden.value = value;
        }

        form.querySelectorAll(`input[name="${chkName}"]`).forEach((input) => {
            input.checked = input.value === value;
        });

        const matched = form.querySelector(`input[name="${chkName}"][value="${value}"]`);
        if (matched) {
            matched.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function populateWizardForm(step1, respondentNumber) {
        if (!form || !step1 || typeof step1 !== 'object') {
            return;
        }

        suppressStep1Autosave = true;

        Object.entries(step1).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            const direct = form.querySelector(`[name="${key}"]`);
            if (direct && direct.type !== 'hidden' && direct.type !== 'checkbox' && direct.type !== 'radio') {
                direct.value = value;
                direct.dispatchEvent(new Event('input', { bubbles: true }));
                direct.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        KKP_CHECKBOX_FIELDS.forEach(({ name, chk, hiddenId }) => {
            const raw = Array.isArray(step1[name]) ? step1[name][0] : step1[name];
            const value = name === 'group_chat'
                ? (raw === 'Yes' || raw === 'No' ? raw : '')
                : raw;
            if (value) {
                setCheckboxGroupValue(chk, hiddenId, value);
            }
        });

        if (typeof window.syncAssemblyFollowUp === 'function') {
            window.syncAssemblyFollowUp();
        }

        if (step1.kk_times) {
            setCheckboxGroupValue('kk_timesChk', 'kkpKkTimes', step1.kk_times);
        }
        if (step1.kk_reason) {
            setCheckboxGroupValue('kk_reasonChk', 'kkpKkReason', step1.kk_reason);
        }

        if (step1.suffix) {
            const suffixSelect = document.getElementById('kkpSuffix');
            if (suffixSelect) {
                const options = Array.from(suffixSelect.options).map((option) => option.value);
                let suffixValue = String(step1.suffix).trim();
                if (!options.includes(suffixValue) && suffixValue && suffixValue.toLowerCase() !== 'none') {
                    suffixSelect.value = 'Others';
                    suffixSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    const customSuffix = document.getElementById('kkpCustomSuffix');
                    if (customSuffix) {
                        customSuffix.value = suffixValue;
                    }
                } else {
                    suffixSelect.value = suffixValue || 'None';
                    suffixSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        if (step1.custom_suffix) {
            const customSuffix = document.getElementById('kkpCustomSuffix');
            if (customSuffix) {
                customSuffix.value = step1.custom_suffix;
            }
        }

        if (step1.kk_times) {
            const kkTimes = document.getElementById('kkpKkTimes');
            if (kkTimes) {
                kkTimes.value = step1.kk_times;
            }
        }

        if (step1.kk_reason) {
            const kkReason = document.getElementById('kkpKkReason');
            if (kkReason) {
                kkReason.value = step1.kk_reason;
            }
        }

        if (step1.age && typeof window.kkpSyncYouthAgeGroupFromAge === 'function') {
            window.kkpSyncYouthAgeGroupFromAge(step1.age);
        }

        if (step1.signature) {
            const sigInput = document.getElementById('kkpSignatureData');
            if (sigInput) {
                sigInput.value = step1.signature;
            }
            if (typeof window.kkpRestoreSignaturePreview === 'function') {
                window.kkpRestoreSignaturePreview(step1.signature);
            }
        }

        if (step1.data_agreement) {
            const agreement = form.querySelector('[name="data_agreement"]');
            if (agreement) {
                agreement.checked = true;
            }
        }

        if (respondentNumber) {
            const respondentInput = form.querySelector('[name="respondent_number"]');
            if (respondentInput) {
                respondentInput.value = respondentNumber;
            }
            root.dataset.respondentNumber = respondentNumber;
        }

        if (typeof window.kkpRefreshSignatureName === 'function') {
            window.kkpRefreshSignatureName();
        }

        suppressStep1Autosave = false;
    }

    function restoreStep2Documents(step2) {
        if (!step2 || !step2.document_type) {
            return;
        }

        const documentType = step2.document_type;
        const typeRadio = document.querySelector(`input[name="document_type"][value="${documentType}"]`);

        if (typeRadio) {
            typeRadio.checked = true;
            typeRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const sides = step2.sides || {};
        const inputIds = DOCUMENT_INPUT_IDS[documentType] || [];

        ['front', 'back'].forEach((side, index) => {
            const inputId = inputIds[index];
            const config = previewConfig[inputId];

            if (!config || !sides[side]) {
                return;
            }

            const previewUrl = `${apiBase}/document/${documentType}/${side}`;

            if (config.img) {
                config.img.src = previewUrl;
            }

            if (config.fileName && sides[side].original_name) {
                config.fileName.textContent = sides[side].original_name;
            }

            if (config.preview) {
                config.preview.hidden = false;
            }

            if (config.empty) {
                config.empty.hidden = true;
            }

            if (config.dropzone) {
                config.dropzone.hidden = true;
            }
        });

        if (PHILIPPINE_OCR_DOC_TYPES.includes(documentType) && step2.id_verification) {
            renderOcrFields({
                id_type: step2.id_verification.id_type,
                confidence: step2.id_verification.confidence,
                full_name: step2.id_verification.detected_name,
                birthdate: step2.id_verification.detected_birthdate,
                sex: step2.id_verification.detected_sex,
                address: step2.id_verification.detected_address,
                id_number: step2.id_verification.id_number,
                success: step2.id_verification.success,
                validation_error: !step2.id_verification.success,
            });

            if (step2.id_verification.form_suggestions) {
                applyFormSuggestions(step2.id_verification.form_suggestions, { onlyEmpty: true });
            }
        }

        updateNavButtons(currentStep);
    }

    function showVerifyCard() {
        if (emailVerifyCard) {
            emailVerifyCard.hidden = false;
            emailVerifyCard.style.display = 'block';
        }
    }

    function showEmailStatus(message, type = 'error') {
        const emailErrorEl = document.getElementById('kkpWizardEmailError');
        if (!emailErrorEl) {
            return;
        }

        emailErrorEl.textContent = message || '';
        emailErrorEl.hidden = !message;
        emailErrorEl.classList.toggle('is-success', type === 'success');
    }

    function enableResendButton() {
        const resendBtn = document.getElementById('resendEmailBtn');
        const timer = document.getElementById('resendTimer');

        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.hidden = false;
        }

        if (timer) {
            timer.hidden = true;
            timer.textContent = '';
        }
    }

    function lockResendButton() {
        const resendBtn = document.getElementById('resendEmailBtn');
        const timer = document.getElementById('resendTimer');

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.hidden = false;
        }

        if (timer) {
            timer.hidden = true;
            timer.textContent = '';
        }
    }

    function startResendCooldownAfterSend() {
        if (window.startResendTimer) {
            window.startResendTimer();
            return;
        }

        lockResendButton();
    }

    async function prepareStep3(options = {}) {
        const skipAutoSend = options.skipAutoSend === true;
        const email = getDraftEmail();

        if (displayEmail && email) {
            displayEmail.textContent = email;
        }

        showVerifyCard();

        if (!verificationSent && !skipAutoSend) {
            const sent = await sendVerificationEmail(false);
            if (sent) {
                verificationSent = true;
                root.dataset.verificationSent = '1';
            } else {
                enableResendButton();
            }
        } else if (verificationSent) {
            const email = (displayEmail?.textContent || getDraftEmail() || 'default').trim().toLowerCase();
            const cooldownKey = 'kkp_setpw_resend_' + email;
            const until = parseInt(sessionStorage.getItem(cooldownKey) || '0', 10);
            const remaining = Math.ceil((until - Date.now()) / 1000);

            if (remaining > 0 && window.startResendTimer) {
                window.startResendTimer({ seconds: remaining, persist: false });
            } else if (until > 0 && remaining <= 0) {
                sessionStorage.removeItem(cooldownKey);
                enableResendButton();
            } else if (window.startResendTimer) {
                window.startResendTimer();
            } else {
                lockResendButton();
            }
        } else {
            lockResendButton();
        }

        if (!registrationCompleted) {
            startRegistrationCompletionPoll();
        }
    }

    function updateNavButtons(step) {
        const canGoBack = step >= 2;
        const hasSelectedFiles = hasPartialDocumentUpload();

        if (navBar) {
            navBar.hidden = false;
            navBar.classList.toggle('kkp-wizard-nav--step1', step === 1);
            navBar.classList.toggle('kkp-wizard-nav--step3', step === 3);
        }

        if (backBtn) {
            backBtn.hidden = !canGoBack;
            backBtn.disabled = !canGoBack;
            backBtn.style.display = canGoBack ? '' : 'none';
        }

        if (nextBtn) {
            nextBtn.hidden = step === 3;
            nextBtn.disabled = step === 2 && hasBlockingOcrError();
        }

        if (nextLabelEl) {
            if (step === 1) {
                nextLabelEl.textContent = 'Save & Continue';
            } else if (step === 2) {
                nextLabelEl.textContent = hasSelectedFiles ? 'Upload & Continue' : 'Skip & Continue';
            } else {
                nextLabelEl.textContent = 'Continue';
            }
        }

        clearAllButtons.forEach((btn) => {
            btn.hidden = registrationCompleted || step !== 1;
        });
    }

    async function setStep(step, options = {}) {
        currentStep = step;
        persistWizardStepLocally(step);

        if (!options.skipRemotePersist) {
            // Fire-and-forget so mobile "desktop site" reloads keep the same step.
            persistWizardStepRemotely(step);
        }

        Object.entries(panels).forEach(([key, panel]) => {
            if (!panel) {
                return;
            }

            const isActive = parseInt(key, 10) === step;
            panel.hidden = !isActive;

            if (isActive) {
                panel.style.animation = 'none';
                panel.offsetHeight;
                panel.style.animation = '';
            }
        });

        progressItems.forEach((item) => {
            const itemStep = parseInt(item.dataset.step, 10);
            item.classList.toggle('is-active', itemStep === step);
            item.classList.toggle('is-complete', itemStep < step);
        });

        progressConnectors.forEach((connector) => {
            const afterStep = parseInt(connector.dataset.afterStep, 10);
            connector.classList.toggle('is-complete', afterStep < step);
        });

        document.body.classList.toggle('kkp-wizard-step3-active', step === 3);

        updateStepMeta(step);
        updateNavButtons(step);

        if (step === 3) {
            await prepareStep3(options);
        }

        if (!options.skipScroll) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        scheduleMobileFormScale();
        setTimeout(scheduleMobileFormScale, 80);
    }

    function applyServerErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return;
        }

        Object.entries(errors).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const input = form?.querySelector(`[name="${field}"]`);

            if (input) {
                input.classList.add('kkp-input-err');
                const err = document.createElement('span');
                err.className = 'kkp-field-error';
                err.textContent = message;
                input.parentNode?.insertBefore(err, input.nextSibling);
            }
        });
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || 'Request failed.');
            error.errors = data.errors || {};
            error.turnstile_required = Boolean(data.turnstile_required);
            error.payload = data;
            throw error;
        }

        return data;
    }

    async function postFormData(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(data.message || 'Request failed.');
            error.errors = data.errors || {};
            error.turnstile_required = Boolean(data.turnstile_required);
            error.payload = data;
            throw error;
        }

        return data;
    }

    async function saveStep1() {
        if (!form) {
            return false;
        }

        document.querySelectorAll('.kkp-field-error').forEach((el) => el.remove());
        document.querySelectorAll('.kkp-input-err').forEach((el) => el.classList.remove('kkp-input-err'));

        if (typeof window.validateKkProfilingForm !== 'function') {
            return false;
        }

        const valid = await window.validateKkProfilingForm({
            skipEmailExistenceCheck: false,
        });

        if (!valid) {
            return false;
        }

        try {
            syncHiddenCheckboxFields();

            const formData = new FormData(form);
            formData.append('respondent_number', root.dataset.respondentNumber || '');
            const turnstileToken = typeof window.kkpConsumeTurnstileToken === 'function'
                ? window.kkpConsumeTurnstileToken()
                : '';
            if (turnstileToken) {
                formData.append('cf-turnstile-response', turnstileToken);
            }

            await postFormData(`${apiBase}/step-1`, formData);
            await setStep(2);
            return true;
        } catch (error) {
            applyServerErrors(error.errors);
            if (!error.errors || Object.keys(error.errors).length === 0) {
                alert(error.message);
            }
            return false;
        }
    }

    async function getStep2TurnstileToken() {
        if (typeof window.kabataanTurnstileChallenge === 'function') {
            return window.kabataanTurnstileChallenge();
        }
        if (window.KabataanTurnstileGate?.challenge) {
            return window.KabataanTurnstileGate.challenge();
        }
        if (typeof window.kkpChallengeTurnstile === 'function') {
            return window.kkpChallengeTurnstile();
        }
        return '';
    }

    async function saveStep2() {
        hideDocUploadError();

        const hasFiles = hasPartialDocumentUpload();

        if (hasFiles && !hasCompleteDocumentUpload()) {
            showDocUploadError('Please upload both front and back images of your selected ID, or remove the files to skip this step.');
            return false;
        }

        let turnstileToken = '';
        try {
            turnstileToken = await getStep2TurnstileToken();
        } catch (error) {
            if (error?.message === 'Verification cancelled.') {
                return false;
            }
            showDocUploadError(error?.message || 'Security verification failed. Please try again.');
            return false;
        }

        if (!hasFiles) {

            let saved = false;

            try {
                const formData = new FormData();
                formData.append('skip_documents', '1');
                if (turnstileToken) {
                    formData.append('cf-turnstile-response', turnstileToken);
                }

                const response = await postFormData(`${apiBase}/step-2`, formData);

                if (response?.verification_sent) {
                    verificationSent = true;
                    root.dataset.verificationSent = '1';

                    if (displayEmail && response.email) {
                        displayEmail.textContent = response.email;
                    }

                    if (window.startResendTimer) {
                        window.startResendTimer();
                    }
                }

                saved = true;

                if (saved) {
                    const skipAutoSend = Boolean(response?.verification_sent);
                    await setStep(3, { skipAutoSend });

                    if (response?.email_error) {
                        showEmailStatus(response.email_error, 'error');
                        enableResendButton();
                    }
                }
            } catch (error) {
                const message = error.errors?.document_type?.[0]
                    || error.errors?.registration?.[0]
                    || error.message;

                showDocUploadError(message);
            }

            return saved;
        }

        const documentType = getSelectedDocumentType();
        const files = getActiveDocumentFiles();

        if (!documentType) {
            showDocUploadError('Please select a document type.');
            return false;
        }

        const frontError = validateDocumentFile(files.front);
        const backError = validateDocumentFile(files.back);

        if (frontError || backError) {
            showDocUploadError(frontError || backError);
            return false;
        }

        const sameSideError = await validateDistinctFrontAndBack(files);
        if (sameSideError) {
            showDocUploadError(sameSideError);
            lastOcrPayload = {
                success: false,
                validation_error: true,
                message: sameSideError,
            };
            renderOcrFields(lastOcrPayload);
            return false;
        }

        if (PHILIPPINE_OCR_DOC_TYPES.includes(documentType)) {
            if (hasBlockingOcrError()) {
                showDocUploadError(lastOcrBlockingError);
                return false;
            }

            if (!lastOcrPayload || lastOcrPayload.validation_error || (!lastOcrPayload.success && !lastOcrPayload.needs_review)) {
                showDocUploadError(
                    lastOcrPayload?.message
                    || 'Please wait for ID scanning to finish, or upload a clearer front and back photo of your selected ID.',
                );
                await scanPhilippineIdIfReady();

                if (hasBlockingOcrError()) {
                    showDocUploadError(lastOcrBlockingError);
                    return false;
                }

                if (!lastOcrPayload || lastOcrPayload.validation_error || (!lastOcrPayload.success && !lastOcrPayload.needs_review)) {
                    showDocUploadError(
                        lastOcrPayload?.message
                        || 'Please upload a clearer supporting ID photo before continuing.',
                    );
                    return false;
                }
            }
        }

        try {
            const formData = new FormData();
            formData.append('document_type', documentType);
            formData.append(`${documentType}_front`, files.front);
            formData.append(`${documentType}_back`, files.back);

            const selfie = selfieVerificationEnabled ? getSelfieFile() : null;
            if (selfie) {
                formData.append('selfie', selfie);
            }
            if (turnstileToken) {
                formData.append('cf-turnstile-response', turnstileToken);
            }

            const response = await postFormData(`${apiBase}/step-2`, formData);

            if (response?.ocr) {
                lastOcrPayload = response.ocr;
                renderOcrFields(response.ocr);

                if (response.ocr.validation_error) {
                    const message = response.ocr.message
                        || response.message
                        || 'We couldn\'t validate this ID. Please upload a clearer front and back photo.';
                    showDocUploadError(message);
                    updateNavButtons(currentStep);
                    return false;
                }
            }

            if (response?.form_suggestions) {
                applyFormSuggestions(response.form_suggestions, { onlyEmpty: true });
            }

            if (response?.verification_sent) {
                verificationSent = true;
                root.dataset.verificationSent = '1';

                if (displayEmail && response.email) {
                    displayEmail.textContent = response.email;
                }

                if (window.startResendTimer) {
                    window.startResendTimer();
                }
            }

            const skipAutoSend = Boolean(response?.verification_sent);
            await setStep(3, { skipAutoSend });

            if (response?.email_error) {
                showEmailStatus(response.email_error, 'error');
                enableResendButton();
            }

            return true;
        } catch (error) {
            const message = error.errors?.document_type?.[0]
                || error.errors?.registration?.[0]
                || Object.values(error.errors || {}).flat?.()?.[0]
                || error.message
                || 'We couldn\'t validate this ID. Please upload a clearer front and back photo.';

            showDocUploadError(message);
            updateNavButtons(currentStep);
            return false;
        }
    }

    async function obtainEmailVerifyTurnstileToken(required) {
        if (!required) {
            return '';
        }
        if (typeof window.kabataanTurnstileChallengeIfRequired === 'function') {
            return window.kabataanTurnstileChallengeIfRequired(true);
        }
        if (typeof window.kabataanTurnstileChallenge === 'function') {
            return window.kabataanTurnstileChallenge();
        }
        if (window.KabataanTurnstileGate?.challenge) {
            return window.KabataanTurnstileGate.challenge();
        }
        if (typeof window.kkpChallengeTurnstile === 'function') {
            return window.kkpChallengeTurnstile();
        }
        return '';
    }

    async function sendVerificationEmail(isResend) {
        showEmailStatus('');

        try {
            let required = root.dataset.turnstileRequired === '1';
            let turnstileToken = '';
            try {
                turnstileToken = await obtainEmailVerifyTurnstileToken(required);
            } catch (challengeError) {
                if (challengeError?.message === 'Verification cancelled.') {
                    return false;
                }
                throw challengeError;
            }

            const endpoint = isResend ? `${apiBase}/resend-verification` : `${apiBase}/send-verification`;

            let data;
            try {
                data = await postJson(endpoint, {
                    'cf-turnstile-response': turnstileToken,
                });
            } catch (error) {
                if (error.turnstile_required && !required) {
                    root.dataset.turnstileRequired = '1';
                    turnstileToken = await obtainEmailVerifyTurnstileToken(true);
                    data = await postJson(endpoint, {
                        'cf-turnstile-response': turnstileToken,
                    });
                } else {
                    throw error;
                }
            }

            if (typeof data.turnstile_required !== 'undefined') {
                root.dataset.turnstileRequired = data.turnstile_required ? '1' : '0';
            }

            if (data.registration_completed) {
                showRegistrationCompleteState(Boolean(data.auto_approved));
                return true;
            }

            if (displayEmail && data.email) {
                displayEmail.textContent = data.email;
            }

            verificationSent = true;
            root.dataset.verificationSent = '1';

            showEmailStatus(
                data.message || 'Set password link sent. Please check your inbox.',
                'success',
            );

            const resendBtn = document.getElementById('resendEmailBtn');
            if (isResend && resendBtn) {
                const originalLabel = resendBtn.textContent;
                resendBtn.textContent = 'Email sent!';
                setTimeout(() => {
                    resendBtn.textContent = originalLabel || 'Resend set password link';
                }, 2500);
            }

            if (window.startResendTimer) {
                window.startResendTimer();
            }

            return true;
        } catch (error) {
            if (error?.message === 'Verification cancelled.') {
                return false;
            }

            if (typeof error.turnstile_required !== 'undefined') {
                root.dataset.turnstileRequired = error.turnstile_required ? '1' : '0';
            }

            if (error.errors?.draft?.[0] && registrationCompleted) {
                showRegistrationCompleteState(registrationAutoApproved);
                return false;
            }

            const emailMsg = error.errors?.email?.[0]
                || error.errors?.['cf-turnstile-response']?.[0]
                || error.errors?.draft?.[0]
                || error.message
                || 'Failed to send set password link.';

            showEmailStatus(emailMsg, 'error');
            enableResendButton();

            return false;
        }
    }

    window.kkpWizardSendVerification = sendVerificationEmail;

    async function handleNext() {
        if (currentStep === 1) {
            await saveStep1();
            return;
        }

        if (currentStep === 2) {
            await saveStep2();
        }
    }

    async function handleBack() {
        if (currentStep <= 1) {
            return;
        }

        const targetStep = currentStep - 1;

        try {
            await postJson(`${apiBase}/set-step`, { step: targetStep });
        } catch (error) {
            // Non-blocking — still show the previous step locally
        }

        await setStep(targetStep, { skipAutoSend: true, skipRemotePersist: true });

        if (targetStep === 2) {
            try {
                const response = await fetch(`${apiBase}/status`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();

                if (data.draft?.step2) {
                    restoreStep2Documents(data.draft.step2);
                }
            } catch (error) {
                // Non-blocking
            }
        }
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', handleNext);
    }

    if (backBtn) {
        backBtn.addEventListener('click', handleBack);
    }

    async function restoreDraftState() {
        try {
            const response = await fetch(`${apiBase}/status`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            const draft = data.draft;

            if (data.registration_completed) {
                if (data.email && displayEmail) {
                    displayEmail.textContent = data.email;
                }

                registrationAutoApproved = Boolean(data.auto_approved);
                showRegistrationCompleteState(registrationAutoApproved);
                return;
            }

            if (!draft) {
                return;
            }

            if (draft.email && displayEmail) {
                displayEmail.textContent = draft.email;
            }

            if (draft.verification_sent) {
                verificationSent = true;
                root.dataset.verificationSent = '1';
            }

            if (draft.current_step) {
                restoredStep = parseInt(draft.current_step, 10) || restoredStep;
            }

            if (draft.step1) {
                populateWizardForm(draft.step1, draft.respondent_number);
            }

            if (draft.step2) {
                restoreStep2Documents(draft.step2);
            }
        } catch (error) {
            // Non-blocking
        }
    }

    async function resetWizardFormState() {
        suppressStep1Autosave = true;

        if (step1DraftTimer) {
            clearTimeout(step1DraftTimer);
            step1DraftTimer = null;
        }

        if (form) {
            form.reset();
        }

        clearAllDocumentInputs();
        hideDocUploadError();

        docTypeRadios.forEach((radio) => {
            radio.checked = false;
        });

        if (schoolIdUploadPanel) schoolIdUploadPanel.hidden = true;
        if (nationalIdUploadPanel) nationalIdUploadPanel.hidden = true;
        if (votersIdUploadPanel) votersIdUploadPanel.hidden = true;
        if (philhealthIdUploadPanel) philhealthIdUploadPanel.hidden = true;
        if (otherIdUploadPanel) otherIdUploadPanel.hidden = true;
        if (selfieUploadPanel) selfieUploadPanel.hidden = true;

        const sigInput = document.getElementById('kkpSignatureData');
        if (sigInput) {
            sigInput.value = '';
        }

        if (typeof window.kkpRestoreSignaturePreview === 'function') {
            window.kkpRestoreSignaturePreview('');
        }

        if (displayEmail) {
            displayEmail.textContent = 'your-email@example.com';
        }

        verificationSent = false;
        root.dataset.verificationSent = '0';
        restoredStep = 1;
        root.dataset.initialStep = '1';
        persistWizardStepLocally(1);
        delete root.dataset.draftEmail;

        if (ocrPanel) {
            ocrPanel.hidden = true;
        }

        lastOcrPayload = null;
        hideDocUploadError();

        suppressStep1Autosave = false;
    }

    function openClearDraftModal() {
        if (!clearDraftModal || registrationCompleted) {
            return;
        }

        clearDraftModal.hidden = false;
        clearDraftConfirmBtn?.focus();
    }

    function closeClearDraftModal() {
        if (!clearDraftModal) {
            return;
        }

        clearDraftModal.hidden = true;
        formClearAllBtn?.focus();
    }

    async function confirmClearAllData() {
        if (registrationCompleted) {
            closeClearDraftModal();
            return;
        }

        if (clearDraftConfirmBtn) {
            clearDraftConfirmBtn.disabled = true;
        }

        try {
            await postJson(`${apiBase}/clear-draft`, {});
            await resetWizardFormState();
            await setStep(1, { skipAutoSend: true });
            closeClearDraftModal();
        } catch (error) {
            alert(error.message || 'Unable to clear draft data. Please try again.');
        } finally {
            if (clearDraftConfirmBtn) {
                clearDraftConfirmBtn.disabled = false;
            }
        }
    }

    function syncHiddenCheckboxFields() {
        const assemblyHidden = document.getElementById('kkpKkAssembly');
        const timesHidden = document.getElementById('kkpKkTimes');
        const reasonHidden = document.getElementById('kkpKkReason');
        const checkedAssembly = document.querySelector('input[name="kk_assemblyChk"]:checked');
        const checkedTimes = document.querySelector('input[name="kk_timesChk"]:checked');
        const checkedReason = document.querySelector('input[name="kk_reasonChk"]:checked');

        if (assemblyHidden && checkedAssembly) {
            assemblyHidden.disabled = false;
            assemblyHidden.value = checkedAssembly.value;
        }

        if (timesHidden) {
            timesHidden.disabled = false;
            timesHidden.value = checkedTimes
                ? checkedTimes.value
                : (assemblyHidden?.value === 'Yes' ? timesHidden.value : '');
        }

        if (reasonHidden) {
            reasonHidden.disabled = false;
            reasonHidden.value = checkedReason
                ? checkedReason.value
                : (assemblyHidden?.value === 'No' ? reasonHidden.value : '');
        }
    }

    async function persistStep1Draft() {
        if (!form || registrationCompleted || suppressStep1Autosave || currentStep !== 1) {
            return;
        }

        if (step1DraftInFlight) {
            step1DraftQueued = true;
            return;
        }

        step1DraftInFlight = true;

        try {
            syncHiddenCheckboxFields();
            const formData = new FormData(form);
            formData.append('respondent_number', root.dataset.respondentNumber || '');
            await postFormData(`${apiBase}/draft-step-1`, formData);
        } catch (error) {
            // Non-blocking — draft autosave must not interrupt typing
        } finally {
            step1DraftInFlight = false;

            if (step1DraftQueued) {
                step1DraftQueued = false;
                scheduleStep1DraftSave(150);
            }
        }
    }

    function scheduleStep1DraftSave(delayMs = 700) {
        if (!form || registrationCompleted || suppressStep1Autosave || currentStep !== 1) {
            return;
        }

        if (step1DraftTimer) {
            clearTimeout(step1DraftTimer);
        }

        step1DraftTimer = setTimeout(() => {
            step1DraftTimer = null;
            persistStep1Draft();
        }, delayMs);
    }

    function bindStep1DraftAutosave() {
        if (!form) {
            return;
        }

        form.addEventListener('input', () => scheduleStep1DraftSave());
        form.addEventListener('change', () => scheduleStep1DraftSave(350));

        // Flush pending autosave before refresh/close so cleared fields are not restored
        const flushDraft = () => {
            if (step1DraftTimer) {
                clearTimeout(step1DraftTimer);
                step1DraftTimer = null;
            }
            if (!form || registrationCompleted || suppressStep1Autosave || currentStep !== 1) {
                return;
            }
            if (step1DraftInFlight) {
                return;
            }

            try {
                syncHiddenCheckboxFields();
                const formData = new FormData(form);
                formData.append('respondent_number', root.dataset.respondentNumber || '');
                formData.append('_token', csrfToken());
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(`${apiBase}/draft-step-1`, formData);
                }
            } catch (e) {
                // ignore flush errors
            }
        };

        window.addEventListener('pagehide', flushDraft);
        window.addEventListener('beforeunload', flushDraft);
    }

    let mobileScaleRaf = null;
    let mobileScaleApplying = false;

    function isMobileFormViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function clearMobileFormScale() {
        if (!formCard) {
            return;
        }

        const shell = formCard.querySelector('.kkp-fs-scale-shell');
        const inner = formCard.querySelector('.kkp-fs-scale-inner');
        if (shell) {
            shell.style.height = '';
            shell.style.width = '';
        }
        if (inner) {
            inner.style.width = '';
            inner.style.minWidth = '';
            inner.style.transform = '';
            inner.style.zoom = '';
        }
    }

    function applyMobileFormScale() {
        if (mobileScaleApplying || !formCard) {
            return;
        }

        const step1 = document.getElementById('kkpWizardStep1');
        const shell = formCard.querySelector('.kkp-fs-scale-shell');
        const inner = formCard.querySelector('.kkp-fs-scale-inner');
        if (!shell || !inner) {
            return;
        }

        // Only scale the profiling form (step 1). Steps 2–3 stay normal responsive.
        if (!isMobileFormViewport() || currentStep !== 1 || (step1 && step1.hidden)) {
            clearMobileFormScale();
            return;
        }

        mobileScaleApplying = true;
        try {
            // Reset before measuring.
            inner.style.zoom = '1';
            inner.style.transform = 'none';
            inner.style.width = '860px';
            inner.style.minWidth = '860px';

            const designWidth = Math.max(860, Math.ceil(inner.scrollWidth || 860));
            inner.style.width = `${designWidth}px`;
            inner.style.minWidth = `${designWidth}px`;

            const available = Math.max(
                1,
                Math.floor(shell.clientWidth || formCard.clientWidth || window.innerWidth || 1),
            );
            const scale = Math.min(1, available / designWidth);

            // CSS zoom keeps tap/click hit-testing aligned with the visual.
            // transform:scale() looks right but mis-hits checkboxes on mobile.
            if ('zoom' in inner.style) {
                inner.style.transform = '';
                inner.style.zoom = String(scale);
                shell.style.width = '100%';
                shell.style.height = '';
            } else {
                inner.style.zoom = '';
                inner.style.transformOrigin = 'top left';
                inner.style.transform = `scale(${scale})`;
                shell.style.width = '100%';
                shell.style.height = `${Math.ceil(inner.scrollHeight * scale)}px`;
            }
        } finally {
            mobileScaleApplying = false;
        }
    }

    function scheduleMobileFormScale() {
        if (mobileScaleRaf) {
            cancelAnimationFrame(mobileScaleRaf);
        }
        mobileScaleRaf = requestAnimationFrame(() => {
            mobileScaleRaf = null;
            applyMobileFormScale();
        });
    }

    function bindMobileFormScale() {
        const run = () => {
            scheduleMobileFormScale();
            setTimeout(scheduleMobileFormScale, 80);
            setTimeout(scheduleMobileFormScale, 300);
        };

        run();
        window.addEventListener('resize', scheduleMobileFormScale);
        window.addEventListener('orientationchange', () => {
            setTimeout(scheduleMobileFormScale, 200);
        });

        // Re-scale when form fields change size — do NOT watch style (avoids feedback loop).
        const observer = new MutationObserver(() => scheduleMobileFormScale());
        if (form) {
            observer.observe(form, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['hidden', 'class'],
            });
        }
        window.addEventListener('load', scheduleMobileFormScale);
    }

    function bindClearAllDataControls() {
        clearAllButtons.forEach((btn) => {
            btn.addEventListener('click', openClearDraftModal);
        });
        clearDraftBackdrop?.addEventListener('click', closeClearDraftModal);
        clearDraftCloseBtn?.addEventListener('click', closeClearDraftModal);
        clearDraftCancelBtn?.addEventListener('click', closeClearDraftModal);
        clearDraftConfirmBtn?.addEventListener('click', confirmClearAllData);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && clearDraftModal && !clearDraftModal.hidden) {
                closeClearDraftModal();
            }
        });
    }

    async function initWizard() {
        bindDocumentTypeControls();
        bindStep1DraftAutosave();
        bindClearAllDataControls();
        bindMobileFormScale();

        const storedStep = readStoredWizardStep();
        if (storedStep) {
            restoredStep = storedStep;
        }

        if (root.dataset.completedEmail && displayEmail) {
            displayEmail.textContent = root.dataset.completedEmail;
        }

        if (registrationCompleted) {
            await setStep(3, { skipAutoSend: true, skipRemotePersist: true });
            showRegistrationCompleteState(registrationAutoApproved);
            return;
        }

        if (root.dataset.draftEmail && displayEmail) {
            displayEmail.textContent = root.dataset.draftEmail;
        }

        await restoreDraftState();
        suppressStep1Autosave = false;

        const serverEmailError = root.dataset.emailError;

        if (serverEmailError) {
            if (verificationSentOnLoad) {
                verificationSent = true;
                root.dataset.verificationSent = '1';
            }

            await setStep(3, { skipAutoSend: verificationSent, skipRemotePersist: true });

            showEmailStatus(serverEmailError, 'error');
            return;
        }

        // Prefer the highest trusted step among local storage, draft, and blade initial step.
        const draftStep = restoredStep || initialStep;
        const localStep = storedStep || draftStep;
        const targetStep = Math.max(1, Math.min(3, Math.max(draftStep, localStep)));
        const skipAutoSendOnStep3 = targetStep === 3 && (verificationSent || verificationSentOnLoad);

        await setStep(targetStep, {
            skipAutoSend: targetStep !== 3 || skipAutoSendOnStep3,
            skipRemotePersist: true,
        });
        persistWizardStepLocally(targetStep);
    }

    initWizard();
})();
