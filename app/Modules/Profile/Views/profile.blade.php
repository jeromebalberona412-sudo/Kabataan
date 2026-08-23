<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>My Profile - SK OnePortal</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-bootstrap.css',
        'app/Modules/Layout/assets/css/kabataan-responsive.css',
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/css/programs-drawer.css',
        'app/Modules/Layout/assets/css/kabataan-logout.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Programs/assets/js/kabataan-programs.js',
        'app/Modules/Layout/assets/js/kabataan-logout.js',
        'app/Modules/Profile/assets/css/profile.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling.css',
        'app/Modules/KKProfiling/assets/css/kkprofiling-wizard.css',
        'app/Modules/KKProfiling/assets/css/kk-profiling-update.css',
        'app/Modules/Profile/assets/js/profile.js',
        'app/Modules/Profile/assets/js/profile-participation.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
    ])
    <style>
        .kabataan-modal-backdrop {
            padding: 32px 24px;
            align-items: center;
            justify-content: center;
        }
        .kk-preview-modal-container.kabataan-modal-box,
        .kkp-docs-modal-container.kabataan-modal-box {
            width: min(880px, calc(100% - 48px));
            max-width: 880px;
            max-height: min(78vh, calc(100vh - 96px));
            border-radius: 16px;
        }
        .profile-picture-upload-modal__box,
        .profile-picture-confirm-modal__box,
        .profile-picture-lock-modal__box {
            width: min(360px, calc(100% - 32px));
            max-width: 360px;
            max-height: min(70vh, calc(100vh - 72px));
            border-radius: 14px;
        }
        .kabataan-modal-backdrop .modal-toggle-btn,
        .kabataan-modal-backdrop .modal-close {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .kabataan-modal-backdrop .modal-toggle-btn svg {
            width: 16px;
            height: 16px;
            display: block;
        }
        .kabataan-modal-backdrop.modal-maximized {
            padding: 0;
            align-items: stretch;
            justify-content: stretch;
        }
        .kabataan-modal-backdrop.modal-maximized .kabataan-modal-box,
        .kabataan-modal-box.modal-maximized {
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            max-width: none;
            max-height: none;
            border-radius: 0;
        }
        .kkp-profile-preview .kkp-personal-row {
            overflow: visible;
            grid-template-columns: auto minmax(0, 1fr) minmax(260px, 1.6fr);
        }
        .kkp-profile-preview .kkp-email-value {
            display: block;
            width: 100%;
            text-align: left;
            white-space: normal;
            overflow: visible;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.35;
        }
        @media (max-width: 768px) {
            .kabataan-modal-backdrop:not(.modal-maximized) {
                padding: 56px 14px 28px;
            }
            .kabataan-modal-backdrop:not(.modal-maximized) .kk-preview-modal-container,
            .kabataan-modal-backdrop:not(.modal-maximized) .kkp-docs-modal-container {
                width: calc(100% - 8px);
                max-height: 68vh;
                border-radius: 14px;
            }
            .profile-picture-upload-modal__box,
            .profile-picture-confirm-modal__box,
            .profile-picture-lock-modal__box {
                width: min(340px, calc(100% - 24px));
                max-width: 340px;
                max-height: min(68vh, calc(100vh - 64px));
            }
        }
    </style>
    <script>
        function closeProfilePictureLockModal() {
            const modal = document.getElementById('profilePictureLockModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeScheduleModal();
                closeProfilePictureLockModal();
                closeProfilePictureUploadModal();
                closeProfilePictureConfirmModal();
                closeSupportingDocsModal();
                if (typeof closeKkPreviewModal === 'function') {
                    closeKkPreviewModal();
                }
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeScheduleModal();
            }
            if (e.target.classList.contains('kabataan-modal-backdrop')) {
                if (typeof closeKkPreviewModal === 'function') {
                    closeKkPreviewModal();
                }
            }
        });
    </script>
</head>
<body class="youth-profile kabataan-app-page">
    @include('layout::kabataan-header', ['user' => $user])
    @include('layout::programs-drawer')

    <!-- Main Content -->
    <main class="profile-main">
        <div class="profile-container">
            <!-- Profile Header Card -->
            <div class="profile-header-card">
                <div class="profile-info-section">
                    <div
                        class="profile-avatar-wrapper profile-avatar-wrapper--interactive"
                        id="profileAvatarWrapper"
                        data-upload-url="{{ route('profile.upload-picture') }}"
                        data-can-change="{{ $canChangeProfileImage ? '1' : '0' }}"
                        data-next-change="{{ $profileImageNextChangeDisplay ?? '' }}"
                        data-fallback-avatar="{{ $profileImageFallbackUrl }}"
                        role="button"
                        tabindex="0"
                        aria-label="{{ $canChangeProfileImage ? 'Change profile picture' : 'Profile picture update locked' }}"
                        title="{{ $canChangeProfileImage ? 'Click to change profile picture' : 'Profile picture update locked' }}"
                    >
                        <img
                            src="{{ $profileImageUrl }}"
                            alt="Profile"
                            class="profile-avatar"
                            id="profileAvatar"
                            data-fallback="{{ $profileImageFallbackUrl }}"
                        >
                        <span class="profile-avatar-overlay" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
                            </svg>
                        </span>
                    </div>
                    <input
                        type="file"
                        id="photoUpload"
                        class="profile-photo-input-sr-only"
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        @if(!$canChangeProfileImage) disabled @endif
                    >
                    <div class="profile-header-info">
                        <h1 class="profile-name">{{ $fullName ?? strtoupper($user->name) }}</h1>
                        <p class="profile-location">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            {{ $barangayName ?? 'Santa Cruz' }}, Santa Cruz, Laguna
                        </p>
                    </div>
                </div>
            </div>

            <div class="profile-content-grid">
                    <section class="info-card participation-summary-card" aria-labelledby="participationSummaryTitle">
                        <div class="card-header">
                            <h2 id="participationSummaryTitle">
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                </svg>
                                Participation Summary
                            </h2>
                        </div>
                        <div class="card-body participation-summary-body">
                            <div class="participation-summary-grid">
                                <article class="ps-stat ps-stat--joined">
                                    <p class="ps-stat-label">Programs Joined</p>
                                    <p class="ps-stat-value">{{ $totalPrograms ?? 0 }}</p>
                                </article>
                                <article class="ps-stat ps-stat--approved">
                                    <p class="ps-stat-label">Approved</p>
                                    <p class="ps-stat-value">{{ $approvedPrograms ?? 0 }}</p>
                                </article>
                                <article class="ps-stat ps-stat--pending">
                                    <p class="ps-stat-label">Pending Review</p>
                                    <p class="ps-stat-value">{{ $evaluationPrograms ?? 0 }}</p>
                                </article>
                                <article class="ps-stat ps-stat--completed">
                                    <p class="ps-stat-label">Completed</p>
                                    <p class="ps-stat-value">{{ $completedPrograms ?? 0 }}</p>
                                </article>
                            </div>
                        </div>
                    </section>

                <!-- Left Column - Personal Information -->
                <div class="profile-left-column">
                    <div class="info-card kk-profile-combined-card">
                        <div class="card-body kk-profile-combined-body">
                            <section class="kk-profile-section">
                                <h2 class="kk-profile-section-title">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                    </svg>
                                    Personal Information
                                </h2>
                                @if($kabataanRegistration)
                                    <div class="kkp-preview-toolbar">
                                        <button type="button" class="btn-primary kkp-preview-btn" onclick="openKkPreviewModal()">View Personal Information</button>
                                    </div>
                                @else
                                    <div class="empty-state kk-profile-empty-state">
                                        <h3>No KK Profiling Record</h3>
                                        <p>Wala pang completed KK Profiling form para sa account na ito.</p>
                                    </div>
                                @endif
                            </section>

                            @if($kabataanRegistration)
                            <div class="kk-profile-section-divider" role="separator" aria-hidden="true"></div>
                            <section
                                class="kk-profile-section"
                                id="profileSupportingDocsSection"
                                data-upload-url="{{ route('profile.upload-supporting-document') }}"
                                data-has-documents="{{ !empty($supportingDocuments) ? '1' : '0' }}"
                            >
                                <h2 class="kk-profile-section-title">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                    </svg>
                                    Supporting Documents
                                    <span class="kkp-profile-docs-optional">Optional</span>
                                </h2>
                                @if(!empty($supportingDocuments))
                                    <p class="kkp-docs-summary" id="profileSupportingDocsSummary">
                                        {{ count($supportingDocuments) }} document{{ count($supportingDocuments) === 1 ? '' : 's' }} on file. Uploads are locked and cannot be replaced.
                                    </p>
                                @endif
                                <div class="kkp-preview-toolbar">
                                    <button type="button" class="btn-primary kkp-preview-btn" onclick="openSupportingDocsModal()">Supporting Documents</button>
                                </div>
                            </section>
                            @endif
                        </div>
                    </div>

                    <div class="info-card account-settings-card" id="account-settings">
                        <div class="card-header">
                            <h2>
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                </svg>
                                Account Settings
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="account-setting-item">
                                <div class="setting-info">
                                    <h3>Change Email</h3>
                                    <p>Update the email address used to sign in to your Kabataan account.</p>
                                </div>
                                <a href="{{ route('change-email') }}" class="btn-setting-action">Change Email</a>
                            </div>
                            <div class="setting-divider"></div>
                            <div class="account-setting-item">
                                <div class="setting-info">
                                    <h3>Change Password</h3>
                                    <p>Create a new password. A confirmation link will be sent to your email first.</p>
                                </div>
                                <a href="{{ route('change-password') }}" class="btn-setting-action">Change Password</a>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column - Program Participation -->
                <div class="profile-right-column">
                    <!-- Program Participation Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h2>
                                <svg viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                </svg>
                                Program Participation
                            </h2>
                        </div>
                        <div class="card-body">
                            <!-- Filter Tabs -->
                            <div class="filter-tabs">
                                <button class="tab-btn active" data-filter="all">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                    </svg>
                                    All
                                </button>
                                <button class="tab-btn" data-filter="pending">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    Pending
                                </button>
                                <button class="tab-btn" data-filter="approved">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Approved
                                </button>
                                <button class="tab-btn" data-filter="completed">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Completed
                                </button>
                            </div>

                            <!-- Programs List -->
                            <div class="programs-list">
                                @forelse($programs ?? [] as $program)
                                <div class="program-item program-item--clickable"
                                     data-status="{{ $program->status }}"
                                     data-redirect-url="{{ $program->redirect_url ?? '' }}"
                                     role="link"
                                     tabindex="0"
                                     title="View your {{ ($program->source ?? '') === 'survey' ? 'survey response' : 'application' }}">
                                    <div class="program-icon-wrapper">
                                        <div class="program-icon {{ $program->status }}">
                                            <svg viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="program-details">
                                        <h3>{{ $program->name }}</h3>
                                        <p class="program-category">{{ $program->category ?? 'General Program' }}</p>
                                        @if(!empty($program->answers_preview))
                                            <p class="program-answer-preview">{{ $program->answers_preview }}</p>
                                        @endif
                                        <p class="program-date">
                                            <svg viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                            </svg>
                                            @if(($program->source ?? '') === 'survey')
                                                Responded: {{ \Carbon\Carbon::parse($program->created_at)->format('M d, Y') }}
                                            @else
                                                Applied: {{ \Carbon\Carbon::parse($program->created_at)->format('M d, Y') }}
                                            @endif
                                        </p>
                                        <span class="status-badge {{ $program->status }}">
                                            @if($program->status === 'pending')
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($program->status === 'approved')
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($program->status === 'evaluation')
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($program->status === 'completed')
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($program->status === 'declined')
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                </svg>
                                            @else
                                                <svg viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                            {{ ucfirst($program->status) }}
                                        </span>
                                    </div>
                                    <div class="program-item-chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                @empty
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="6" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                            <path d="M3 10 L12 6 L21 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <line x1="10" y1="16" x2="14" y2="16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <h3>No Programs Yet</h3>
                                    <p>You haven't joined any programs yet. Explore available programs in the dashboard!</p>
                                    <a href="{{ route('dashboard') }}" class="explore-btn">
                                        <svg viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                                        </svg>
                                        Explore Programs
                                    </a>
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Profile picture upload instructions --}}
    <div class="modal-backdrop kabataan-modal-backdrop profile-picture-upload-modal" id="profilePictureUploadModal" style="display: none;">
        <div class="kabataan-modal-box profile-picture-upload-modal__box" role="dialog" aria-labelledby="profilePictureUploadTitle" aria-modal="true">
            <div class="modal-header">
                <h2 class="modal-title" id="profilePictureUploadTitle">Profile Picture Guidelines</h2>
                <button type="button" class="modal-close" onclick="closeProfilePictureUploadModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body kabataan-modal-body profile-picture-upload-modal__body">
                <p class="profile-picture-upload-modal__intro">Before you upload, make sure your photo meets these requirements:</p>
                <ul class="profile-picture-upload-modal__list">
                    <li>Use a clear, recent photo of yourself (face visible, good lighting).</li>
                    <li>Accepted formats: JPG, JPEG, PNG, or WEBP.</li>
                    <li>Maximum file size: 10MB.</li>
                    <li>Avoid blurry, dark, group, or inappropriate images.</li>
                    <li>Profile pictures can only be changed once every 30 days.</li>
                </ul>
                <div class="profile-picture-upload-modal__actions">
                    <button type="button" class="btn-secondary profile-picture-upload-modal__cancel" onclick="closeProfilePictureUploadModal()">Cancel</button>
                    <button type="button" class="btn-primary profile-picture-upload-modal__continue" id="profilePictureUploadContinueBtn">Choose Photo</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-backdrop kabataan-modal-backdrop profile-picture-confirm-modal" id="profilePictureConfirmModal" style="display: none;">
        <div class="kabataan-modal-box profile-picture-confirm-modal__box" role="dialog" aria-labelledby="profilePictureConfirmTitle" aria-modal="true">
            <div class="modal-header">
                <h2 class="modal-title" id="profilePictureConfirmTitle">Confirm Profile Picture</h2>
                <button type="button" class="modal-close" onclick="closeProfilePictureConfirmModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body kabataan-modal-body profile-picture-confirm-modal__body">
                <p class="profile-picture-confirm-modal__intro">Review your selected photo before saving it as your profile picture.</p>
                <div class="profile-picture-confirm-modal__preview-wrap">
                    <img src="" alt="Selected profile picture preview" class="profile-picture-confirm-modal__preview" id="profilePictureConfirmPreview">
                </div>
                <p class="profile-picture-confirm-modal__notice">
                    Once you confirm, this photo will be used as your profile picture and can only be changed again after <strong>30 days</strong>.
                </p>
                <div class="profile-picture-upload-modal__actions">
                    <button type="button" class="btn-secondary profile-picture-upload-modal__cancel" id="profilePictureConfirmCancelBtn">Choose Different Photo</button>
                    <button type="button" class="btn-primary profile-picture-upload-modal__continue" id="profilePictureConfirmSubmitBtn">Confirm &amp; Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-backdrop kabataan-modal-backdrop profile-picture-lock-modal" id="profilePictureLockModal" style="display: none;">
        <div class="kabataan-modal-box profile-picture-lock-modal__box" role="dialog" aria-labelledby="profilePictureLockTitle" aria-modal="true">
            <div class="modal-header">
                <h2 class="modal-title" id="profilePictureLockTitle">Profile Picture Update</h2>
                <button type="button" class="modal-close" onclick="closeProfilePictureLockModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body kabataan-modal-body profile-picture-lock-modal__body">
                <div class="profile-picture-lock-modal__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M8 11V8a4 4 0 118 0v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="profile-picture-lock-modal__message">
                    You can update your profile picture again on
                    <strong id="profilePictureLockDate">{{ $profileImageNextChangeDisplay ?? '' }}</strong>.
                </p>
                <p class="profile-picture-lock-modal__hint">Profile pictures can only be changed once every 30 days.</p>
                <button type="button" class="btn-primary profile-picture-lock-modal__btn" onclick="closeProfilePictureLockModal()">Got it</button>
            </div>
        </div>
    </div>

    @if($kabataanRegistration)
    <div class="modal-backdrop kabataan-modal-backdrop" id="supportingDocsModal" style="display: none;">
        <div class="modal-box kabataan-modal-box kkp-docs-modal-container" id="supportingDocsModalPanel">
            <div class="modal-header">
                <h2 class="modal-title">Supporting Documents</h2>
                <div class="modal-window-controls">
                    <button type="button" class="modal-toggle-btn" id="supportingDocsFullscreenBtn" aria-label="Maximize">
                        <span class="modal-icon-maximize" aria-hidden="true">&#9633;</span>
                        <span class="modal-icon-restore" aria-hidden="true" hidden>&#10697;</span>
                    </button>
                    <button type="button" class="modal-close" onclick="closeSupportingDocsModal()" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="modal-body kabataan-modal-body kkp-docs-modal-body">
                @if(!empty($supportingDocuments))
                <div class="kkp-profile-docs-grid kkp-profile-docs-grid--preview">
                    @foreach($supportingDocuments as $document)
                        <div class="kkp-profile-doc-card">
                            <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="kkp-profile-doc-thumb-link">
                                <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" class="kkp-profile-doc-thumb" loading="lazy">
                            </a>
                            <div class="kkp-profile-doc-meta">
                                <p class="kkp-profile-doc-label">{{ $document['label'] }}</p>
                                <p class="kkp-profile-doc-name">{{ $document['display_name'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="kkp-docs-upload-intro kkp-docs-locked-note">
                    Your supporting documents are on file and cannot be changed or replaced.
                </p>
                <div class="kkp-docs-upload-actions">
                    <button type="button" class="btn-secondary" onclick="closeSupportingDocsModal()">Close</button>
                </div>
                @else
                <div class="kkp-docs-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="kkp-docs-empty-icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <p class="kkp-docs-empty-text">No supporting documents uploaded.</p>
                </div>
                {{-- Original upload form removed: no upload allowed when no documents exist --}}
                <div class="kkp-docs-upload-section" hidden>
                    <h3 class="kkp-docs-upload-heading">Upload document</h3>
                    <p class="kkp-docs-upload-intro">
                        Upload <strong>one</strong> optional supporting ID  School ID or PhilSys / National ID. Provide front and back images. JPG or PNG, max 10MB each.
                    </p>

                    <fieldset class="kkp-wizard-doc-type-fieldset" id="profileDocTypeFieldset">
                        <legend class="kkp-wizard-doc-type-legend">Select document type</legend>
                        <div class="kkp-wizard-doc-type-options" role="radiogroup" aria-label="Document type">
                            <label class="kkp-wizard-doc-type-option">
                                <input type="radio" name="profile_document_type" value="school_id" id="profileDocTypeSchoolId">
                                <span class="kkp-wizard-doc-type-card">
                                    <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="5" width="20" height="14" rx="2"></rect><circle cx="8" cy="12" r="2"></circle><path d="M14 10h5M14 14h5"></path></svg>
                                    </span>
                                    <span class="kkp-wizard-doc-type-text">
                                        <span class="kkp-wizard-doc-type-name">School ID</span>
                                        <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
                                    </span>
                                </span>
                            </label>
                            <label class="kkp-wizard-doc-type-option">
                                <input type="radio" name="profile_document_type" value="national_id" id="profileDocTypeNationalId">
                                <span class="kkp-wizard-doc-type-card">
                                    <span class="kkp-wizard-doc-type-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="11" r="2"></circle><path d="M15 9h4M15 13h4"></path></svg>
                                    </span>
                                    <span class="kkp-wizard-doc-type-text">
                                        <span class="kkp-wizard-doc-type-name">PhilSys / National ID</span>
                                        <span class="kkp-wizard-doc-type-desc">Front and back · optional upload</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    @foreach([
                        ['type' => 'school_id', 'panelId' => 'profileSchoolIdUpload', 'label' => 'School ID', 'prefix' => 'profileSchoolId'],
                        ['type' => 'national_id', 'panelId' => 'profileNationalIdUpload', 'label' => 'PhilSys / National ID', 'prefix' => 'profileNationalId'],
                    ] as $doc)
                    <div class="kkp-wizard-upload-panel" id="{{ $doc['panelId'] }}" hidden>
                        <p class="kkp-wizard-upload-panel-title">{{ $doc['label'] }}  upload front and back</p>
                        <div class="kkp-wizard-upload-grid">
                            @foreach(['front' => 'Front', 'back' => 'Back'] as $side => $sideLabel)
                            @php $inputId = $doc['prefix'].ucfirst($side); @endphp
                            <div class="kkp-wizard-upload-shell">
                                <p class="kkp-wizard-upload-side-label">{{ $sideLabel }}</p>
                                <label class="kkp-wizard-dropzone" for="{{ $inputId }}">
                                    <input type="file" id="{{ $inputId }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="kkp-wizard-file-input" data-doc-side="{{ $side }}">
                                    <span class="kkp-wizard-dropzone-empty">
                                        <span class="kkp-wizard-dropzone-icon" aria-hidden="true">??</span>
                                        <span class="kkp-wizard-dropzone-title">{{ $sideLabel }} image</span>
                                        <span class="kkp-wizard-dropzone-sub">Drop or <span class="kkp-wizard-dropzone-link">browse</span></span>
                                        <span class="kkp-wizard-dropzone-hint">JPG or PNG · max 10MB</span>
                                    </span>
                                </label>
                                <div class="kkp-wizard-dropzone-preview" id="{{ $inputId }}Preview" hidden>
                                    <img id="{{ $inputId }}PreviewImg" alt="{{ $doc['label'] }} {{ strtolower($sideLabel) }} preview">
                                    <div class="kkp-wizard-dropzone-filemeta">
                                        <span class="kkp-wizard-dropzone-filename" id="{{ $inputId }}FileName"></span>
                                        <button type="button" class="kkp-wizard-dropzone-remove" data-clear-profile-doc="{{ $inputId }}" aria-label="Remove {{ $sideLabel }} image">Remove</button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    <p class="kkp-docs-upload-error" id="profileSupportingDocUploadError" hidden></p>

                    <div class="kkp-docs-upload-actions">
                        <button type="button" class="btn-secondary" onclick="closeSupportingDocsModal()">Close</button>
                        <button type="button" class="btn-primary" id="profileSupportingDocSubmitBtn" disabled>Upload Document</button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="modal-backdrop kabataan-modal-backdrop" id="kkPreviewModal" style="display: none;">
        <div class="modal-box kabataan-modal-box kk-preview-modal-container" id="kkPreviewModalPanel">
            <div class="modal-header">
                <h2 class="modal-title">Personal Information</h2>
                <div class="modal-window-controls">
                    <button type="button" class="modal-toggle-btn" id="kkPreviewFullscreenBtn" data-modal-toggle aria-label="Maximize">
                        <span class="modal-icon-maximize" aria-hidden="true">&#9633;</span>
                        <span class="modal-icon-restore" aria-hidden="true" hidden>&#10697;</span>
                    </button>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="modal-body kabataan-modal-body">
                @if($kabataanRegistration)
                    <div class="kkp-responsive-container">
                        <div class="kkp-paper">
                            @include('profile::partials.kk-profiling-preview', [
                                'kabataanRegistration' => $kabataanRegistration,
                                'user' => $user,
                                'barangayName' => $barangayName,
                                'barangayLogoUrl' => $barangayLogoUrl,
                                'profile' => $profile ?? [],
                            ])
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    window.addEventListener('unload', function () {});

    function setKabataanModalMaximized(backdrop, panel, toggleBtn, isMax) {
        backdrop?.classList.toggle('modal-maximized', isMax);
        panel?.classList.toggle('modal-maximized', isMax);
        const maximizeIcon = toggleBtn?.querySelector('.modal-icon-maximize');
        const restoreIcon = toggleBtn?.querySelector('.modal-icon-restore');
        if (maximizeIcon) maximizeIcon.hidden = isMax;
        if (restoreIcon) restoreIcon.hidden = !isMax;
        toggleBtn?.setAttribute('aria-label', isMax ? 'Restore down' : 'Maximize');
    }

    function resetKkPreviewModalState() {
        const backdrop = document.getElementById('kkPreviewModal');
        const panel = document.getElementById('kkPreviewModalPanel');
        const toggleBtn = document.getElementById('kkPreviewFullscreenBtn');
        setKabataanModalMaximized(backdrop, panel, toggleBtn, false);
    }

    function openKkPreviewModal() {
        const modal = document.getElementById('kkPreviewModal');
        if (modal) {
            resetKkPreviewModalState();
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeKkPreviewModal() {
        const modal = document.getElementById('kkPreviewModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            resetKkPreviewModalState();
        }
    }

    function resetSupportingDocsModalState() {
        const backdrop = document.getElementById('supportingDocsModal');
        const panel = document.getElementById('supportingDocsModalPanel');
        const toggleBtn = document.getElementById('supportingDocsFullscreenBtn');
        setKabataanModalMaximized(backdrop, panel, toggleBtn, false);
    }

    function openSupportingDocsModal() {
        const modal = document.getElementById('supportingDocsModal');
        if (modal) {
            resetSupportingDocsModalState();
            const firstBtn = modal.querySelector('.kkp-docs-select-btn');
            if (firstBtn) {
                showSupportingDocumentPreview(firstBtn);
            }
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function showSupportingDocumentPreview(button) {
        if (!button) return;

        const modal = document.getElementById('supportingDocsModal');
        const image = document.getElementById('supportingDocPreviewImage');
        const label = document.getElementById('supportingDocPreviewLabel');
        const name = document.getElementById('supportingDocPreviewName');

        modal?.querySelectorAll('.kkp-docs-select-btn').forEach((btn) => {
            const isActive = btn === button;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        const url = button.dataset.docUrl || '';
        const docLabel = button.dataset.docLabel || 'Supporting document';
        const docName = button.dataset.docName || '';

        if (image) {
            image.src = url;
            image.alt = docLabel;
        }
        if (label) label.textContent = docLabel;
        if (name) name.textContent = docName;
    }

    function closeSupportingDocsModal() {
        const modal = document.getElementById('supportingDocsModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            resetSupportingDocsModalState();
            if (typeof window.resetProfileSupportingDocUpload === 'function') {
                window.resetProfileSupportingDocUpload();
            }
        }
    }

    window.openSupportingDocsModal = openSupportingDocsModal;
    window.closeSupportingDocsModal = closeSupportingDocsModal;

    document.getElementById('supportingDocsModal')?.addEventListener('click', function (event) {
        if (event.target === this) {
            closeSupportingDocsModal();
        }
    });

    document.querySelectorAll('#supportingDocsModal .kkp-docs-select-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            showSupportingDocumentPreview(button);
        });
    });

    document.getElementById('supportingDocsFullscreenBtn')?.addEventListener('click', function () {
        const backdrop = document.getElementById('supportingDocsModal');
        const panel = document.getElementById('supportingDocsModalPanel');
        if (!backdrop || !panel) return;
        setKabataanModalMaximized(backdrop, panel, this, !backdrop.classList.contains('modal-maximized'));
    });

    document.getElementById('kkPreviewFullscreenBtn')?.addEventListener('click', function () {
        const backdrop = document.getElementById('kkPreviewModal');
        const panel = document.getElementById('kkPreviewModalPanel');
        if (!backdrop || !panel) return;
        setKabataanModalMaximized(backdrop, panel, this, !backdrop.classList.contains('modal-maximized'));
    });

    document.querySelectorAll('#kkPreviewModal [data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeKkPreviewModal);
    });
    </script>
</body>
</html>
