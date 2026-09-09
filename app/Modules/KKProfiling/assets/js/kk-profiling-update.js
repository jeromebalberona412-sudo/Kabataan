/**
 * KK Profiling yearly update page
 * Prefills personal/basic fields only — never prior-year demographics.
 */
(function () {
    'use strict';

    const PERSONAL_PREFILL_KEYS = [
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'custom_suffix',
        'purok_zone',
        'sex',
        'age',
        'birthday',
        'email',
        'contact_number',
    ];

    if (window.__KK_PROFILING_UPDATE_REQUIRED) {
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, '', location.href);
        });
    }

    const form = document.getElementById('kkProfilingUpdateForm');
    if (!form) {
        return;
    }

    function setCheckboxGroupValue(chkName, hiddenId, value) {
        if (!value) {
            return;
        }

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

    function personalPrefillOnly(data) {
        const safe = {};
        if (!data || typeof data !== 'object') {
            return safe;
        }

        PERSONAL_PREFILL_KEYS.forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(data, key) && data[key] !== null && data[key] !== '') {
                safe[key] = data[key];
            }
        });

        return safe;
    }

    function populateUpdateForm(rawData) {
        const data = personalPrefillOnly(rawData);

        Object.entries(data).forEach(([key, value]) => {
            if (key === 'suffix' || key === 'email' || key === 'sex') {
                return;
            }
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

        const rawSex = Array.isArray(data.sex) ? data.sex[0] : data.sex;
        if (rawSex) {
            setCheckboxGroupValue('sexChk', 'kkpSex', rawSex);
        }

        const suffixSelect = document.getElementById('kkpSuffix');
        if (suffixSelect) {
            const suffixOptions = Array.from(suffixSelect.options).map((option) => option.value);
            let suffixValue = (data.suffix || '').trim();
            if (!suffixValue || suffixValue.toLowerCase() === 'none') {
                suffixValue = 'None';
            }
            if (!suffixOptions.includes(suffixValue)) {
                suffixValue = 'None';
            }
            suffixSelect.value = suffixValue;
            suffixSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Do NOT sync youth_age_group from age — demographics must stay blank for current year.

        lockEmailField(data.email);
    }

    function getLockedEmail(fallbackEmail) {
        return (window.__KK_PROFILING_ORIGINAL_EMAIL || fallbackEmail || '').trim().toLowerCase();
    }

    function lockEmailField(fallbackEmail) {
        const emailInput = document.getElementById('kkpEmail');
        const lockedEmail = getLockedEmail(fallbackEmail);
        if (emailInput && lockedEmail) {
            emailInput.value = lockedEmail;
            emailInput.readOnly = true;
            emailInput.classList.add('kkp-readonly');
            emailInput.dataset.originalEmail = lockedEmail;
        }
    }

    function clearFieldErrors() {
        form.querySelectorAll('.kkp-field-error').forEach((el) => el.remove());
        form.querySelectorAll('.kkp-input-error, .is-invalid').forEach((el) => {
            el.classList.remove('kkp-input-error', 'is-invalid');
        });
        form.querySelectorAll('.kkp-demo-block-error').forEach((el) => {
            el.classList.remove('kkp-demo-block-error');
        });
    }

    function clearUpdateFormExceptEmail() {
        const lockedEmail = getLockedEmail(
            document.getElementById('kkpEmail')?.value
            || document.getElementById('kkpEmail')?.dataset?.originalEmail
        );

        const locationDefaults = {
            region: 'Region IV-A (CALABARZON)',
            province: 'Laguna',
            city: 'Santa Cruz',
        };

        const preserveNames = new Set([
            '_token',
            '_method',
            'email',
        ]);

        form.querySelectorAll('input, select, textarea').forEach((el) => {
            const name = el.name || '';
            const id = el.id || '';

            if (preserveNames.has(name) || id === 'kkpEmail') {
                return;
            }

            // Keep location defaults + header auto fields (respondent #, date)
            if (el.readOnly || el.classList.contains('kkp-readonly')) {
                return;
            }

            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
                el.disabled = false;
                return;
            }

            if (el.type === 'hidden') {
                if (id === 'kkpSignatureData' || name === 'signature_data') {
                    el.value = '';
                    return;
                }
                if (id.startsWith('kkp') || name) {
                    el.value = '';
                    el.disabled = false;
                }
                return;
            }

            if (el.tagName === 'SELECT') {
                if (id === 'kkpSuffix') {
                    el.value = 'None';
                } else {
                    el.selectedIndex = 0;
                }
                el.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Re-assert location defaults if any readonly fields were wiped.
        form.querySelectorAll('.kkp-loc-row .kkp-readonly').forEach((el) => {
            const label = (el.closest('.kkp-loc-col')?.querySelector('.kkp-col-label')?.textContent || '').toLowerCase();
            if (label.includes('region') && !el.value) {
                el.value = locationDefaults.region;
            } else if (label.includes('province') && !el.value) {
                el.value = locationDefaults.province;
            } else if ((label.includes('city') || label.includes('municipality')) && !el.value) {
                el.value = locationDefaults.city;
            }
        });

        const customSuffixWrap = document.getElementById('kkpCustomSuffixWrap');
        if (customSuffixWrap) {
            customSuffixWrap.hidden = true;
        }

        const sigInput = document.getElementById('kkpSignatureData');
        if (sigInput) {
            sigInput.value = '';
        }
        if (typeof window.kkpRestoreSignaturePreview === 'function') {
            const clearSavedBtn = document.getElementById('kkpSignatureClearSaved');
            if (clearSavedBtn && !clearSavedBtn.hidden) {
                clearSavedBtn.click();
            } else {
                const preview = document.getElementById('kkpSignaturePreview');
                const overlay = document.getElementById('kkpSignatureOverlay');
                const status = document.getElementById('kkpSignatureStatus');
                if (preview) {
                    preview.removeAttribute('src');
                    preview.hidden = true;
                }
                if (overlay) {
                    overlay.hidden = true;
                }
                if (status) {
                    status.textContent = '';
                    status.hidden = true;
                }
                if (clearSavedBtn) {
                    clearSavedBtn.hidden = true;
                }
            }
        }

        if (typeof window.syncAssemblyFollowUp === 'function') {
            window.syncAssemblyFollowUp();
        }

        form.querySelectorAll('input[name="sk_votedChk"]').forEach((cb) => {
            cb.disabled = false;
            cb.checked = false;
        });
        const skVotedHidden = document.getElementById('kkpSkVoted');
        if (skVotedHidden) {
            skVotedHidden.value = '';
        }

        clearFieldErrors();
        lockEmailField(lockedEmail);

        const skVoterHidden = document.getElementById('kkpSkVoter');
        if (skVoterHidden) {
            skVoterHidden.value = '';
        }
    }

    function bindClearDataModal() {
        const clearBtn = document.getElementById('kkpFormClearAllBtn');
        const modal = document.getElementById('kkpClearDraftModal');
        const backdrop = document.getElementById('kkpClearDraftBackdrop');
        const closeBtn = document.getElementById('kkpClearDraftCloseBtn');
        const cancelBtn = document.getElementById('kkpClearDraftCancelBtn');
        const confirmBtn = document.getElementById('kkpClearDraftConfirmBtn');

        if (!clearBtn || !modal) {
            return;
        }

        function openModal() {
            modal.hidden = false;
            confirmBtn?.focus();
        }

        function closeModal() {
            modal.hidden = true;
            clearBtn.focus();
        }

        clearBtn.addEventListener('click', openModal);
        backdrop?.addEventListener('click', closeModal);
        closeBtn?.addEventListener('click', closeModal);
        cancelBtn?.addEventListener('click', closeModal);
        confirmBtn?.addEventListener('click', () => {
            clearUpdateFormExceptEmail();
            closeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        populateUpdateForm(window.__KK_PROFILING_FORM_DATA || {});
        // Always re-lock email after populate/clear so update never shows "Email is required."
        lockEmailField(window.__KK_PROFILING_ORIGINAL_EMAIL || '');
        bindClearDataModal();
        form.addEventListener('submit', (event) => {
            window.handleKkProfilingUpdateSubmit(event);
        });
    });
})();

window.handleKkProfilingUpdateSubmit = async function (event) {
    event.preventDefault();

    const form = document.getElementById('kkProfilingUpdateForm');
    if (!form) {
        return false;
    }

    const submitBtn = document.getElementById('kkpSubmitBtn');
    const submitText = document.getElementById('kkpSubmitText');

    function setSubmitting(active, label) {
        if (submitBtn) {
            submitBtn.disabled = active;
            submitBtn.classList.toggle('is-submitting', active);
        }
        if (submitText && label) {
            submitText.textContent = label;
        }
    }

    function resetSubmit() {
        setSubmitting(false, 'Update KK Profiling');
    }

    setSubmitting(true, 'Checking entries...');

    // Keep locked email filled so batch validation never falsely flags it.
    const lockedEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || '').trim().toLowerCase();
    const emailInput = document.getElementById('kkpEmail');
    if (emailInput && lockedEmail) {
        emailInput.value = lockedEmail;
        emailInput.readOnly = true;
        emailInput.classList.add('kkp-readonly');
    }

    const valid = typeof window.validateKkProfilingForm === 'function'
        ? await window.validateKkProfilingForm({ skipEmailExistenceCheck: true })
        : true;

    if (!valid) {
        resetSubmit();
        const firstErr = form.querySelector('.kkp-field-error, .kkp-demo-block-error, #kkpSignatureStatus.is-invalid');
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        // Recompute mobile scale after batch errors expand the form height.
        window.dispatchEvent(new Event('resize'));
        return false;
    }

    setSubmitting(true, 'Updating KK Profiling...');

    const formData = new FormData(form);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            resetSubmit();
            if (data.errors && typeof data.errors === 'object') {
                Object.entries(data.errors).forEach(([field, messages]) => {
                    const message = Array.isArray(messages) ? messages[0] : messages;
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input && typeof window.showFieldError === 'function') {
                        window.showFieldError(input, message);
                    }
                });
            }
            alert(data.message || 'Unable to update KK Profiling. Please check your entries.');
            return false;
        }

        setSubmitting(true, 'Update complete...');

        const redirectUrl = data.redirect || window.__KK_PROFILING_UPDATE_REDIRECT || '/dashboard';
        const year = data.profiling_year || window.__KK_PROFILING_TARGET_YEAR || '';
        const title = data.title || 'Congratulations!';
        const message = data.message
            || (year
                ? "You've successfully updated your KK Profiling for " + year + '.'
                : "You've successfully updated your KK Profiling.");

        const modal = document.getElementById('kkpuSuccessModal');
        const titleEl = document.getElementById('kkpuSuccessTitle');
        const messageEl = document.getElementById('kkpuSuccessMessage');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (messageEl) {
            messageEl.textContent = message;
        }

        function goDashboard() {
            window.location.href = redirectUrl;
        }

        if (modal) {
            resetSubmit();
            modal.hidden = false;
            document.body.classList.add('kkpu-success-open');
            document.getElementById('kkpuSuccessOkBtn')?.focus();

            const closeBtns = [
                document.getElementById('kkpuSuccessOkBtn'),
                document.getElementById('kkpuSuccessCloseBtn'),
                document.getElementById('kkpuSuccessBackdrop'),
            ];
            closeBtns.forEach((btn) => {
                btn?.addEventListener('click', goDashboard, { once: true });
            });
            document.addEventListener('keydown', function onEsc(event) {
                if (event.key === 'Escape') {
                    document.removeEventListener('keydown', onEsc);
                    goDashboard();
                }
            });
            return false;
        }

        window.location.href = redirectUrl;
        return false;
    } catch (err) {
        resetSubmit();
        alert('Unable to update KK Profiling. Please check your connection and try again.');
        return false;
    }
};
