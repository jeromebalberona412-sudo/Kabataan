{{-- Step 2: Optional supporting documents upload --}}
<section class="kkp-wizard-panel" id="kkpWizardStep2" data-wizard-step="2" @if(($kkpInitialStep ?? 1) !== 2) hidden @endif>
    <div class="kkp-wizard-panel-card kkp-wizard-panel-card--docs">
        <div class="kkp-wizard-panel-head">
            <div class="kkp-wizard-panel-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="15" y2="15"></line>
                </svg>
            </div>
            <div>
                <h2 class="kkp-wizard-panel-title">Supporting Documents <span class="kkp-wizard-optional">Optional</span></h2>
                <p class="kkp-wizard-panel-desc">
                    You may upload your School ID, PhilSys / National ID, Voter's ID, PhilHealth ID, or other valid proof of identity now, or skip this step and continue to email verification.
                </p>
            </div>
        </div>

        <div class="kkp-wizard-info-callout kkp-wizard-info-callout--warning" role="note">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.59c.75 1.335-.213 2.986-1.742 2.986H3.48c-1.53 0-2.493-1.651-1.743-2.986L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-8a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p>
                <strong>Important:</strong> Upload a clear, correct photo of your selected ID (front and back).
                If you upload the wrong image, an unclear photo, or a document that does not match your
                profiling details, your KK Profiling application may be rejected during review by SK officials.
            </p>
        </div>

        <div class="kkp-wizard-info-callout" role="note">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <p>
                <strong>Privacy notice:</strong> Upload one valid supporting ID/document if available.
                Your document will be reviewed by authorized SK officials only for verification purposes.
                Original files are stored privately and deleted according to the retention policy.
            </p>
        </div>

        <p class="kkp-wizard-upload-formats" id="kkpDocFormatHint">
            Supported formats: JPG, JPEG, PNG · Maximum size: 10&nbsp;MB
        </p>

        <fieldset class="kkp-wizard-doc-type-fieldset" id="kkpDocTypeFieldset">
            <legend class="kkp-wizard-doc-type-legend">Select document type to upload</legend>
            <p class="kkp-wizard-doc-type-help" id="kkpDocTypeHelp">
                <span class="kkp-doc-help-desktop">Choose an ID type first, then upload clear front and back photos. Wrong or fake-looking uploads may lead to rejection of your KK Profiling.</span>
                <span class="kkp-doc-help-mobile">Choose an ID type first. Then use live camera or upload front and back photos. Wrong or fake-looking uploads may lead to rejection of your KK Profiling.</span>
            </p>
            <div class="kkp-wizard-doc-type-options" role="radiogroup" aria-label="Document type">
                <label class="kkp-wizard-doc-type-option">
                    <input type="radio" name="document_type" value="school_id" id="kkpDocTypeSchoolId">
                    <span class="kkp-wizard-doc-type-card">
                        <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                <circle cx="8" cy="12" r="2"></circle>
                                <path d="M14 10h5M14 14h5"></path>
                            </svg>
                        </span>
                        <span class="kkp-wizard-doc-type-text">
                            <span class="kkp-wizard-doc-type-name">School ID</span>
                            <span class="kkp-wizard-doc-type-desc">Front and back · upload clear photos</span>
                        </span>
                    </span>
                </label>
                <label class="kkp-wizard-doc-type-option">
                    <input type="radio" name="document_type" value="national_id" id="kkpDocTypeNationalId">
                    <span class="kkp-wizard-doc-type-card">
                        <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                <circle cx="9" cy="11" r="2"></circle>
                                <path d="M15 9h4M15 13h4"></path>
                            </svg>
                        </span>
                        <span class="kkp-wizard-doc-type-text">
                            <span class="kkp-wizard-doc-type-name">PhilSys / National ID</span>
                            <span class="kkp-wizard-doc-type-desc">Front and back · upload clear photos</span>
                        </span>
                    </span>
                </label>
                <label class="kkp-wizard-doc-type-option">
                    <input type="radio" name="document_type" value="voters_id" id="kkpDocTypeVotersId">
                    <span class="kkp-wizard-doc-type-card">
                        <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                <circle cx="8" cy="12" r="2"></circle>
                                <path d="M14 10h5M14 14h5"></path>
                            </svg>
                        </span>
                        <span class="kkp-wizard-doc-type-text">
                            <span class="kkp-wizard-doc-type-name">Voter's ID</span>
                            <span class="kkp-wizard-doc-type-desc">Front and back · upload clear photos</span>
                        </span>
                    </span>
                </label>
                <label class="kkp-wizard-doc-type-option">
                    <input type="radio" name="document_type" value="philhealth_id" id="kkpDocTypePhilhealthId">
                    <span class="kkp-wizard-doc-type-card">
                        <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                <circle cx="9" cy="11" r="2"></circle>
                                <path d="M15 9h4M15 13h4"></path>
                            </svg>
                        </span>
                        <span class="kkp-wizard-doc-type-text">
                            <span class="kkp-wizard-doc-type-name">PhilHealth ID</span>
                            <span class="kkp-wizard-doc-type-desc">Front and back · upload clear photos</span>
                        </span>
                    </span>
                </label>
                <label class="kkp-wizard-doc-type-option">
                    <input type="radio" name="document_type" value="other_id" id="kkpDocTypeOtherId">
                    <span class="kkp-wizard-doc-type-card">
                        <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                <circle cx="9" cy="11" r="2"></circle>
                                <path d="M15 9h4M15 13h4"></path>
                            </svg>
                        </span>
                        <span class="kkp-wizard-doc-type-text">
                            <span class="kkp-wizard-doc-type-name">Other valid proof of identity or residency</span>
                            <span class="kkp-wizard-doc-type-desc">Front and back · upload clear photos</span>
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>

        @foreach([
            ['type' => 'school_id', 'panelId' => 'kkpSchoolIdUpload', 'label' => 'School ID'],
            ['type' => 'national_id', 'panelId' => 'kkpNationalIdUpload', 'label' => 'PhilSys / National ID'],
            ['type' => 'voters_id', 'panelId' => 'kkpVotersIdUpload', 'label' => "Voter's ID"],
            ['type' => 'philhealth_id', 'panelId' => 'kkpPhilhealthIdUpload', 'label' => 'PhilHealth ID'],
            ['type' => 'other_id', 'panelId' => 'kkpOtherIdUpload', 'label' => 'Other valid proof of identity or residency'],
        ] as $doc)
        <div class="kkp-wizard-upload-panel" id="{{ $doc['panelId'] }}" hidden>
            <p class="kkp-wizard-upload-panel-title">{{ $doc['label'] }} — front and back photos</p>
            <p class="kkp-wizard-upload-panel-hint kkp-doc-help-desktop">
                Upload a clear front and back photo of your ID. Both sides are required for verification.
            </p>
            <p class="kkp-wizard-upload-panel-hint kkp-doc-help-mobile">
                Use live camera or upload an existing photo. Front and back are stored separately.
            </p>
            <div class="kkp-wizard-upload-grid-wrap">
            <div class="kkp-wizard-upload-grid">
                @foreach(['front' => 'Front', 'back' => 'Back'] as $side => $sideLabel)
                @php
                    $prefix = match($doc['type']) {
                        'school_id' => 'kkpSchoolId',
                        'national_id' => 'kkpNationalId',
                        'voters_id' => 'kkpVotersId',
                        'philhealth_id' => 'kkpPhilhealthId',
                        'other_id' => 'kkpOtherId',
                        default => 'kkpDoc',
                    };
                    $inputName = $doc['type'].'_'.$side;
                    $inputId = $prefix.ucfirst($side);
                @endphp
                <div class="kkp-wizard-upload-shell" data-upload-shell="{{ $inputId }}" data-id-side="{{ $side }}" data-doc-type="{{ $doc['type'] }}">
                    <div class="kkp-wizard-upload-side-head">
                        <p class="kkp-wizard-upload-side-label">{{ $sideLabel }} of ID</p>
                    </div>
                    <div class="kkp-id-capture-actions">
                        <button
                            type="button"
                            class="kkp-id-capture-btn kkp-id-capture-btn--camera kkp-mobile-camera-only"
                            data-kkp-open-camera="{{ $inputId }}"
                            data-side="{{ $side }}"
                            aria-label="Use live camera for {{ $sideLabel }} ID"
                        >
                            Use Live Camera
                        </button>
                        <button
                            type="button"
                            class="kkp-id-capture-btn kkp-id-capture-btn--upload"
                            data-kkp-trigger-upload="{{ $inputId }}"
                            aria-label="Upload {{ $sideLabel }} ID Photo"
                        >
                            Upload ID Photo
                        </button>
                    </div>
                    <label class="kkp-wizard-dropzone" id="{{ $inputId }}Dropzone" for="{{ $inputId }}">
                        <input type="file" id="{{ $inputId }}" name="{{ $inputName }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="kkp-wizard-file-input">
                        <span class="kkp-wizard-dropzone-empty" id="{{ $inputId }}Empty">
                            <span class="kkp-wizard-dropzone-icon" aria-hidden="true">📷</span>
                            <span class="kkp-wizard-dropzone-title">{{ $sideLabel }} image</span>
                            <span class="kkp-wizard-dropzone-sub kkp-doc-help-desktop">Drop a photo or use Upload ID Photo</span>
                            <span class="kkp-wizard-dropzone-sub kkp-doc-help-mobile">Drop, browse, or use the buttons above</span>
                            <span class="kkp-wizard-dropzone-hint">JPG or PNG · max 10MB</span>
                        </span>
                    </label>
                    <div class="kkp-wizard-dropzone-preview" id="{{ $inputId }}Preview" hidden>
                        <img id="{{ $inputId }}PreviewImg" alt="{{ $doc['label'] }} {{ strtolower($sideLabel) }} preview">
                        <div class="kkp-wizard-dropzone-filemeta">
                            <span class="kkp-wizard-dropzone-filename" id="{{ $inputId }}FileName"></span>
                            <div class="kkp-wizard-dropzone-actions">
                                <button type="button" class="kkp-wizard-dropzone-retake kkp-mobile-camera-only" data-kkp-open-camera="{{ $inputId }}" data-side="{{ $side }}" aria-label="Retake {{ $sideLabel }} ID">Retake {{ $sideLabel }}</button>
                                <button type="button" class="kkp-wizard-dropzone-remove" data-clear-input="{{ $inputId }}" aria-label="Remove {{ $sideLabel }} image">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            {{-- Gemini/Groq AI verify overlay kept in markup but always hidden while AI is disabled --}}
            <div class="kkp-id-verify-overlay" hidden aria-hidden="true" data-ai-verify-disabled="1">
                <div class="kkp-id-verify-overlay-card">
                    <span class="kkp-wizard-ocr-spinner" aria-hidden="true"></span>
                    <p class="kkp-id-verify-overlay-title">Verifying ID…</p>
                    <p class="kkp-id-verify-overlay-sub">Checking photo and details</p>
                    <div class="kkp-wizard-ocr-progress" aria-hidden="true">
                        <span class="kkp-wizard-ocr-progress-bar"></span>
                    </div>
                </div>
            </div>
            </div>
        </div>
        @endforeach

        {{-- Inline live camera appears here when Use Live Camera is clicked --}}
        <div
            class="kkp-id-camera-panel"
            id="kkpIdCameraModal"
            hidden
            aria-labelledby="kkpIdCameraTitle"
            data-auto-capture="{{ config('documents.camera.auto_capture_enabled', true) ? '1' : '0' }}"
            data-stability-ms="{{ (int) config('documents.camera.auto_capture_stability_ms', 700) }}"
            data-sample-interval-ms="{{ (int) config('documents.camera.sample_interval_ms', 180) }}"
            data-help-after-ms="{{ (int) config('documents.camera.help_after_ms', 10000) }}"
            data-min-edge="{{ (float) config('documents.camera.min_edge_score', 7) }}"
            data-min-contrast="{{ (float) config('documents.camera.min_contrast', 10) }}"
            data-min-brightness="{{ (float) config('documents.camera.min_brightness', 28) }}"
            data-max-brightness="{{ (float) config('documents.camera.max_brightness', 235) }}"
            data-max-motion="{{ (float) config('documents.camera.max_motion', 22) }}"
        >
            <div class="kkp-id-camera-dialog">
                <div class="kkp-id-camera-header">
                    <p class="kkp-id-camera-eyebrow">Live camera</p>
                    <div class="kkp-id-camera-header-row">
                        <h3 id="kkpIdCameraTitle">Capture the Front of your ID</h3>
                        <button type="button" class="kkp-id-camera-close" id="kkpIdCameraClose" aria-label="Close camera">×</button>
                    </div>
                </div>
                <p class="kkp-id-camera-instructions" id="kkpIdCameraHint">
                    Position your ID inside the frame. Hold steady for automatic capture, or capture manually.
                </p>
                <ul class="kkp-id-camera-tips">
                    <li>Place the entire ID inside the guide</li>
                    <li>Keep the ID flat and avoid glare</li>
                    <li>Auto-capture starts when your ID is detected and held steady</li>
                </ul>
                <div class="kkp-id-camera-stage" id="kkpIdCameraStage">
                    <video id="kkpIdCameraVideo" playsinline muted autoplay aria-label="Live camera preview"></video>
                    <div class="kkp-id-camera-guide" id="kkpIdCameraGuide" aria-hidden="true" data-state="idle">
                        <div class="kkp-id-camera-guide-frame">
                            <span id="kkpIdCameraGuideLabel">PLACE ID HERE</span>
                        </div>
                    </div>
                </div>
                <canvas id="kkpIdCameraCanvas" hidden></canvas>
                <p class="kkp-id-camera-detect" id="kkpIdCameraDetect" role="status" aria-live="polite">Position your ID inside the frame.</p>
                <p class="kkp-id-camera-status" id="kkpIdCameraStatus" role="status" hidden></p>
                <div class="kkp-id-camera-fallback" id="kkpIdCameraFallback" hidden>
                    <p data-fallback-message>Unable to open the camera. Please allow camera access, or upload an ID photo instead.</p>
                    <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--upload" id="kkpIdCameraUseUpload">Upload ID Photo</button>
                </div>
                <div class="kkp-id-camera-help" id="kkpIdCameraHelp" hidden>
                    <p>Having trouble detecting the ID?</p>
                    <div class="kkp-id-camera-help-actions">
                        <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--camera" id="kkpIdCameraManualHint" aria-label="Capture manually">Capture Manually</button>
                        <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--upload" id="kkpIdCameraHelpUpload">Upload Photo</button>
                    </div>
                </div>
                <div class="kkp-id-camera-footer">
                    <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--camera kkp-id-capture-btn--primary" id="kkpIdCameraCapture">
                        Capture Manually
                    </button>
                    <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--upload" id="kkpIdCameraFooterUpload">
                        Upload ID Photo
                    </button>
                </div>
            </div>
        </div>

        <div class="kkp-wizard-doc-error-panel" id="kkpWizardDocError" role="alert" hidden></div>

        {{-- Gemini/Groq AI OCR results panel kept but unused/hidden while AI ID verification is disabled --}}
        <div class="kkp-wizard-ocr-panel" id="kkpWizardOcrPanel" hidden aria-hidden="true" data-ai-verify-disabled="1">
            <p class="kkp-wizard-ocr-title" id="kkpWizardOcrTitle">ID verification</p>
            <div class="kkp-wizard-ocr-loading" id="kkpWizardOcrLoading" hidden>
                <span class="kkp-wizard-ocr-spinner" aria-hidden="true"></span>
                <div class="kkp-wizard-ocr-loading-copy">
                    <p class="kkp-wizard-ocr-loading-title" id="kkpWizardOcrLoadingTitle"></p>
                    <p class="kkp-wizard-ocr-loading-sub" id="kkpWizardOcrLoadingSub"></p>
                </div>
                <div class="kkp-wizard-ocr-progress" aria-hidden="true">
                    <span class="kkp-wizard-ocr-progress-bar" id="kkpWizardOcrProgressBar"></span>
                </div>
            </div>
            <p class="kkp-wizard-ocr-status" id="kkpWizardOcrStatus">Upload front and back of your ID.</p>
            <dl class="kkp-wizard-ocr-fields" id="kkpWizardOcrFields" hidden></dl>
            <p class="kkp-wizard-ocr-note" id="kkpWizardOcrNote" hidden>
                Supporting ID upload is optional. You can continue to the next step anytime.
            </p>
            <div class="kkp-wizard-ocr-retry" id="kkpWizardOcrRetry" hidden>
                <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--camera" id="kkpOcrRetakeFront" data-side="front">Retake Front</button>
                <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--camera" id="kkpOcrRetakeBack" data-side="back">Retake Back</button>
                <button type="button" class="kkp-id-capture-btn kkp-id-capture-btn--upload" id="kkpOcrUploadAnother">Upload Another Photo</button>
            </div>
        </div>

        <div class="kkp-wizard-upload-panel" id="kkpSelfieUploadPanel" hidden data-selfie-enabled="{{ config('documents.selfie_verification_enabled') ? '1' : '0' }}">
            <p class="kkp-wizard-upload-panel-title">Selfie verification</p>
            <p class="kkp-wizard-panel-desc">After your ID is verified, you may upload a clear selfie only if this option is enabled by administrators. Facial biometric matching is disabled by default.</p>
            <div class="kkp-wizard-upload-grid">
                <div class="kkp-wizard-upload-shell" data-upload-shell="kkpSelfie">
                    <p class="kkp-wizard-upload-side-label">Selfie</p>
                    <label class="kkp-wizard-dropzone" id="kkpSelfieDropzone" for="kkpSelfie">
                        <input type="file" id="kkpSelfie" name="selfie" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="kkp-wizard-file-input">
                        <span class="kkp-wizard-dropzone-empty" id="kkpSelfieEmpty">
                            <span class="kkp-wizard-dropzone-icon" aria-hidden="true">🤳</span>
                            <span class="kkp-wizard-dropzone-title">Selfie image</span>
                            <span class="kkp-wizard-dropzone-sub">Drop or <span class="kkp-wizard-dropzone-link">browse</span></span>
                            <span class="kkp-wizard-dropzone-hint">JPG or PNG · max 10MB</span>
                        </span>
                    </label>
                    <div class="kkp-wizard-dropzone-preview" id="kkpSelfiePreview" hidden>
                        <img id="kkpSelfiePreviewImg" alt="Selfie preview">
                        <div class="kkp-wizard-dropzone-filemeta">
                            <span class="kkp-wizard-dropzone-filename" id="kkpSelfieFileName"></span>
                            <button type="button" class="kkp-wizard-dropzone-remove" data-clear-input="kkpSelfie" aria-label="Remove selfie image">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
