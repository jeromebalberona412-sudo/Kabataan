/**
 * KK Profiling — device-style live camera ID capture.
 * Full viewfinder (no ID card edge frame). Manual shutter only.
 * On phones, prefer the native device camera app when available.
 */
(function () {
    const modal = document.getElementById('kkpIdCameraModal');
    if (!modal) {
        return;
    }

    const video = document.getElementById('kkpIdCameraVideo');
    const canvas = document.getElementById('kkpIdCameraCanvas');
    const titleEl = document.getElementById('kkpIdCameraTitle');
    const hintEl = document.getElementById('kkpIdCameraHint');
    const statusEl = document.getElementById('kkpIdCameraStatus');
    const detectEl = document.getElementById('kkpIdCameraDetect');
    const guideEl = document.getElementById('kkpIdCameraGuide');
    const guideLabelEl = document.getElementById('kkpIdCameraGuideLabel');
    const captureBtn = document.getElementById('kkpIdCameraCapture');
    const closeBtn = document.getElementById('kkpIdCameraClose');
    const switchUploadBtn = document.getElementById('kkpIdCameraUseUpload');
    const footerUploadBtn = document.getElementById('kkpIdCameraFooterUpload');
    const helpUploadBtn = document.getElementById('kkpIdCameraHelpUpload');
    const helpManualBtn = document.getElementById('kkpIdCameraManualHint');
    const fallbackEl = document.getElementById('kkpIdCameraFallback');
    const helpEl = document.getElementById('kkpIdCameraHelp');
    const stageEl = document.getElementById('kkpIdCameraStage');

    let mediaStream = null;
    let targetInputId = null;
    let targetSide = 'front';
    let opening = false;
    let capturing = false;
    let detectionTimer = null;
    let helpTimer = null;

    const MAX_BYTES = 10 * 1024 * 1024;
    const MIN_WIDTH = 480;
    const MIN_HEIGHT = 300;

    function setStatus(message, tone = 'info') {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.dataset.tone = tone;
        statusEl.hidden = !message;
    }

    function setDetectMessage(message) {
        if (detectEl) {
            detectEl.textContent = message || '';
        }
    }

    function setGuideState(state) {
        if (!guideEl) {
            return;
        }
        guideEl.dataset.state = state || 'idle';
        if (guideLabelEl) {
            const labels = {
                idle: 'Camera ready',
                searching: 'Camera ready',
                detected: 'Camera ready',
                steady: 'Hold steady',
                capturing: 'Capturing…',
            };
            guideLabelEl.textContent = labels[state] || 'Camera ready';
        }
    }

    function showHelpPanel(show) {
        if (helpEl) {
            helpEl.hidden = !show;
        }
    }

    function showFallback(message) {
        stopDetectionLoop();
        if (stageEl) {
            stageEl.hidden = true;
        }
        if (captureBtn) {
            captureBtn.hidden = true;
        }
        if (footerUploadBtn) {
            footerUploadBtn.hidden = true;
        }
        showHelpPanel(false);
        if (fallbackEl) {
            fallbackEl.hidden = false;
            const msg = fallbackEl.querySelector('[data-fallback-message]');
            if (msg) {
                msg.textContent = message;
            }
        }
        setDetectMessage('');
        setStatus(message, 'error');
    }

    function showCameraUi() {
        if (stageEl) {
            stageEl.hidden = false;
        }
        if (captureBtn) {
            captureBtn.hidden = false;
        }
        if (footerUploadBtn) {
            footerUploadBtn.hidden = false;
        }
        if (fallbackEl) {
            fallbackEl.hidden = true;
        }
        showHelpPanel(false);
    }

    function stopDetectionLoop() {
        if (detectionTimer) {
            window.clearInterval(detectionTimer);
            detectionTimer = null;
        }
        if (helpTimer) {
            window.clearTimeout(helpTimer);
            helpTimer = null;
        }
    }

    function stopCamera() {
        stopDetectionLoop();
        if (mediaStream) {
            mediaStream.getTracks().forEach((track) => {
                try {
                    track.stop();
                } catch (_error) {
                    // ignore
                }
            });
            mediaStream = null;
        }
        if (video) {
            video.srcObject = null;
        }
    }

    function closeModal() {
        stopCamera();
        targetInputId = null;
        capturing = false;
        modal.hidden = true;
        modal.removeAttribute('open');
        setStatus('');
        setDetectMessage('');
        setGuideState('idle');
        showCameraUi();
        if (captureBtn) {
            captureBtn.disabled = false;
        }
    }

    function openModalShell() {
        modal.hidden = false;
        modal.setAttribute('open', 'open');
        try {
            modal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (_error) {
            // ignore
        }
    }

    function sideLabel(side) {
        return side === 'back' ? 'Back' : 'Front';
    }

    function updateCopy(side) {
        const label = sideLabel(side);
        if (titleEl) {
            titleEl.textContent = `Capture the ${label} of your ID`;
        }
        if (hintEl) {
            hintEl.textContent = 'Use your device camera. Point at your ID, then tap the shutter to take the photo.';
        }
        if (captureBtn) {
            captureBtn.setAttribute('aria-label', `Capture ${label} ID`);
        }
    }

    function prefersNativeDeviceCamera() {
        const ua = String(navigator.userAgent || '');
        const isPhone = /Android|iPhone|iPod/i.test(ua);
        const isTablet = /iPad/i.test(ua) || (navigator.maxTouchPoints > 1 && /Macintosh/i.test(ua));
        const narrow = window.matchMedia('(max-width: 900px)').matches;
        return isPhone || (isTablet && narrow) || (navigator.maxTouchPoints > 1 && narrow);
    }

    function startDetectionLoop() {
        stopDetectionLoop();
        setGuideState('idle');
        setDetectMessage('Camera ready — tap the shutter when your ID looks clear.');
        showHelpPanel(false);
    }

    function isLocalhostHost() {
        const host = String(location.hostname || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
    }

    function canUseLiveMediaStream() {
        return Boolean(navigator.mediaDevices?.getUserMedia)
            && (window.isSecureContext || isLocalhostHost());
    }

    /**
     * Open the device camera via capture=environment (native camera app on phones).
     */
    function openNativeDeviceCamera(inputId) {
        const input = document.getElementById(inputId);
        if (!input) {
            return false;
        }

        const previousAccept = input.getAttribute('accept');
        input.setAttribute('accept', 'image/*');
        input.setAttribute('capture', 'environment');

        const cleanup = () => {
            input.removeAttribute('capture');
            if (previousAccept) {
                input.setAttribute('accept', previousAccept);
            } else {
                input.setAttribute('accept', '.jpg,.jpeg,.png,image/jpeg,image/png');
            }
        };

        input.addEventListener('change', cleanup, { once: true });
        window.setTimeout(cleanup, 120000);
        input.click();
        return true;
    }

    function markWizardCaptured(inputId) {
        if (typeof window.KkpWizardMarkIdCaptured === 'function') {
            window.KkpWizardMarkIdCaptured(inputId);
        } else {
            const badge = document.getElementById(`${inputId}CapturedBadge`);
            if (badge) {
                badge.hidden = false;
                badge.removeAttribute('hidden');
            }
        }
    }

    function canvasToJpegBlob(sourceCanvas, quality) {
        return new Promise((resolve) => {
            sourceCanvas.toBlob((result) => resolve(result), 'image/jpeg', quality);
        });
    }

    async function exportCaptureBlob(sourceCanvas, sourceWidth, sourceHeight) {
        const maxEdge = 1600;
        const maxBytes = Math.floor(1.6 * 1024 * 1024);
        let outW = sourceWidth;
        let outH = sourceHeight;
        const longest = Math.max(sourceWidth, sourceHeight);

        if (longest > maxEdge) {
            const scale = maxEdge / longest;
            outW = Math.max(1, Math.round(sourceWidth * scale));
            outH = Math.max(1, Math.round(sourceHeight * scale));
        }

        let exportCanvas = sourceCanvas;
        if (outW !== sourceWidth || outH !== sourceHeight) {
            exportCanvas = document.createElement('canvas');
            exportCanvas.width = outW;
            exportCanvas.height = outH;
            const exportCtx = exportCanvas.getContext('2d');
            if (!exportCtx) {
                return null;
            }
            exportCtx.drawImage(sourceCanvas, 0, 0, outW, outH);
        }

        let quality = 0.82;
        let blob = await canvasToJpegBlob(exportCanvas, quality);
        while (blob && blob.size > maxBytes && quality > 0.55) {
            quality = Math.max(0.55, quality - 0.08);
            blob = await canvasToJpegBlob(exportCanvas, quality);
        }

        return blob;
    }

    async function startCamera() {
        if (!canUseLiveMediaStream()) {
            const inputId = targetInputId;
            closeModal();
            if (inputId) {
                openNativeDeviceCamera(inputId);
            }
            return;
        }

        showCameraUi();
        setStatus('');
        setDetectMessage('Starting camera…');
        setGuideState('idle');

        const attempts = [
            { audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } },
            { audio: false, video: { facingMode: 'environment' } },
            { audio: false, video: true },
        ];

        let lastError = null;

        for (const constraints of attempts) {
            try {
                mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
                if (video) {
                    video.srcObject = mediaStream;
                    await video.play().catch(() => {});
                    if (video.readyState < 2) {
                        await new Promise((resolve) => {
                            const onReady = () => {
                                video.removeEventListener('loadeddata', onReady);
                                video.removeEventListener('playing', onReady);
                                resolve();
                            };
                            video.addEventListener('loadeddata', onReady, { once: true });
                            video.addEventListener('playing', onReady, { once: true });
                            window.setTimeout(resolve, 900);
                        });
                    }
                }
                startDetectionLoop();
                setDetectMessage('Camera ready — tap the shutter when your ID looks clear.');
                setGuideState('idle');
                return;
            } catch (error) {
                lastError = error;
            }
        }

        const name = String(lastError?.name || '');
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            const inputId = targetInputId;
            closeModal();
            if (inputId && openNativeDeviceCamera(inputId)) {
                return;
            }
            showFallback('Camera permission was denied. Please allow camera access in your browser settings or upload an ID photo instead.');
            return;
        }

        const inputId = targetInputId;
        closeModal();
        if (inputId && openNativeDeviceCamera(inputId)) {
            return;
        }
        showFallback('Camera is unavailable. You can upload an ID photo instead.');
    }

    function clientQualityFromCanvas(ctx, width, height) {
        const sampleW = Math.min(160, width);
        const sampleH = Math.max(1, Math.round(sampleW * (height / Math.max(1, width))));
        const tmp = document.createElement('canvas');
        tmp.width = sampleW;
        tmp.height = sampleH;
        const tctx = tmp.getContext('2d', { willReadFrequently: true });
        if (!tctx) {
            return null;
        }
        tctx.drawImage(ctx.canvas, 0, 0, width, height, 0, 0, sampleW, sampleH);
        const data = tctx.getImageData(0, 0, sampleW, sampleH).data;
        let sum = 0;
        let sumSq = 0;
        const n = sampleW * sampleH;
        for (let i = 0; i < data.length; i += 4) {
            const y = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            sum += y;
            sumSq += y * y;
        }
        const mean = sum / n;
        const variance = Math.max(0, (sumSq / n) - (mean * mean));
        const contrast = Math.sqrt(variance);

        if (mean < 22) {
            return 'Too dark. Move to better lighting and try again.';
        }
        if (mean > 245) {
            return 'Too bright / glare. Tilt the ID slightly and try again.';
        }
        if (contrast < 8) {
            return 'Image looks blurry. Hold steady and tap the shutter again.';
        }
        return null;
    }

    function assignFileToInput(inputId, file) {
        const input = document.getElementById(inputId);
        if (!input || !file) {
            return false;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    async function captureFrame() {
        if (!video || !canvas || !mediaStream || !targetInputId || capturing) {
            return;
        }

        const width = video.videoWidth || 0;
        const height = video.videoHeight || 0;

        if (width < MIN_WIDTH || height < MIN_HEIGHT) {
            setStatus('Image resolution is too low. Please move closer to the ID.', 'error');
            setDetectMessage('Move closer to the ID.');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        capturing = true;
        stopDetectionLoop();
        if (captureBtn) {
            captureBtn.disabled = true;
        }

        setGuideState('capturing');
        setDetectMessage('Capturing…');
        setStatus('Checking image quality…', 'info');

        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            setStatus('Unable to capture image. Please try upload instead.', 'error');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        ctx.drawImage(video, 0, 0, width, height);

        const qualityError = clientQualityFromCanvas(ctx, width, height);
        if (qualityError) {
            setStatus(qualityError, 'error');
            setDetectMessage(qualityError);
            setGuideState('idle');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        setStatus('Image quality looks good. Processing your ID…', 'info');
        setDetectMessage('Image quality looks good.');

        const blob = await exportCaptureBlob(canvas, width, height);

        if (!blob) {
            setStatus('Unable to capture image. Please try upload instead.', 'error');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        if (blob.size > MAX_BYTES) {
            setStatus('Image file is too large. Please capture again or upload a smaller image.', 'error');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        const fileName = `id-${targetSide}-${Date.now()}.jpg`;
        const file = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
        const capturedInputId = targetInputId;

        const ok = assignFileToInput(capturedInputId, file);
        if (!ok) {
            setStatus('Unable to save capture. Please upload an ID photo instead.', 'error');
            capturing = false;
            if (captureBtn) {
                captureBtn.disabled = false;
            }
            startDetectionLoop();
            return;
        }

        markWizardCaptured(capturedInputId);
        closeModal();
    }

    async function openForInput(inputId, side) {
        if (opening) {
            return;
        }
        opening = true;
        targetInputId = inputId;
        targetSide = side === 'back' ? 'back' : 'front';
        capturing = false;
        updateCopy(targetSide);

        // Phones: open the real device camera app (no in-page ID edge frame).
        if (prefersNativeDeviceCamera() && openNativeDeviceCamera(inputId)) {
            targetInputId = null;
            opening = false;
            return;
        }

        openModalShell();
        stopCamera();
        try {
            await startCamera();
        } finally {
            opening = false;
        }
    }

    function triggerUpload(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.click();
        }
    }

    function useUploadFallback() {
        const inputId = targetInputId;
        closeModal();
        if (inputId) {
            triggerUpload(inputId);
        }
    }

    document.addEventListener('click', (event) => {
        const cameraBtn = event.target.closest?.('[data-kkp-open-camera]');
        if (cameraBtn) {
            event.preventDefault();
            if (cameraBtn.disabled) {
                return;
            }
            const selectedType = document.querySelector('input[name="document_type"]:checked')?.value || '';
            if (!selectedType) {
                const help = document.getElementById('kkpDocTypeHelp');
                const err = document.getElementById('kkpWizardDocError');
                if (err) {
                    err.hidden = false;
                    err.textContent = 'Please select a document type first, then scan your ID.';
                }
                document.getElementById('kkpDocTypeFieldset')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                help?.focus?.();
                return;
            }
            const inputId = cameraBtn.getAttribute('data-kkp-open-camera');
            const side = cameraBtn.getAttribute('data-side') || 'front';
            if (inputId) {
                openForInput(inputId, side);
            }
            return;
        }

        const uploadBtn = event.target.closest?.('[data-kkp-trigger-upload]');
        if (uploadBtn) {
            event.preventDefault();
            if (uploadBtn.disabled) {
                return;
            }
            const selectedType = document.querySelector('input[name="document_type"]:checked')?.value || '';
            if (!selectedType) {
                const err = document.getElementById('kkpWizardDocError');
                if (err) {
                    err.hidden = false;
                    err.textContent = 'Please select a document type first, then upload your ID photo.';
                }
                document.getElementById('kkpDocTypeFieldset')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            const inputId = uploadBtn.getAttribute('data-kkp-trigger-upload');
            if (inputId) {
                triggerUpload(inputId);
            }
        }
    });

    captureBtn?.addEventListener('click', () => {
        captureFrame();
    });

    closeBtn?.addEventListener('click', () => {
        closeModal();
    });

    switchUploadBtn?.addEventListener('click', () => {
        useUploadFallback();
    });

    footerUploadBtn?.addEventListener('click', () => {
        useUploadFallback();
    });

    helpUploadBtn?.addEventListener('click', () => {
        useUploadFallback();
    });

    helpManualBtn?.addEventListener('click', () => {
        showHelpPanel(false);
        captureFrame();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });

    window.KkpIdCamera = {
        open: openForInput,
        close: closeModal,
    };
})();
