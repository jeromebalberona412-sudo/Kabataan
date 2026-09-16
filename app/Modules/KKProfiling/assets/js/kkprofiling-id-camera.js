/**
 * KK Profiling — smart live camera ID capture.
 * Lightweight document detection + stability auto-capture.
 * AI ID verification runs only AFTER capture (existing wizard detect-id path).
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
    let stableSince = null;
    let previousGray = null;
    let detectSampleCanvas = null;
    let detectedStreak = 0;
    let captureLockUntil = 0;

    const MAX_BYTES = 10 * 1024 * 1024;
    const MIN_WIDTH = 480;
    const MIN_HEIGHT = 300;

    const cfg = {
        // Manual capture only — never auto-detect / auto-snap.
        autoCapture: false,
        stabilityMs: Math.max(350, Number(modal.dataset.stabilityMs) || 700),
        sampleIntervalMs: Math.max(100, Number(modal.dataset.sampleIntervalMs) || 180),
        helpAfterMs: Math.max(4000, Number(modal.dataset.helpAfterMs) || 10000),
        minEdge: Number(modal.dataset.minEdge) || 7,
        minContrast: Number(modal.dataset.minContrast) || 10,
        minBrightness: Number(modal.dataset.minBrightness) || 28,
        maxBrightness: Number(modal.dataset.maxBrightness) || 235,
        maxMotion: Number(modal.dataset.maxMotion) || 22,
    };

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
                idle: 'PLACE ID HERE',
                searching: 'PLACE ID HERE',
                detected: 'ID DETECTED',
                steady: 'HOLD STEADY',
                capturing: 'CAPTURING…',
            };
            guideLabelEl.textContent = labels[state] || 'PLACE ID HERE';
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
        stableSince = null;
        previousGray = null;
        detectedStreak = 0;
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
        // Inline live camera panel (not a dialog/modal overlay).
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
            hintEl.textContent = 'Position your ID inside the frame, then tap Capture to take the photo yourself.';
        }
        if (captureBtn) {
            captureBtn.setAttribute('aria-label', `Capture ${label} ID`);
            captureBtn.textContent = 'Capture';
        }
    }

    function getGuideSampleRect(videoWidth, videoHeight) {
        // Approximate the CSS guide frame (centered ~86% width, card aspect ~1.586).
        const frameW = videoWidth * 0.72;
        const frameH = frameW / 1.586;
        const x = (videoWidth - frameW) / 2;
        const y = (videoHeight - frameH) / 2;
        return {
            x: Math.max(0, Math.floor(x)),
            y: Math.max(0, Math.floor(y)),
            w: Math.max(1, Math.floor(Math.min(frameW, videoWidth))),
            h: Math.max(1, Math.floor(Math.min(frameH, videoHeight))),
        };
    }

    /**
     * Lightweight document heuristics inside the guide (not full AI analysis).
     */
    function analyzeGuideRegion() {
        if (!video || video.readyState < 2) {
            return null;
        }

        const vw = video.videoWidth || 0;
        const vh = video.videoHeight || 0;
        if (vw < 80 || vh < 80) {
            return null;
        }

        const rect = getGuideSampleRect(vw, vh);
        const sampleW = 96;
        const sampleH = Math.max(40, Math.round(sampleW / 1.586));

        if (!detectSampleCanvas) {
            detectSampleCanvas = document.createElement('canvas');
        }
        detectSampleCanvas.width = sampleW;
        detectSampleCanvas.height = sampleH;
        const ctx = detectSampleCanvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return null;
        }

        ctx.drawImage(video, rect.x, rect.y, rect.w, rect.h, 0, 0, sampleW, sampleH);
        const { data } = ctx.getImageData(0, 0, sampleW, sampleH);

        const gray = new Float32Array(sampleW * sampleH);
        let sum = 0;
        let sumSq = 0;
        let bright = 0;
        let dark = 0;

        for (let i = 0, p = 0; i < data.length; i += 4, p += 1) {
            const y = (0.299 * data[i]) + (0.587 * data[i + 1]) + (0.114 * data[i + 2]);
            gray[p] = y;
            sum += y;
            sumSq += y * y;
            if (y >= 245) {
                bright += 1;
            }
            if (y <= 20) {
                dark += 1;
            }
        }

        const count = gray.length;
        const mean = sum / count;
        const variance = Math.max(0, (sumSq / count) - (mean * mean));
        const contrast = Math.sqrt(variance);
        const glare = bright / count;
        const crush = dark / count;

        // Edge / sharpness score (Laplacian-ish).
        let edgeSum = 0;
        let edgeCount = 0;
        for (let y = 1; y < sampleH - 1; y += 1) {
            for (let x = 1; x < sampleW - 1; x += 1) {
                const i = (y * sampleW) + x;
                const lap = Math.abs(
                    (4 * gray[i])
                    - gray[i - sampleW]
                    - gray[i + sampleW]
                    - gray[i - 1]
                    - gray[i + 1]
                );
                edgeSum += lap;
                edgeCount += 1;
            }
        }
        const edgeScore = edgeCount > 0 ? edgeSum / edgeCount : 0;

        // Border vs center contrast: card-like docs often differ from outer band.
        const insetX = Math.floor(sampleW * 0.18);
        const insetY = Math.floor(sampleH * 0.18);
        let borderSum = 0;
        let borderN = 0;
        let centerSum = 0;
        let centerN = 0;
        for (let y = 0; y < sampleH; y += 1) {
            for (let x = 0; x < sampleW; x += 1) {
                const v = gray[(y * sampleW) + x];
                const inCenter = x >= insetX && x < sampleW - insetX && y >= insetY && y < sampleH - insetY;
                if (inCenter) {
                    centerSum += v;
                    centerN += 1;
                } else {
                    borderSum += v;
                    borderN += 1;
                }
            }
        }
        const borderMean = borderN ? borderSum / borderN : mean;
        const centerMean = centerN ? centerSum / centerN : mean;
        const structureGap = Math.abs(centerMean - borderMean);

        // Motion vs previous sample (first sample is never treated as stable).
        let motion = 999;
        let hasPrevious = Boolean(previousGray && previousGray.length === gray.length);
        if (hasPrevious) {
            let diff = 0;
            for (let i = 0; i < gray.length; i += 1) {
                diff += Math.abs(gray[i] - previousGray[i]);
            }
            motion = diff / gray.length;
        }
        previousGray = gray;

        const lightingOk = mean >= cfg.minBrightness
            && mean <= cfg.maxBrightness
            && glare < 0.42
            && crush < 0.55;
        const contrastOk = contrast >= cfg.minContrast;
        const edgeOk = edgeScore >= cfg.minEdge;
        // Blank paper / empty scene: low edges + low structure.
        const structureOk = edgeScore >= (cfg.minEdge * 0.75)
            && (structureGap >= 3.5 || contrast >= Math.max(8, cfg.minContrast * 0.85));
        const detected = lightingOk && contrastOk && edgeOk && structureOk;
        const stable = detected && hasPrevious && motion <= cfg.maxMotion;

        return {
            mean,
            contrast,
            edgeScore,
            motion,
            glare,
            structureGap,
            detected,
            stable,
            lightingOk,
            contrastOk,
            edgeOk,
        };
    }

    function startDetectionLoop() {
        stopDetectionLoop();
        setGuideState('idle');
        setDetectMessage('Position your ID, then tap Capture.');
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
     * On HTTP LAN / insecure origins, getUserMedia is blocked.
     * Open the device camera via capture=environment instead of showing an insecure warning.
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
        // Prefer device camera capture on insecure HTTP (e.g. LAN IP serve) — do not show insecure warning.
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
                    // Wait until frames are available so auto-detect can start immediately.
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
                setDetectMessage('Looking for an ID inside the frame…');
                setGuideState('searching');
                return;
            } catch (error) {
                lastError = error;
            }
        }

        const name = String(lastError?.name || '');
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            // Still try native capture (mobile) before falling back to gallery upload.
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
        let bright = 0;
        let sumSq = 0;
        const pixels = data.length / 4;
        const gray = [];

        for (let i = 0; i < data.length; i += 4) {
            const y = (0.299 * data[i]) + (0.587 * data[i + 1]) + (0.114 * data[i + 2]);
            gray.push(y);
            sum += y;
            sumSq += y * y;
            if (y >= 245) {
                bright += 1;
            }
        }

        const mean = pixels > 0 ? sum / pixels : 0;
        const glare = pixels > 0 ? bright / pixels : 0;
        const contrast = Math.sqrt(Math.max(0, (sumSq / pixels) - (mean * mean)));

        let lapSum = 0;
        let lapN = 0;
        for (let y = 1; y < sampleH - 1; y += 1) {
            for (let x = 1; x < sampleW - 1; x += 1) {
                const i = (y * sampleW) + x;
                const lap = Math.abs((4 * gray[i]) - gray[i - sampleW] - gray[i + sampleW] - gray[i - 1] - gray[i + 1]);
                lapSum += lap;
                lapN += 1;
            }
        }
        const sharpness = lapN ? lapSum / lapN : 0;

        if (mean < 35) {
            return 'Image is too dark. Please improve the lighting.';
        }
        if (mean > 230 || (glare > 0.28 && mean > 190)) {
            return 'Image is too bright. Please avoid glare.';
        }
        if (contrast < 14) {
            return 'Image contrast is too low. Please retake with clearer lighting.';
        }
        if (sharpness < 6) {
            return 'Image is too blurry. Please hold your camera steady and try again.';
        }

        return null;
    }

    function assignFileToInput(inputId, file) {
        const input = document.getElementById(inputId);
        if (!input || typeof DataTransfer === 'undefined') {
            return false;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    async function captureFrame(options = {}) {
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
        setDetectMessage(options.auto ? 'Capturing…' : 'Capturing…');
        setStatus('Checking image quality…', 'info');

        // Pause tracks briefly for a sharp still.
        mediaStream.getVideoTracks().forEach((track) => {
            try {
                track.enabled = true;
            } catch (_error) {
                // ignore
            }
        });

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
            setGuideState('searching');
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

        // Wizard change handler will run quality → AI verification on the server.
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

    captureBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        captureFrame({ auto: false });
    });

    helpManualBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        captureFrame({ auto: false });
    });

    closeBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    });

    switchUploadBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        useUploadFallback();
    });

    footerUploadBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        useUploadFallback();
    });

    helpUploadBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        useUploadFallback();
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            event.preventDefault();
            closeModal();
        }
    });

    window.KkpIdCamera = {
        open: openForInput,
        close: closeModal,
    };
})();
