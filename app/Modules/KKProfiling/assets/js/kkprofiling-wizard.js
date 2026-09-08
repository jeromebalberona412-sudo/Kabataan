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

    function bindEmailLowercase(input) {
        if (!input) return;
        const applyLower = () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const lower = String(input.value || '').toLowerCase();
            if (input.value !== lower) {
                input.value = lower;
                if (typeof start === 'number' && typeof end === 'number' && document.activeElement === input) {
                    input.setSelectionRange(start, end);
                }
            }
        };
        const onType = () => {
            applyLower();
            showEmailStatus('');
            const fieldErr = input.closest('.kkp-form-group, .kkp-inline-pair--email, .form-group')
                ?.querySelector('.kkp-field-error');
            if (fieldErr) fieldErr.remove();
            input.classList.remove('is-invalid', 'error');
        };
        input.addEventListener('input', onType);
        input.addEventListener('blur', () => {
            input.value = String(input.value || '').trim().toLowerCase();
        });
        applyLower();
    }

    function bindEmailInputsWhenReady() {
        document.querySelectorAll(
            'input[type="email"], input[name="email"], input.kkp-email-input'
        ).forEach(bindEmailLowercase);
    }

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
    const OCR_SCAN_DOC_TYPES = ['national_id', 'philhealth_id', 'voters_id', 'school_id', 'other_id'];
    // Back-compat alias used by older helpers in this file.
    const PHILIPPINE_OCR_DOC_TYPES = OCR_SCAN_DOC_TYPES;

    const DOC_TYPE_LABELS = {
        national_id: 'PhilSys / National ID',
        philhealth_id: 'PhilHealth ID',
        voters_id: "Voter's ID",
        school_id: 'School ID',
        other_id: 'Other valid proof of identity',
    };

    const ocrPanel = document.getElementById('kkpWizardOcrPanel');
    const ocrTitleEl = document.getElementById('kkpWizardOcrTitle');
    const ocrStatusEl = document.getElementById('kkpWizardOcrStatus');
    const ocrFieldsEl = document.getElementById('kkpWizardOcrFields');
    const ocrNoteEl = document.getElementById('kkpWizardOcrNote');
    const ocrRetryEl = document.getElementById('kkpWizardOcrRetry');
    const ocrLoadingEl = document.getElementById('kkpWizardOcrLoading');
    const ocrLoadingTitleEl = document.getElementById('kkpWizardOcrLoadingTitle');
    const ocrLoadingSubEl = document.getElementById('kkpWizardOcrLoadingSub');
    let ocrLoadingTimer = null;
    let ocrStatusTypeTimer = null;
    let ocrLoadingPhaseIndex = 0;
    const OCR_LOADING_PHASES = [
        { title: 'Checking your ID…', sub: 'Reading the photo' },
        { title: 'Verifying with AI…', sub: 'Name and address check' },
        { title: 'Almost done…', sub: 'Finalizing result' },
    ];
    const docErrorEl = document.getElementById('kkpWizardDocError');
    const selfieUploadPanel = document.getElementById('kkpSelfieUploadPanel');
    const selfieInput = document.getElementById('kkpSelfie');
    const selfieVerificationEnabled = selfieUploadPanel?.dataset?.selfieEnabled === '1';

    let ocrScanToken = 0;
    let lastOcrPayload = null;
    let lastOcrBlockingError = null;
    let lastOcrScanFingerprint = null;
    let ocrScanInFlight = null;
    let ocrScanInFlightFingerprint = null;
    let lastStep1IdentityFingerprint = null;
    /** Tracks server-persisted ID sides after refresh (File inputs are empty). */
    let restoredDocumentSides = { documentType: '', front: false, back: false };

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
            badge: document.getElementById(`${inputId}CapturedBadge`),
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
            return sanitizeVerificationMessage(payload.message);
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

        const safeMessage = sanitizeVerificationMessage(message);
        lastOcrBlockingError = safeMessage;

        if (docErrorEl) {
            docErrorEl.hidden = false;
            docErrorEl.textContent = safeMessage;
            docErrorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        alert(safeMessage);
    }

    function stopStatusTypewriter() {
        if (ocrStatusTypeTimer) {
            window.clearInterval(ocrStatusTypeTimer);
            ocrStatusTypeTimer = null;
        }
        ocrStatusEl?.classList.remove('is-streaming');
        ocrLoadingTitleEl?.classList.remove('is-streaming');
    }

    function typeIntoElement(el, text, { charMs = 16 } = {}) {
        return new Promise((resolve) => {
            stopStatusTypewriter();
            if (!el) {
                resolve();
                return;
            }

            const full = String(text || '');
            el.hidden = false;
            el.textContent = '';
            el.classList.add('is-streaming');

            if (!full) {
                el.classList.remove('is-streaming');
                resolve();
                return;
            }

            let index = 0;
            ocrStatusTypeTimer = window.setInterval(() => {
                index += 1;
                el.textContent = full.slice(0, index);
                if (index >= full.length) {
                    stopStatusTypewriter();
                    resolve();
                }
            }, Math.max(8, charMs));
        });
    }

    function streamOcrStatus(text) {
        return typeIntoElement(ocrStatusEl, text, { charMs: 14 });
    }

    function setLoadingPhase(phase) {
        if (ocrLoadingTitleEl) {
            ocrLoadingTitleEl.hidden = false;
            ocrLoadingTitleEl.textContent = phase?.title || '';
        }
        if (ocrLoadingSubEl) {
            ocrLoadingSubEl.hidden = false;
            ocrLoadingSubEl.textContent = phase?.sub || '';
        }

        document.querySelectorAll('.kkp-id-verify-overlay:not([hidden])').forEach((overlay) => {
            const title = overlay.querySelector('.kkp-id-verify-overlay-title');
            const sub = overlay.querySelector('.kkp-id-verify-overlay-sub');
            if (title) {
                title.textContent = phase?.title || 'Verifying ID…';
            }
            if (sub) {
                sub.textContent = phase?.sub || 'Checking photo and details';
            }
        });
    }

    function setIdVerifyOverlayVisible(visible) {
        const activePanel = document.querySelector('.kkp-wizard-upload-panel:not([hidden])');
        document.querySelectorAll('.kkp-id-verify-overlay').forEach((overlay) => {
            const inActivePanel = Boolean(activePanel && activePanel.contains(overlay));
            if (visible && inActivePanel) {
                overlay.hidden = false;
                overlay.removeAttribute('hidden');
            } else {
                overlay.hidden = true;
                overlay.setAttribute('hidden', 'hidden');
            }
        });
    }

    function startOcrLoadingAnimation() {
        stopOcrLoadingAnimation();
        ocrLoadingPhaseIndex = 0;

        setIdVerifyOverlayVisible(true);
        setLoadingPhase(OCR_LOADING_PHASES[0]);

        // Results panel stays for post-verify messages; loading UI is the image overlay.
        if (ocrPanel) {
            ocrPanel.hidden = true;
            ocrPanel.setAttribute('aria-busy', 'true');
        }

        if (ocrLoadingEl) {
            ocrLoadingEl.hidden = true;
            ocrLoadingEl.setAttribute('hidden', 'hidden');
        }

        if (ocrStatusEl) {
            ocrStatusEl.textContent = '';
            ocrStatusEl.hidden = true;
        }

        if (ocrFieldsEl) {
            ocrFieldsEl.innerHTML = '';
            ocrFieldsEl.hidden = true;
        }

        if (ocrNoteEl) {
            ocrNoteEl.hidden = true;
        }

        if (ocrRetryEl) {
            ocrRetryEl.hidden = true;
        }

        ocrLoadingTimer = window.setInterval(() => {
            ocrLoadingPhaseIndex = Math.min(ocrLoadingPhaseIndex + 1, OCR_LOADING_PHASES.length - 1);
            setLoadingPhase(OCR_LOADING_PHASES[ocrLoadingPhaseIndex]);
        }, 1400);

        updateNavButtons(currentStep);
    }

    function stopOcrLoadingAnimation() {
        stopStatusTypewriter();

        if (ocrLoadingTimer) {
            window.clearInterval(ocrLoadingTimer);
            ocrLoadingTimer = null;
        }

        setIdVerifyOverlayVisible(false);

        if (ocrLoadingEl) {
            ocrLoadingEl.hidden = true;
            ocrLoadingEl.setAttribute('hidden', 'hidden');
        }
        if (ocrLoadingTitleEl) {
            ocrLoadingTitleEl.hidden = false;
            ocrLoadingTitleEl.classList.remove('is-streaming');
        }
        if (ocrLoadingSubEl) {
            ocrLoadingSubEl.hidden = false;
        }
        if (ocrStatusEl) {
            ocrStatusEl.hidden = false;
        }
        if (ocrPanel) {
            ocrPanel.removeAttribute('aria-busy');
        }
    }

    function setOcrPanelState(state) {
        if (!ocrPanel) {
            if (state === 'loading') {
                startOcrLoadingAnimation();
            } else {
                stopOcrLoadingAnimation();
            }
            updateNavButtons(currentStep);
            return;
        }

        ocrPanel.classList.remove('is-error', 'is-loading', 'is-success');

        if (state === 'loading') {
            ocrPanel.classList.add('is-loading');
            ocrPanel.hidden = true; // loading UI is the image overlay
            startOcrLoadingAnimation();
        } else {
            stopOcrLoadingAnimation();
            ocrPanel.hidden = false;
            if (state === 'error') {
                ocrPanel.classList.add('is-error');
            } else if (state === 'success') {
                ocrPanel.classList.add('is-success');
            }
            updateNavButtons(currentStep);
        }
    }

    function isOcrAnalysisInProgress() {
        if (ocrScanInFlight) {
            return true;
        }

        const activeOverlay = document.querySelector('.kkp-wizard-upload-panel:not([hidden]) .kkp-id-verify-overlay:not([hidden])');
        return Boolean(activeOverlay);
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

    function clearOcrUiState({ hidePanel = true } = {}) {
        lastOcrPayload = null;
        lastOcrBlockingError = null;
        lastOcrScanFingerprint = null;
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

        if (ocrRetryEl) {
            ocrRetryEl.hidden = true;
        }

        if (ocrPanel) {
            setOcrPanelState('ok');
            if (hidePanel) {
                ocrPanel.hidden = true;
            }
        }

        stopOcrLoadingAnimation();
        updateNavButtons(currentStep);
    }

    function sanitizeVerificationMessage(message) {
        if (!message || typeof message !== 'string') {
            return message;
        }

        return message
            .replace(/\bOCR\b/gi, 'verification')
            .replace(/\bTesseract\b/gi, 'verification')
            .replace(/AI ID analysis/gi, 'ID verification')
            .replace(/AI is analyzing your ID[.…]*/gi, '')
            .replace(/Analyzing your ID[.…]*/gi, '')
            .replace(/ID text detected/gi, 'ID details detected')
            .replace(/Text detected from/gi, 'Details detected from')
            .replace(/couldn't read any text from this ID/gi, "couldn't verify this ID")
            .replace(/could not read any text from this ID/gi, 'could not verify this ID')
            .replace(/No useful text detected/gi, 'No usable ID details detected')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    function formatConfidenceBandLabel(band) {
        const map = {
            high: 'High — details successfully detected',
            medium: 'Medium — please review the information',
            low: 'Low — please retake the photo',
        };

        return map[String(band || '')] || null;
    }

    function showOcrRetryActions(show) {
        if (ocrRetryEl) {
            ocrRetryEl.hidden = !show;
        }
    }

    function openRetakeForActiveSide(side) {
        const documentType = getSelectedDocumentType();
        const ids = DOCUMENT_INPUT_IDS[documentType] || [];
        const inputId = side === 'back' ? ids[1] : ids[0];

        if (!inputId) {
            return;
        }

        if (window.KkpIdCamera?.open) {
            window.KkpIdCamera.open(inputId, side);
            return;
        }

        document.getElementById(inputId)?.click();
    }

    function normalizeDocumentDetected(value) {
        if (value === true || value === 1 || value === '1') {
            return true;
        }
        if (value === false || value === 0 || value === '0') {
            return false;
        }
        if (value == null) {
            return null;
        }
        const raw = String(value).trim().toLowerCase();
        if (raw === '' || raw === 'null' || raw === 'unknown') {
            return null;
        }
        if (['yes', 'true', 'y'].includes(raw)) {
            return true;
        }
        if (['no', 'false', 'n'].includes(raw)) {
            return false;
        }
        return null;
    }

    function resolveVerificationStatus(payload) {
        const explicit = String(payload?.verification_status || '').trim().toLowerCase();
        if (explicit) {
            return explicit;
        }

        const detected = normalizeDocumentDetected(payload?.document_detected);
        if (payload?.quality_error || payload?.ocr_status === 'invalid_image' || payload?.ocr_status === 'invalid_upload') {
            return 'invalid_image';
        }
        if (payload?.validation_error && detected === null) {
            return 'unavailable';
        }
        if (detected === false || detected === true || payload?.success || payload?.needs_review) {
            return 'success';
        }
        if (payload?.validation_error) {
            return 'unavailable';
        }
        return 'success';
    }

    function renderOcrFields(payload) {
        if (!ocrStatusEl || !ocrPanel) {
            return;
        }

        if (!payload) {
            clearOcrUiState();
            return;
        }

        const verificationStatus = resolveVerificationStatus(payload);
        const detected = normalizeDocumentDetected(payload?.document_detected);
        const selectedType = getSelectedDocumentType();

        // Never show extracted ID field details (name/ID number) or Document detected YES/NO.
        if (ocrFieldsEl) {
            ocrFieldsEl.innerHTML = '';
            ocrFieldsEl.hidden = true;
        }
        if (ocrNoteEl) {
            ocrNoteEl.hidden = true;
        }

        ocrPanel.hidden = false;
        ocrStatusEl.hidden = false;

        const finishSoftContinueHint = () => {
            if (ocrNoteEl) {
                ocrNoteEl.hidden = false;
                ocrNoteEl.textContent = 'Supporting ID upload is optional. You can continue to the next step anytime.';
            }
            updateNavButtons(currentStep);
        };

        const showErrorState = (title, message, { showUploadError = false } = {}) => {
            if (ocrTitleEl) {
                ocrTitleEl.textContent = title || 'ID verification';
            }
            setOcrPanelState('error');
            streamOcrStatus(message);
            // Avoid duplicate red + blue panels with the same text.
            if (showUploadError) {
                hideDocUploadError();
            } else {
                hideDocUploadError();
            }
            showOcrRetryActions(true);
            lastOcrBlockingError = null;
            finishSoftContinueHint();
        };

        // CASE C/D/E — service unavailable / timeout / quota / auth
        if (verificationStatus === 'unavailable' || verificationStatus === 'error') {
            showErrorState(
                'ID verification',
                sanitizeVerificationMessage(payload?.message)
                    || 'ID verification is temporarily unavailable. Please try again.',
            );
            return;
        }

        // CASE F — malformed/empty AI response
        if (verificationStatus === 'invalid_response') {
            showErrorState(
                'ID verification',
                sanitizeVerificationMessage(payload?.message)
                    || 'Unable to analyze the document. Please try again.',
            );
            return;
        }

        // CASE G — unreadable / quality / invalid image
        if (verificationStatus === 'invalid_image') {
            showErrorState(
                'ID verification',
                sanitizeVerificationMessage(payload?.message)
                    || 'We couldn\'t reliably read this document. Please upload a clearer image.',
                { showUploadError: true },
            );
            return;
        }

        // Validation failures: wrong ID type, name mismatch, or not an ID — show the real message.
        if (payload?.validation_error || detected === false) {
            const mismatchMessage = formatOcrMismatchMessage(payload, selectedType)
                || sanitizeVerificationMessage(payload?.message)
                || 'The uploaded ID could not be verified. Please check your photos and try again.';
            const looksLikeTypeMismatch = Boolean(
                payload?.expected_id_type
                && payload?.detected_id_type
                && payload.expected_id_type !== payload.detected_id_type
            );
            const looksLikeNameMismatch = /name mismatch|step 1 profile|does not match your step 1/i.test(
                String(mismatchMessage || ''),
            );
            const looksLikeBirthdayMismatch = /birthday on the id does not match/i.test(String(mismatchMessage || ''));
            const looksLikeSexMismatch = /sex on the id does not match/i.test(String(mismatchMessage || ''));
            const looksLikeAddressMismatch = /address on the id does not match/i.test(String(mismatchMessage || ''));
            const title = looksLikeNameMismatch
                ? 'Name does not match'
                : (looksLikeBirthdayMismatch
                    ? 'Birthday does not match'
                    : (looksLikeSexMismatch
                        ? 'Sex does not match'
                        : (looksLikeAddressMismatch
                            ? 'Address does not match'
                            : (looksLikeTypeMismatch ? 'Wrong ID type' : 'ID verification'))));
            showErrorState(title, mismatchMessage);
            return;
        }

        if (payload?.success || payload?.needs_review || detected === true) {
            if (selfieVerificationEnabled && payload?.face_verification?.decision === 'FAIL') {
                showErrorState(
                    'ID verification',
                    'We could not confirm the selfie against the ID photo. Please upload a clearer image.',
                    { showUploadError: true },
                );
                return;
            }

            hideDocUploadError();
            showOcrRetryActions(false);
            // Success is silent — no "ID verified / matches profiling" panel.
            if (ocrPanel) {
                ocrPanel.hidden = true;
                ocrPanel.classList.remove('is-error', 'is-loading', 'is-success');
            }
            if (ocrStatusEl) {
                ocrStatusEl.textContent = '';
                ocrStatusEl.hidden = true;
            }
            stopOcrLoadingAnimation();
            if (selfieVerificationEnabled && PHILIPPINE_OCR_DOC_TYPES.includes(selectedType) && selfieUploadPanel) {
                selfieUploadPanel.hidden = false;
            } else if (selfieUploadPanel) {
                selfieUploadPanel.hidden = true;
            }
            updateNavButtons(currentStep);
            return;
        }

        showErrorState(
            'ID verification',
            sanitizeVerificationMessage(payload?.message)
                || 'We couldn\'t verify this ID. Please make sure the photos are clear, then continue when ready.',
        );
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
        if (!allowed.includes(detectedType) || Number(confidence) < 0.55) {
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

        if (ocrNoteEl) {
            ocrNoteEl.hidden = !applied;
        }

        return applied;
    }

    function getSelfieFile() {
        return selfieInput?.files?.[0] || null;
    }

    function buildStep1IdentityFingerprint() {
        if (!form) {
            return '';
        }

        const valueOf = (name) => String(form.querySelector(`[name="${name}"]`)?.value || '').trim().toLowerCase();

        return [
            valueOf('first_name'),
            valueOf('middle_name'),
            valueOf('last_name'),
            valueOf('birthday'),
            valueOf('sex'),
            valueOf('purok_zone'),
        ].join('|');
    }

    function buildDocumentScanFingerprint(documentType, files) {
        const front = files?.front;
        const back = files?.back;
        if (!documentType || !front || !back) {
            return null;
        }

        return [
            documentType,
            buildStep1IdentityFingerprint(),
            front.name || '',
            front.size || 0,
            front.lastModified || 0,
            back.name || '',
            back.size || 0,
            back.lastModified || 0,
        ].join('|');
    }

    function isReusableClientOcrPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        const status = resolveVerificationStatus(payload);
        if (status === 'unavailable' || status === 'error' || status === 'invalid_response') {
            return false;
        }

        const detected = payload.document_detected;
        if (detected === null || detected === undefined || detected === '') {
            return status === 'invalid_image';
        }

        return true;
    }

    async function scanIdIfReady(options = {}) {
        const retryCount = Number(options.retryCount || 0);
        const force = Boolean(options.force);
        const documentType = getSelectedDocumentType();

        if (!documentType) {
            clearOcrUiState({ hidePanel: true });
            return;
        }

        if (!OCR_SCAN_DOC_TYPES.includes(documentType) || !hasCompleteDocumentUpload()) {
            clearOcrUiState({ hidePanel: !hasPartialDocumentUpload() });
            return;
        }

        const liveFiles = getActiveDocumentFiles();

        // After refresh, keep the saved verification result unless Step 1 identity changed / force retry.
        if (
            !force
            && (!liveFiles.front || !liveFiles.back)
            && hasRestoredDocumentSide('front')
            && hasRestoredDocumentSide('back')
            && isReusableClientOcrPayload(lastOcrPayload)
        ) {
            renderOcrFields(lastOcrPayload);
            updateNavButtons(currentStep);
            return;
        }

        let files = liveFiles;
        if (!files.front || !files.back) {
            try {
                files = await resolveActiveDocumentFiles();
            } catch (restoreError) {
                lastOcrPayload = {
                    success: false,
                    validation_error: true,
                    message: restoreError?.message
                        || 'Unable to restore the saved ID photo. Please upload again.',
                };
                renderOcrFields(lastOcrPayload);
                showDocUploadError(lastOcrPayload.message);
                updateNavButtons(currentStep);
                return;
            }
        }

        const fingerprint = buildDocumentScanFingerprint(documentType, files);
        const sameSideError = await validateDistinctFrontAndBack(files);

        if (sameSideError) {
            lastOcrPayload = {
                success: false,
                validation_error: true,
                needs_review: false,
                verification_status: 'invalid_image',
                document_detected: null,
                id_type: null,
                confidence: 0,
                ocr_status: 'invalid_upload',
                message: sameSideError,
            };
            lastOcrScanFingerprint = fingerprint;
            renderOcrFields(lastOcrPayload);
            showDocUploadError(sameSideError);
            updateNavButtons(currentStep);
            return;
        }

        // Reuse prior result for the exact same front/back files (refresh / step nav / rapid events).
        if (
            !force
            && fingerprint
            && fingerprint === lastOcrScanFingerprint
            && isReusableClientOcrPayload(lastOcrPayload)
        ) {
            renderOcrFields(lastOcrPayload);
            updateNavButtons(currentStep);
            return;
        }

        // Deduplicate concurrent scans for the same fingerprint.
        if (
            !force
            && fingerprint
            && ocrScanInFlight
            && fingerprint === ocrScanInFlightFingerprint
        ) {
            return ocrScanInFlight;
        }

        const token = ++ocrScanToken;

        hideDocUploadError();
        lastOcrBlockingError = null;
        if (force || fingerprint !== lastOcrScanFingerprint) {
            lastOcrPayload = null;
        }

        if (ocrFieldsEl) {
            ocrFieldsEl.innerHTML = '';
            ocrFieldsEl.hidden = true;
        }

        setOcrPanelState('loading');
        showOcrRetryActions(false);

        const run = (async () => {
            await new Promise((resolve) => window.setTimeout(resolve, 40));
            if (token !== ocrScanToken) {
                return;
            }

            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            // Align with server Gemini window (~18s + overhead).
            const timeoutMs = 45000;
            const timeoutId = controller
                ? window.setTimeout(() => {
                    try {
                        controller.abort();
                    } catch (_abortError) {
                        // ignore
                    }
                }, timeoutMs)
                : null;

            try {
                const compressed = await compressActiveDocumentFiles();
                if (token !== ocrScanToken) {
                    return;
                }

                if (!compressed.front || !compressed.back) {
                    clearOcrUiState({ hidePanel: !hasPartialDocumentUpload() });
                    return;
                }

                const formData = new FormData();
                formData.append('document_type', documentType);
                formData.append('front', compressed.front);
                formData.append('back', compressed.back);

                const selfie = selfieVerificationEnabled ? getSelfieFile() : null;
                if (selfie) {
                    const selfieCompressed = await compressImageFile(selfie, { maxEdge: 1100, maxBytes: 1.2 * 1024 * 1024 });
                    formData.append('selfie', selfieCompressed);
                }

                const response = await fetch(`${apiBase}/detect-id`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    signal: controller?.signal,
                });

                const data = await response.json().catch(() => ({}));

                if (token !== ocrScanToken) {
                    return;
                }

                lastOcrPayload = data.ocr || data;
                lastOcrScanFingerprint = fingerprint;

                if (!response.ok || lastOcrPayload?.validation_error) {
                    if (!lastOcrPayload?.validation_error) {
                        lastOcrPayload = {
                            success: false,
                            validation_error: true,
                            message: friendlyUploadFailureMessage(data.message)
                                || formatOcrMismatchMessage({}, documentType),
                        };
                    } else if (lastOcrPayload?.message) {
                        lastOcrPayload.message = friendlyUploadFailureMessage(lastOcrPayload.message);
                    }
                } else if (lastOcrPayload?.auto_corrected) {
                    const detectedType = lastOcrPayload?.detected_id_type || lastOcrPayload?.id_type;
                    applyAutoDetectedDocumentType(detectedType, {
                        confidence: lastOcrPayload?.confidence || 0,
                    });
                }

                // Do NOT auto-retry unavailable/429 — that multiplies Groq token burn.
                // User can tap Retry (force) after waiting.
                renderOcrFields(lastOcrPayload);

                if ((lastOcrPayload?.success || lastOcrPayload?.needs_review) && !lastOcrPayload?.validation_error && data.form_suggestions) {
                    applyFormSuggestions(data.form_suggestions, { onlyEmpty: true });
                }

                updateNavButtons(currentStep);
            } catch (error) {
                if (token !== ocrScanToken) {
                    return;
                }

                const timedOut = error?.name === 'AbortError' || error?.name === 'TimeoutError';

                if (timedOut) {
                    lastOcrPayload = {
                        success: false,
                        validation_error: true,
                        needs_review: false,
                        verification_status: 'unavailable',
                        document_detected: null,
                        id_type: 'Unknown',
                        confidence: 0,
                        message: 'ID verification is temporarily unavailable. Please try again.',
                    };
                    lastOcrScanFingerprint = fingerprint;
                    renderOcrFields(lastOcrPayload);
                    updateNavButtons(currentStep);
                    return;
                }

                if (retryCount < 1) {
                    return scanIdIfReady({ retryCount: retryCount + 1, force: true });
                }

                const offlineMessage = 'Unable to verify right now. Please tap Retry, or continue to the next step.';
                lastOcrPayload = {
                    success: false,
                    validation_error: true,
                    needs_review: false,
                    verification_status: 'unavailable',
                    document_detected: null,
                    id_type: 'Unknown',
                    confidence: 0,
                    message: offlineMessage,
                };
                lastOcrScanFingerprint = fingerprint;
                renderOcrFields(lastOcrPayload);
                updateNavButtons(currentStep);
            } finally {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
            }
        })();

        ocrScanInFlight = run;
        ocrScanInFlightFingerprint = fingerprint;
        updateNavButtons(currentStep);

        try {
            await run;
        } finally {
            if (ocrScanInFlight === run) {
                ocrScanInFlight = null;
                ocrScanInFlightFingerprint = null;
            }
            updateNavButtons(currentStep);
        }
    }

    // Alias for existing call sites.
    async function scanPhilippineIdIfReady() {
        return scanIdIfReady();
    }

    function hasBlockingOcrError() {
        // Supporting ID verification is optional — never block Next / Skip & Continue.
        return false;
    }

    function ocrResultIsAcceptableForContinue() {
        // Optional step: always allow continue to Step 3.
        return true;
    }

    function requireDocumentTypeSelected(actionLabel = 'capture or upload') {
        if (getSelectedDocumentType()) {
            return true;
        }

        showDocUploadError(`Please select a document type first, then ${actionLabel} your ID.`);
        document.getElementById('kkpDocTypeFieldset')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    function syncCaptureControlsEnabled() {
        const enabled = Boolean(getSelectedDocumentType());
        document.querySelectorAll('[data-kkp-open-camera], [data-kkp-trigger-upload]').forEach((button) => {
            button.disabled = !enabled;
            button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            button.title = enabled
                ? ''
                : 'Select a document type first';
        });

        document.querySelectorAll('.kkp-wizard-file-input').forEach((input) => {
            if (input.id === 'kkpSelfie') {
                return;
            }
            input.disabled = !enabled;
        });
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
            return 'Image file is too large. Please upload a smaller image.';
        }

        return null;
    }

    /**
     * Shrink ID photos so both sides fit under typical PHP upload_max_filesize (often 2MB).
     * @param {File} file
     * @param {{ maxEdge?: number, quality?: number, maxBytes?: number }} [options]
     * @returns {Promise<File>}
     */
    async function compressImageFile(file, options = {}) {
        if (!file || !String(file.type || '').startsWith('image/')) {
            return file;
        }

        const maxEdge = Math.max(640, Number(options.maxEdge) || 1100);
        const maxBytes = Math.max(200_000, Number(options.maxBytes) || Math.floor(1.4 * 1024 * 1024));
        let quality = Math.min(0.9, Math.max(0.5, Number(options.quality) || 0.78));

        // Already small enough — keep original (PNG may still need convert if oversized).
        if (file.size <= maxBytes && file.type === 'image/jpeg') {
            return file;
        }

        if (typeof createImageBitmap !== 'function') {
            return file;
        }

        let bitmap = null;

        try {
            bitmap = await createImageBitmap(file);
            let width = bitmap.width || 0;
            let height = bitmap.height || 0;

            if (width < 1 || height < 1) {
                return file;
            }

            const scale = Math.min(1, maxEdge / Math.max(width, height));
            width = Math.max(1, Math.round(width * scale));
            height = Math.max(1, Math.round(height * scale));

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return file;
            }

            ctx.drawImage(bitmap, 0, 0, width, height);

            let blob = await new Promise((resolve) => {
                canvas.toBlob((result) => resolve(result), 'image/jpeg', quality);
            });

            while (blob && blob.size > maxBytes && quality > 0.52) {
                quality = Math.max(0.52, quality - 0.08);
                blob = await new Promise((resolve) => {
                    canvas.toBlob((result) => resolve(result), 'image/jpeg', quality);
                });
            }

            if (!blob || blob.size <= 0) {
                return file;
            }

            const baseName = String(file.name || 'id-photo').replace(/\.[^.]+$/, '') || 'id-photo';

            return new File([blob], `${baseName}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now(),
            });
        } catch (_error) {
            return file;
        } finally {
            try {
                bitmap?.close?.();
            } catch (_closeError) {
                // ignore
            }
        }
    }

    function assignFileQuietly(input, file) {
        if (!input || !file || typeof DataTransfer === 'undefined') {
            return false;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;

        return Boolean(input.files?.[0]);
    }

    /**
     * Compress active front/back files and rewrite the file inputs so later step-2 uploads stay small.
     * @returns {Promise<{ front: File|null, back: File|null }>}
     */
    async function compressActiveDocumentFiles() {
        const documentType = getSelectedDocumentType();
        const inputs = getDocumentInputsForType(documentType);
        const frontInput = inputs[0] || null;
        const backInput = inputs[1] || null;
        const current = await resolveActiveDocumentFiles();

        let front = current.front;
        let back = current.back;

        if (front) {
            front = await compressImageFile(front, { maxEdge: 900, quality: 0.72, maxBytes: 900 * 1024 });
            if (frontInput) {
                assignFileQuietly(frontInput, front);
            }
        }

        if (back) {
            back = await compressImageFile(back, { maxEdge: 900, quality: 0.72, maxBytes: 900 * 1024 });
            if (backInput) {
                assignFileQuietly(backInput, back);
            }
        }

        return { front, back };
    }

    function friendlyUploadFailureMessage(message) {
        const text = String(message || '');
        if (/failed to upload/i.test(text)) {
            return 'One of the ID images was too large to upload. Please retake or choose a smaller photo, then try again.';
        }
        return text;
    }

    async function averageHashFromFile(file) {
        if (!file || typeof createImageBitmap !== 'function') {
            return null;
        }

        let bitmap = null;

        try {
            bitmap = await createImageBitmap(file);
            const canvas = document.createElement('canvas');
            canvas.width = 8;
            canvas.height = 8;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) {
                return null;
            }

            ctx.drawImage(bitmap, 0, 0, 8, 8);
            const data = ctx.getImageData(0, 0, 8, 8).data;
            const grays = [];
            let sum = 0;

            for (let i = 0; i < 64; i += 1) {
                const offset = i * 4;
                const gray = Math.round((data[offset] * 0.299) + (data[offset + 1] * 0.587) + (data[offset + 2] * 0.114));
                grays.push(gray);
                sum += gray;
            }

            const average = sum / 64;
            let bits = '';
            grays.forEach((gray) => {
                bits += gray >= average ? '1' : '0';
            });

            let hex = '';
            for (let i = 0; i < 64; i += 4) {
                hex += parseInt(bits.slice(i, i + 4), 2).toString(16);
            }

            return hex.padStart(16, '0');
        } catch (error) {
            return null;
        } finally {
            if (bitmap && typeof bitmap.close === 'function') {
                bitmap.close();
            }
        }
    }

    function hammingHexDistance(hashA, hashB) {
        if (!hashA || !hashB || String(hashA).length !== String(hashB).length) {
            return null;
        }

        const a = String(hashA).toLowerCase();
        const b = String(hashB).toLowerCase();

        if (!/^[0-9a-f]+$/.test(a) || !/^[0-9a-f]+$/.test(b)) {
            return null;
        }

        let distance = 0;
        for (let i = 0; i < a.length; i += 1) {
            const nibbleA = parseInt(a[i], 16);
            const nibbleB = parseInt(b[i], 16);
            let xor = nibbleA ^ nibbleB;
            while (xor > 0) {
                distance += xor & 1;
                xor >>= 1;
            }
        }

        return distance;
    }

    async function filesAppearIdentical(front, back) {
        if (!front || !back) {
            return false;
        }

        if (front === back) {
            return true;
        }

        // Always compare content hashes (sizes often differ for real front vs back).
        if (window.crypto?.subtle) {
            try {
                const [frontHash, backHash] = await Promise.all([
                    crypto.subtle.digest('SHA-256', await front.arrayBuffer()),
                    crypto.subtle.digest('SHA-256', await back.arrayBuffer()),
                ]);

                const toHex = (buffer) => [...new Uint8Array(buffer)]
                    .map((byte) => byte.toString(16).padStart(2, '0'))
                    .join('');

                if (toHex(frontHash) === toHex(backHash)) {
                    return true;
                }
            } catch (_error) {
                // Fall through.
            }
        } else if (
            front.size === back.size
            && front.name === back.name
            && front.lastModified === back.lastModified
        ) {
            return true;
        }

        // Only treat as the same photo when perceptual hashes are essentially identical.
        // ID front/back cards often look similar at coarse aHash — do not use a loose threshold.
        const [phashFront, phashBack] = await Promise.all([
            averageHashFromFile(front),
            averageHashFromFile(back),
        ]);
        const distance = hammingHexDistance(phashFront, phashBack);

        if (distance === null || distance > 1) {
            return false;
        }

        // Extra guard: near-identical aHash still needs nearly the same byte size.
        const larger = Math.max(front.size, back.size);
        const smaller = Math.min(front.size, back.size);

        return larger > 0 && (smaller / larger) >= 0.97;
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

    function hasRestoredDocumentSide(side) {
        const documentType = getSelectedDocumentType();
        if (!documentType || restoredDocumentSides.documentType !== documentType) {
            return false;
        }
        return Boolean(restoredDocumentSides[side]);
    }

    function clearRestoredDocumentSides(documentType = null) {
        if (documentType && restoredDocumentSides.documentType && restoredDocumentSides.documentType !== documentType) {
            return;
        }
        restoredDocumentSides = { documentType: '', front: false, back: false };
    }

    function markRestoredDocumentSide(documentType, side, present) {
        if (!documentType || !side) {
            return;
        }

        if (!present) {
            if (restoredDocumentSides.documentType === documentType) {
                restoredDocumentSides[side] = false;
                if (!restoredDocumentSides.front && !restoredDocumentSides.back) {
                    restoredDocumentSides.documentType = '';
                }
            }
            return;
        }

        if (restoredDocumentSides.documentType && restoredDocumentSides.documentType !== documentType) {
            restoredDocumentSides = { documentType, front: false, back: false };
        }
        restoredDocumentSides.documentType = documentType;
        restoredDocumentSides[side] = true;
    }

    function hasPartialDocumentUpload() {
        const files = getActiveDocumentFiles();

        return Boolean(
            files.front
            || files.back
            || hasRestoredDocumentSide('front')
            || hasRestoredDocumentSide('back')
        );
    }

    function hasCompleteDocumentUpload() {
        const files = getActiveDocumentFiles();

        if (files.front && files.back) {
            return true;
        }

        return hasRestoredDocumentSide('front') && hasRestoredDocumentSide('back');
    }

    async function fileFromPreviewUrl(url, filename) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            throw new Error('Unable to restore the saved ID photo. Please upload again.');
        }
        const blob = await response.blob();
        const mime = blob.type || 'image/jpeg';
        const safeName = filename || 'id-photo.jpg';

        return new File([blob], safeName, { type: mime, lastModified: Date.now() });
    }

    /**
     * Prefer live File inputs; after refresh, rebuild Files from stored draft preview URLs.
     */
    async function resolveActiveDocumentFiles() {
        const documentType = getSelectedDocumentType();
        const live = getActiveDocumentFiles();
        if (live.front && live.back) {
            return live;
        }

        if (!documentType || !hasCompleteDocumentUpload()) {
            return { front: live.front, back: live.back };
        }

        const inputIds = DOCUMENT_INPUT_IDS[documentType] || [];
        const sides = ['front', 'back'];
        const resolved = { front: live.front, back: live.back };

        for (let index = 0; index < sides.length; index += 1) {
            const side = sides[index];
            if (resolved[side]) {
                continue;
            }
            if (!hasRestoredDocumentSide(side)) {
                continue;
            }

            const inputId = inputIds[index];
            const config = previewConfig[inputId];
            const previewUrl = config?.img?.getAttribute('src');
            if (!previewUrl) {
                continue;
            }

            const originalName = config?.fileName?.textContent?.trim() || `${documentType}-${side}.jpg`;
            const file = await fileFromPreviewUrl(previewUrl, originalName);
            resolved[side] = file;

            const input = getDocumentInput(inputId);
            if (input && typeof DataTransfer !== 'undefined') {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
            }
            markRestoredDocumentSide(documentType, side, false);
            setCaptureBadge(inputId, true);
        }

        return resolved;
    }

    function clearDocumentInputsForType(documentType) {
        getDocumentInputsForType(documentType).forEach((input) => clearDocumentInput(input));
    }

    function clearAllDocumentInputs() {
        clearRestoredDocumentSides();
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

    function setCaptureBadge(inputId, captured) {
        const badge = document.getElementById(`${inputId}CapturedBadge`);
        if (!badge) {
            return;
        }
        if (captured) {
            badge.hidden = false;
            badge.removeAttribute('hidden');
            badge.setAttribute('aria-hidden', 'false');
        } else {
            badge.hidden = true;
            badge.setAttribute('hidden', 'hidden');
            badge.setAttribute('aria-hidden', 'true');
        }
    }

    function updateIdCaptureProgress() {
        const documentType = getSelectedDocumentType();
        if (!documentType) {
            return;
        }

        const files = getActiveDocumentFiles();
        const panel = document.querySelector(`.kkp-wizard-id-progress[data-doc-type="${documentType}"]`);
        if (!panel) {
            return;
        }

        ['front', 'back'].forEach((side) => {
            const item = panel.querySelector(`[data-progress-side="${side}"] [data-progress-label]`);
            if (!item) {
                return;
            }
            const hasFile = Boolean(files[side] || hasRestoredDocumentSide(side));
            item.textContent = hasFile ? '✓ Captured' : 'Not captured';
        });
    }

    // Live-camera / native capture can reinforce the ✓ Captured badge after assigning a file.
    window.KkpWizardMarkIdCaptured = function markIdCapturedFromCamera(inputId) {
        if (!inputId) {
            return;
        }
        setCaptureBadge(inputId, true);
        updateIdCaptureProgress();
    };

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

        Object.entries(DOCUMENT_INPUT_IDS).forEach(([documentType, ids]) => {
            const sideIndex = ids.indexOf(inputId);
            if (sideIndex === 0) {
                markRestoredDocumentSide(documentType, 'front', false);
            } else if (sideIndex === 1) {
                markRestoredDocumentSide(documentType, 'back', false);
            }
        });

        setCaptureBadge(inputId, false);
        updateIdCaptureProgress();
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

        if (input.id !== 'kkpSelfie' && !requireDocumentTypeSelected('upload')) {
            input.value = '';
            resetFilePreview(input.id);
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

        setCaptureBadge(input.id, true);
        updateIdCaptureProgress();
        updateNavButtons(currentStep);

        if (!skipScan) {
            // New image uploaded — clear previous AI result before rescanning.
            if (!hasCompleteDocumentUpload()) {
                clearOcrUiState({ hidePanel: true });
                if (ocrStatusEl && ocrPanel && hasPartialDocumentUpload()) {
                    ocrPanel.hidden = false;
                    ocrStatusEl.textContent = 'Front or back captured. Add the other side to verify your ID.';
                    setOcrPanelState('ok');
                }
            } else {
                lastOcrPayload = null;
                lastOcrBlockingError = null;
                hideDocUploadError();
                scanIdIfReady();
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
        syncCaptureControlsEnabled();

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

        scanIdIfReady();
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
            input?.addEventListener('change', async () => {
                const selected = input.files?.[0];
                if (selected && selected.size > 900_000) {
                    const compressed = await compressImageFile(selected);
                    if (compressed && compressed !== selected) {
                        assignFileQuietly(input, compressed);
                    }
                }
                updateFilePreview(input);
            });
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

        document.getElementById('kkpOcrRetakeFront')?.addEventListener('click', (event) => {
            event.preventDefault();
            openRetakeForActiveSide('front');
        });
        document.getElementById('kkpOcrRetakeBack')?.addEventListener('click', (event) => {
            event.preventDefault();
            openRetakeForActiveSide('back');
        });
        document.getElementById('kkpOcrUploadAnother')?.addEventListener('click', (event) => {
            event.preventDefault();
            const documentType = getSelectedDocumentType();
            const ids = DOCUMENT_INPUT_IDS[documentType] || [];
            const missingFront = !getActiveDocumentFiles().front;
            const inputId = missingFront ? ids[0] : ids[1];
            if (inputId) {
                document.getElementById(inputId)?.click();
            }
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
        clearRestoredDocumentSides();

        ['front', 'back'].forEach((side, index) => {
            const inputId = inputIds[index];
            const config = previewConfig[inputId];

            if (!config || !sides[side]) {
                markRestoredDocumentSide(documentType, side, false);
                return;
            }

            const previewUrl = `${apiBase}/document/${documentType}/${side}?t=${Date.now()}`;

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

            markRestoredDocumentSide(documentType, side, true);
            setCaptureBadge(inputId, true);
        });

        updateIdCaptureProgress();

        if (PHILIPPINE_OCR_DOC_TYPES.includes(documentType) && step2.id_verification) {
            const restored = {
                id_type: step2.id_verification.id_type,
                confidence: step2.id_verification.confidence,
                full_name: step2.id_verification.detected_name,
                birthdate: step2.id_verification.detected_birthdate,
                sex: step2.id_verification.detected_sex || null,
                address: step2.id_verification.detected_address,
                id_number: step2.id_verification.id_number,
                success: step2.id_verification.success,
                validation_error: Boolean(step2.id_verification.validation_error)
                    || !step2.id_verification.success,
                verification_status: step2.id_verification.verification_status || 'success',
                document_detected: step2.id_verification.document_detected ?? null,
                message: step2.id_verification.message || null,
                pair_hash: step2.id_verification.pair_hash || null,
                name_match: step2.id_verification.name_match ?? null,
                birthdate_match: step2.id_verification.birthdate_match ?? null,
                sex_match: step2.id_verification.sex_match ?? null,
                address_match: step2.id_verification.address_match ?? null,
                from_cache: true,
            };
            lastOcrPayload = restored;
            lastOcrScanFingerprint = [
                documentType,
                buildStep1IdentityFingerprint(),
                'restored',
                sides.front?.original_name || '',
                sides.back?.original_name || '',
            ].join('|');
            renderOcrFields(restored);

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
        const analyzing = step === 2 && isOcrAnalysisInProgress();

        if (navBar) {
            navBar.hidden = false;
            navBar.classList.toggle('kkp-wizard-nav--step1', step === 1);
            navBar.classList.toggle('kkp-wizard-nav--step3', step === 3);
        }

        if (backBtn) {
            backBtn.hidden = !canGoBack;
            backBtn.disabled = !canGoBack || analyzing;
            backBtn.style.display = canGoBack ? '' : 'none';
            backBtn.title = analyzing ? 'Please wait while your ID is being verified.' : '';
        }

        if (nextBtn) {
            nextBtn.hidden = step === 3;
            nextBtn.disabled = analyzing;
            nextBtn.setAttribute('aria-disabled', analyzing ? 'true' : 'false');
            nextBtn.title = analyzing ? 'Please wait while your ID is being verified.' : '';
        }

        if (nextLabelEl) {
            if (analyzing) {
                nextLabelEl.textContent = 'Verifying ID…';
            } else if (step === 1) {
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

        if (step === 2) {
            const identityFp = buildStep1IdentityFingerprint();
            const identityChanged = lastStep1IdentityFingerprint !== null
                && identityFp !== lastStep1IdentityFingerprint;
            lastStep1IdentityFingerprint = identityFp;

            if (hasCompleteDocumentUpload() && OCR_SCAN_DOC_TYPES.includes(getSelectedDocumentType())) {
                if (identityChanged || !isReusableClientOcrPayload(lastOcrPayload)) {
                    if (identityChanged) {
                        lastOcrScanFingerprint = null;
                        lastOcrPayload = null;
                    }
                    scanIdIfReady({ force: identityChanged });
                } else {
                    renderOcrFields(lastOcrPayload);
                }
            }
        }

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
            // Clear stale Step 2 OCR so setStep(2) rechecks against the updated Step 1 identity.
            lastOcrScanFingerprint = null;
            lastOcrPayload = null;
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
        const liveFiles = getActiveDocumentFiles();

        if (!documentType) {
            showDocUploadError('Please select a document type.');
            return false;
        }

        // After refresh, prefer continuing with already-persisted draft images.
        if (
            (!liveFiles.front || !liveFiles.back)
            && hasRestoredDocumentSide('front')
            && hasRestoredDocumentSide('back')
            && isReusableClientOcrPayload(lastOcrPayload)
            && !lastOcrPayload?.validation_error
        ) {
            try {
                const formData = new FormData();
                formData.append('document_type', documentType);
                formData.append('use_stored_documents', '1');
                if (turnstileToken) {
                    formData.append('cf-turnstile-response', turnstileToken);
                }

                const response = await postFormData(`${apiBase}/step-2`, formData);

                if (response?.ocr) {
                    lastOcrPayload = response.ocr;
                    renderOcrFields(response.ocr);
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
                const message = friendlyUploadFailureMessage(
                    error.errors?.document_type?.[0]
                    || error.errors?.registration?.[0]
                    || error.message
                    || 'We couldn\'t continue with your saved ID photos. Please re-upload and try again.',
                );
                showDocUploadError(message);
                updateNavButtons(currentStep);
                return false;
            }
        }

        let files = liveFiles;
        try {
            files = await resolveActiveDocumentFiles();
        } catch (restoreError) {
            showDocUploadError(restoreError?.message || 'Unable to restore the saved ID photo. Please upload again.');
            return false;
        }

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

        try {
            const compressed = await compressActiveDocumentFiles();
            if (!compressed.front || !compressed.back) {
                showDocUploadError('Please upload both front and back images of your selected ID.');
                return false;
            }

            const formData = new FormData();
            formData.append('document_type', documentType);
            formData.append(`${documentType}_front`, compressed.front);
            formData.append(`${documentType}_back`, compressed.back);

            const selfie = selfieVerificationEnabled ? getSelfieFile() : null;
            if (selfie) {
                const selfieCompressed = await compressImageFile(selfie, { maxEdge: 1280, maxBytes: 1.2 * 1024 * 1024 });
                formData.append('selfie', selfieCompressed);
            }
            if (turnstileToken) {
                formData.append('cf-turnstile-response', turnstileToken);
            }

            const response = await postFormData(`${apiBase}/step-2`, formData);

            if (response?.ocr) {
                lastOcrPayload = response.ocr;
                renderOcrFields(response.ocr);
                // Soft notice only — never block Step 3 for invalid ID on this optional step.
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
            const message = friendlyUploadFailureMessage(
                error.errors?.document_type?.[0]
                || error.errors?.registration?.[0]
                || error.errors?.back?.[0]
                || error.errors?.front?.[0]
                || Object.values(error.errors || {}).flat?.()?.[0]
                || error.message
                || 'We couldn\'t save your documents. Please try again, or skip this step.',
            );

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
        if (currentStep === 2 && isOcrAnalysisInProgress()) {
            showDocUploadError('Please wait — your ID is still being verified.');
            ocrPanel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

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

        // Signature pad sets the hidden field programmatically — force an immediate draft save
        // so refresh / mobile↔desktop mode switches keep the uploaded signature.
        window.kkpOnSignatureChanged = function () {
            if (registrationCompleted || suppressStep1Autosave || currentStep !== 1) {
                return;
            }
            if (step1DraftTimer) {
                clearTimeout(step1DraftTimer);
                step1DraftTimer = null;
            }
            persistStep1Draft();
        };

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

                // sendBeacon / keepalive payloads are capped (~64KB). Large signature data URLs
                // fail silently and can block flushing other field updates. Signature is already
                // persisted via kkpOnSignatureChanged; omit oversized payloads from unload flush.
                const signatureValue = String(formData.get('signature') || '');
                if (signatureValue.length > 40000) {
                    formData.delete('signature');
                }

                if (navigator.sendBeacon) {
                    navigator.sendBeacon(`${apiBase}/draft-step-1`, formData);
                    return;
                }

                fetch(`${apiBase}/draft-step-1`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).catch(() => {});
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
        bindEmailInputsWhenReady();
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
