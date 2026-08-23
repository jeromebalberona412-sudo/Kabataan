/**
 * KK Profiling Form JavaScript
 * Navigation, age auto-fill, alert dismiss, and e-signature pad
 */

const VALID_ROMAN_SUFFIXES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

function isValidSuffixText(value) {
    if (!value) return false;
    if (value.length > 5) return false;
    return VALID_ROMAN_SUFFIXES.includes(value.toUpperCase()) || /^[A-Za-z.]+$/.test(value);
}

function kkpHasAnySpace(value) {
    return /\s/.test(value || '');
}

function kkpValidateLastName(value, touched) {
    const v = (value || '').trim();
    if (!v) {
        return touched ? 'Last Name is required.' : null;
    }
    if (v.length < 3) {
        return 'Minimum 3 characters required.';
    }
    if (v.length > 50) {
        return '50 maximum characters only.';
    }
    if (kkpHasAnySpace(v) || !/^[A-Za-z.\-]+$/.test(v)) {
        return 'Letters only, no spaces.';
    }
    return null;
}

function kkpValidateFirstName(value, touched) {
    const v = (value || '').trim();
    if (!v) {
        return touched ? 'First Name is required.' : null;
    }
    if (v.length < 3) {
        return 'Minimum 3 characters required.';
    }
    if (v.length > 50) {
        return '50 maximum characters only.';
    }
    if (!/^[A-Za-z.\-\s]+$/.test(v)) {
        return 'Letters only, no leading spaces.';
    }
    return null;
}

const KKP_NAME_MAX_CHARS = 50;
const KKP_NAME_MAX_MSG = '50 maximum characters only.';

let kkpNameMeasureEl = null;

function kkpGetNameMeasureEl() {
    if (!kkpNameMeasureEl) {
        kkpNameMeasureEl = document.createElement('span');
        kkpNameMeasureEl.setAttribute('aria-hidden', 'true');
        kkpNameMeasureEl.style.cssText = 'position:absolute;left:-9999px;top:-9999px;visibility:hidden;white-space:nowrap;pointer-events:none;';
        document.body.appendChild(kkpNameMeasureEl);
    }

    return kkpNameMeasureEl;
}

function kkpSyncNameMaxIndicator(el) {
    const col = el?.closest('.kkp-name-col');
    if (!col) {
        return;
    }

    col.querySelectorAll('.kkp-name-max-hint').forEach((node) => node.remove());

    if ((el.value || '').length >= KKP_NAME_MAX_CHARS) {
        const hint = document.createElement('span');
        hint.className = 'kkp-field-hint kkp-name-max-hint';
        hint.textContent = KKP_NAME_MAX_MSG;
        col.appendChild(hint);
    }
}

function kkpFitInputTextToWidth(el, options = {}) {
    if (!el) {
        return;
    }

    const baseSize = options.baseSize ?? 12;
    const minSize = options.minSize ?? 8;
    const pad = options.pad ?? 6;
    const uppercase = options.uppercase ?? true;
    const maxWidth = Math.max(el.clientWidth - pad, 40);

    el.style.fontSize = baseSize + 'px';
    el.style.letterSpacing = '';
    el.style.textAlign = 'center';
    el.scrollLeft = 0;

    if (!el.value) {
        return;
    }

    const len = el.value.length;
    let size = baseSize;
    const tiers = options.tiers || [
        [32, 10],
        [24, 10.5],
        [18, 11],
        [14, 11.5],
    ];

    for (const [threshold, tierSize] of tiers) {
        if (len > threshold) {
            size = tierSize;
            break;
        }
    }

    const measure = kkpGetNameMeasureEl();
    const style = window.getComputedStyle(el);
    measure.style.fontFamily = style.fontFamily;
    measure.style.fontWeight = style.fontWeight;
    measure.style.textTransform = uppercase ? 'uppercase' : 'none';
    measure.textContent = el.value;
    measure.style.fontSize = size + 'px';
    measure.style.letterSpacing = '0px';

    while (measure.offsetWidth > maxWidth && size > minSize) {
        size -= 0.25;
        measure.style.fontSize = size + 'px';
    }

    let tracking = 0;
    while (measure.offsetWidth > maxWidth && tracking > -1) {
        tracking -= 0.05;
        measure.style.letterSpacing = tracking + 'px';
    }

    el.style.fontSize = size + 'px';
    el.style.letterSpacing = tracking < 0 ? tracking + 'px' : '';
}

function kkpFitNameInputFont(el) {
    kkpFitInputTextToWidth(el);
}

function kkpFitSignatureNameFont(el) {
    kkpFitInputTextToWidth(el, {
        minSize: 7,
        uppercase: false,
        tiers: [
            [120, 7.5],
            [90, 8],
            [70, 8.5],
            [50, 9],
            [35, 10],
            [24, 10.5],
            [18, 11],
            [14, 11.5],
        ],
    });
}

function kkpValidateMiddleName(value, touched) {
    const v = (value || '').trim();
    if (!v) {
        return null;
    }
    if (v.length < 3) {
        return touched ? 'Minimum 3 characters required.' : null;
    }
    if (v.length > 50) {
        return '50 maximum characters only.';
    }
    if (kkpHasAnySpace(v) || !/^[A-Za-z.\-]+$/.test(v)) {
        return 'Letters only, no spaces.';
    }
    return null;
}

function kkpFitEmailInputFont(el) {
    if (!el) return;
    kkpFitInputTextToWidth(el, {
        baseSize: 11,
        minSize: 8.5,
        uppercase: false,
        pad: 4,
        tiers: [
            [28, 9],
            [22, 9.5],
            [16, 10],
            [12, 10.5],
        ],
    });
    el.style.textAlign = 'left';
}

function kkpValidateEmail(value, touched) {
    const v = (value || '').trim().toLowerCase();
    if (!v) {
        return null;
    }
    if (kkpHasAnySpace(v)) {
        return 'Email must not contain spaces.';
    }
    if (v.length > 254) {
        return 'Email must not exceed 254 characters.';
    }
    const match = v.match(/^([^@]+)@gmail\.com$/i);
    if (!match) {
        return 'Use valid @gmail.com only.';
    }
    const localPart = match[1];
    if (localPart.length > 64) {
        return 'Email username must not exceed 64 characters.';
    }
    if (localPart.length < 6 || localPart.length > 30) {
        return 'Sorry, your username must be between 6 and 30 characters long.';
    }
    return null;
}

function kkpValidateFacebook(value, touched) {
    const raw = value || '';
    const v = raw.trim();

    if (raw.length > 50) {
        return 'Maximum 50 characters allowed.';
    }

    if (!v) {
        if (kkpIsGroupChatFilled() && touched) {
            return 'FB Account is required when you answer the group chat question.';
        }
        return null;
    }
    if (v.length < 3) {
        return touched ? 'Minimum 3 characters required.' : null;
    }
    if (v.length > 50) {
        return 'Maximum 50 characters allowed.';
    }
    try {
        const parsed = new URL(v);
        if (!/^https?:$/i.test(parsed.protocol)) {
            return 'Please enter a valid Facebook profile link.';
        }
    } catch {
        return 'Please enter a valid Facebook profile link.';
    }
    if (!/^https?:\/\/(www\.|m\.)?(facebook\.com|fb\.com)\//i.test(v)) {
        return 'Link must be a Facebook profile URL (e.g. https://www.facebook.com/yourprofile).';
    }
    return null;
}

function kkpIsFacebookFilled() {
    const el = document.getElementById('kkpFacebook');
    return Boolean((el?.value || '').trim());
}

function kkpIsGroupChatFilled() {
    const hidden = document.getElementById('kkpGroupChat');
    return Boolean((hidden?.value || '').trim());
}

function kkpValidatePurok(value, touched) {
    const v = (value || '').trim();
    if (!v) {
        return touched ? 'Purok/Sitio/Zone is required.' : null;
    }
    return null;
}

function kkpPurokField() {
    return document.querySelector('select[name="purok_zone"]') || document.querySelector('input[name="purok_zone"]');
}

function kkpValidateContact(value, touched) {
    const v = (value || '').trim();
    if (!v || v === '09') {
        return touched ? 'Contact # is required.' : null;
    }
    if (!/^09\d{9}$/.test(v)) {
        return 'Use 11 digits only. Format: 09XXXXXXXXX.';
    }
    return null;
}

(function () {
    'use strict';

    function getFieldErrorHost(el) {
        if (!el) {
            return null;
        }

        const fbWrap = el.closest('.kkp-footer-fb-field');
        if (fbWrap) {
            return fbWrap;
        }

        const inlinePair = el.closest('.kkp-inline-pair');
        if (inlinePair) {
            return inlinePair;
        }

        const nameCol = el.closest('.kkp-name-col');
        if (nameCol) {
            return nameCol;
        }

        return el.parentNode;
    }

    function showFieldError(el, msg) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        host.querySelectorAll('.kkp-name-max-hint').forEach((node) => node.remove());
        el.classList.add('kkp-input-err');
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
        err.textContent = msg;
        host.appendChild(err);
    }

    window.showFieldError = showFieldError;

    function clearFieldError(el) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        el.classList.remove('kkp-input-err');
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        if (el.closest('.kkp-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    // ── Navigation Drawer ──
    const navHamburger = document.getElementById('navHamburger');
    const navDrawer = document.getElementById('navDrawer');
    if (navHamburger && navDrawer) {
        navHamburger.addEventListener('click', function (e) {
            e.stopPropagation();
            navDrawer.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!navHamburger.contains(e.target) && !navDrawer.contains(e.target)) {
                navDrawer.classList.remove('open');
            }
        });
        navDrawer.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    // ── Login buttons ──
    const navLoginBtn = document.getElementById('navLoginBtn');
    const navDrawerLoginBtn = document.getElementById('navDrawerLoginBtn');
    if (navLoginBtn) navLoginBtn.addEventListener('click', () => window.location.href = '/youth/login');
    if (navDrawerLoginBtn) navDrawerLoginBtn.addEventListener('click', () => window.location.href = '/youth/login');

    // ── Youth Age Group auto-select from age ──
    function youthAgeGroupValueForAge(age) {
        const value = parseInt(age, 10);

        if (Number.isNaN(value)) {
            return '';
        }

        if (value >= 15 && value <= 17) {
            return 'Child Youth (15-17 yrs old)';
        }

        if (value >= 18 && value <= 24) {
            return 'Core Youth (18-24 yrs old)';
        }

        if (value >= 25 && value <= 30) {
            return 'Young Adult (15-30 yrs old)';
        }

        return '';
    }

    function syncYouthAgeGroupFromAge(age) {
        const groupValue = youthAgeGroupValueForAge(age);

        if (!groupValue) {
            return;
        }

        const checkboxes = document.querySelectorAll('input[name="youth_age_groupChk"]');
        let matched = false;

        checkboxes.forEach((checkbox) => {
            const isMatch = checkbox.value === groupValue;
            checkbox.checked = isMatch;

            if (isMatch) {
                matched = true;
            }
        });

        const hidden = document.getElementById('kkpYouthAgeGroup');

        if (hidden && matched) {
            hidden.value = groupValue;
            clearDemoBlockError('kkpYouthAgeGroup');
        }
    }

    function initYouthAgeGroupReadonly() {
        document.querySelectorAll('input[name="youth_age_groupChk"]').forEach((checkbox) => {
            checkbox.disabled = true;
            checkbox.tabIndex = -1;
        });
    }

    initYouthAgeGroupReadonly();

    window.kkpSyncYouthAgeGroupFromAge = syncYouthAgeGroupFromAge;

    // ── Age + birthday sync (15–30 only) ──
    const form = document.getElementById('kkProfilingForm') || document.getElementById('kkProfilingUpdateForm');
    const birthdayInput = form && form.querySelector('input[name="birthday"]');
    const ageInput = form && form.querySelector('[name="age"]');
    if (birthdayInput && ageInput) {
        const today = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const toDateInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        const maxBirthday = new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
        const minBirthday = new Date(today.getFullYear() - 30, today.getMonth(), today.getDate());

        function calcAgeFromDate(dateStr) {
            if (!dateStr) {
                return null;
            }

            const bday = new Date(`${dateStr}T00:00:00`);
            if (Number.isNaN(bday.getTime())) {
                return null;
            }

            let age = today.getFullYear() - bday.getFullYear();
            const monthDiff = today.getMonth() - bday.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < bday.getDate())) {
                age--;
            }

            return age;
        }

        function setFullBirthdayRange() {
            birthdayInput.max = toDateInput(maxBirthday);
            birthdayInput.min = toDateInput(minBirthday);
        }

        function defaultBirthdayForAge(age) {
            const maxBday = new Date(today.getFullYear() - age, today.getMonth(), today.getDate());
            return toDateInput(maxBday);
        }

        setFullBirthdayRange();

        birthdayInput.addEventListener('change', function () {
            const age = calcAgeFromDate(this.value);

            if (age !== null && age >= 15 && age <= 30) {
                ageInput.value = String(age);
                syncYouthAgeGroupFromAge(age);
                setFullBirthdayRange();
                clearFieldError(ageInput);
                clearFieldError(this);
                return;
            }

            this.value = '';
            ageInput.value = '';
            showFieldError(this, 'Birthday must result in age 15 to 30 only.');
        });

        birthdayInput.addEventListener('focus', function () {
            const selectedAge = parseInt(ageInput.value, 10);

            if (!this.value && !Number.isNaN(selectedAge) && selectedAge >= 15 && selectedAge <= 30) {
                this.value = defaultBirthdayForAge(selectedAge);
            }
        });

        ageInput.addEventListener('change', function () {
            const value = parseInt(this.value, 10);

            if (Number.isNaN(value) || value < 15 || value > 30) {
                this.value = '';
                birthdayInput.value = '';
                setFullBirthdayRange();
                showFieldError(this, 'Age must be 15 to 30 only.');
                return;
            }

            clearFieldError(this);
            syncYouthAgeGroupFromAge(value);
            setFullBirthdayRange();
            birthdayInput.value = defaultBirthdayForAge(value);
            clearFieldError(birthdayInput);
        });
    }

    const ageSelectCompact = document.getElementById('kkpAge');
    if (ageSelectCompact && ageSelectCompact.classList.contains('kkp-age-select-compact')) {
        const collapseAgeSelect = () => {
            ageSelectCompact.size = 1;
            ageSelectCompact.classList.remove('is-expanded');
        };

        ageSelectCompact.addEventListener('mousedown', function () {
            if (this.size === 1) {
                this.size = 6;
                this.classList.add('is-expanded');
            }
        });

        ageSelectCompact.addEventListener('blur', collapseAgeSelect);
        ageSelectCompact.addEventListener('change', collapseAgeSelect);

        document.addEventListener('click', (event) => {
            if (!ageSelectCompact.contains(event.target)) {
                collapseAgeSelect();
            }
        });
    }

    // ── Name fields: auto-uppercase (capslock) ──
    ['kkpFirstName', 'kkpMiddleName'].forEach((id) => {
        const nameInput = document.getElementById(id);
        if (!nameInput) return;

        nameInput.addEventListener('input', function () {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(start, end);
        });

        nameInput.addEventListener('blur', function () {
            this.value = this.value.trim().replace(/\s{2,}/g, ' ').toUpperCase();
        });
    });

    const lastNameInput = document.getElementById('kkpLastName');
    if (lastNameInput) {
        lastNameInput.addEventListener('input', function () {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.toUpperCase().replace(/\s/g, '');
            this.setSelectionRange(start, end);
        });

        lastNameInput.addEventListener('blur', function () {
            this.value = this.value.trim().toUpperCase();
        });
    }

    // ── Email existence check (backend) — disabled in wizard mode (checked at Step 4 only) ──
    const emailInput = document.querySelector('input[name="email"]');
    const isWizardForm = document.getElementById('kkProfilingForm')?.dataset?.wizardMode === '1';
    let emailCheckTimer = null;

    async function checkEmailExists(value) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const originalEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || '').trim().toLowerCase();
        const payload = { email: value };
        if (originalEmail) {
            payload.current_email = originalEmail;
        }
        const response = await fetch('/api/kkprofiling/check-email-exists', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        return response.json();
    }

    function clearDemoBlockError(hiddenId) {
        const el = document.getElementById(hiddenId);
        const block = el?.closest('.kkp-demo-block');
        block?.querySelectorAll('.kkp-demo-block-error').forEach((node) => node.remove());
    }

    function clearPersonalLeftError() {
        document.querySelector('.kkp-personal-left')?.querySelectorAll('.kkp-section-error').forEach((node) => node.remove());
    }

    function clearSignatureError() {
        document.querySelector('.kkp-sig-section-left')?.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
    }

    window.clearSignatureError = clearSignatureError;

    function bindRealtimeField(el, validateFn) {
        if (!el) {
            return;
        }

        let touched = false;

        const runValidation = () => {
            const message = validateFn(el.value, touched);
            if (message) {
                showFieldError(el, message);
            } else {
                clearFieldError(el);
            }
        };

        el.addEventListener('input', () => {
            if ((el.value || '').length > 0) {
                touched = true;
            }
            runValidation();
        });

        el.addEventListener('blur', () => {
            touched = true;
            runValidation();
        });
    }

    bindRealtimeField(lastNameInput, kkpValidateLastName);

    // ── Contact number formatter: 09 + 9 digits only (11 chars total) ──
    const contactInput = document.getElementById('kkpContactNumber');
    if (contactInput) {
        if (!contactInput.value) contactInput.value = '09';
        contactInput.addEventListener('focus', function () {
            if (!this.value) this.value = '09';
        });
        contactInput.addEventListener('input', function () {
            let value = (this.value || '').replace(/\D/g, '');
            if (!value.startsWith('09')) {
                value = value.startsWith('9') ? `0${value}` : `09${value.replace(/^0+/, '')}`;
            }
            this.value = value.slice(0, 11);
            if (/^09\d{9}$/.test(this.value.trim())) {
                clearFieldError(this);
            }
        });
    }

    // ── Auto-fill signature name from name fields ──
    // When any name field changes, update the signature name input automatically
    function updateSignatureName() {
        const last = (document.getElementById('kkpLastName') || {}).value || '';
        const first = (document.getElementById('kkpFirstName') || {}).value || '';
        const middle = (document.getElementById('kkpMiddleName') || {}).value || '';
        const suffixSelect = document.getElementById('kkpSuffix');
        const customSuffix = document.getElementById('kkpCustomSuffix');
        const rawSuffix = (suffixSelect || {}).value || '';
        const suffix = rawSuffix === 'Others'
            ? ((customSuffix || {}).value || '')
            : (rawSuffix === 'None' ? '' : rawSuffix);
        const sigNameInput = document.getElementById('kkpSignatureName');
        const triggerBtn = document.getElementById('kkpSignatureTrigger');
        const sigInput = document.getElementById('kkpSignatureData');

        if (!sigNameInput) return;

        const parts = [first, middle, last, suffix].filter(Boolean);
        const fullName = parts.join(' ');
        sigNameInput.value = fullName;
        kkpFitSignatureNameFont(sigNameInput);

        // Enable Sign button only when name is filled and no signature yet
        if (triggerBtn) {
            const hasSig = !!(sigInput && sigInput.value);
            const canSign = fullName.trim().length > 0 && !hasSig;
            triggerBtn.disabled = !canSign;
            triggerBtn.setAttribute('aria-disabled', canSign ? 'false' : 'true');
        }
    }

    ['kkpLastName', 'kkpFirstName', 'kkpMiddleName', 'kkpSuffix'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateSignatureName);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', updateSignatureName);
    });
    const customSuffixInput = document.getElementById('kkpCustomSuffix');
    if (customSuffixInput) customSuffixInput.addEventListener('input', updateSignatureName);
    updateSignatureName();
    window.kkpRefreshSignatureName = updateSignatureName;

    // ── Suffix dropdown dynamic behavior ──
    const suffixSelect = document.getElementById('kkpSuffix');
    const customSuffixWrap = document.getElementById('kkpCustomSuffixWrap');
    if (suffixSelect && customSuffixWrap && customSuffixInput) {
        const toggleCustomSuffix = function () {
            const isOthers = suffixSelect.value === 'Others';
            customSuffixWrap.classList.toggle('show', isOthers);
            customSuffixInput.required = isOthers;
            if (!isOthers) {
                customSuffixInput.value = '';
                customSuffixInput.classList.remove('kkp-input-err');
                const err = customSuffixWrap.querySelector('.kkp-field-error');
                if (err) err.remove();
            }
            updateSignatureName();
        };

        customSuffixInput.addEventListener('input', function () {
            let value = (this.value || '').replace(/[^A-Za-z.\s]/g, '');
            value = value.replace(/\s{2,}/g, ' ').trimStart();
            this.value = value.slice(0, 5);
        });
        customSuffixInput.addEventListener('blur', function () {
            this.value = (this.value || '').trim();
        });

        suffixSelect.addEventListener('change', toggleCustomSuffix);
        toggleCustomSuffix();
    }

    // ── Name input restrictions ──
    const firstNameEl = document.getElementById('kkpFirstName');
    const middleNameEl = document.getElementById('kkpMiddleName');
    const nameFitInputs = [lastNameInput, firstNameEl, middleNameEl].filter(Boolean);

    const runNameFit = (el) => {
        kkpFitNameInputFont(el);
        kkpSyncNameMaxIndicator(el);
    };

    if (firstNameEl) {
        firstNameEl.addEventListener('input', function () {
            this.value = this.value
                .replace(/^\s+/, '')
                .replace(/[^A-Za-z.\-\s]/g, '')
                .replace(/\s{2,}/g, ' ')
                .slice(0, 50);
            runNameFit(this);
        });
    }

    if (lastNameInput) {
        lastNameInput.addEventListener('input', function () {
            this.value = this.value
                .replace(/\s/g, '')
                .replace(/[^A-Za-z.\-]/g, '')
                .slice(0, 50);
            runNameFit(this);
        });
    }

    if (middleNameEl) {
        middleNameEl.addEventListener('input', function () {
            this.value = this.value
                .replace(/\s/g, '')
                .replace(/[^A-Za-z.\-]/g, '')
                .slice(0, 50);
            runNameFit(this);
        });
    }

    nameFitInputs.forEach((el) => {
        runNameFit(el);
    });

    window.addEventListener('resize', () => {
        nameFitInputs.forEach(runNameFit);
        const sigNameInput = document.getElementById('kkpSignatureName');
        if (sigNameInput) {
            kkpFitSignatureNameFont(sigNameInput);
        }
        const emailEl = document.getElementById('kkpEmail');
        if (emailEl) {
            kkpFitEmailInputFont(emailEl);
        }
    });

    const facebookInput = document.getElementById('kkpFacebook');
    const KKP_FB_MAX_LEN = 50;

    function validateGroupChat(touched) {
        if (!kkpIsFacebookFilled()) {
            return null;
        }
        const hidden = document.getElementById('kkpGroupChat');
        if (!hidden || !(hidden.value || '').trim()) {
            return touched ? 'Please select Yes or No.' : null;
        }
        return null;
    }

    function runGroupChatValidation(touched) {
        const chat = document.getElementById('kkpFooterChat') || document.querySelector('.kkp-footer-chat');
        if (!chat) {
            return;
        }

        chat.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());

        const message = validateGroupChat(touched);
        if (message) {
            footerChatError(message);
        }
    }

    function updateFooterFieldRequirements() {
        const fbFilled = kkpIsFacebookFilled();
        const chatFilled = kkpIsGroupChatFilled();
        const groupChatRequired = document.getElementById('kkpGroupChatRequired');
        const fbRequired = document.getElementById('kkpFacebookRequired');
        const fbOptional = document.getElementById('kkpFacebookOptional');

        if (groupChatRequired) {
            groupChatRequired.hidden = !fbFilled;
        }
        if (fbRequired) {
            fbRequired.hidden = !chatFilled;
        }
        if (fbOptional) {
            fbOptional.hidden = chatFilled;
        }

        if (facebookInput) {
            const fbTouched = chatFilled || fbFilled;
            const fbMsg = kkpValidateFacebook(facebookInput.value, fbTouched);
            if (fbMsg) {
                showFieldError(facebookInput, fbMsg);
            } else {
                clearFieldError(facebookInput);
            }
        }

        runGroupChatValidation(fbFilled);
    }

    if (facebookInput) {
        facebookInput.addEventListener('input', function () {
            const cleaned = this.value.replace(/\s/g, '');
            const exceeded = cleaned.length > KKP_FB_MAX_LEN;
            this.value = cleaned.slice(0, KKP_FB_MAX_LEN);

            const message = exceeded
                ? 'Maximum 50 characters allowed.'
                : kkpValidateFacebook(this.value, true);

            if (message) {
                showFieldError(this, message);
            } else {
                clearFieldError(this);
            }

            updateFooterFieldRequirements();
        });

        facebookInput.addEventListener('blur', function () {
            this.value = this.value.trim().slice(0, KKP_FB_MAX_LEN);
            const message = kkpValidateFacebook(this.value, true);
            if (message) {
                showFieldError(this, message);
            } else {
                clearFieldError(this);
            }
            updateFooterFieldRequirements();
        });
    }

    document.querySelectorAll('input[name="group_chatChk"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateFooterFieldRequirements();
        });
    });

    (function sanitizeGroupChatAnswer() {
        const hidden = document.getElementById('kkpGroupChat');
        const answer = hidden ? String(hidden.value || '').trim() : '';
        const valid = answer === 'Yes' || answer === 'No';
        if (hidden && !valid) {
            hidden.value = '';
        }
        document.querySelectorAll('input[name="group_chatChk"]').forEach((checkbox) => {
            checkbox.checked = valid && checkbox.value === answer;
        });
    }());

    updateFooterFieldRequirements();

    bindRealtimeField(firstNameEl, kkpValidateFirstName);
    bindRealtimeField(middleNameEl, kkpValidateMiddleName);
    bindRealtimeField(kkpPurokField(), kkpValidatePurok);
    bindRealtimeField(contactInput, kkpValidateContact);

    if (emailInput) {
        let emailTouched = false;

        const normalizeEmailValue = () => {
            const start = emailInput.selectionStart;
            const end = emailInput.selectionEnd;
            emailInput.value = (emailInput.value || '').toLowerCase();
            if (start !== null && end !== null) {
                emailInput.setSelectionRange(start, end);
            }
        };

        const runEmailValidation = async (checkExists) => {
            const message = kkpValidateEmail(emailInput.value, emailTouched);
            if (message) {
                showFieldError(emailInput, message);
                return;
            }

            clearFieldError(emailInput);

            if (!checkExists || isWizardForm || !emailTouched) {
                return;
            }

            const value = (emailInput.value || '').trim();
            const originalEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || emailInput.dataset.originalEmail || '').trim().toLowerCase();
            if (originalEmail && value.toLowerCase() === originalEmail) {
                delete emailInput.dataset.emailExists;
                return;
            }

            clearTimeout(emailCheckTimer);
            emailCheckTimer = setTimeout(async () => {
                try {
                    const result = await checkEmailExists(value);
                    if (result.exists) {
                        emailInput.dataset.emailExists = 'true';
                        showFieldError(emailInput, result.message || 'This email already exists. Please use a different email address.');
                    } else {
                        delete emailInput.dataset.emailExists;
                    }
                } catch (err) {
                    // Non-blocking
                }
            }, 300);
        };

        emailInput.addEventListener('input', () => {
            normalizeEmailValue();
            kkpFitEmailInputFont(emailInput);
            if ((emailInput.value || '').length > 0) {
                emailTouched = true;
            }
            delete emailInput.dataset.emailExists;
            runEmailValidation(false);
        });

        emailInput.addEventListener('blur', () => {
            normalizeEmailValue();
            emailInput.value = (emailInput.value || '').trim().toLowerCase();
            kkpFitEmailInputFont(emailInput);
            emailTouched = true;
            runEmailValidation(true);
        });

        kkpFitEmailInputFont(emailInput);
    }

    // ── Single-check helper (like SK Officials kkfSingleCheck) ──
    // Allows only one checkbox checked per group
    window.kkpSingleCheck = function (checkbox, hiddenId) {
        const group = document.querySelectorAll('input[name="' + checkbox.name + '"]');
        group.forEach(function (cb) {
            if (cb !== checkbox) cb.checked = false;
        });
        const hidden = document.getElementById(hiddenId);
        if (hidden) {
            hidden.value = checkbox.checked ? checkbox.value : '';
            if (hidden.value) {
                clearDemoBlockError(hiddenId);
                if (hiddenId === 'kkpSex') {
                    clearPersonalLeftError();
                }
            }
        }

        // ── When Registered SK Voter = No, auto-set "Did you vote last SK?" to No ──
        if (hiddenId === 'kkpSkVoter') {
            const skVoterVal = hidden ? hidden.value : '';
            const votedHidden = document.getElementById('kkpSkVoted');
            const votedChks = document.querySelectorAll('input[name="sk_votedChk"]');

            if (skVoterVal === 'No') {
                // Force sk_voted = No, lock Yes checkbox
                votedChks.forEach(function (cb) {
                    if (cb.value === 'No') {
                        cb.checked = true;
                        cb.disabled = false;
                    } else {
                        cb.checked = false;
                        cb.disabled = true;
                    }
                });
                if (votedHidden) {
                    votedHidden.value = 'No';
                    clearDemoBlockError('kkpSkVoted');
                }
            } else {
                // Unlock voted checkboxes when SK Voter is Yes or cleared
                votedChks.forEach(function (cb) {
                    cb.disabled = false;
                });
            }
        }

        if (hiddenId === 'kkpGroupChat') {
            const chat = document.getElementById('kkpFooterChat') || document.querySelector('.kkp-footer-chat');
            chat?.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
            if (typeof updateFooterFieldRequirements === 'function') {
                updateFooterFieldRequirements();
            }
        }
    };

    function setAssemblyFollowupState(cell, enabled) {
        if (!cell) {
            return;
        }

        cell.classList.toggle('kkp-assembly-followup--inactive', !enabled);
        cell.classList.toggle('kkp-assembly-followup--active', enabled);

        cell.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            cb.disabled = !enabled;
            if (!enabled) {
                cb.checked = false;
            }
        });

        const hidden = cell.querySelector('input[type="hidden"]');
        if (hidden) {
            hidden.disabled = false;
            if (!enabled) {
                hidden.value = '';
            }
        }
    }

    function syncAssemblyFollowUp() {
        const assemblyVal = document.getElementById('kkpKkAssembly')?.value || '';
        const yesCell = document.getElementById('kkpAssemblyYesCell');
        const noCell = document.getElementById('kkpAssemblyNoCell');
        const arrowYes = document.querySelector('.kkp-assembly-arrow--yes');
        const arrowNo = document.querySelector('.kkp-assembly-arrow--no');
        const flowYes = document.querySelector('.kkp-assembly-flow-path--yes');
        const flowNo = document.querySelector('.kkp-assembly-flow-path--no');

        if (assemblyVal === 'Yes') {
            setAssemblyFollowupState(yesCell, true);
            setAssemblyFollowupState(noCell, false);
            arrowYes?.classList.add('kkp-assembly-arrow--on');
            arrowNo?.classList.remove('kkp-assembly-arrow--on');
            flowYes?.classList.add('kkp-assembly-flow-path--on');
            flowNo?.classList.remove('kkp-assembly-flow-path--on');
            return;
        }

        if (assemblyVal === 'No') {
            setAssemblyFollowupState(yesCell, false);
            setAssemblyFollowupState(noCell, true);
            arrowYes?.classList.remove('kkp-assembly-arrow--on');
            arrowNo?.classList.add('kkp-assembly-arrow--on');
            flowYes?.classList.remove('kkp-assembly-flow-path--on');
            flowNo?.classList.add('kkp-assembly-flow-path--on');
            return;
        }

        setAssemblyFollowupState(yesCell, false);
        setAssemblyFollowupState(noCell, false);
        arrowYes?.classList.remove('kkp-assembly-arrow--on');
        arrowNo?.classList.remove('kkp-assembly-arrow--on');
        flowYes?.classList.remove('kkp-assembly-flow-path--on');
        flowNo?.classList.remove('kkp-assembly-flow-path--on');
    }

    window.kkpHandleAssembly = function (checkbox) {
        if (!checkbox.checked) {
            return;
        }

        if (checkbox.value === 'Yes') {
            document.querySelectorAll('input[name="kk_reasonChk"]').forEach((cb) => {
                cb.checked = false;
            });
            const reasonHidden = document.getElementById('kkpKkReason');
            if (reasonHidden) {
                reasonHidden.value = '';
            }
        } else {
            document.querySelectorAll('input[name="kk_timesChk"]').forEach((cb) => {
                cb.checked = false;
            });
            const timesHidden = document.getElementById('kkpKkTimes');
            if (timesHidden) {
                timesHidden.value = '';
            }
        }

        syncAssemblyFollowUp();
    };

    window.syncAssemblyFollowUp = syncAssemblyFollowUp;

    const assemblyHidden = document.getElementById('kkpKkAssembly');
    if (assemblyHidden && !assemblyHidden.value) {
        const checkedAssembly = document.querySelector('input[name="kk_assemblyChk"]:checked');
        if (checkedAssembly) {
            assemblyHidden.value = checkedAssembly.value;
        }
    }

    syncAssemblyFollowUp();

    // ── Auto-dismiss success alert ──
    const successAlert = document.querySelector('.kkp-alert-success');
    if (successAlert) {
        setTimeout(function () {
            successAlert.style.transition = 'opacity 0.5s, transform 0.5s';
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-10px)';
            setTimeout(() => successAlert.remove(), 500);
        }, 5000);
    }

    console.log('KK Profiling form initialized');
})();

/* ═══════════════════════════════════════════════════════════════
   FORM SUBMISSION HANDLER - Validate then Submit Form
═══════════════════════════════════════════════════════════════ */
window.validateKkProfilingForm = async function (options = {}) {
    const skipEmailExistenceCheck = options.skipEmailExistenceCheck === true
        || document.getElementById('kkProfilingUpdateForm')?.dataset?.emailLocked === '1';

    // ── Clear previous errors ──
    document.querySelectorAll('.kkp-field-error').forEach(el => el.remove());
    document.querySelectorAll('.kkp-input-err').forEach(el => el.classList.remove('kkp-input-err'));

    const errors = [];

    function demoBlockError(hiddenId, message) {
        const el = document.getElementById(hiddenId);
        if (!el) {
            return;
        }

        const block = el.closest('.kkp-demo-block');
        if (!block) {
            return;
        }

        block.querySelectorAll('.kkp-demo-block-error').forEach((node) => node.remove());

        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-demo-block-error';
        err.textContent = message;
        block.appendChild(err);
    }

    function footerChatError(message) {
        const chat = document.querySelector('.kkp-footer-chat');
        if (!chat) {
            return;
        }

        chat.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());

        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-footer-chat-error';
        err.textContent = message;
        chat.appendChild(err);
    }

    function personalLeftError(message) {
        const left = document.querySelector('.kkp-personal-left');
        if (!left) {
            return;
        }

        left.querySelectorAll('.kkp-section-error').forEach((node) => node.remove());

        const err = document.createElement('span');
        err.className = 'kkp-field-error kkp-section-error';
        err.textContent = message;
        left.appendChild(err);
    }

    // Helper: show inline error below an element
    function fieldError(el, msg) {
        if (!el) return;
        el.classList.add('kkp-input-err');
        const host = el.closest('.kkp-inline-pair')
            || el.closest('.kkp-footer-fb-field')
            || el.closest('.kkp-name-col')
            || el.parentNode;
        if (!host) return;
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        host.querySelectorAll('.kkp-name-max-hint').forEach((node) => node.remove());
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
        err.textContent = msg;
        host.appendChild(err);
    }

    // Helper: get hidden input value (single-check groups)
    function hiddenVal(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function hasAnySpace(v) {
        return /\s/.test(v || '');
    }

    // ── 1. Last Name ──
    const lastName = document.querySelector('input[name="last_name"]');
    const lastNameMsg = kkpValidateLastName(lastName?.value, true);
    if (lastNameMsg) {
        errors.push(lastNameMsg);
        fieldError(lastName, lastNameMsg);
    }

    // ── 2. First Name ──
    const firstName = document.querySelector('input[name="first_name"]');
    const firstNameMsg = kkpValidateFirstName(firstName?.value, true);
    if (firstNameMsg) {
        errors.push(firstNameMsg);
        fieldError(firstName, firstNameMsg);
    }

    const middleName = document.querySelector('input[name="middle_name"]');
    const middleNameMsg = kkpValidateMiddleName(middleName?.value, true);
    if (middleNameMsg) {
        errors.push(middleNameMsg);
        fieldError(middleName, middleNameMsg);
    }

    // ── 3. Purok/Zone ──
    const purok = kkpPurokField();
    if (!purok || !purok.value.trim()) {
        errors.push('Purok/Sitio/Zone is required.');
        fieldError(purok, 'Purok/Sitio/Zone is required.');
    }

    // ── 3b. Suffix ──
    const suffix = document.getElementById('kkpSuffix');
    const customSuffix = document.getElementById('kkpCustomSuffix');
    const suffixValue = (suffix?.value || '').trim();
    if (!suffix || !suffixValue) {
        errors.push('Suffix is required.');
        fieldError(suffix, 'Please select a suffix.');
    } else if (suffixValue === 'None') {
        // "None" is a valid required selection.
    } else if (suffixValue === 'Others') {
        const raw = (customSuffix && customSuffix.value ? customSuffix.value : '').trim();
        if (!raw) {
            errors.push('Custom suffix is required.');
            fieldError(customSuffix, 'Please specify your suffix.');
        } else if (raw.length > 5) {
            errors.push('Suffix must not exceed 5 characters.');
            fieldError(customSuffix, 'Suffix must not exceed 5 characters.');
        } else if (!/^[A-Za-z.\s]+$/.test(raw) || !/[A-Za-z]/.test(raw)) {
            errors.push('Only text and valid Roman numeral suffixes are allowed.');
            fieldError(customSuffix, 'Only text and valid Roman numeral suffixes are allowed.');
        }
    } else if (!isValidSuffixText(suffixValue)) {
        errors.push('Only text and valid Roman numeral suffixes are allowed.');
        fieldError(suffix, 'Only text and valid Roman numeral suffixes are allowed.');
    }

    // ── 4. Sex ──
    if (!hiddenVal('kkpSex')) {
        errors.push('Sex Assigned by Birth is required.');
        personalLeftError('Please select Sex Assigned by Birth.');
    }

    // ── 5. Age ──
    const age = document.querySelector('[name="age"]');
    if (!age || !age.value.trim()) {
        errors.push('Age is required.');
        fieldError(age, 'Age is required.');
    } else if (+age.value < 15 || +age.value > 30) {
        errors.push('Age must be 15 to 30 only.');
        fieldError(age, 'Age must be 15 to 30 only.');
    }

    // ── 6. Birthday ──
    const birthday = document.querySelector('input[name="birthday"]');
    if (!birthday || !birthday.value.trim()) {
        errors.push('Birthday is required.');
        fieldError(birthday, 'Birthday is required.');
    } else {
        const bday = new Date(birthday.value + 'T00:00:00');
        const now = new Date();
        let derivedAge = now.getFullYear() - bday.getFullYear();
        const monthDiff = now.getMonth() - bday.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < bday.getDate())) derivedAge--;
        if (bday > now) {
            errors.push('Birthday cannot be in the future.');
            fieldError(birthday, 'Birthday cannot be in the future.');
        } else if (derivedAge < 15) {
            errors.push('Age must be at least 15 years old.');
            fieldError(birthday, 'Age must be at least 15 years old.');
        } else if (derivedAge > 30) {
            errors.push('Age must not exceed 30 years old.');
            fieldError(birthday, 'Age must not exceed 30 years old.');
        } else if (age && age.value.trim() && derivedAge !== parseInt(age.value, 10)) {
            errors.push('Birthday must match the selected age.');
            fieldError(birthday, 'Birthday must match the selected age.');
        }
    }

    // ── 7. Email ──
    const email = document.querySelector('input[name="email"]');
    if (email) {
        email.value = (email.value || '').trim().toLowerCase();
    }
    const emailMsg = kkpValidateEmail(email?.value, true);
    if (emailMsg) {
        errors.push(emailMsg);
        fieldError(email, emailMsg);
    } else {
        const originalEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || email?.dataset?.originalEmail || '').trim().toLowerCase();
        const emailUnchanged = originalEmail && (email?.value || '').trim().toLowerCase() === originalEmail;
        if (!skipEmailExistenceCheck && !emailUnchanged && email?.dataset.emailExists === 'true') {
            errors.push('This email already exists. Please use a different email address.');
            fieldError(email, 'This email already exists. Please use a different email address.');
        }
    }

    // ── 8. Contact # ──
    const contact = document.querySelector('input[name="contact_number"]');
    if (!contact || !contact.value.trim()) {
        errors.push('Contact # is required.');
        fieldError(contact, 'Contact # is required.');
    } else if (!/^09\d{9}$/.test(contact.value.trim())) {
        errors.push('Contact # must be 11 digits and start with 09.');
        fieldError(contact, 'Use 11 digits only. Format: 09XXXXXXXXX.');
    }

    // ── 9. Civil Status ──
    if (!hiddenVal('kkpCivilStatus')) {
        errors.push('Civil Status is required.');
        demoBlockError('kkpCivilStatus', 'Please select Civil Status.');
    }

    // ── 10. Youth Age Group ──
    if (!hiddenVal('kkpYouthAgeGroup')) {
        errors.push('Youth Age Group is required.');
        demoBlockError('kkpYouthAgeGroup', 'Please select Youth Age Group.');
    }

    // ── 11. Educational Background ──
    if (!hiddenVal('kkpEducation')) {
        errors.push('Educational Background is required.');
        demoBlockError('kkpEducation', 'Please select Educational Background.');
    }

    // ── 12. Youth Classification ──
    if (!hiddenVal('kkpYouthClass')) {
        errors.push('Youth Classification is required.');
        demoBlockError('kkpYouthClass', 'Please select Youth Classification.');
    }

    // ── 13. Work Status ──
    if (!hiddenVal('kkpWorkStatus')) {
        errors.push('Work Status is required.');
        demoBlockError('kkpWorkStatus', 'Please select Work Status.');
    }

    // ── 14. Registered SK Voter ──
    if (!hiddenVal('kkpSkVoter')) {
        errors.push('Registered SK Voter is required.');
        demoBlockError('kkpSkVoter', 'Please select Yes or No.');
    }

    // ── 15. Did you vote last SK ──
    if (!hiddenVal('kkpSkVoted')) {
        errors.push('Did you vote last SK is required.');
        demoBlockError('kkpSkVoted', 'Please select Yes or No.');
    }

    // ── 16. Registered National Voter ──
    if (!hiddenVal('kkpNationalVoter')) {
        errors.push('Registered National Voter is required.');
        demoBlockError('kkpNationalVoter', 'Please select Yes or No.');
    }

    // ── 17. KK Assembly (conditional follow-up) ──
    const kkAssemblyVal = hiddenVal('kkpKkAssembly');
    if (!kkAssemblyVal) {
        errors.push('Have you attended a KK Assembly is required.');
        demoBlockError('kkpKkAssembly', 'Please select Yes or No.');
    } else if (kkAssemblyVal === 'Yes' && !hiddenVal('kkpKkTimes')) {
        errors.push('KK Assembly attendance count is required.');
        demoBlockError('kkpKkTimes', 'Please select number of times attended.');
    } else if (kkAssemblyVal === 'No' && !hiddenVal('kkpKkReason')) {
        errors.push('KK Assembly reason is required.');
        demoBlockError('kkpKkReason', 'Please select a reason.');
    }

    // ── 18. FB Account ──
    const facebook = document.querySelector('input[name="facebook_profile_url"]');
    const facebookMsg = kkpValidateFacebook(facebook?.value, true);
    if (facebookMsg) {
        errors.push(facebookMsg);
        fieldError(facebook, facebookMsg);
    }

    // ── 19. Willing to join group chat (required only when FB profile link is provided) ──
    if (kkpIsFacebookFilled() && !hiddenVal('kkpGroupChat')) {
        errors.push('Willing to join the group chat is required when FB Account is provided.');
        footerChatError('Please select Yes or No.');
    }

    // ── 20. Signature ──
    const sigData = document.getElementById('kkpSignatureData');
    if (!sigData || !sigData.value.trim()) {
        errors.push('Signature is required.');
        const sigSection = document.querySelector('.kkp-sig-section-left');
        if (sigSection) {
            let err = sigSection.querySelector('.kkp-field-error');
            if (!err) {
                err = document.createElement('span');
                err.className = 'kkp-field-error';
                err.textContent = 'Please provide your signature before proceeding.';
                sigSection.appendChild(err);
            }
        }
    }

    // ── If errors, scroll to first error and stop ──
    if (errors.length > 0) {
        const firstErr = document.querySelector('.kkp-field-error');
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    // ── Backend email existence check before submit (non-wizard only) ──
    const emailField = document.querySelector('input[name="email"]');
    const formEl = document.getElementById('kkProfilingForm');
    const updateFormEl = document.getElementById('kkProfilingUpdateForm');
    const isWizardSubmit = formEl?.dataset?.wizardMode === '1';
    const originalEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || emailField?.dataset?.originalEmail || '').trim().toLowerCase();
    const emailUnchanged = originalEmail && (emailField?.value || '').trim().toLowerCase() === originalEmail;

    if (!skipEmailExistenceCheck && !isWizardSubmit && !emailUnchanged && emailField && emailField.value.trim()) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const emailCheckResponse = await fetch('/api/kkprofiling/check-email-exists', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    email: emailField.value.trim(),
                    current_email: originalEmail || undefined,
                }),
            });
            const emailCheckResult = await emailCheckResponse.json();
            if (emailCheckResult.exists) {
                emailField.dataset.emailExists = 'true';
                fieldError(emailField, emailCheckResult.message || 'This email already exists. Please use a different email address.');
                emailField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        } catch (err) {
            // Continue to submit; server will validate
        }
    }

    return true;
};

window.handleFormSubmit = async function (event) {
    event.preventDefault();

    const form = document.getElementById('kkProfilingForm');
    const isWizardMode = form?.dataset?.wizardMode === '1';

    if (!await window.validateKkProfilingForm()) {
        return false;
    }

    if (isWizardMode) {
        return false;
    }

    // ── All valid — submit via AJAX for proper error handling ──
    console.log('Form validation passed. Submitting to backend...');
    const submitBtn = document.getElementById('kkpSubmitBtn');
    const submitText = document.getElementById('kkpSubmitText');

    function resetSubmitState() {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-submitting');
        }
        if (submitText) submitText.textContent = 'Submit KK Profiling';
    }

    function applyServerFieldErrors(serverErrors) {
        if (!serverErrors || typeof serverErrors !== 'object') return;

        Object.entries(serverErrors).forEach(function ([field, messages]) {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                fieldError(input, message);
            }
        });
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-submitting');
    }
    if (submitText) submitText.textContent = 'Submitting...';

    // Prepare form data
    const formData = new FormData(form);
    console.log('Form data prepared:', Object.fromEntries(formData));

    // Submit via fetch API
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('Response received:', response.status, response.statusText);

            // Check if response is a redirect (302)
            if (response.redirected) {
                console.log('Redirect detected to:', response.url);
                window.location.href = response.url;
                return null;
            }

            // Try to parse as JSON
            return response.json().then(data => {
                console.log('Response data:', data);
                return { response, data };
            }).catch(() => {
                // If not JSON, check for HTML response (redirect)
                return response.text().then(text => {
                    console.log('Response text (first 200 chars):', text.substring(0, 200));
                    // If it's HTML, the backend likely redirected - follow it
                    if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                        // The response is a full HTML page, likely a redirect
                        // Force a page reload to the current URL to follow the redirect
                        window.location.reload();
                    }
                    return { response, data: null };
                });
            });
        })
        .then((result) => {
            if (!result) return;

            const { response, data } = result;

            // Handle successful response
            if (response.ok) {
                console.log('Submission successful');
                // If backend returned a redirect URL in data
                if (data && data.redirect) {
                    console.log('Redirecting to:', data.redirect);
                    // Append email as URL parameter if provided
                    if (data.email) {
                        const redirectUrl = new URL(data.redirect, window.location.origin);
                        redirectUrl.searchParams.set('email', data.email);
                        window.location.href = redirectUrl.toString();
                    } else {
                        window.location.href = data.redirect;
                    }
                } else if (data && data.message) {
                    // Show success message
                    alert(data.message);
                    // Redirect to check-email page with email
                    const email = data.email || document.querySelector('input[name="email"]')?.value;
                    const checkEmailUrl = new URL('/kkprofiling/check-email', window.location.origin);
                    if (email) {
                        checkEmailUrl.searchParams.set('email', email);
                    }
                    window.location.href = checkEmailUrl.toString();
                } else {
                    // Default redirect to check-email page
                    console.log('Redirecting to check-email page');
                    const email = document.querySelector('input[name="email"]')?.value;
                    const checkEmailUrl = new URL('/kkprofiling/check-email', window.location.origin);
                    if (email) {
                        checkEmailUrl.searchParams.set('email', email);
                    }
                    window.location.href = checkEmailUrl.toString();
                }
            } else {
                // Handle error response
                console.error('Submission failed with status:', response.status);
                resetSubmitState();

                let errorMessage = 'Registration failed. Please try again.';

                if (data && data.errors) {
                    console.error('Validation errors:', data.errors);
                    applyServerFieldErrors(data.errors);
                    errorMessage = Object.values(data.errors).flat().join('\n');
                    const firstErr = document.querySelector('.kkp-field-error');
                    if (firstErr) {
                        firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (data && data.message) {
                    errorMessage = data.message;
                } else if (response.status === 422) {
                    errorMessage = 'Validation failed. Please check your inputs.';
                } else if (response.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                }

                alert(errorMessage);
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            resetSubmitState();
            alert('Registration failed. Please check your connection and try again.');
        });

    return false;
};

// ── Show email verification card ──
function showEmailVerification(email) {
    const formCard = document.getElementById('kkpFormCard');
    const emailVerifyCard = document.getElementById('emailVerifyCard');
    const displayEmail = document.getElementById('displayEmail');

    if (formCard) formCard.style.display = 'none';
    if (emailVerifyCard) emailVerifyCard.style.display = 'block';
    if (displayEmail) displayEmail.textContent = email;

    // Start the resend timer
    if (window.startResendTimer) {
        window.startResendTimer();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ═══════════════════════════════════════════════════════════════
   EMAIL VERIFICATION HANDLERS
═══════════════════════════════════════════════════════════════ */
(function () {
    // Back to form button
    const backToFormBtn = document.getElementById('backToFormBtn');
    const backToFormBtn2 = document.getElementById('backToFormBtn2');

    function showForm() {
        const formCard = document.getElementById('kkpFormCard');
        const emailVerifyCard = document.getElementById('emailVerifyCard');
        const setPasswordCard = document.getElementById('setPasswordCard');
        const regSuccessModal = document.getElementById('kkpRegSuccessModal');

        if (formCard) formCard.style.display = 'block';
        if (emailVerifyCard) emailVerifyCard.style.display = 'none';
        if (setPasswordCard) setPasswordCard.style.display = 'none';
        if (regSuccessModal) {
            regSuccessModal.hidden = true;
            regSuccessModal.setAttribute('aria-hidden', 'true');
        }

        // Reset submit button state
        const submitBtn = document.getElementById('kkpSubmitBtn');
        const submitText = document.getElementById('kkpSubmitText');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-submitting');
        }
        if (submitText) submitText.textContent = 'Submit KK Profiling';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    if (backToFormBtn) backToFormBtn.addEventListener('click', showForm);
    if (backToFormBtn2) backToFormBtn2.addEventListener('click', showForm);

    // ── Resend set password link with 1-minute countdown ──
    const RESEND_COOLDOWN_SEC = 60;
    let resendInterval = null;

    function getResendCooldownKey() {
        const email = document.getElementById('displayEmail')?.textContent?.trim() || 'default';
        return 'kkp_setpw_resend_' + email.toLowerCase();
    }

    function formatResendTimer(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return '(' + m + ':' + (s < 10 ? '0' : '') + s + ')';
    }

    window.startResendTimer = function (options = {}) {
        const btn = document.getElementById('resendEmailBtn');
        const timer = document.getElementById('resendTimer');
        if (!btn || !timer) return;

        const cooldownKey = getResendCooldownKey();
        let seconds = typeof options.seconds === 'number' ? options.seconds : RESEND_COOLDOWN_SEC;

        if (options.persist !== false) {
            sessionStorage.setItem(cooldownKey, String(Date.now() + seconds * 1000));
        }

        btn.disabled = true;
        timer.hidden = false;
        timer.textContent = formatResendTimer(seconds);

        clearInterval(resendInterval);
        resendInterval = setInterval(function () {
            seconds -= 1;
            timer.textContent = formatResendTimer(seconds);

            if (seconds <= 0) {
                clearInterval(resendInterval);
                sessionStorage.removeItem(cooldownKey);
                btn.disabled = false;
                timer.hidden = true;
                timer.textContent = '';
            }
        }, 1000);
    };

    window.restoreResendTimer = function () {
        const btn = document.getElementById('resendEmailBtn');
        const timer = document.getElementById('resendTimer');
        if (!btn || !timer) return;

        if (document.body.classList.contains('kkp-wizard-registration-complete')) {
            btn.disabled = true;
            btn.hidden = true;
            timer.hidden = true;
            return;
        }

        const cooldownKey = getResendCooldownKey();
        const until = parseInt(sessionStorage.getItem(cooldownKey) || '0', 10);
        const remaining = Math.ceil((until - Date.now()) / 1000);

        if (remaining > 0) {
            window.startResendTimer({ seconds: remaining, persist: false });
            return;
        }

        sessionStorage.removeItem(cooldownKey);
        btn.disabled = false;
        timer.hidden = true;
        timer.textContent = '';
    };

    window.kkpStopResendTimer = function () {
        clearInterval(resendInterval);
        resendInterval = null;

        const btn = document.getElementById('resendEmailBtn');
        const timer = document.getElementById('resendTimer');

        if (btn) {
            btn.disabled = true;
        }

        if (timer) {
            timer.hidden = true;
            timer.textContent = '';
        }
    };

    const resendEmailBtn = document.getElementById('resendEmailBtn');
    if (resendEmailBtn) {
        resendEmailBtn.addEventListener('click', async function () {
            if (this.disabled || document.body.classList.contains('kkp-wizard-registration-complete')) {
                return;
            }

            const wizardRoot = document.getElementById('kkpRegistrationWizard');

            if (wizardRoot && typeof window.kkpWizardSendVerification === 'function') {
                await window.kkpWizardSendVerification(true);
                return;
            }

            const btn = this;
            const displayEmail = document.getElementById('displayEmail');
            const email = (displayEmail && displayEmail.textContent.trim()) || '';
            const form = document.getElementById('kkProfilingForm');
            const barangay = form ? (form.dataset.barangaySlug || '') : (wizardRoot?.dataset.barangaySlug || '');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (!email || email === 'your-email@example.com') {
                return;
            }

            btn.disabled = true;

            try {
                let turnstileToken = '';
                if (typeof window.kabataanTurnstileChallenge === 'function') {
                    turnstileToken = await window.kabataanTurnstileChallenge();
                } else if (window.KabataanTurnstileGate?.challenge) {
                    turnstileToken = await window.KabataanTurnstileGate.challenge();
                } else if (typeof window.kkpChallengeTurnstile === 'function') {
                    turnstileToken = await window.kkpChallengeTurnstile();
                }

                const response = await fetch('/api/kkprofiling/resend-verification', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        email: email,
                        barangay: barangay,
                        'cf-turnstile-response': turnstileToken,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    if (data.registration_completed && window.kkpShowRegistrationComplete) {
                        window.kkpShowRegistrationComplete(Boolean(data.auto_approved));
                        return;
                    }

                    btn.textContent = 'Email sent!';
                    setTimeout(() => { btn.textContent = 'Resend set password link'; }, 2500);
                    window.startResendTimer();
                } else {
                    alert(data.message || 'Failed to resend verification email. Please try again.');
                    btn.disabled = false;
                }
            } catch (err) {
                if (err?.message === 'Verification cancelled.') {
                    btn.disabled = false;
                    return;
                }
                alert('Failed to resend verification email. Please check your connection and try again.');
                btn.disabled = false;
            }
        });
    }
})();

/* ═══════════════════════════════════════════════════════════════
   SET PASSWORD PAGE (wizard token + legacy)
═══════════════════════════════════════════════════════════════ */
(function () {
    if (!document.body.classList.contains('kkp-setpw-page')) {
        return;
    }

    const successModal = document.getElementById('kkpRegSuccessModal');
    const successMessageEl = document.getElementById('kkpRegSuccessMessage');

    function showSuccessModal(message, autoApproved = false, options = {}) {
        if (!successModal) return;

        const titleEl = document.getElementById('kkpRegSuccessTitle');
        const loginBtn = successModal.querySelector('.kkp-reg-success-modal-btn');
        const isActivated = Boolean(options.activated);

        if (titleEl) {
            if (isActivated) {
                titleEl.textContent = 'Account Successfully Activated';
            } else {
                titleEl.textContent = autoApproved
                    ? 'Registration Verified!'
                    : 'Registration Submitted Successfully';
            }
        }

        if (successMessageEl) {
            successMessageEl.textContent = message || (isActivated
                ? 'Your email and password have been saved to your KK Profiling record. You can now sign in.'
                : 'Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.');
        }

        if (loginBtn) {
            loginBtn.textContent = 'Go to Sign in';
            if (options.redirectUrl) {
                loginBtn.setAttribute('href', options.redirectUrl);
            }
        }

        successModal.hidden = false;
        successModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('kkp-wizard-success-modal-open');
    }

    if (document.body.dataset.registrationAlreadyComplete === '1') {
        showSuccessModal(
            'Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.',
            document.body.dataset.autoApproved === '1',
        );
        return;
    }

    const form = document.getElementById('setPasswordForm');
    if (!form) {
        return;
    }

    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const rulesWrap = document.getElementById('pwRules');
    const passwordError = document.getElementById('passwordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    const submitBtn = document.getElementById('setpwSubmitBtn');
    const finalizeUrl = form.dataset.finalizeUrl || '';
    const isWizardToken = Boolean(form.dataset.wizardToken);
    const isAccountInvite = form.dataset.accountInvite === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const originalBtnText = submitBtn?.querySelector('.setpw-btn-text')?.textContent?.trim()
        || (isAccountInvite ? 'Activate Account' : 'Complete Registration');

    function syncPasswordEyeToggle(btn, input) {
        const isVisible = input.type === 'text';

        btn.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        btn.classList.toggle('pw-visible', isVisible);
    }

    document.querySelectorAll('.kkp-setpw-toggle[data-target], .pw-toggle-btn[data-target]').forEach((btn) => {
        const target = document.getElementById(btn.dataset.target || '');
        if (!target) {
            return;
        }

        syncPasswordEyeToggle(btn, target);

        btn.addEventListener('click', () => {
            const showPassword = target.type === 'password';
            target.type = showPassword ? 'text' : 'password';
            syncPasswordEyeToggle(btn, target);
        });
    });

    function validatePasswordStrength(password) {
        return {
            len: password.length >= 8,
            lower: /[a-z]/.test(password),
            upper: /[A-Z]/.test(password),
            num: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password),
        };
    }

    function updatePasswordRules() {
        if (!passwordInput || !rulesWrap) return null;

        const value = passwordInput.value || '';
        const checks = validatePasswordStrength(value);

        Object.entries(checks).forEach(([key, passed]) => {
            const el = rulesWrap.querySelector(`[data-rule="${key}"]`);
            if (el) el.classList.toggle('ok', passed);
        });

        const allPassed = Object.values(checks).every(Boolean);
        const showRules = value.length > 0 && !allPassed;
        rulesWrap.hidden = !showRules;
        rulesWrap.classList.toggle('is-visible', showRules);

        return checks;
    }

    function setFieldError(input, errorEl, message) {
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.hidden = !message;
        }
        if (input) {
            input.classList.toggle('is-error', Boolean(message));
        }
    }

    function clearErrors() {
        setFieldError(passwordInput, passwordError, '');
        setFieldError(confirmInput, confirmPasswordError, '');
    }

    function updateConfirmMatch() {
        if (!passwordInput || !confirmInput) {
            return;
        }

        const password = passwordInput.value || '';
        const confirmation = confirmInput.value || '';

        if (!confirmation) {
            setFieldError(confirmInput, confirmPasswordError, '');
            return;
        }

        if (password !== confirmation) {
            setFieldError(confirmInput, confirmPasswordError, 'Passwords do not match.');
            return;
        }

        setFieldError(confirmInput, confirmPasswordError, '');
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', () => {
            updatePasswordRules();
            updateConfirmMatch();
        });
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', updateConfirmMatch);
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        const password = passwordInput?.value || '';
        const confirmation = confirmInput?.value || '';
        const checks = updatePasswordRules();
        const strengthOk = checks && Object.values(checks).every(Boolean);

        if (!password) {
            setFieldError(passwordInput, passwordError, 'Password is required.');
            passwordInput?.focus();
            return;
        }

        if (!strengthOk) {
            setFieldError(passwordInput, passwordError, 'Password must satisfy all requirements.');
            passwordInput?.focus();
            return;
        }

        if (!confirmation) {
            setFieldError(confirmInput, confirmPasswordError, 'Please confirm your password.');
            confirmInput?.focus();
            return;
        }

        if (password !== confirmation) {
            setFieldError(confirmInput, confirmPasswordError, 'Passwords do not match.');
            confirmInput?.focus();
            return;
        }

        const btnText = submitBtn?.querySelector('.setpw-btn-text');
        if (submitBtn) submitBtn.disabled = true;
        if (btnText) btnText.textContent = isAccountInvite ? 'Activating account...' : 'Completing registration...';

        try {
            let turnstileToken = '';
            if (window.kabataanTurnstileChallenge) {
                try {
                    turnstileToken = await window.kabataanTurnstileChallenge();
                } catch {
                    if (submitBtn) submitBtn.disabled = false;
                    if (btnText) btnText.textContent = originalBtnText;
                    return;
                }
            } else if (window.KabataanTurnstileGate?.challenge) {
                try {
                    turnstileToken = await window.KabataanTurnstileGate.challenge();
                } catch {
                    if (submitBtn) submitBtn.disabled = false;
                    if (btnText) btnText.textContent = originalBtnText;
                    return;
                }
            }

            let response;

            if (isWizardToken && finalizeUrl) {
                response = await fetch(finalizeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        password,
                        password_confirmation: confirmation,
                        'cf-turnstile-response': turnstileToken,
                    }),
                });
            } else {
                if (window.KabataanTurnstileGate?.injectToken) {
                    window.KabataanTurnstileGate.injectToken(form, turnstileToken);
                }
                const formData = new FormData(form);
                response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
            }

            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                showSuccessModal(
                    data.message || (isAccountInvite
                        ? 'Your email and password have been saved to your KK Profiling record. You can now sign in.'
                        : 'Your account has been created successfully. Please wait for SK Officials to review and verify your registration before you can access the system.'),
                    Boolean(data.auto_approved),
                    {
                        activated: isAccountInvite || Boolean(data.activated),
                        redirectUrl: data.redirect_url || '',
                    },
                );
                return;
            }

            let errorMessage = data.message || (isAccountInvite
                ? 'Unable to activate this account. Please try again.'
                : 'Unable to complete registration. Please try again.');
            if (data.errors) {
                errorMessage = Object.values(data.errors).flat().join(' ');
            }

            setFieldError(passwordInput, passwordError, errorMessage);
        } catch {
            setFieldError(
                passwordInput,
                passwordError,
                isAccountInvite
                    ? 'Unable to activate this account. Please check your connection and try again.'
                    : 'Unable to complete registration. Please check your connection and try again.',
            );
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalBtnText;
        }
    });
})();

/* ═══════════════════════════════════════════════════════════════
   SIGNATURE PAD — mirrors SK Officials Kabataan module pattern
   Signature image overlaid on top of printed name
═══════════════════════════════════════════════════════════════ */
(function initKKPSignaturePad() {
    const overlay = document.getElementById('kkpSignaturePadOverlay');
    const triggerBtn = document.getElementById('kkpSignatureTrigger');
    const closeBtn = document.getElementById('kkpSignaturePadClose');
    const clearBtn = document.getElementById('kkpSignaturePadClear');
    const saveBtn = document.getElementById('kkpSignaturePadSave');
    const canvas = document.getElementById('kkpSignaturePadCanvas');
    const placeholder = document.getElementById('kkpSignatureCanvasPlaceholder');
    const sigInput = document.getElementById('kkpSignatureData');
    const sigPreview = document.getElementById('kkpSignaturePreview');
    const sigOverlay = document.getElementById('kkpSignatureOverlay');
    const clearSavedBtn = document.getElementById('kkpSignatureClearSaved');

    if (!canvas || !overlay) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasSignature = false;

    // Show Sign button only after name is auto-filled and no signature yet
    // (name is auto-filled from name fields — handled in main IIFE above)
    // triggerBtn visibility is managed by updateSignatureName()

    function setupCanvas(preserveDrawing) {
        const rect = canvas.getBoundingClientRect();
        const cssW = rect.width || 500;
        const cssH = rect.height || 260;
        const dpr = window.devicePixelRatio || 1;

        const snapshot = preserveDrawing ? canvas.toDataURL('image/png') : null;

        canvas.width = Math.floor(cssW * dpr);
        canvas.height = Math.floor(cssH * dpr);
        canvas.style.width = cssW + 'px';
        canvas.style.height = cssH + 'px';

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (snapshot && snapshot !== 'data:,') {
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, cssW, cssH);
            };
            img.src = snapshot;
        }
    }

    function openPad() {
        overlay.style.display = 'flex';
        setupCanvas(false);
        // Restore existing signature if any
        if (sigInput && sigInput.value) {
            const img = new Image();
            img.onload = function () {
                const rect = canvas.getBoundingClientRect();
                ctx.drawImage(img, 0, 0, rect.width || 500, rect.height || 260);
                hasSignature = true;
                hidePlaceholder();
            };
            img.src = sigInput.value;
        } else {
            clearCanvas();
        }
    }

    function closePad() {
        overlay.style.display = 'none';
    }

    function clearCanvas() {
        const rect = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width || 500, rect.height || 260);
        hasSignature = false;
        showPlaceholder();
    }

    function hidePlaceholder() { if (placeholder) placeholder.style.display = 'none'; }
    function showPlaceholder() { if (placeholder) placeholder.style.display = 'block'; }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const cx = e.touches ? e.touches[0].clientX : e.clientX;
        const cy = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: cx - rect.left, y: cy - rect.top };
    }

    function startDraw(e) {
        isDrawing = true;
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        hidePlaceholder();
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        hasSignature = true;
    }

    function stopDraw() { isDrawing = false; }

    function canvasHasInk() {
        const w = canvas.width;
        const h = canvas.height;
        if (!w || !h) return false;
        const data = ctx.getImageData(0, 0, w, h).data;
        for (let i = 3; i < data.length; i += 4) {
            if (data[i] !== 0) return true;
        }
        return false;
    }

    function saveSig() {
        if (!hasSignature || !canvasHasInk()) {
            alert('Please provide a signature before saving.');
            return;
        }
        // Show confirmation modal instead of saving immediately
        const confirmOverlay = document.getElementById('kkpSigConfirmOverlay');
        if (confirmOverlay) confirmOverlay.style.display = 'flex';
    }

    function doSaveSig() {
        const data = canvas.toDataURL('image/png');

        // Store in hidden input
        if (sigInput) sigInput.value = data;

        clearSignatureError();

        // Show signature image overlaid on top of printed name
        if (sigPreview && sigOverlay) {
            sigPreview.src = data;
            sigOverlay.style.display = 'flex';
        }

        // Lock Sign button; show clear button for re-signing
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.setAttribute('aria-disabled', 'true');
        }
        if (clearSavedBtn) clearSavedBtn.style.display = 'inline-flex';

        // Close confirmation modal
        const confirmOverlay = document.getElementById('kkpSigConfirmOverlay');
        if (confirmOverlay) confirmOverlay.style.display = 'none';

        closePad();
    }

    function clearSavedSignature() {
        if (sigInput) sigInput.value = '';
        if (sigOverlay) sigOverlay.style.display = 'none';
        if (sigPreview) sigPreview.removeAttribute('src');
        if (clearSavedBtn) clearSavedBtn.style.display = 'none';
        if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.setAttribute('aria-disabled', 'false');
        }
        hasSignature = false;
        showPlaceholder();
        clearCanvas();
    }

    // Button events
    if (triggerBtn) {
        triggerBtn.addEventListener('click', function () {
            if (triggerBtn.disabled) return;
            openPad();
        });
    }

    // Validation function for required fields before signature
    function validateRequiredFields() {
        const errors = [];

        // Check required name fields
        const lastName = document.querySelector('input[name="last_name"]');
        const firstName = document.querySelector('input[name="first_name"]');
        const suffix = document.getElementById('kkpSuffix');
        const customSuffix = document.getElementById('kkpCustomSuffix');

        if (!lastName || !lastName.value.trim()) {
            errors.push('- Last Name is required');
        }
        if (!firstName || !firstName.value.trim()) {
            errors.push('- First Name is required');
        }
        if (!suffix || !suffix.value) {
            errors.push('- Suffix is required');
        } else if (suffix.value === 'Others') {
            const raw = (customSuffix && customSuffix.value ? customSuffix.value : '').trim();
            if (!raw) {
                errors.push('- Custom suffix is required');
            }
        }

        // Check other required fields
        const purok = document.querySelector('select[name="purok_zone"]') || document.querySelector('input[name="purok_zone"]');
        if (!purok || !purok.value.trim()) {
            errors.push('- Purok/Zone is required');
        }

        const sex = document.getElementById('kkpSex');
        if (!sex || !sex.value.trim()) {
            errors.push('- Sex Assigned by Birth is required');
        }

        const age = document.querySelector('[name="age"]');
        if (!age || !age.value.trim()) {
            errors.push('- Age is required');
        }

        const birthday = document.querySelector('input[name="birthday"]');
        if (!birthday || !birthday.value.trim()) {
            errors.push('- Birthday is required');
        }

        const email = document.querySelector('input[name="email"]');
        const emailLocked = document.getElementById('kkProfilingUpdateForm')?.dataset?.emailLocked === '1';
        if (emailLocked && (!email || !email.value.trim())) {
            errors.push('- Email is required');
        }

        const contact = document.querySelector('input[name="contact_number"]');
        if (!contact || !contact.value.trim()) {
            errors.push('- Contact # is required');
        }

        const civilStatus = document.getElementById('kkpCivilStatus');
        if (!civilStatus || !civilStatus.value.trim()) {
            errors.push('- Civil Status is required');
        }

        const youthAgeGroup = document.getElementById('kkpYouthAgeGroup');
        if (!youthAgeGroup || !youthAgeGroup.value.trim()) {
            errors.push('- Youth Age Group is required');
        }

        const education = document.getElementById('kkpEducation');
        if (!education || !education.value.trim()) {
            errors.push('- Educational Background is required');
        }

        const youthClass = document.getElementById('kkpYouthClass');
        if (!youthClass || !youthClass.value.trim()) {
            errors.push('- Youth Classification is required');
        }

        const workStatus = document.getElementById('kkpWorkStatus');
        if (!workStatus || !workStatus.value.trim()) {
            errors.push('- Work Status is required');
        }

        const skVoter = document.getElementById('kkpSkVoter');
        if (!skVoter || !skVoter.value.trim()) {
            errors.push('- Registered SK Voter is required');
        }

        const skVoted = document.getElementById('kkpSkVoted');
        if (!skVoted || !skVoted.value.trim()) {
            errors.push('- Did you vote last SK is required');
        }

        const nationalVoter = document.getElementById('kkpNationalVoter');
        if (!nationalVoter || !nationalVoter.value.trim()) {
            errors.push('- Registered National Voter is required');
        }

        const kkAssembly = document.getElementById('kkpKkAssembly');
        const kkAssemblyVal = kkAssembly ? kkAssembly.value.trim() : '';
        if (!kkAssemblyVal) {
            errors.push('- KK Assembly attendance is required');
        } else if (kkAssemblyVal === 'Yes') {
            const kkTimes = document.getElementById('kkpKkTimes');
            if (!kkTimes || !kkTimes.value.trim()) {
                errors.push('- KK Assembly attendance count is required');
            }
        } else if (kkAssemblyVal === 'No') {
            const kkReason = document.getElementById('kkpKkReason');
            if (!kkReason || !kkReason.value.trim()) {
                errors.push('- KK Assembly reason is required');
            }
        }

        const facebook = document.querySelector('input[name="facebook_profile_url"]');
        const facebookMsg = kkpValidateFacebook(facebook?.value, true);
        if (facebookMsg) {
            errors.push(`- ${facebookMsg}`);
        }

        const groupChat = document.getElementById('kkpGroupChat');
        if (kkpIsFacebookFilled() && (!groupChat || !groupChat.value.trim())) {
            errors.push('- Willing to join group chat is required when FB Account is provided');
        }

        return errors;
    }
    if (closeBtn) closeBtn.addEventListener('click', closePad);
    if (clearBtn) clearBtn.addEventListener('click', clearCanvas);
    if (saveBtn) saveBtn.addEventListener('click', saveSig);
    if (clearSavedBtn) clearSavedBtn.addEventListener('click', clearSavedSignature);

    window.kkpRestoreSignaturePreview = function (dataUrl) {
        if (!dataUrl || !sigInput) {
            return;
        }

        sigInput.value = dataUrl;

        clearSignatureError();

        if (sigPreview && sigOverlay) {
            sigPreview.src = dataUrl;
            sigOverlay.style.display = 'flex';
        }

        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.setAttribute('aria-disabled', 'true');
        }

        if (clearSavedBtn) {
            clearSavedBtn.style.display = 'inline-flex';
        }
    };

    // Initial state (in case of server-side repopulation)
    if (sigInput && sigInput.value && sigPreview && sigOverlay) {
        sigPreview.src = sigInput.value;
        sigOverlay.style.display = 'flex';
        clearSignatureError();
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.setAttribute('aria-disabled', 'true');
        }
        if (clearSavedBtn) clearSavedBtn.style.display = 'inline-flex';
    }

    // Confirmation modal buttons
    const confirmOverlay = document.getElementById('kkpSigConfirmOverlay');
    const confirmCancelBtn = document.getElementById('kkpSigConfirmCancel');
    const confirmSaveBtn = document.getElementById('kkpSigConfirmSave');

    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', function () {
            if (confirmOverlay) confirmOverlay.style.display = 'none';
        });
    }
    if (confirmSaveBtn) {
        confirmSaveBtn.addEventListener('click', doSaveSig);
    }
    // Close confirmation on backdrop click
    if (confirmOverlay) {
        confirmOverlay.addEventListener('click', function (e) {
            if (e.target === confirmOverlay) confirmOverlay.style.display = 'none';
        });
    }

    // Close on backdrop click
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closePad();
        });
    }

    // Mouse events
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);

    // Touch events
    canvas.addEventListener('touchstart', function (e) { e.preventDefault(); startDraw(e); }, { passive: false });
    canvas.addEventListener('touchmove', function (e) { e.preventDefault(); draw(e); }, { passive: false });
    canvas.addEventListener('touchend', function (e) { e.preventDefault(); stopDraw(); }, { passive: false });

    // Resize
    window.addEventListener('resize', function () {
        if (overlay.style.display !== 'none') setupCanvas(true);
    });
})();
