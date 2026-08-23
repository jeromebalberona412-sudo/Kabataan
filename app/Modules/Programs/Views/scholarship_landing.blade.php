<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scholarship Application - SK OnePortal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Programs/assets/css/scholarship_landing.css',
        'app/Modules/Programs/assets/css/scholarship_application_preview.css',
        'app/Modules/Programs/assets/css/scholarship_application.css',
        'app/Modules/Programs/assets/css/scholarship-data-privacy.css',
        'app/Modules/Programs/assets/js/scholarship-data-privacy.js',
        'app/Modules/Dashboard/assets/css/dashboard.css',
        'app/Modules/Programs/assets/js/scholarship-system-fields.js',
        'app/Modules/Programs/assets/js/scholarship_application_preview.js',
        'app/Modules/Programs/assets/js/scholarship_apply_wizard.js',
        'app/Modules/Programs/assets/js/scholarship_landing.js',
    ])
</head>
<body class="sl-body kabataan-app-page">
    @include('layout::kabataan-header', ['showSearch' => false, 'pageBadge' => null])

    <div id="scholarshipPreviewShell" hidden></div>
    <div id="scholarshipWizardShell" hidden></div>

    @if(!$scheduleProgramId)
    <div id="scholarshipLandingContent" class="sl-container">
        <!-- Header -->
        <div class="sl-header">
            <div class="sl-back-link">
                <a href="{{ route('dashboard') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span>Back to Dashboard</span>
                </a>
            </div>
            <h1 class="sl-title">Scholarship Application</h1>
        </div>

        <!-- Previous Applications Card -->
        <div class="sl-card sl-card-history" id="scholarshipApplicationsHistory" hidden>
            <div class="sl-card-header">
                <h2 class="sl-card-title">Previous Applications</h2>
            </div>
            <div class="sl-card-body">
                <div class="sl-table-wrapper">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Program Name</th>
                                <th>Application Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="previousApplicationsTable">
                            <tr class="sl-empty-row">
                                <td colspan="4">No previous applications found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Requirements Preview Card -->
        <div class="sl-card sl-card-requirements">
            <div class="sl-card-header">
                <h2 class="sl-card-title">Required Documents</h2>
            </div>
            <div class="sl-card-body">
                <ul class="sl-requirements-list">
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>Certificate of Registration (COR)</span>
                    </li>
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>Copy of Grades</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- KK Profiling Preview Card -->
        <div class="sl-card sl-card-kk-profile">
            <div class="sl-card-header">
                <h2 class="sl-card-title">KK Profiling Data Included</h2>
                <span class="sl-badge sl-badge-autofill">Auto-Filled</span>
            </div>
            <div class="sl-card-body">
                <p class="sl-kk-notice">The following information will be automatically retrieved from your KK Profile:</p>
                <div class="sl-kk-fields">
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Full Name</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Birthday</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Age</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Sex</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Civil Status</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Contact Number</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Home Address</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Current School</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Year Level</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Course / Strand</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Barangay</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>City/Municipality</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Province</span>
                    </div>
                    <div class="sl-kk-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Region</span>
                    </div>
                </div>
                <p class="sl-kk-note">You do not need to enter the information above again because it will be automatically filled from your KK Profile.</p>
            </div>
        </div>

        <!-- Start Application Button -->
        <div class="sl-actions">
            <button class="sl-btn sl-btn-primary" id="startApplicationBtn">
                <span>Start Scholarship Application</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>
    </div>
    @endif

    @if(!$scheduleProgramId)
    <!-- Mobile Drawer Backdrop -->
    <div id="programsDrawerBackdrop" class="programs-drawer-backdrop"></div>

    <!-- Mobile Drawer -->
    <aside class="programs-sidebar" id="programsDrawerSidebar">
        <div class="sidebar-card">
            <div class="programs-drawer-head">
                <div class="programs-drawer-head__text">
                    <h2 class="sidebar-title">Programs in Your Barangay</h2>
                    <p class="sidebar-subtitle">Available programs in Barangay Santa Cruz</p>
                </div>
                <button type="button" class="programs-drawer-close" data-programs-drawer-close aria-label="Close programs">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            
            <div class="program-categories">
                <a href="{{ route('scholarship.apply') }}" class="program-category">
                    <div class="category-icon">📚</div>
                    <div class="category-info">
                        <h3>Scholarship Programs</h3>
                        <p>Apply for educational assistance</p>
                    </div>
                </a>
                <a href="{{ route('sports.apply') }}" class="program-category">
                    <div class="category-icon">⚽</div>
                    <div class="category-info">
                        <h3>Sports Programs</h3>
                        <p>Join sports activities and competitions</p>
                    </div>
                </a>
            </div>
        </div>
    </aside>
    @endif

    <div id="applicationViewModal" class="sl-view-modal" hidden>
        <div class="sl-view-modal-overlay"></div>
        <div class="sl-view-modal-container" id="applicationViewContainer">
            <div class="sl-view-modal-header">
                <div class="sl-view-modal-header-main">
                    <h2 id="applicationViewTitle">Application Details</h2>
                    <p id="applicationViewMeta" class="sl-view-modal-meta"></p>
                </div>
                <div class="sl-view-modal-header-actions">
                    <button type="button" class="sl-view-modal-icon-btn sl-view-modal-maximize-btn" id="applicationViewMaximize" title="Maximize" aria-label="Maximize">
                        <svg class="sl-modal-icon-maximize" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                        <svg class="sl-modal-icon-restore" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>
                    </button>
                    <button type="button" class="sl-view-modal-icon-btn sl-view-modal-close" id="applicationViewClose" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
            <div class="sl-view-modal-body">
                <section class="sl-view-section">
                    <h3 class="sl-view-section-title">Personal Information</h3>
                    <div id="applicationViewPersonalInfo" class="sl-view-personal-grid"></div>
                </section>
                <section class="sl-view-section">
                    <h3 class="sl-view-section-title">Application Answers</h3>
                    <div id="applicationViewAnswers" class="sl-view-answers"></div>
                </section>
                <div id="applicationViewCancelledInfo" class="sl-view-cancelled-info" hidden>
                    <h3>Cancellation Details</h3>
                    <p id="applicationViewCancelledType" class="sl-view-cancelled-type"></p>
                    <p id="applicationViewCancelledReason"></p>
                </div>
            </div>
        </div>
    </div>

    <div id="applicationCancelModal" class="sl-cancel-modal" hidden>
        <div class="sl-cancel-modal-overlay"></div>
        <div class="sl-cancel-modal-box" id="applicationCancelModalBox">
            <div class="sl-cancel-modal-header">
                <h3>Cancel Application</h3>
                <div class="sl-cancel-modal-controls">
                    <button type="button" class="modal-toggle-btn" id="applicationCancelMaximize" aria-label="Maximize">□</button>
                    <button type="button" class="modal-close sl-cancel-close-btn" id="applicationCancelClose" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="sl-cancel-modal-body">
                <p class="sl-view-cancel-note">Please select a cancel reason and type <strong>Confirm</strong> before cancelling your scholarship application.</p>
                <fieldset class="sl-cancel-reason-group">
                    <legend class="sl-view-cancel-label">Cancel Reason</legend>
                    <label class="sl-cancel-reason-option">
                        <input type="checkbox" name="applicationCancelReasonType" value="Voluntary Withdrawal" data-cancel-reason-option>
                        <span class="sl-cancel-reason-check" aria-hidden="true"></span>
                        <span class="sl-cancel-reason-text">Voluntary Withdrawal</span>
                    </label>
                    <label class="sl-cancel-reason-option">
                        <input type="checkbox" name="applicationCancelReasonType" value="Incorrect Information" data-cancel-reason-option>
                        <span class="sl-cancel-reason-check" aria-hidden="true"></span>
                        <span class="sl-cancel-reason-text">Incorrect Information</span>
                    </label>
                    <label class="sl-cancel-reason-option">
                        <input type="checkbox" name="applicationCancelReasonType" value="Wrong Program Applied" data-cancel-reason-option>
                        <span class="sl-cancel-reason-check" aria-hidden="true"></span>
                        <span class="sl-cancel-reason-text">Wrong Program Applied</span>
                    </label>
                    <label class="sl-cancel-reason-option">
                        <input type="checkbox" name="applicationCancelReasonType" value="Other" data-cancel-reason-option data-cancel-reason-other>
                        <span class="sl-cancel-reason-check" aria-hidden="true"></span>
                        <span class="sl-cancel-reason-text">Other</span>
                    </label>
                </fieldset>
                <div id="applicationCancelOtherWrap" class="sl-cancel-other-wrap" hidden>
                    <label class="sl-view-cancel-label" for="applicationCancelReason">Please specify your reason</label>
                    <textarea id="applicationCancelReason" class="sl-view-cancel-input sl-view-cancel-input-no-resize" rows="4" maxlength="500" placeholder="Type your reason for cancelling this application..."></textarea>
                    <p id="applicationCancelCharCount" class="sl-view-cancel-char-count">0 / 500 characters</p>
                </div>
                <div class="sl-cancel-confirm-wrap">
                    <label class="sl-view-cancel-label" for="applicationCancelConfirm">Type <strong>Confirm</strong> to cancel your scholarship application</label>
                    <input type="text" id="applicationCancelConfirm" class="sl-view-cancel-confirm-input" placeholder="Confirm" autocomplete="off" spellcheck="false">
                </div>
                <p id="applicationCancelError" class="sl-view-cancel-error" hidden></p>
            </div>
            <div class="sl-cancel-modal-footer">
                <button type="button" class="sl-btn-action sl-btn-secondary" id="applicationCancelDismissBtn">Keep Application</button>
                <button type="button" class="sl-btn-action sl-btn-cancel" id="applicationCancelBtn">Confirm Cancel</button>
            </div>
        </div>
    </div>

    <div id="pdfPreviewModal" class="gf-pdf-modal" hidden>
        <div class="gf-pdf-modal-overlay"></div>
        <div class="gf-pdf-modal-container">
            <div class="gf-pdf-modal-header">
                <h3 id="pdfPreviewTitle">PDF Preview</h3>
                <button type="button" class="gf-pdf-close-btn" id="pdfPreviewClose" aria-label="Close preview">×</button>
            </div>
            <div class="gf-pdf-modal-body" id="pdfPreviewPages"></div>
        </div>
    </div>

    <div id="confirmSubmitModal" class="gf-confirm-modal" hidden>
        <div class="gf-confirm-modal-overlay" data-close-confirm-modal></div>
        <div class="gf-confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmSubmitTitle">
            <h2 id="confirmSubmitTitle" class="gf-confirm-modal-title">Confirm Application Submission</h2>
            <p class="gf-confirm-modal-text">Please review your information carefully. After submission, editing may no longer be allowed.</p>
            <div class="gf-confirm-modal-actions">
                <button type="button" class="gf-btn gf-btn-secondary" id="backToReviewBtn">Back to Review</button>
                <button type="button" class="gf-btn gf-btn-submit" id="confirmSubmitBtn">Submit Application</button>
            </div>
        </div>
    </div>

    <div id="successModal" class="gf-success-modal" hidden>
        <div class="gf-success-card">
            <h2 class="gf-success-title">Application Submitted Successfully</h2>
            <div class="gf-success-details">
                <div class="gf-success-detail-row">
                    <span class="gf-success-detail-label">Status:</span>
                    <span class="gf-status-badge gf-status-pending">Pending Review</span>
                </div>
            </div>
            <p class="gf-success-message">You will be notified once your application has been processed.</p>
            <div class="gf-success-actions">
                <button type="button" class="gf-btn gf-btn-primary" id="goToDashboardBtn">View My Application</button>
            </div>
        </div>
    </div>

    <script>
        window.__scheduleProgramId = @json($scheduleProgramId);
        window.__kkFieldLabels = @json($kkFieldLabels);
        window.__dashboardUrl = @json(route('dashboard'));
    </script>

    @include('programs::scholarship.partials.data-privacy-modal')
</body>
</html>
