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

        <div class="kkp-wizard-info-callout" role="note">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <p>
                <strong>Note:</strong> Supporting documents are optional. However, uploading a valid ID or other proof of identity can help verify your information and may expedite the approval of your KK Profiling application.
            </p>
        </div>

        <fieldset class="kkp-wizard-doc-type-fieldset" id="kkpDocTypeFieldset">
            <legend class="kkp-wizard-doc-type-legend">Select document type (if uploading)</legend>
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
                            <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
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
                            <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
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
                            <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
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
                            <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
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
                            <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
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
            <p class="kkp-wizard-upload-panel-title">{{ $doc['label'] }} — upload front and back</p>
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
                <div class="kkp-wizard-upload-shell" data-upload-shell="{{ $inputId }}">
                    <p class="kkp-wizard-upload-side-label">{{ $sideLabel }}</p>
                    <label class="kkp-wizard-dropzone" id="{{ $inputId }}Dropzone" for="{{ $inputId }}">
                        <input type="file" id="{{ $inputId }}" name="{{ $inputName }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="kkp-wizard-file-input">
                        <span class="kkp-wizard-dropzone-empty" id="{{ $inputId }}Empty">
                            <span class="kkp-wizard-dropzone-icon" aria-hidden="true">📷</span>
                            <span class="kkp-wizard-dropzone-title">{{ $sideLabel }} image</span>
                            <span class="kkp-wizard-dropzone-sub">Drop or <span class="kkp-wizard-dropzone-link">browse</span></span>
                            <span class="kkp-wizard-dropzone-hint">JPG or PNG · max 10MB</span>
                        </span>
                    </label>
                    <div class="kkp-wizard-dropzone-preview" id="{{ $inputId }}Preview" hidden>
                        <img id="{{ $inputId }}PreviewImg" alt="{{ $doc['label'] }} {{ strtolower($sideLabel) }} preview">
                        <div class="kkp-wizard-dropzone-filemeta">
                            <span class="kkp-wizard-dropzone-filename" id="{{ $inputId }}FileName"></span>
                            <button type="button" class="kkp-wizard-dropzone-remove" data-clear-input="{{ $inputId }}" aria-label="Remove {{ $sideLabel }} image">Remove</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="kkp-wizard-doc-error-panel" id="kkpWizardDocError" role="alert" hidden></div>

        <div class="kkp-wizard-ocr-panel" id="kkpWizardOcrPanel" hidden aria-live="polite">
            <p class="kkp-wizard-ocr-title">ID scan results</p>
            <p class="kkp-wizard-ocr-status" id="kkpWizardOcrStatus">Upload front and back to scan your ID.</p>
            <dl class="kkp-wizard-ocr-fields" id="kkpWizardOcrFields" hidden></dl>
            <p class="kkp-wizard-ocr-note" id="kkpWizardOcrNote" hidden>
                Detected values were applied to your profiling form where fields were empty. Review Step 1 and edit before you finish registration.
            </p>
        </div>

        <div class="kkp-wizard-upload-panel" id="kkpSelfieUploadPanel" hidden>
            <p class="kkp-wizard-upload-panel-title">Selfie verification</p>
            <p class="kkp-wizard-panel-desc">After your ID is scanned, upload a clear selfie so we can match your face with your ID photo.</p>
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
