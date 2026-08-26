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

function kkpNormalizeNameValue(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

const KKP_NAME_SOFT_MAX = 50;
const KKP_NAME_MAX_CHARS = 150;
const KKP_NAME_MAX_MSG = '150 maximum characters only.';

/** KK Profiling practical email limits (not RFC 254). */
const KKP_EMAIL_TOTAL_MAX = 64;
/** Local-part (before @): min 6 / max 30 for every domain. */
const KKP_EMAIL_LOCAL_MIN = 6;
const KKP_EMAIL_LOCAL_MAX = 30;

/** Live typing: letters/.- , one space between words, no leading/pure multi-space. */
function kkpSanitizeNameInput(value) {
    return String(value || '')
        .toUpperCase()
        .replace(/[^A-Z.\-\s]/g, '')
        .replace(/^\s+/, '')
        .replace(/\s{2,}/g, ' ');
}

function kkpValidateNamePart(value, options = {}) {
    const required = Boolean(options.required);
    const label = options.label || 'Name';
    const touched = Boolean(options.touched);
    const raw = String(value || '');
    const v = kkpNormalizeNameValue(raw);
    const isPureSpaces = raw.length > 0 && v === '';

    if (!v) {
        if (isPureSpaces) {
            return 'Minimum 2 characters required.';
        }
        return required && touched ? `${label} is required.` : null;
    }

    if (v.length < 2) {
        return 'Minimum 2 characters required.';
    }

    if (v.length > KKP_NAME_MAX_CHARS || raw.length > KKP_NAME_MAX_CHARS) {
        return KKP_NAME_MAX_MSG;
    }

    if (!/^[A-Za-z.\-\s]+$/.test(v)) {
        return 'Letters, spaces, periods, and hyphens only.';
    }

    return null;
}

function kkpValidateLastName(value, touched) {
    return kkpValidateNamePart(value, { required: true, label: 'Last Name', touched });
}

function kkpValidateFirstName(value, touched) {
    return kkpValidateNamePart(value, { required: true, label: 'First Name', touched });
}

let kkpNameMeasureEl = null;
let kkpNameMaxHintTimers = new WeakMap();
let kkpLongNameAllowed = false;
let kkpPendingLongName = null;

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

    const prevTimer = kkpNameMaxHintTimers.get(el);
    if (prevTimer) {
        clearTimeout(prevTimer);
        kkpNameMaxHintTimers.delete(el);
    }

    col.querySelectorAll('.kkp-name-max-hint').forEach((node) => node.remove());

    const len = (el.value || '').length;
    if (len < KKP_NAME_MAX_CHARS) {
        return;
    }

    // Avoid duplicating the same text if validation already shows it as a field error.
    const existingErr = col.querySelector('.kkp-field-error');
    if (existingErr && existingErr.textContent === KKP_NAME_MAX_MSG) {
        return;
    }

    const hint = document.createElement('span');
    hint.className = 'kkp-field-hint kkp-name-max-hint';
    hint.setAttribute('role', 'status');
    hint.textContent = KKP_NAME_MAX_MSG;
    col.appendChild(hint);
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
    return kkpValidateNamePart(value, { required: false, label: 'Middle Name', touched });
}

function kkpFitEmailInputFont(el) {
    if (!el) return;
    // Match other .kkp-uline fields (12px) — do not shrink typed email text.
    el.style.fontSize = '';
    el.style.textAlign = 'center';
}

function kkpHasAnySpace(value) {
    return /\s/.test(value || '');
}

function kkpEmailLooksComplete(value) {
    const v = String(value || '').trim().toLowerCase();
    if (!v || /\s/.test(v)) {
        return false;
    }
    const atCount = (v.match(/@/g) || []).length;
    if (atCount !== 1) {
        return false;
    }
    const atIndex = v.indexOf('@');
    const localPart = v.slice(0, atIndex);
    const domain = v.slice(atIndex + 1);
    if (!localPart || !domain || !domain.includes('.')) {
        return false;
    }
    return /^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)
        && /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain);
}

/**
 * @param {string} value
 * @param {boolean} touched
 * @param {{ strict?: boolean }} [options] - strict on blur/submit; while typing only format-check complete emails
 */
function kkpValidateEmail(value, touched, options = {}) {
    const strict = options.strict === true;
    const v = (value || '').trim().toLowerCase();

    if (!v) {
        return (touched && strict) ? 'Email is required.' : null;
    }

    if (v.length > KKP_EMAIL_TOTAL_MAX) {
        return 'Email must not exceed 64 characters.';
    }

    if (kkpHasAnySpace(v) || (v.match(/@/g) || []).length > 1) {
        return 'Please enter a valid email address.';
    }

    const atIndex = v.indexOf('@');

    // Local-part (before @): min 6 / max 30 — all domains.
    if (atIndex >= 0) {
        const localPart = v.slice(0, atIndex);
        if (localPart.length < KKP_EMAIL_LOCAL_MIN) {
            return 'Email username must be at least 6 characters.';
        }
        if (localPart.length > KKP_EMAIL_LOCAL_MAX) {
            return 'Email username must not exceed 30 characters.';
        }
    } else if (strict && v.length < KKP_EMAIL_LOCAL_MIN) {
        return 'Email username must be at least 6 characters.';
    } else if (!strict && v.length > KKP_EMAIL_LOCAL_MAX) {
        // Typing username only — still enforce 30 before @.
        return 'Email username must not exceed 30 characters.';
    }

    const complete = kkpEmailLooksComplete(v);

    // While typing an incomplete domain after @, do not spam format errors.
    if (!strict && !complete) {
        return null;
    }

    if ((v.match(/@/g) || []).length !== 1) {
        return 'Please enter a valid email address.';
    }

    const localPart = v.slice(0, atIndex);
    const domain = v.slice(atIndex + 1);

    if (!localPart || !domain) {
        return 'Please enter a valid email address.';
    }
    if (
        localPart.startsWith('.')
        || localPart.endsWith('.')
        || localPart.includes('..')
        || domain.startsWith('.')
        || domain.endsWith('.')
        || domain.includes('..')
        || domain.startsWith('-')
        || domain.endsWith('-')
    ) {
        return 'Please enter a valid email address.';
    }
    if (!/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)) {
        return 'Please enter a valid email address.';
    }
    if (!/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain)) {
        return 'Please enter a valid email address.';
    }

    return null;
}

function kkpValidatePurok(value, touched) {
    const v = (value || '').trim();
    if (!v) {
        return touched ? 'Purok/Zone is required.' : null;
    }
    return null;
}

function kkpPurokField() {
    return document.querySelector('select[name="purok_zone"]') || document.querySelector('input[name="purok_zone"]');
}

function kkpIsObviouslyFakeContact(digits) {
    const raw = String(digits || '').replace(/\D+/g, '');
    if (!raw) {
        return false;
    }

    const local = raw.length === 11 && raw.startsWith('09')
        ? raw
        : (raw.length === 10 && raw.startsWith('9') ? `0${raw}` : null);
    const body = local ? local.slice(2) : raw;

    const isSameDigit = (d) => (
        /^09(\d)\1{8}$/.test(d)
        || /^9(\d)\1{8}$/.test(d)
        || (d.length >= 8 && /^(\d)\1+$/.test(d))
    );

    const isFullSequence = (s) => {
        if (!s || s.length < 8) {
            return false;
        }
        let asc = true;
        let desc = true;
        for (let i = 1; i < s.length; i += 1) {
            const diff = Number(s[i]) - Number(s[i - 1]);
            if (diff !== 1) {
                asc = false;
            }
            if (diff !== -1) {
                desc = false;
            }
        }
        return asc || desc;
    };

    const coversRepeat = (s, blockLen) => {
        if (!s || s.length < 8) {
            return false;
        }
        const repeats = Math.floor(s.length / blockLen);
        if (repeats < 3) {
            return false;
        }
        const block = s.slice(0, blockLen);
        if (!block || /^(.)\1+$/.test(block)) {
            return false;
        }
        const built = block.repeat(repeats);
        return built === s.slice(0, built.length) && built.length >= Math.floor(s.length * 0.85);
    };

    const isRepeatedPattern = (d) => {
        for (let blockLen = 2; blockLen <= 4; blockLen += 1) {
            if (coversRepeat(d, blockLen)) {
                return true;
            }
            if (d.length >= 9 && coversRepeat(d.slice(1), blockLen)) {
                return true;
            }
            if (d.length === 11 && d.startsWith('09') && coversRepeat(d.slice(2), blockLen)) {
                return true;
            }
        }
        return false;
    };

    if (isSameDigit(raw) || (local && isSameDigit(local))) {
        return true;
    }
    if (isFullSequence(raw) || (local && isFullSequence(local.slice(2)))) {
        return true;
    }
    if (isRepeatedPattern(raw) || (local && isRepeatedPattern(local))) {
        return true;
    }

    if (body.length >= 8) {
        const unique = new Set(body.split('')).size;
        if (unique <= 2) {
            return true;
        }
        if (/(\d)\1{5,}/.test(body)) {
            return true;
        }
        const counts = {};
        for (const ch of body) {
            counts[ch] = (counts[ch] || 0) + 1;
            if (counts[ch] >= 6) {
                return true;
            }
        }

        let asc = 0;
        let desc = 0;
        const steps = body.length - 1;
        for (let i = 1; i < body.length; i += 1) {
            const diff = Number(body[i]) - Number(body[i - 1]);
            if (diff === 1) {
                asc += 1;
            }
            if (diff === -1) {
                desc += 1;
            }
        }
        if (asc >= steps - 1 || desc >= steps - 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param {string} value
 * @param {boolean} requireEmpty - true only on form submit; do not show required while typing
 */
function kkpValidateContact(value, requireEmpty) {
    const v = (value || '').trim();
    if (!v || v === '09') {
        return requireEmpty === true ? 'Contact number is required.' : null;
    }

    // UI accepts local PH mobile only: 09 + 9 digits (11 total).
    if (!/^09\d{9}$/.test(v)) {
        return 'Please enter a valid Philippine mobile number.';
    }

    if (kkpIsObviouslyFakeContact(v)) {
        return 'Please enter your actual mobile number.';
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

    function clearFieldError(el) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        el.classList.remove('kkp-input-err');
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        if (el.closest('.kkp-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    function showFieldError(el, msg) {
        const host = getFieldErrorHost(el);
        if (!el || !host) return;
        host.querySelectorAll('.kkp-field-error').forEach((node) => node.remove());
        el.classList.add('kkp-input-err');
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
        err.textContent = msg;
        host.appendChild(err);
        if (el.closest('.kkp-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
    }

    window.showFieldError = showFieldError;

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

    // ── Name fields: auto-uppercase + soft/hard length gates ──
    function resetLongNameConfirmInput() {
        const input = document.getElementById('kkpLongNameConfirmInput');
        const hint = document.getElementById('kkpLongNameConfirmHint');
        if (input) {
            input.value = '';
        }
        if (hint) {
            hint.hidden = true;
        }
    }

    function syncLongNameConfirmFromTypedYes() {
        const input = document.getElementById('kkpLongNameConfirmInput');
        const hint = document.getElementById('kkpLongNameConfirmHint');
        const typed = (input?.value || '').trim().toLowerCase();
        const ok = typed === 'yes';

        if (hint) {
            hint.hidden = typed === '' || ok;
        }

        return ok;
    }

    function openLongNameModal(el, nextValue, caret) {
        const modal = document.getElementById('kkpLongNameModal');
        if (!modal) {
            kkpLongNameAllowed = true;
            el.value = nextValue.slice(0, KKP_NAME_MAX_CHARS);
            kkpSyncNameMaxIndicator(el);
            return;
        }

        kkpPendingLongName = { el, value: nextValue.slice(0, KKP_NAME_MAX_CHARS), caret };
        resetLongNameConfirmInput();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('kkpLongNameConfirmInput')?.focus();
    }

    function closeLongNameModal() {
        const modal = document.getElementById('kkpLongNameModal');
        if (modal) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
        }
        resetLongNameConfirmInput();
        kkpPendingLongName = null;
    }

    function confirmLongNameModal() {
        if (!syncLongNameConfirmFromTypedYes()) {
            const hint = document.getElementById('kkpLongNameConfirmHint');
            if (hint) {
                hint.hidden = false;
            }
            document.getElementById('kkpLongNameConfirmInput')?.focus();
            return;
        }

        if (!kkpPendingLongName?.el) {
            closeLongNameModal();
            return;
        }

        kkpLongNameAllowed = true;
        const { el, value, caret } = kkpPendingLongName;
        el.value = value;
        const pos = Math.min(caret ?? value.length, value.length);
        try {
            el.setSelectionRange(pos, pos);
        } catch (e) {
            // ignore
        }
        kkpSyncNameMaxIndicator(el);
        kkpFitNameInputFont(el);
        closeLongNameModal();
        el.focus();
    }

    function cancelLongNameModal() {
        if (kkpPendingLongName?.el) {
            const el = kkpPendingLongName.el;
            el.value = el.value.slice(0, KKP_NAME_SOFT_MAX);
            try {
                el.setSelectionRange(KKP_NAME_SOFT_MAX, KKP_NAME_SOFT_MAX);
            } catch (e) {
                // ignore
            }
            kkpFitNameInputFont(el);
            el.focus();
        }
        closeLongNameModal();
    }

    document.getElementById('kkpLongNameConfirmInput')?.addEventListener('input', function () {
        if (syncLongNameConfirmFromTypedYes()) {
            confirmLongNameModal();
        }
    });

    document.getElementById('kkpLongNameConfirmInput')?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (syncLongNameConfirmFromTypedYes()) {
                confirmLongNameModal();
            } else {
                const hint = document.getElementById('kkpLongNameConfirmHint');
                if (hint) {
                    hint.hidden = false;
                }
            }
        }
    });

    document.getElementById('kkpLongNameCancelBtn')?.addEventListener('click', cancelLongNameModal);
    document.getElementById('kkpLongNameCloseBtn')?.addEventListener('click', cancelLongNameModal);
    document.getElementById('kkpLongNameBackdrop')?.addEventListener('click', cancelLongNameModal);

    function bindNameFieldInput(el) {
        if (!el) {
            return;
        }

        el.addEventListener('input', function () {
            const start = this.selectionStart;
            const before = this.value;
            let next = kkpSanitizeNameInput(before);

            // Keep caret stable when sanitize removes leading/extra spaces.
            const removed = Math.max(0, before.length - next.length);
            const caret = Math.max(0, (start ?? next.length) - removed);

            if (next.length > KKP_NAME_MAX_CHARS) {
                next = next.slice(0, KKP_NAME_MAX_CHARS);
                this.value = next;
                kkpSyncNameMaxIndicator(this);
                try {
                    this.setSelectionRange(KKP_NAME_MAX_CHARS, KKP_NAME_MAX_CHARS);
                } catch (e) {
                    // ignore
                }
                kkpFitNameInputFont(this);
                return;
            }

            if (!kkpLongNameAllowed && next.length > KKP_NAME_SOFT_MAX) {
                this.value = next.slice(0, KKP_NAME_SOFT_MAX);
                openLongNameModal(this, next, caret);
                kkpFitNameInputFont(this);
                return;
            }

            this.value = next;
            try {
                this.setSelectionRange(caret, caret);
            } catch (e) {
                // ignore
            }

            kkpSyncNameMaxIndicator(this);
            kkpFitNameInputFont(this);
        });

        el.addEventListener('blur', function () {
            this.value = kkpNormalizeNameValue(this.value).toUpperCase();
            kkpSyncNameMaxIndicator(this);
            kkpFitNameInputFont(this);
        });
    }

    ['kkpLastName', 'kkpFirstName', 'kkpMiddleName'].forEach((id) => {
        bindNameFieldInput(document.getElementById(id));
    });

    const lastNameInput = document.getElementById('kkpLastName');

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
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationError = data.errors?.email?.[0]
                || data.message
                || 'Please enter a valid email address.';
            return {
                exists: false,
                validation_error: validationError,
                message: validationError,
            };
        }
        return data;
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
        const statusEl = document.getElementById('kkpSignatureStatus');
        if (statusEl) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.classList.remove('is-valid', 'is-invalid');
        }
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
            touched = true;
            runValidation();
        });

        el.addEventListener('blur', () => {
            touched = true;
            runValidation();
        });
    }

    bindRealtimeField(lastNameInput, kkpValidateLastName);

    // ── Contact number: 09XXXXXXXXX only (11 digits) ──
    // Required message only on submit. Invalid/fake messages update in real time.
    const contactInput = document.getElementById('kkpContactNumber');
    if (contactInput) {
        if (!contactInput.value) {
            contactInput.value = '09';
        }
        contactInput.addEventListener('focus', function () {
            if (!this.value) {
                this.value = '09';
            }
        });
        const runContactRealtimeValidation = () => {
            const message = kkpValidateContact(contactInput.value, false);
            if (message) {
                showFieldError(contactInput, message);
            } else {
                clearFieldError(contactInput);
            }
        };
        contactInput.addEventListener('input', function () {
            let value = (this.value || '').replace(/\D/g, '');
            if (!value.startsWith('09')) {
                value = value.startsWith('9') ? `0${value}` : `09${value.replace(/^0+/, '')}`;
            }
            this.value = value.slice(0, 11);
            runContactRealtimeValidation();
        });
        contactInput.addEventListener('blur', runContactRealtimeValidation);
    }

    // ── Auto-fill signature name from name fields ──
    // When any name field changes, update the signature name input automatically
    function updateSignatureName() {
        const last = String((document.getElementById('kkpLastName') || {}).value || '').trim();
        const first = String((document.getElementById('kkpFirstName') || {}).value || '').trim();
        const middle = String((document.getElementById('kkpMiddleName') || {}).value || '').trim();
        const suffixSelect = document.getElementById('kkpSuffix');
        const customSuffix = document.getElementById('kkpCustomSuffix');
        const rawSuffix = String((suffixSelect || {}).value || '').trim();
        const suffix = rawSuffix === 'Others'
            ? String((customSuffix || {}).value || '').trim()
            : (rawSuffix === 'None' ? '' : rawSuffix);
        const hasSuffix = rawSuffix === 'Others'
            ? String((customSuffix || {}).value || '').trim().length > 0
            : rawSuffix.length > 0;
        const sigNameInput = document.getElementById('kkpSignatureName');
        const triggerBtn = document.getElementById('kkpSignatureTrigger');
        const sigInput = document.getElementById('kkpSignatureData');

        if (!sigNameInput) return;

        const parts = [first, middle, last, suffix].filter(Boolean);
        const fullName = parts.join(' ');
        sigNameInput.value = fullName;
        kkpFitSignatureNameFont(sigNameInput);

        // Sign requires last name, first name, and suffix (None counts)
        if (triggerBtn) {
            const canSign = last.length > 0 && first.length > 0 && hasSuffix;
            triggerBtn.disabled = !canSign;
            triggerBtn.setAttribute('aria-disabled', canSign ? 'false' : 'true');
        }

        const clearSavedBtn = document.getElementById('kkpSignatureClearSaved');
        if (clearSavedBtn) {
            const hasSig = !!(sigInput && sigInput.value);
            clearSavedBtn.hidden = !hasSig;
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
            if (suffixSelect.value === 'Others') {
                const trimmed = (this.value || '').trim();
                if (!trimmed) {
                    showFieldError(this, 'Please specify your suffix.');
                } else if (!isValidSuffixText(trimmed)) {
                    showFieldError(this, 'Only text and valid Roman numeral suffixes are allowed.');
                } else {
                    clearFieldError(this);
                }
            }
        });
        customSuffixInput.addEventListener('blur', function () {
            this.value = (this.value || '').trim();
            if (suffixSelect.value === 'Others') {
                const trimmed = this.value;
                if (!trimmed) {
                    showFieldError(this, 'Please specify your suffix.');
                } else if (!isValidSuffixText(trimmed)) {
                    showFieldError(this, 'Only text and valid Roman numeral suffixes are allowed.');
                } else {
                    clearFieldError(this);
                }
            }
        });

        suffixSelect.addEventListener('change', toggleCustomSuffix);
        toggleCustomSuffix();
    }

    // Name fit on load/resize (input filtering lives in bindNameFieldInput — spaces + max 150).
    const firstNameEl = document.getElementById('kkpFirstName');
    const middleNameEl = document.getElementById('kkpMiddleName');
    const nameFitInputs = [lastNameInput, firstNameEl, middleNameEl].filter(Boolean);

    const runNameFit = (el) => {
        kkpFitNameInputFont(el);
        kkpSyncNameMaxIndicator(el);
    };

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

    bindRealtimeField(firstNameEl, kkpValidateFirstName);
    bindRealtimeField(middleNameEl, kkpValidateMiddleName);
    bindRealtimeField(kkpPurokField(), kkpValidatePurok);

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

        const runEmailValidation = async (checkServer) => {
            const message = kkpValidateEmail(emailInput.value, emailTouched, { strict: checkServer });
            if (message) {
                showFieldError(emailInput, message);
                return;
            }

            clearFieldError(emailInput);

            // DNS / existence only on blur/submit — no "email is valid" success text.
            if (!checkServer || !emailTouched) {
                return;
            }

            const value = (emailInput.value || '').trim();
            if (!value) {
                return;
            }

            const originalEmail = (window.__KK_PROFILING_ORIGINAL_EMAIL || emailInput.dataset.originalEmail || '').trim().toLowerCase();
            if (originalEmail && value.toLowerCase() === originalEmail) {
                delete emailInput.dataset.emailExists;
                return;
            }

            clearTimeout(emailCheckTimer);
            emailCheckTimer = setTimeout(async () => {
                try {
                    const result = await checkEmailExists(value);
                    if (result.validation_error) {
                        showFieldError(emailInput, result.validation_error);
                        return;
                    }
                    if (!isWizardForm && result.exists) {
                        emailInput.dataset.emailExists = 'true';
                        showFieldError(emailInput, result.message || 'This email already exists. Please use a different email address.');
                    } else {
                        delete emailInput.dataset.emailExists;
                        clearFieldError(emailInput);
                    }
                } catch (err) {
                    // Non-blocking network errors; submit still validates on the server.
                }
            }, 300);
        };

        emailInput.addEventListener('input', () => {
            normalizeEmailValue();
            if ((emailInput.value || '').length > KKP_EMAIL_TOTAL_MAX) {
                emailInput.value = (emailInput.value || '').slice(0, KKP_EMAIL_TOTAL_MAX);
            }
            kkpFitEmailInputFont(emailInput);
            if ((emailInput.value || '').length > 0) {
                emailTouched = true;
            }
            delete emailInput.dataset.emailExists;
            runEmailValidation(false);
        });

        emailInput.addEventListener('blur', () => {
            normalizeEmailValue();
            emailInput.value = (emailInput.value || '').trim().toLowerCase().slice(0, KKP_EMAIL_TOTAL_MAX);
            kkpFitEmailInputFont(emailInput);
            emailTouched = true;
            runEmailValidation(true);
        });

        emailInput.maxLength = KKP_EMAIL_TOTAL_MAX;
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
        err.setAttribute('role', 'alert');
        err.textContent = message;
        // Always place below label + options so it never covers checkboxes.
        block.appendChild(err);
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
        const err = document.createElement('span');
        err.className = 'kkp-field-error';
        err.textContent = msg;
        host.appendChild(err);
        if (el.closest('.kkp-name-col')) {
            kkpSyncNameMaxIndicator(el);
        }
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
        errors.push('Purok/Zone is required.');
        fieldError(purok, 'Purok/Zone is required.');
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
    const emailMsg = kkpValidateEmail(email?.value, true, { strict: true });
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
    const contactErr = kkpValidateContact(contact?.value || '', true);
    if (contactErr) {
        errors.push(contactErr);
        fieldError(contact, contactErr);
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

    // ── 18. Name and Signature of Participant ──
    const sigName = document.getElementById('kkpSignatureName');
    const sigNameVal = (sigName?.value || '').trim();
    if (!sigNameVal) {
        errors.push('Name and Signature of Participant is required.');
        const sigSection = document.querySelector('.kkp-sig-section-left');
        if (sigSection) {
            let err = sigSection.querySelector('.kkp-field-error');
            if (!err) {
                err = document.createElement('span');
                err.className = 'kkp-field-error';
                err.textContent = 'Name and Signature of Participant is required.';
                sigSection.appendChild(err);
            }
        }
    } else if (sigNameVal.length > 255) {
        errors.push('Participant name must not exceed 255 characters.');
    }

    const sigData = document.getElementById('kkpSignatureData');
    if (!sigData || !sigData.value.trim()) {
        const sigRequiredMsg = 'Signature is required. Please sign.';
        errors.push(sigRequiredMsg);
        // Show only under Sign/Clear — avoid duplicate .kkp-field-error above.
        document.querySelector('.kkp-sig-section-left')?.querySelectorAll('.kkp-field-error').forEach((node) => {
            if ((node.textContent || '').toLowerCase().includes('signature is required')) {
                node.remove();
            }
        });
        const statusEl = document.getElementById('kkpSignatureStatus');
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.textContent = sigRequiredMsg;
            statusEl.classList.add('is-invalid');
            statusEl.classList.remove('is-valid');
        }
    }

    // ── If errors, scroll to first error and stop ──
    if (errors.length > 0) {
        const firstErr = document.querySelector('.kkp-field-error, .kkp-demo-block-error, #kkpSignatureStatus.is-invalid');
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
            const emailCheckResult = await emailCheckResponse.json().catch(() => ({}));
            if (!emailCheckResponse.ok) {
                const validationError = emailCheckResult.errors?.email?.[0]
                    || emailCheckResult.message
                    || 'Please enter a valid email address.';
                fieldError(emailField, validationError);
                emailField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
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
    const statusEl = document.getElementById('kkpSignatureStatus');
    const padStatusEl = document.getElementById('kkpSignaturePadStatus');
    const uploadInput = document.getElementById('kkpSignatureUploadInput');

    if (!canvas || !overlay) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasSignature = false;

    const KKP_SIG_MAX_BYTES = 2 * 1024 * 1024;
    const KKP_SIG_NEAR_WHITE = 250;
    const KKP_SIG_BG_RATIO = 0.90;
    const KKP_SIG_MIN_INK_RATIO = 0.0015;
    const MSG_VALID = 'Valid image selected';
    const MSG_INVALID = 'Please upload a valid image.';
    const MSG_FORMAT = 'Only PNG, JPG, and JPEG images are allowed.';
    const MSG_FORMAT_UX = 'Please upload a PNG, JPG, or JPEG image with a white background.';
    const MSG_TOO_LARGE = 'Image size must not exceed 2 MB.';
    const MSG_NON_WHITE = 'Please upload an image with a plain white background.';
    const MSG_BLANK = 'Please upload an image containing visible text or a signature.';
    const VIEWPORT_LOCK = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';

    let viewportMetaPrev = null;
    let visualViewportBound = false;

    function syncOverlayToVisualViewport() {
        if (!overlay || overlay.style.display === 'none') {
            return;
        }
        const vv = window.visualViewport;
        if (!vv) {
            return;
        }
        overlay.style.top = vv.offsetTop + 'px';
        overlay.style.left = vv.offsetLeft + 'px';
        overlay.style.width = vv.width + 'px';
        overlay.style.height = vv.height + 'px';
        overlay.style.right = 'auto';
        overlay.style.bottom = 'auto';
    }

    function clearOverlayViewportStyles() {
        if (!overlay) {
            return;
        }
        overlay.style.top = '';
        overlay.style.left = '';
        overlay.style.width = '';
        overlay.style.height = '';
        overlay.style.right = '';
        overlay.style.bottom = '';
    }

    function lockViewportForSignatureModal() {
        const meta = document.querySelector('meta[name="viewport"]');
        if (meta) {
            if (viewportMetaPrev === null) {
                viewportMetaPrev = meta.getAttribute('content') || 'width=device-width, initial-scale=1.0';
            }
            // Force browser zoom back to 100% so the modal is fully visible and centered.
            meta.setAttribute('content', VIEWPORT_LOCK);
        }

        document.documentElement.classList.add('kkp-sig-modal-open');
        document.body.classList.add('kkp-sig-modal-open');

        try {
            window.scrollTo({ left: 0, top: window.scrollY, behavior: 'auto' });
        } catch (e) {
            window.scrollTo(0, window.scrollY);
        }

        syncOverlayToVisualViewport();

        const vv = window.visualViewport;
        if (vv && !visualViewportBound) {
            vv.addEventListener('resize', syncOverlayToVisualViewport);
            vv.addEventListener('scroll', syncOverlayToVisualViewport);
            visualViewportBound = true;
        }
    }

    function unlockViewportForSignatureModal() {
        const meta = document.querySelector('meta[name="viewport"]');
        if (meta && viewportMetaPrev !== null) {
            meta.setAttribute('content', viewportMetaPrev);
            viewportMetaPrev = null;
        }

        const vv = window.visualViewport;
        if (vv && visualViewportBound) {
            vv.removeEventListener('resize', syncOverlayToVisualViewport);
            vv.removeEventListener('scroll', syncOverlayToVisualViewport);
            visualViewportBound = false;
        }

        clearOverlayViewportStyles();
        document.documentElement.classList.remove('kkp-sig-modal-open');
        document.body.classList.remove('kkp-sig-modal-open');
    }

    function setSignatureStatus(message, isValid) {
        if (!statusEl) return;
        if (!message) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.classList.remove('is-valid', 'is-invalid');
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('is-valid', Boolean(isValid));
        statusEl.classList.toggle('is-invalid', !isValid);
    }

    function setPadStatus(message, isValid) {
        if (!padStatusEl) return;
        if (!message) {
            padStatusEl.hidden = true;
            padStatusEl.textContent = '';
            padStatusEl.classList.remove('is-valid', 'is-invalid');
            return;
        }
        padStatusEl.hidden = false;
        padStatusEl.textContent = message;
        padStatusEl.classList.toggle('is-valid', Boolean(isValid));
        padStatusEl.classList.toggle('is-invalid', !isValid);
    }

    function applySavedSignature(dataUrl) {
        if (sigInput) sigInput.value = dataUrl;
        if (typeof window.clearSignatureError === 'function') {
            window.clearSignatureError();
        }
        // Success feedback only inside the Sign pad modal — not under Sign/Clear
        setSignatureStatus('', false);

        if (sigPreview && sigOverlay) {
            sigPreview.src = dataUrl;
            sigOverlay.style.display = 'flex';
        }

        // Keep Sign enabled so the user can reopen pad to redraw or upload again
        if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.setAttribute('aria-disabled', 'false');
        }
        if (clearSavedBtn) clearSavedBtn.hidden = false;
        hasSignature = true;
    }

    function fillWhiteBackground() {
        const rect = canvas.getBoundingClientRect();
        const w = rect.width || 500;
        const h = rect.height || 260;
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
        // Restore drawing transform for CSS pixels
        const dpr = window.devicePixelRatio || 1;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    function exportSignatureWithWhiteBackground() {
        const out = document.createElement('canvas');
        out.width = canvas.width;
        out.height = canvas.height;
        const octx = out.getContext('2d');
        octx.fillStyle = '#ffffff';
        octx.fillRect(0, 0, out.width, out.height);
        octx.drawImage(canvas, 0, 0);
        return out.toDataURL('image/png');
    }

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

        fillWhiteBackground();

        if (snapshot && snapshot !== 'data:,') {
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, cssW, cssH);
            };
            img.src = snapshot;
        }
    }

    function openPad() {
        lockViewportForSignatureModal();
        overlay.style.display = 'flex';
        setPadStatus('', false);

        // Wait for viewport scale reset + layout so canvas sizing/centering are correct.
        requestAnimationFrame(function () {
            syncOverlayToVisualViewport();
            setupCanvas(false);
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
        });
    }

    function closePad() {
        overlay.style.display = 'none';
        unlockViewportForSignatureModal();
        setPadStatus('', false);
        if (uploadInput) uploadInput.value = '';
    }

    function clearCanvas() {
        fillWhiteBackground();
        hasSignature = false;
        showPlaceholder();
        setPadStatus('', false);
        if (uploadInput) uploadInput.value = '';
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
        setPadStatus('', false);
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
        // Non-near-white opaque pixels count as ink
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            const a = data[i + 3];
            if (a < 32) continue;
            if (r < 250 || g < 250 || b < 250) return true;
        }
        return false;
    }

    function saveSig() {
        if (!hasSignature || !canvasHasInk()) {
            setPadStatus(MSG_BLANK, false);
            return;
        }
        const confirmOverlay = document.getElementById('kkpSigConfirmOverlay');
        if (confirmOverlay) confirmOverlay.style.display = 'flex';
    }

    function doSaveSig() {
        if (!canvasHasInk()) {
            setPadStatus(MSG_BLANK, false);
            return;
        }

        applySavedSignature(exportSignatureWithWhiteBackground());

        const confirmOverlay = document.getElementById('kkpSigConfirmOverlay');
        if (confirmOverlay) confirmOverlay.style.display = 'none';

        closePad();
    }

    function clearSavedSignature() {
        if (sigInput) sigInput.value = '';
        if (sigOverlay) sigOverlay.style.display = 'none';
        if (sigPreview) sigPreview.removeAttribute('src');
        if (clearSavedBtn) clearSavedBtn.hidden = true;
        if (uploadInput) uploadInput.value = '';
        hasSignature = false;
        setSignatureStatus('', false);
        setPadStatus('', false);
        showPlaceholder();
        clearCanvas();
        if (typeof window.kkpRefreshSignatureName === 'function') {
            window.kkpRefreshSignatureName();
        }
    }

    function isNearWhitePixel(r, g, b, a) {
        // Transparent is not a white background
        if (a < 32) return false;
        return r >= KKP_SIG_NEAR_WHITE && g >= KKP_SIG_NEAR_WHITE && b >= KKP_SIG_NEAR_WHITE;
    }

    function analyzeImageData(imageData, width, height) {
        const data = imageData.data;
        const step = width * height > 800000 ? 2 : 1;
        let sampleTotal = 0;
        let sampleWhite = 0;
        let ink = 0;

        for (let y = 0; y < height; y += step) {
            for (let x = 0; x < width; x += step) {
                const i = (y * width + x) * 4;
                sampleTotal++;
                if (isNearWhitePixel(data[i], data[i + 1], data[i + 2], data[i + 3])) {
                    sampleWhite++;
                } else {
                    ink++;
                }
            }
        }

        return {
            bgRatio: sampleTotal ? sampleWhite / sampleTotal : 0,
            inkRatio: sampleTotal ? ink / sampleTotal : 0,
        };
    }

    function drawValidatedImageOntoPad(dataUrl) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = function () {
                const rect = canvas.getBoundingClientRect();
                const cssW = rect.width || 500;
                const cssH = rect.height || 260;
                fillWhiteBackground();
                // Fit image into pad while preserving aspect ratio
                const scale = Math.min(cssW / img.naturalWidth, cssH / img.naturalHeight, 1);
                const dw = img.naturalWidth * scale;
                const dh = img.naturalHeight * scale;
                const dx = (cssW - dw) / 2;
                const dy = (cssH - dh) / 2;
                ctx.drawImage(img, dx, dy, dw, dh);
                hasSignature = true;
                hidePlaceholder();
                resolve();
            };
            img.onerror = function () {
                reject(new Error(MSG_INVALID));
            };
            img.src = dataUrl;
        });
    }

    function validateUploadedSignatureFile(file) {
        return new Promise((resolve) => {
            if (!file) {
                resolve({ ok: false, error: MSG_INVALID });
                return;
            }

            const name = String(file.name || '').toLowerCase();
            const type = String(file.type || '').toLowerCase();
            const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            const allowedExt = /\.(png|jpe?g)$/i.test(name);

            if (type && !allowedTypes.includes(type)) {
                resolve({ ok: false, error: MSG_FORMAT });
                return;
            }

            if (!type && !allowedExt) {
                resolve({ ok: false, error: MSG_FORMAT });
                return;
            }

            if (file.size > KKP_SIG_MAX_BYTES) {
                resolve({ ok: false, error: MSG_TOO_LARGE });
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            const img = new Image();

            img.onload = function () {
                try {
                    if (img.naturalWidth < 40 || img.naturalHeight < 20) {
                        URL.revokeObjectURL(objectUrl);
                        resolve({ ok: false, error: MSG_INVALID });
                        return;
                    }

                    if (img.naturalWidth > 4000 || img.naturalHeight > 4000) {
                        URL.revokeObjectURL(objectUrl);
                        resolve({ ok: false, error: 'Signature image is too large to process.' });
                        return;
                    }

                    const out = document.createElement('canvas');
                    out.width = img.naturalWidth;
                    out.height = img.naturalHeight;
                    const octx = out.getContext('2d', { willReadFrequently: true });
                    // Do NOT fill white first — transparency must fail white-bg check
                    octx.drawImage(img, 0, 0);

                    const imageData = octx.getImageData(0, 0, out.width, out.height);
                    const analysis = analyzeImageData(imageData, out.width, out.height);
                    URL.revokeObjectURL(objectUrl);

                    if (analysis.bgRatio < KKP_SIG_BG_RATIO) {
                        resolve({ ok: false, error: MSG_NON_WHITE });
                        return;
                    }

                    if (analysis.inkRatio < KKP_SIG_MIN_INK_RATIO) {
                        resolve({ ok: false, error: MSG_BLANK });
                        return;
                    }

                    // Composite onto white for pad preview / save export
                    const exportCanvas = document.createElement('canvas');
                    exportCanvas.width = out.width;
                    exportCanvas.height = out.height;
                    const ectx = exportCanvas.getContext('2d');
                    ectx.fillStyle = '#ffffff';
                    ectx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
                    ectx.drawImage(out, 0, 0);

                    resolve({ ok: true, dataUrl: exportCanvas.toDataURL('image/png') });
                } catch (e) {
                    URL.revokeObjectURL(objectUrl);
                    resolve({ ok: false, error: MSG_INVALID });
                }
            };

            img.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                resolve({ ok: false, error: MSG_INVALID });
            };

            img.src = objectUrl;
        });
    }

    async function handleSignatureUpload(event) {
        const file = event.target?.files?.[0];
        if (!file) return;

        setPadStatus('Validating image…', true);
        const result = await validateUploadedSignatureFile(file);

        if (!result.ok) {
            setPadStatus('✗ ' + (result.error || MSG_FORMAT_UX), false);
            if (uploadInput) uploadInput.value = '';
            return;
        }

        try {
            await drawValidatedImageOntoPad(result.dataUrl);
            setPadStatus('✓ ' + MSG_VALID, true);
        } catch (e) {
            setPadStatus('✗ ' + MSG_INVALID, false);
        }

        if (uploadInput) uploadInput.value = '';
    }

    // Button events
    if (triggerBtn) {
        triggerBtn.addEventListener('click', function () {
            if (triggerBtn.disabled || triggerBtn.getAttribute('aria-disabled') === 'true') {
                return;
            }
            setSignatureStatus('', false);
            openPad();
        });
    }

    if (uploadInput) {
        uploadInput.addEventListener('change', handleSignatureUpload);
    }

    if (closeBtn) closeBtn.addEventListener('click', closePad);
    if (clearBtn) clearBtn.addEventListener('click', clearCanvas);
    if (saveBtn) saveBtn.addEventListener('click', saveSig);
    if (clearSavedBtn) clearSavedBtn.addEventListener('click', clearSavedSignature);

    window.kkpRestoreSignaturePreview = function (dataUrl) {
        if (!dataUrl || !sigInput) {
            return;
        }
        applySavedSignature(dataUrl);
    };

    // Initial state (in case of server-side repopulation)
    if (sigInput && sigInput.value && sigPreview && sigOverlay) {
        applySavedSignature(sigInput.value);
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

/* Mobile: desktop layout scaled to fit (update form only; wizard has its own scaler) */
(function () {
    'use strict';

    let applying = false;
    let rafId = null;

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function applyScale(root) {
        const shell = root.querySelector('.kkp-fs-scale-shell');
        const inner = root.querySelector('.kkp-fs-scale-inner');
        if (!shell || !inner || applying) {
            return;
        }

        if (!isMobile()) {
            shell.style.height = '';
            shell.style.width = '';
            inner.style.width = '';
            inner.style.minWidth = '';
            inner.style.transform = '';
            inner.style.zoom = '';
            return;
        }

        applying = true;
        try {
            inner.style.zoom = '1';
            inner.style.transform = 'none';
            inner.style.width = '860px';
            inner.style.minWidth = '860px';

            const designWidth = Math.max(860, Math.ceil(inner.scrollWidth || 860));
            inner.style.width = `${designWidth}px`;
            inner.style.minWidth = `${designWidth}px`;

            const available = Math.max(
                1,
                Math.floor(shell.clientWidth || root.clientWidth || window.innerWidth || 1),
            );
            const scale = Math.min(1, available / designWidth);

            if ('zoom' in inner.style) {
                inner.style.transform = '';
                inner.style.zoom = String(scale);
                shell.style.width = '100%';
                shell.style.height = '';
            } else {
                inner.style.zoom = '';
                inner.style.transformOrigin = 'top left';
                inner.style.transform = `scale(${scale})`;
                shell.style.width = '100%';
                shell.style.height = `${Math.ceil(inner.scrollHeight * scale)}px`;
            }
        } finally {
            applying = false;
        }
    }

    function run() {
        // Wizard registration page is handled by kkprofiling-wizard.js
        if (document.getElementById('kkpRegistrationWizard')) {
            return;
        }
        document.querySelectorAll('#kkpuFormSection').forEach((root) => applyScale(root));
    }

    function scheduleRun() {
        if (rafId) {
            cancelAnimationFrame(rafId);
        }
        rafId = requestAnimationFrame(() => {
            rafId = null;
            run();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleRun);
    } else {
        scheduleRun();
    }

    window.addEventListener('resize', scheduleRun);
    window.addEventListener('orientationchange', () => setTimeout(scheduleRun, 200));
    window.addEventListener('load', scheduleRun);
    setTimeout(scheduleRun, 80);
    setTimeout(scheduleRun, 300);
})();
