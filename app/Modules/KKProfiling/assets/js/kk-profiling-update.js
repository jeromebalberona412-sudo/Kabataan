/**
 * KK Profiling yearly update page
 */
(function () {
    'use strict';

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

    function populateUpdateForm(data) {
        if (!data || typeof data !== 'object') {
            return;
        }

        Object.entries(data).forEach(([key, value]) => {
            if (key === 'suffix' || key === 'email') {
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

        if (data.age && typeof window.kkpSyncYouthAgeGroupFromAge === 'function') {
            window.kkpSyncYouthAgeGroupFromAge(data.age);
        }

        const emailInput = document.getElementById('kkpEmail');
        const lockedEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || data.email || '').trim().toLowerCase();
        if (emailInput && lockedEmail) {
            emailInput.value = lockedEmail;
            emailInput.readOnly = true;
            emailInput.classList.add('kkp-readonly');
            emailInput.dataset.originalEmail = lockedEmail;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        populateUpdateForm(window.__KK_PROFILING_FORM_DATA || {});
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

    const valid = typeof window.validateKkProfilingForm === 'function'
        ? await window.validateKkProfilingForm({ skipEmailExistenceCheck: true })
        : true;

    if (!valid) {
        resetSubmit();
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

        setSubmitting(true, 'Update complete. Redirecting...');
        window.location.href = data.redirect || window.__KK_PROFILING_UPDATE_REDIRECT || '/dashboard';
        return false;
    } catch (err) {
        resetSubmit();
        alert('Unable to update KK Profiling. Please check your connection and try again.');
        return false;
    }
};
