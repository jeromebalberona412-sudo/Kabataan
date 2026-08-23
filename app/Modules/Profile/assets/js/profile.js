// Profile Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initProfileAvatarChange();
    initProfileSupportingDocuments();
    openAccountSettingsFromHash();

    // ── Toast Notification ──────────────────────────────────────────────────
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `profile-toast profile-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            z-index: 9999;
            animation: profileToastIn 0.3s ease;
            max-width: min(90vw, 420px);
            text-align: center;
        `;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'profileToastOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    window.showProfileToast = showToast;

    // Add animation styles
    if (!document.getElementById('profileToastStyles')) {
        const style = document.createElement('style');
        style.id = 'profileToastStyles';
        style.textContent = `
            @keyframes profileToastIn {
                from { transform: translate(-50%, -12px); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            @keyframes profileToastOut {
                from { transform: translate(-50%, 0); opacity: 1; }
                to { transform: translate(-50%, -12px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }

    // Elements
    const filterTabs = document.querySelectorAll('.tab-btn');
    const programItems = document.querySelectorAll('.program-item');

    // Filter Programs
    if (filterTabs.length > 0) {
        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');

                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                filterPrograms(filter);
            });
        });
    }

    function filterPrograms(filter) {
        programItems.forEach(item => {
            const status = item.getAttribute('data-status');

            if (filter === 'all') {
                item.style.display = 'flex';
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 10);
            } else if (status === filter) {
                item.style.display = 'flex';
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 10);
            } else {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.style.display = 'none';
                }, 300);
            }
        });

        setTimeout(() => {
            const visiblePrograms = Array.from(programItems).filter(item =>
                item.style.display !== 'none'
            );

            const programsList = document.querySelector('.programs-list');
            const existingEmpty = programsList?.querySelector('.empty-state');

            if (programsList && visiblePrograms.length === 0 && !existingEmpty) {
                programsList.appendChild(createEmptyState(filter));
            } else if (existingEmpty && visiblePrograms.length > 0) {
                existingEmpty.remove();
            }
        }, 350);
    }

    function createEmptyState(filter) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';

        let message = 'No programs found';
        if (filter === 'pending') message = 'No pending programs';
        if (filter === 'approved') message = 'No approved programs';
        if (filter === 'evaluation') message = 'No programs in evaluation';
        if (filter === 'completed') message = 'No completed programs';

        emptyDiv.innerHTML = `
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="6" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                    <path d="M3 10 L12 6 L21 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="10" y1="16" x2="14" y2="16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <h3>${message}</h3>
            <p>Check back later or explore new programs in the dashboard!</p>
        `;

        return emptyDiv;
    }

    const programsList = document.querySelector('.programs-list');
    if (programsList) {
        programsList.style.scrollBehavior = 'smooth';
    }

    programItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';

        setTimeout(() => {
            item.style.transition = 'all 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 50);
    });
});

function openAccountSettingsFromHash() {
    const card = document.getElementById('account-settings');
    if (!card) return;

    const openCard = () => {
        card.classList.add('is-open', 'is-highlighted');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    if (window.location.hash === '#account-settings') {
        requestAnimationFrame(openCard);
    }

    window.addEventListener('hashchange', function () {
        if (window.location.hash === '#account-settings') {
            openCard();
        }
    });
}

function initProfileAvatarChange() {
    const wrapper = document.getElementById('profileAvatarWrapper');
    if (!wrapper) return;

    const uploadUrl = wrapper.dataset.uploadUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const profileAvatar = document.getElementById('profileAvatar');
    const fileInput = document.getElementById('photoUpload');
    const lockModal = document.getElementById('profilePictureLockModal');
    const uploadInstructionsModal = document.getElementById('profilePictureUploadModal');
    const uploadContinueBtn = document.getElementById('profilePictureUploadContinueBtn');
    const lockDateEl = document.getElementById('profilePictureLockDate');
    const confirmModal = document.getElementById('profilePictureConfirmModal');
    const confirmPreview = document.getElementById('profilePictureConfirmPreview');
    const confirmCancelBtn = document.getElementById('profilePictureConfirmCancelBtn');
    const confirmSubmitBtn = document.getElementById('profilePictureConfirmSubmitBtn');
    let pendingProfileFile = null;
    let pendingPreviewUrl = null;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const maxBytes = 10 * 1024 * 1024;

    if (profileAvatar) {
        profileAvatar.addEventListener('error', () => {
            const fallback = profileAvatar.dataset.fallback || wrapper.dataset.fallbackAvatar;
            if (fallback && profileAvatar.src !== fallback) {
                profileAvatar.src = fallback;
            }
        });
    }

    function isUploadAllowed() {
        return wrapper.dataset.canChange === '1';
    }

    function normalizeAvatarUrl(url) {
        if (!url) return url;
        const storageMatch = url.match(/\/storage\/.+$/);
        if (storageMatch) return storageMatch[0];
        return url;
    }

    function updateAvatarImages(url) {
        const resolved = normalizeAvatarUrl(url);
        if (profileAvatar) {
            profileAvatar.onerror = () => {
                profileAvatar.onerror = null;
                profileAvatar.src = profileAvatar.dataset.fallback || wrapper.dataset.fallbackAvatar || '';
            };
            profileAvatar.src = resolved;
        }

        document.querySelectorAll('.kabataan-header__avatar-btn img, .kabataan-header__dropdown-head img').forEach((img) => {
            img.src = resolved;
        });
    }

    function lockUpload(nextChangeDisplay) {
        wrapper.dataset.canChange = '0';
        wrapper.dataset.nextChange = nextChangeDisplay || '';
        wrapper.setAttribute('aria-label', 'Profile picture update locked');
        wrapper.setAttribute('title', 'Profile picture update locked');
        if (fileInput) fileInput.disabled = true;
        if (lockDateEl && nextChangeDisplay) {
            lockDateEl.textContent = nextChangeDisplay;
        }
    }

    function showLockModal() {
        const nextChange = wrapper.dataset.nextChange;
        if (lockDateEl && nextChange) {
            lockDateEl.textContent = nextChange;
        }
        if (lockModal) {
            lockModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    window.closeProfilePictureLockModal = function() {
        if (lockModal) {
            lockModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };

    window.closeProfilePictureUploadModal = function() {
        if (uploadInstructionsModal) {
            uploadInstructionsModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };

    function openPhotoPicker() {
        if (!fileInput || fileInput.disabled) {
            return;
        }

        // Must run synchronously inside the user click handler (no modal close before this).
        fileInput.click();
    }

    function closeModalsForUpload() {
        if (uploadInstructionsModal) {
            uploadInstructionsModal.style.display = 'none';
        }
        if (confirmModal) {
            confirmModal.style.display = 'none';
        }
        document.body.style.overflow = 'auto';
    }

    function clearPendingProfileFile() {
        if (pendingPreviewUrl) {
            URL.revokeObjectURL(pendingPreviewUrl);
            pendingPreviewUrl = null;
        }
        pendingProfileFile = null;
        if (confirmPreview) {
            confirmPreview.removeAttribute('src');
        }
    }

    window.closeProfilePictureConfirmModal = function() {
        clearPendingProfileFile();
        if (confirmModal) {
            confirmModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };

    function showConfirmModal(file) {
        clearPendingProfileFile();
        pendingProfileFile = file;
        pendingPreviewUrl = URL.createObjectURL(file);

        if (confirmPreview) {
            confirmPreview.src = pendingPreviewUrl;
        }
        if (confirmModal) {
            confirmModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function showUploadInstructionsModal() {
        if (uploadInstructionsModal) {
            uploadInstructionsModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function validateFile(file) {
        if (!file) {
            return 'Please select an image file.';
        }

        if (!allowedTypes.includes(file.type)) {
            return 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
        }

        if (file.size > maxBytes) {
            return 'Profile image must be 10MB or smaller.';
        }

        return null;
    }

    function uploadFile(file) {
        if (!isUploadAllowed()) {
            showLockModal();
            return;
        }

        const validationError = validateFile(file);
        if (validationError) {
            if (typeof window.showProfileToast === 'function') {
                window.showProfileToast(validationError, 'error');
            }
            return;
        }

        closeModalsForUpload();
        clearPendingProfileFile();

        const formData = new FormData();
        formData.append('profile_picture', file);
        formData.append('_token', csrfToken);

        if (confirmSubmitBtn) {
            confirmSubmitBtn.disabled = true;
            confirmSubmitBtn.textContent = 'Uploading...';
        }

        fetch(uploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
            credentials: 'same-origin',
        })
            .then(async (response) => {
                let payload = {};
                try {
                    payload = await response.json();
                } catch (error) {
                    throw new Error('Unexpected server response. Please try again.');
                }

                if (response.ok && payload.success) {
                    updateAvatarImages(payload.picture_url);
                    const message = payload.message || 'Profile picture uploaded successfully.';
                    if (typeof window.showProfileToast === 'function') {
                        window.showProfileToast(message, 'success');
                    }
                    if (payload.next_change_display) {
                        lockUpload(payload.next_change_display);
                    }
                    return;
                }

                const message = payload.message
                    || (payload.errors && Object.values(payload.errors).flat()[0])
                    || 'Failed to upload profile picture.';
                throw new Error(message);
            })
            .catch((error) => {
                if (typeof window.showProfileToast === 'function') {
                    window.showProfileToast(error.message || 'Network error while uploading. Please try again.', 'error');
                }
            })
            .finally(() => {
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.disabled = false;
                    confirmSubmitBtn.textContent = 'Confirm & Save';
                }
            });
    }

    function handleAvatarClick(event) {
        event.preventDefault();
        if (!isUploadAllowed()) {
            showLockModal();
            return;
        }
        showUploadInstructionsModal();
    }

    uploadContinueBtn?.addEventListener('click', () => {
        window.closeProfilePictureUploadModal();
        openPhotoPicker();
    });

    uploadInstructionsModal?.addEventListener('click', (event) => {
        if (event.target === uploadInstructionsModal) {
            window.closeProfilePictureUploadModal();
        }
    });

    wrapper.addEventListener('click', handleAvatarClick);
    wrapper.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            handleAvatarClick(event);
        }
    });

    fileInput?.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        const validationError = validateFile(file);
        if (validationError) {
            if (typeof window.showProfileToast === 'function') {
                window.showProfileToast(validationError, 'error');
            }
            return;
        }

        closeModalsForUpload();
        showConfirmModal(file);
    });

    confirmCancelBtn?.addEventListener('click', () => {
        window.closeProfilePictureConfirmModal();
        openPhotoPicker();
    });

    confirmSubmitBtn?.addEventListener('click', () => {
        if (!pendingProfileFile) {
            window.closeProfilePictureConfirmModal();
            return;
        }
        const file = pendingProfileFile;
        uploadFile(file);
    });

    confirmModal?.addEventListener('click', (event) => {
        if (event.target === confirmModal) {
            window.closeProfilePictureConfirmModal();
        }
    });

    fileInput?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    lockModal?.addEventListener('click', (event) => {
        if (event.target === lockModal) {
            window.closeProfilePictureLockModal();
        }
    });
}

function initProfileSupportingDocuments() {
    const section = document.getElementById('profileSupportingDocsSection');
    const uploadModal = document.getElementById('supportingDocsModal');
    if (!section || !uploadModal) {
        return;
    }

    const uploadUrl = section.dataset.uploadUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const submitBtn = document.getElementById('profileSupportingDocSubmitBtn');
    const errorEl = document.getElementById('profileSupportingDocUploadError');
    if (!submitBtn) {
        return;
    }
    const schoolIdPanel = document.getElementById('profileSchoolIdUpload');
    const nationalIdPanel = document.getElementById('profileNationalIdUpload');
    const docTypeRadios = uploadModal.querySelectorAll('input[name="profile_document_type"]');
    const maxBytes = 10 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

    const inputMap = {
        school_id: {
            front: document.getElementById('profileSchoolIdFront'),
            back: document.getElementById('profileSchoolIdBack'),
        },
        national_id: {
            front: document.getElementById('profileNationalIdFront'),
            back: document.getElementById('profileNationalIdBack'),
        },
    };

    const pendingFiles = { school_id: { front: null, back: null }, national_id: { front: null, back: null } };

    function showError(message) {
        if (!errorEl) return;
        if (!message) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = message;
    }

    function selectedDocumentType() {
        const checked = uploadModal.querySelector('input[name="profile_document_type"]:checked');
        return checked ? checked.value : '';
    }

    function previewElements(inputId) {
        return {
            preview: document.getElementById(inputId + 'Preview'),
            previewImg: document.getElementById(inputId + 'PreviewImg'),
            fileName: document.getElementById(inputId + 'FileName'),
            dropzone: document.querySelector('label[for="' + inputId + '"]'),
        };
    }

    function clearSidePreview(type, side) {
        const input = inputMap[type]?.[side];
        const inputId = input?.id;
        if (!inputId) return;
        const els = previewElements(inputId);
        input.value = '';
        if (els.preview) els.preview.hidden = true;
        if (els.dropzone) els.dropzone.hidden = false;
        if (els.previewImg) els.previewImg.removeAttribute('src');
        if (els.fileName) els.fileName.textContent = '';
        pendingFiles[type][side] = null;
    }

    function clearAllPending() {
        Object.keys(inputMap).forEach((type) => {
            clearSidePreview(type, 'front');
            clearSidePreview(type, 'back');
        });
        showError('');
    }

    function updatePanels() {
        const type = selectedDocumentType();
        if (schoolIdPanel) schoolIdPanel.hidden = type !== 'school_id';
        if (nationalIdPanel) nationalIdPanel.hidden = type !== 'national_id';
        if (type === 'school_id') {
            clearSidePreview('national_id', 'front');
            clearSidePreview('national_id', 'back');
        } else if (type === 'national_id') {
            clearSidePreview('school_id', 'front');
            clearSidePreview('school_id', 'back');
        }
        updateSubmitState();
    }

    function updateSubmitState() {
        if (!submitBtn) return;
        const type = selectedDocumentType();
        const files = type ? pendingFiles[type] : null;
        submitBtn.disabled = !(type && files?.front && files?.back);
    }

    function validateFile(file) {
        if (!file) return 'Please select an image file.';
        if (!allowedTypes.includes(file.type)) return 'Only JPG and PNG images are allowed.';
        if (file.size > maxBytes) return 'Supporting document must be 10MB or smaller.';
        return null;
    }

    function setPendingFile(type, side, file) {
        const validationError = validateFile(file);
        if (validationError) {
            showError(validationError);
            return;
        }

        const input = inputMap[type]?.[side];
        if (!input?.id) return;

        pendingFiles[type][side] = file;
        showError('');

        const els = previewElements(input.id);
        const reader = new FileReader();
        reader.onload = function (event) {
            if (els.previewImg && event.target?.result) els.previewImg.src = event.target.result;
            if (els.fileName) els.fileName.textContent = file.name;
            if (els.preview) els.preview.hidden = false;
            if (els.dropzone) els.dropzone.hidden = true;
        };
        reader.readAsDataURL(file);
        updateSubmitState();
    }

    function resetUploadModal() {
        docTypeRadios.forEach((radio) => { radio.checked = false; });
        if (schoolIdPanel) schoolIdPanel.hidden = true;
        if (nationalIdPanel) nationalIdPanel.hidden = true;
        clearAllPending();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Upload Document';
        }
    }

    function uploadDocument() {
        const documentType = selectedDocumentType();
        const files = documentType ? pendingFiles[documentType] : null;
        if (!documentType || !files?.front || !files?.back || !uploadUrl) {
            showError('Please select a document type and upload both front and back images.');
            return;
        }

        const formData = new FormData();
        formData.append('document_type', documentType);
        formData.append('_token', csrfToken);
        formData.append(documentType + '_front', files.front);
        formData.append(documentType + '_back', files.back);

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
        }

        fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            body: formData,
            credentials: 'same-origin',
        })
            .then(async (response) => {
                let payload = {};
                try { payload = await response.json(); } catch (_) {
                    throw new Error('Unexpected server response. Please try again.');
                }
                if (response.ok && payload.success) {
                    if (typeof window.showProfileToast === 'function') {
                        window.showProfileToast(payload.message || 'Supporting documents uploaded successfully.', 'success');
                    }
                    window.closeSupportingDocsModal?.();
                    window.location.reload();
                    return;
                }
                throw new Error(payload.message || (payload.errors && Object.values(payload.errors).flat()[0]) || 'Failed to upload supporting documents.');
            })
            .catch((error) => {
                showError(error.message || 'Network error while uploading. Please try again.');
                if (typeof window.showProfileToast === 'function') {
                    window.showProfileToast(error.message || 'Failed to upload supporting documents.', 'error');
                }
            })
            .finally(() => {
                updateSubmitState();
                if (submitBtn) submitBtn.textContent = 'Upload Document';
            });
    }

    window.resetProfileSupportingDocUpload = resetUploadModal;

    docTypeRadios.forEach((radio) => radio.addEventListener('change', updatePanels));

    Object.entries(inputMap).forEach(([type, sides]) => {
        Object.entries(sides).forEach(([side, input]) => {
            input?.addEventListener('change', function () {
                if (this.files?.[0]) setPendingFile(type, side, this.files[0]);
            });
        });
    });

    uploadModal.querySelectorAll('[data-clear-profile-doc]').forEach((button) => {
        button.addEventListener('click', function () {
            const inputId = button.getAttribute('data-clear-profile-doc');
            if (inputId?.includes('SchoolId')) {
                clearSidePreview('school_id', inputId.includes('Front') ? 'front' : 'back');
            } else if (inputId?.includes('NationalId')) {
                clearSidePreview('national_id', inputId.includes('Front') ? 'front' : 'back');
            }
            updateSubmitState();
        });
    });

    submitBtn?.addEventListener('click', uploadDocument);
}
