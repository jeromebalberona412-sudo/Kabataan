import { flashGuestToast, showFlashedGuestToast, showGuestToast } from './guest_kabataan_toast';

function filterList(input, itemSelector, emptyEl) {
    if (!input) {
        return;
    }

    const items = Array.from(document.querySelectorAll(itemSelector));
    const apply = () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        items.forEach((item) => {
            const haystack = item.getAttribute('data-guest-barangay')
                || item.getAttribute('data-guest-program')
                || '';
            const show = query === '' || haystack.includes(query);
            item.hidden = !show;
            if (show) {
                visible += 1;
            }
        });
        if (emptyEl) {
            emptyEl.hidden = visible !== 0 || items.length === 0;
        }
    };

    input.addEventListener('input', apply);
    input.form?.addEventListener('submit', (event) => {
        event.preventDefault();
    });
}

function bindLogout() {
    const modal = document.getElementById('guestKabataanLogout');
    const openBtn = document.getElementById('guestKabataanLogoutBtn');
    if (!modal || !openBtn) {
        return;
    }

    const logoutForm = modal.querySelector('form');
    let loggingOut = false;

    const close = () => {
        if (loggingOut) {
            return;
        }
        modal.hidden = true;
        openBtn.focus();
    };

    openBtn.addEventListener('click', () => {
        modal.hidden = false;
        modal.querySelector('[data-guest-logout-close]')?.focus();
    });

    logoutForm?.addEventListener('submit', (event) => {
        if (loggingOut) {
            event.preventDefault();
            return;
        }
        loggingOut = true;
        modal.querySelectorAll('button[data-guest-logout-close]').forEach((button) => {
            button.disabled = true;
        });
        setBusy(logoutForm.querySelector('button[type="submit"]'), true, 'Logging out');
        openBtn.disabled = true;
    });

    // bfcache restores the page with the busy state still applied when the user presses Back.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted || !loggingOut) {
            return;
        }
        loggingOut = false;
        openBtn.disabled = false;
        modal.querySelectorAll('button[data-guest-logout-close]').forEach((button) => {
            button.disabled = false;
        });
        setBusy(logoutForm?.querySelector('button[type="submit"]'), false);
        modal.hidden = true;
    });

    modal.querySelectorAll('[data-guest-logout-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
}

filterList(
    document.getElementById('guestKabataanBarangaySearch'),
    '[data-guest-barangay]',
    document.getElementById('guestKabataanBarangayEmpty'),
);

filterList(
    document.getElementById('guestKabataanProgramSearch'),
    '[data-guest-program]',
    document.getElementById('guestKabataanProgramEmpty'),
);

bindLogout();
bindGuestClaim();
bindGuestActivation();
showFlashedGuestToast();

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function setBusy(button, busy, busyLabel) {
    if (!button) {
        return;
    }
    if (busy) {
        if (!button.dataset.idleLabel) {
            button.dataset.idleLabel = button.textContent;
        }
        button.disabled = true;
        button.classList.add('is-busy');
        button.setAttribute('aria-busy', 'true');
        const label = document.createElement('span');
        label.textContent = String(busyLabel || 'Submitting').replace(/\.+$/, '');
        const dots = document.createElement('span');
        dots.className = 'guest-kabataan-btn__dots';
        dots.setAttribute('aria-hidden', 'true');
        button.replaceChildren(label, dots);
        return;
    }
    button.removeAttribute('aria-busy');
    button.disabled = false;
    button.classList.remove('is-busy');
    if (button.dataset.idleLabel) {
        button.textContent = button.dataset.idleLabel;
    }
}

function bindGuestClaim() {
    const home = document.getElementById('guestKabataanHome');
    const modal = document.getElementById('guestKabataanApplyModal');
    if (!home || !modal) {
        return;
    }

    const ask = document.getElementById('guestKabataanApplyAsk');
    const form = document.getElementById('guestKabataanClaimForm');
    const errorEl = document.getElementById('guestKabataanClaimError');
    const lockEl = document.getElementById('guestKabataanLock');
    const pickedEl = document.getElementById('guestKabataanSecqPicked');
    let remaining = Number(home.dataset.remaining || 0);
    let lockTimer = null;

    function showError(message) {
        if (!errorEl) {
            return;
        }
        errorEl.hidden = !message;
        errorEl.textContent = message || '';
    }

    function setLocked(seconds) {
        remaining = Math.max(0, Number(seconds) || 0);
        home.dataset.locked = remaining > 0 ? '1' : '0';
        document.querySelectorAll('[data-guest-apply], [data-guest-add-email]').forEach((button) => {
            button.disabled = remaining > 0;
        });
        const message = lockMessage(remaining);
        const lockedNoticeCopy = document.querySelector('#guestKabataanWrongModal[data-mode="locked"]:not([hidden]) #guestKabataanWrongCopy');
        if (lockedNoticeCopy) {
            lockedNoticeCopy.textContent = message || 'Maaari ka nang sumubok muli.';
        }
        if (!lockEl) {
            return;
        }
        lockEl.hidden = !message;
        lockEl.textContent = message;
    }

    function lockMessage(seconds) {
        if (seconds <= 0) {
            return '';
        }
        const minutes = Math.floor(seconds / 60);
        const padded = String(seconds % 60).padStart(2, '0');
        return `Masyadong maraming maling subok. Maaari kang sumubok muli pagkalipas ng ${minutes}:${padded}.`;
    }

    async function refreshLock() {
        try {
            const response = await fetch(home.dataset.lockUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            setLocked(payload.locked ? payload.remaining_seconds : 0);
        } catch (error) {
            setLocked(remaining);
        }
    }

    function tickLock() {
        if (remaining <= 0) {
            return;
        }
        remaining -= 1;
        setLocked(remaining);
        if (remaining <= 0) {
            refreshLock();
        }
    }

    setLocked(home.dataset.locked === '1' ? remaining : 0);
    lockTimer = window.setInterval(tickLock, 1000);
    window.addEventListener('pageshow', refreshLock);

    const securityModal = document.getElementById('guestKabataanSecurityModal');
    const securityForm = document.getElementById('guestKabataanSecurityForm');
    const securityError = document.getElementById('guestKabataanSecurityError');
    const noDataModal = document.getElementById('guestKabataanNoDataModal');
    const questionRoot = securityModal || modal;

    function showAsk() {
        modal.classList.add('guest-kabataan-modal--ask');
        modal.classList.remove('guest-kabataan-modal--confirm');
        if (ask) {
            ask.hidden = false;
        }
        if (form) {
            form.hidden = true;
        }
    }

    function showConfirm() {
        modal.classList.remove('guest-kabataan-modal--ask');
        modal.classList.add('guest-kabataan-modal--confirm');
        if (ask) {
            ask.hidden = true;
        }
        if (form) {
            form.hidden = false;
            form.querySelector('[name="last_name"]')?.focus();
        }
    }

    function resetSecurityForm() {
        securityForm?.reset();
        questionRoot.querySelectorAll('.guest-kabataan-secq__body').forEach((body) => {
            body.hidden = true;
        });
        questionRoot.querySelectorAll('.guest-kabataan-secq__custom').forEach((wrap) => {
            if (wrap.closest('.guest-kabataan-secq__item')?.dataset.kind !== 'own') {
                wrap.hidden = true;
            }
        });
        questionRoot.querySelectorAll('.guest-kabataan-secq__error').forEach((node) => {
            node.hidden = true;
            node.textContent = '';
        });
        if (securityError) {
            securityError.hidden = true;
            securityError.textContent = '';
        }
        updatePicked();
    }

    const touchedFields = new Set();

    function resetClaimForm() {
        form?.reset();
        if (customSuffix) {
            customSuffix.hidden = true;
        }
        clearFieldErrors();
        touchedFields.clear();
        showError('');
        resetSecurityForm();
    }

    function openModal(event) {
        if (home.dataset.locked === '1') {
            return;
        }
        const action = event.currentTarget?.getAttribute('data-guest-action') || 'apply';
        const programName = event.currentTarget?.closest('.guest-kabataan-program')?.querySelector('h2')?.textContent?.trim() || 'this program';
        const title = document.getElementById('guestKabataanApplyTitle');
        const copy = document.getElementById('guestKabataanApplyCopy');
        if (title) {
            title.textContent = 'Do you already have a KK Profiling?';
        }
        if (copy) {
            copy.textContent = action === 'survey'
                ? 'Answering '+programName+' uses the KK Profiling you already submitted. If you have not filled one out yet, sign up first.'
                : 'Applying for '+programName+' uses the KK Profiling you already submitted. If you have not filled one out yet, sign up first.';
        }
        resetClaimForm();
        showAsk();
        modal.hidden = false;
    }

    function closeModal() {
        modal.hidden = true;
    }

    document.querySelectorAll('[data-guest-apply]').forEach((button) => {
        button.addEventListener('click', openModal);
    });
    modal.querySelectorAll('[data-guest-apply-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });
    document.getElementById('guestKabataanHasProfiling')?.addEventListener('click', () => {
        showConfirm();
    });
    document.querySelectorAll('[data-guest-add-email]').forEach((button) => {
        button.addEventListener('click', () => {
            if (home.dataset.locked === '1') {
                return;
            }
            resetClaimForm();
            modal.hidden = false;
            showConfirm();
        });
    });

    const suffix = document.getElementById('guestKabataanSuffix');
    const customSuffix = document.getElementById('guestKabataanCustomSuffixWrap');
    suffix?.addEventListener('change', () => {
        if (customSuffix) {
            customSuffix.hidden = suffix.value !== 'Others';
        }
        syncSecurityVisibility(false);
    });

    function selectedQuestions() {
        return Array.from(questionRoot.querySelectorAll('.guest-kabataan-secq__check:checked'))
            .map((input) => input.closest('.guest-kabataan-secq__item'))
            .filter(Boolean);
    }

    function updatePicked() {
        const count = selectedQuestions().length;
        if (pickedEl) {
            pickedEl.textContent = `Napili: ${count} ng 3`;
        }
        questionRoot.querySelectorAll('.guest-kabataan-secq__check').forEach((input) => {
            input.disabled = count >= 3 && !input.checked;
        });
    }

    questionRoot.querySelectorAll('.guest-kabataan-secq__check').forEach((input) => {
        input.addEventListener('change', () => {
            const card = input.closest('.guest-kabataan-secq__item');
            const body = card?.querySelector('.guest-kabataan-secq__body');
            if (!input.checked && card) {
                card.querySelectorAll('input[type="radio"]').forEach((radio) => {
                    radio.checked = false;
                });
                const custom = card.querySelector('.guest-kabataan-secq__input');
                if (custom) {
                    custom.value = '';
                }
                const customWrap = card.querySelector('.guest-kabataan-secq__custom');
                if (customWrap && card.dataset.kind !== 'own') {
                    customWrap.hidden = true;
                }
            }
            if (body) {
                body.hidden = !input.checked;
            }
            if (input.checked && card?.dataset.kind === 'own') {
                card.querySelector('.guest-kabataan-secq__input')?.focus();
            }
            updatePicked();
        });
    });

    questionRoot.querySelectorAll('.guest-kabataan-secq__item').forEach((card) => {
        card.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const customWrap = card.querySelector('.guest-kabataan-secq__custom');
                const custom = card.querySelector('.guest-kabataan-secq__input');
                const show = radio.checked && radio.value === 'Iba pa';
                if (customWrap) {
                    customWrap.hidden = !show;
                }
                if (!show && custom) {
                    custom.value = '';
                }
                if (show) {
                    custom?.focus();
                }
            });
        });
        card.querySelector('.guest-kabataan-secq__input')?.addEventListener('input', (event) => {
            const input = event.target;
            if (card.dataset.kind === 'number') {
                const raw = input.value;
                input.value = raw.replace(/[^\d].*$/, '').replace(/\D/g, '').slice(0, 2);
                return;
            }
            input.value = input.value.replace(/[^\p{L}]/gu, '').slice(0, 15);
        });
    });

    const NAME_PATTERN = /^[A-Za-z.\s]+$/;
    const ROMAN_SUFFIXES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

    function normalizeName(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function sanitizeName(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/[^A-Z.\s]/g, '')
            .replace(/^\s+/, '')
            .replace(/\s{2,}/g, ' ');
    }

    function nameMessage(value, required, label) {
        const raw = String(value || '');
        const normalized = normalizeName(raw);
        if (!normalized) {
            if (raw.length > 0) {
                return 'Minimum 2 characters required.';
            }
            return required ? `${label} is required.` : '';
        }
        if (normalized.length < 2) {
            return 'Minimum 2 characters required.';
        }
        if (normalized.length > 150) {
            return '150 maximum characters only.';
        }
        if (!NAME_PATTERN.test(normalized)) {
            return 'Letters, spaces, and periods only.';
        }
        return '';
    }

    function ageFromBirthday(value) {
        if (!value) {
            return null;
        }
        const born = new Date(`${value}T00:00:00`);
        if (Number.isNaN(born.getTime())) {
            return null;
        }
        const today = new Date();
        let computed = today.getFullYear() - born.getFullYear();
        const month = today.getMonth() - born.getMonth();
        if (month < 0 || (month === 0 && today.getDate() < born.getDate())) {
            computed -= 1;
        }
        return computed;
    }

    function suffixMessage() {
        const value = suffix?.value || '';
        if (!value) {
            return 'Please select a suffix.';
        }
        if (value !== 'Others') {
            return '';
        }
        const custom = normalizeName(form?.custom_suffix?.value || '');
        if (!custom) {
            return 'Please specify your suffix.';
        }
        const compact = custom.replace(/\s+/g, '');
        if (compact.length > 5) {
            return 'Suffix must not exceed 5 characters.';
        }
        const roman = ROMAN_SUFFIXES.includes(compact.toUpperCase());
        const text = /^[A-Za-z.]+$/.test(compact);
        if (!roman && !text) {
            return 'Only text and valid Roman numeral suffixes are allowed.';
        }
        return '';
    }

    function identityErrors() {
        const errors = {};
        const last = nameMessage(form?.last_name?.value, true, 'Last Name');
        const first = nameMessage(form?.first_name?.value, true, 'First Name');
        const middle = nameMessage(form?.middle_name?.value, false, 'Middle Name');
        if (last) {
            errors.last_name = last;
        }
        if (first) {
            errors.first_name = first;
        }
        if (middle) {
            errors.middle_name = middle;
        }
        const suffixError = suffixMessage();
        if (suffixError) {
            errors[suffix?.value === 'Others' ? 'custom_suffix' : 'suffix'] = suffixError;
        }
        if (!form?.purok_zone?.value) {
            errors.purok_zone = 'Purok/Zone is required.';
        }
        if (!form?.querySelector('input[name="sex"]:checked')) {
            errors.sex = 'Please select Sex Assigned by Birth.';
        }
        const ageValue = String(form?.age?.value || '').trim();
        const ageNumber = Number(ageValue);
        if (!ageValue) {
            errors.age = 'Age is required.';
        } else if (!Number.isInteger(ageNumber) || ageNumber < 15 || ageNumber > 30) {
            errors.age = 'Age must be 15 to 30 only.';
        }
        const birthday = form?.birthday?.value || '';
        if (!birthday) {
            errors.birthday = 'Birthday is required.';
        } else {
            const born = new Date(`${birthday}T00:00:00`);
            const computed = ageFromBirthday(birthday);
            if (Number.isNaN(born.getTime())) {
                errors.birthday = 'Invalid birthday value.';
            } else if (born > new Date()) {
                errors.birthday = 'Birthday cannot be in the future.';
            } else if (computed !== null && computed < 15) {
                errors.birthday = 'Age must be at least 15 years old.';
            } else if (computed !== null && computed > 30) {
                errors.birthday = 'Age must not exceed 30 years old.';
            } else if (!errors.age && computed !== ageNumber) {
                errors.birthday = 'Birthday must match the selected age.';
            }
        }
        return errors;
    }

    function clearFieldErrors() {
        modal.querySelectorAll('[data-guest-error]').forEach((node) => {
            node.hidden = true;
            node.textContent = '';
        });
        modal.querySelectorAll('.is-invalid').forEach((node) => {
            node.classList.remove('is-invalid');
        });
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        Object.entries(errors).forEach(([name, message]) => {
            const errorNode = modal.querySelector(`[data-guest-error="${name}"]`);
            const field = modal.querySelector(`[data-guest-field="${name}"]`);
            if (errorNode) {
                errorNode.hidden = false;
                errorNode.textContent = message;
            }
            field?.classList.add('is-invalid');
        });
    }

    function setFieldMessage(name, message) {
        const errorNode = form?.querySelector(`[data-guest-error="${name}"]`);
        const field = form?.querySelector(`[data-guest-field="${name}"]`);
        if (errorNode) {
            errorNode.hidden = !message;
            errorNode.textContent = message || '';
        }
        field?.classList.toggle('is-invalid', Boolean(message));
    }

    function paintTouchedFields() {
        const errors = identityErrors();
        touchedFields.forEach((name) => {
            setFieldMessage(name, errors[name] || '');
        });
    }

    function touchField(name) {
        touchedFields.add(name);
        paintTouchedFields();
    }

    function syncSecurityVisibility(showErrors) {
        const errors = identityErrors();
        const ready = Object.keys(errors).length === 0;
        if (showErrors) {
            ['last_name', 'first_name', 'middle_name', 'suffix', 'custom_suffix', 'purok_zone', 'sex', 'age', 'birthday'].forEach((name) => {
                touchedFields.add(name);
            });
            showFieldErrors(errors);
        } else {
            paintTouchedFields();
        }
        return ready;
    }

    function bindIdentityWatchers() {
        if (!form) {
            return;
        }
        const today = new Date();
        const pad = (value) => String(value).padStart(2, '0');
        const toDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const maxBirthday = new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
        const minBirthday = new Date(today.getFullYear() - 30, today.getMonth(), today.getDate());
        if (form.birthday) {
            form.birthday.max = toDate(maxBirthday);
            form.birthday.min = toDate(minBirthday);
        }

        function defaultBirthdayForAge(age) {
            return toDate(new Date(today.getFullYear() - age, today.getMonth(), today.getDate()));
        }

        ['last_name', 'first_name', 'middle_name'].forEach((name) => {
            const input = form[name];
            input?.addEventListener('input', (event) => {
                const next = sanitizeName(event.target.value);
                if (event.target.value !== next) {
                    event.target.value = next;
                }
                touchField(name);
            });
            input?.addEventListener('blur', () => touchField(name));
        });
        form.custom_suffix?.addEventListener('input', (event) => {
            event.target.value = String(event.target.value || '').replace(/[^A-Za-z.\s]/g, '').slice(0, 5);
            touchField('custom_suffix');
        });
        suffix?.addEventListener('change', () => {
            touchField('suffix');
            if (suffix.value === 'Others') {
                touchField('custom_suffix');
            }
        });
        form.purok_zone?.addEventListener('change', () => touchField('purok_zone'));
        form.querySelectorAll('input[name="sex"]').forEach((input) => {
            input.addEventListener('change', () => touchField('sex'));
        });
        form.age?.addEventListener('change', () => {
            const value = parseInt(form.age.value, 10);
            touchedFields.add('age');
            if (form.age.value === '') {
                paintTouchedFields();
                return;
            }
            if (Number.isNaN(value) || value < 15 || value > 30) {
                form.age.value = '';
                form.birthday.value = '';
                touchedFields.add('birthday');
                paintTouchedFields();
                setFieldMessage('age', 'Age must be 15 to 30 only.');
                return;
            }
            form.birthday.value = defaultBirthdayForAge(value);
            touchedFields.add('birthday');
            paintTouchedFields();
        });
        form.birthday?.addEventListener('focus', () => {
            const selectedAge = parseInt(form.age?.value, 10);
            if (!form.birthday.value && !Number.isNaN(selectedAge) && selectedAge >= 15 && selectedAge <= 30) {
                form.birthday.value = defaultBirthdayForAge(selectedAge);
                touchedFields.add('birthday');
                paintTouchedFields();
            }
        });
        form.birthday?.addEventListener('change', () => {
            touchedFields.add('birthday');
            const computed = ageFromBirthday(form.birthday.value);
            if (computed !== null && computed >= 15 && computed <= 30) {
                form.age.value = String(computed);
                touchedFields.add('age');
                paintTouchedFields();
                return;
            }
            if (form.birthday.value) {
                form.birthday.value = '';
                form.age.value = '';
                touchedFields.add('age');
                paintTouchedFields();
                setFieldMessage('birthday', 'Birthday must result in age 15 to 30 only.');
                return;
            }
            paintTouchedFields();
        });
    }

    bindIdentityWatchers();

    function collectAnswers() {
        const cards = selectedQuestions();
        if (cards.length !== 3) {
            return { error: 'Pumili at sagutan ang eksaktong 3 tanong.' };
        }
        const answers = [];
        for (const card of cards) {
            const fieldError = card.querySelector('.guest-kabataan-secq__error');
            const setField = (message) => {
                if (fieldError) {
                    fieldError.hidden = !message;
                    fieldError.textContent = message || '';
                }
            };
            if (card.dataset.kind === 'own') {
                const value = card.querySelector('.guest-kabataan-secq__input')?.value.trim() || '';
                if (!/^\p{L}{1,15}$/u.test(value)) {
                    setField('Ang sagot ay dapat binubuo lamang ng mga titik at hanggang 15 karakter. Walang espasyo.');
                    return { error: 'Sagutan ang lahat ng 3 napiling tanong.' };
                }
                setField('');
                answers.push({ number: card.dataset.question, choice: 'Iba pa', custom: value });
                continue;
            }
            const selected = card.querySelector('input[type="radio"]:checked');
            if (!selected) {
                setField('Pumili ng sagot.');
                return { error: 'Sagutan ang lahat ng 3 napiling tanong.' };
            }
            if (selected.value !== 'Iba pa') {
                setField('');
                answers.push({ number: card.dataset.question, choice: selected.value, custom: '' });
                continue;
            }
            const custom = card.querySelector('.guest-kabataan-secq__input')?.value.trim() || '';
            if (card.dataset.kind === 'number') {
                if (!/^\d{1,2}$/.test(custom) || Number(custom) > 99) {
                    setField('Maglagay lamang ng numerong 0 hanggang 99.');
                    return { error: 'Sagutan ang lahat ng 3 napiling tanong.' };
                }
            } else if (!/^\p{L}{1,15}$/u.test(custom)) {
                setField('Ang sagot ay dapat binubuo lamang ng mga titik at hanggang 15 karakter. Walang espasyo.');
                return { error: 'Sagutan ang lahat ng 3 napiling tanong.' };
            }
            setField('');
            answers.push({ number: card.dataset.question, choice: 'Iba pa', custom });
        }
        return { answers };
    }

    function showNoData(message) {
        const copy = document.getElementById('guestKabataanNoDataCopy');
        if (copy && message) {
            copy.textContent = message;
        }
        if (noDataModal) {
            noDataModal.hidden = false;
        }
    }

    function closeNoData() {
        if (noDataModal) {
            noDataModal.hidden = true;
        }
    }

    const hasEmailModal = document.getElementById('guestKabataanHasEmailModal');

    function showHasEmail(message) {
        if (securityModal && !securityModal.hidden) {
            securityModal.hidden = true;
            modal.hidden = false;
            showConfirm();
        }
        showError('');
        const copy = document.getElementById('guestKabataanHasEmailCopy');
        if (copy && message) {
            copy.textContent = message;
        }
        if (hasEmailModal) {
            hasEmailModal.hidden = false;
            document.getElementById('guestKabataanHasEmailOk')?.focus();
        }
    }

    function closeHasEmail() {
        if (hasEmailModal) {
            hasEmailModal.hidden = true;
        }
    }

    hasEmailModal?.querySelectorAll('[data-guest-hasemail-close]').forEach((button) => {
        button.addEventListener('click', closeHasEmail);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && hasEmailModal && !hasEmailModal.hidden) {
            event.stopImmediatePropagation();
            closeHasEmail();
        }
    }, true);

    const wrongModal = document.getElementById('guestKabataanWrongModal');

    function showWrongAnswer(payload) {
        if (!wrongModal) {
            return false;
        }
        const locked = Boolean(payload.locked);
        const attemptsLeft = Number(payload.attempts_left);
        const title = document.getElementById('guestKabataanWrongTitle');
        const copy = document.getElementById('guestKabataanWrongCopy');
        const attempts = document.getElementById('guestKabataanWrongAttempts');
        const note = document.getElementById('guestKabataanWrongNote');
        const okBtn = document.getElementById('guestKabataanWrongOk');

        wrongModal.dataset.mode = locked ? 'locked' : 'wrong';
        if (title) {
            title.textContent = locked ? 'Pansamantalang naka-lock' : 'Mali ang sagot';
        }
        if (copy) {
            copy.textContent = locked
                ? lockMessage(Number(payload.remaining_seconds) || remaining)
                : 'Mali ang mga security questions.';
        }
        if (attempts) {
            attempts.hidden = locked || !Number.isFinite(attemptsLeft);
            attempts.textContent = `Natitirang subok: ${attemptsLeft}`;
        }
        if (note) {
            note.hidden = locked;
        }
        if (okBtn) {
            okBtn.textContent = locked ? 'OK' : 'Subukan muli';
        }
        wrongModal.hidden = false;
        okBtn?.focus();
        return true;
    }

    function closeWrongAnswer() {
        if (!wrongModal || wrongModal.hidden) {
            return;
        }
        const wasLocked = wrongModal.dataset.mode === 'locked';
        wrongModal.hidden = true;
        if (wasLocked) {
            if (securityModal) {
                securityModal.hidden = true;
            }
            closeModal();
            return;
        }
        securityForm?.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
    }

    wrongModal?.querySelectorAll('[data-guest-wrong-close]').forEach((button) => {
        button.addEventListener('click', closeWrongAnswer);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && wrongModal && !wrongModal.hidden) {
            event.stopImmediatePropagation();
            closeWrongAnswer();
        }
    }, true);

    function showSecurity() {
        modal.hidden = true;
        resetSecurityForm();
        if (securityModal) {
            securityModal.hidden = false;
        }
    }

    function closeSecurity() {
        if (securityModal) {
            securityModal.hidden = true;
        }
        modal.hidden = false;
        showConfirm();
    }

    noDataModal?.querySelectorAll('[data-guest-nodata-close]').forEach((button) => {
        button.addEventListener('click', closeNoData);
    });
    securityModal?.querySelectorAll('[data-guest-security-close]').forEach((button) => {
        button.addEventListener('click', closeSecurity);
    });

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body,
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        return { response, payload };
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (home.dataset.locked === '1') {
            showError(lockEl?.textContent || 'Please wait before trying again.');
            return;
        }
        showError('');
        if (!syncSecurityVisibility(true)) {
            return;
        }

        const continueBtn = document.getElementById('guestKabataanContinue');
        setBusy(continueBtn, true, 'Continuing');
        try {
            const body = new FormData(form);
            body.delete('custom_suffix');
            if (form.suffix.value === 'Others') {
                body.set('custom_suffix', form.custom_suffix.value || '');
            }
            const { response, payload } = await postJson(home.dataset.identityUrl, body);
            if (response.status === 422) {
                const errors = payload.errors || {};
                const fieldErrors = {};
                ['last_name', 'first_name', 'middle_name', 'suffix', 'custom_suffix', 'purok_zone', 'sex', 'age', 'birthday'].forEach((name) => {
                    if (errors[name]?.[0]) {
                        fieldErrors[name] = errors[name][0];
                    }
                });
                if (Object.keys(fieldErrors).length) {
                    showFieldErrors(fieldErrors);
                }
                const message = errors.claim?.[0] || Object.values(fieldErrors)[0] || payload.message || 'Please check the form and try again.';
                showError(message);
                if (errors.claim) {
                    refreshLock();
                }
                return;
            }
            if (payload.not_found) {
                showNoData(payload.message);
                return;
            }
            if (payload.already_account) {
                showHasEmail(payload.message);
                return;
            }
            if (!response.ok || !payload.success) {
                showError(payload.message || 'Unable to confirm KK Profiling. Please try again.');
                showGuestToast(payload.message || 'Unable to confirm KK Profiling. Please try again.', 'error');
                return;
            }
            showSecurity();
            showGuestToast('KK Profiling found. Answer your security questions.', 'success');
        } catch (error) {
            showError(error?.message || 'Unable to confirm KK Profiling. Please try again.');
            showGuestToast('Unable to confirm KK Profiling. Check your connection and try again.', 'error');
        } finally {
            setBusy(continueBtn, false);
        }
    });

    securityForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (home.dataset.locked === '1') {
            showWrongAnswer({ locked: true, remaining_seconds: remaining });
            return;
        }
        if (securityError) {
            securityError.hidden = true;
            securityError.textContent = '';
        }
        const collected = collectAnswers();
        if (collected.error) {
            if (securityError) {
                securityError.hidden = false;
                securityError.textContent = collected.error;
            }
            return;
        }

        const submitBtn = document.getElementById('guestKabataanClaimSubmit');
        setBusy(submitBtn, true);
        try {
            let token = '';
            if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                token = await window.KabataanTurnstileGate.challenge();
            }
            if (window.KabataanTurnstileGate?.isEnabled?.() && !token) {
                if (securityError) {
                    securityError.hidden = false;
                    securityError.textContent = 'Security verification failed. Please try again.';
                }
                showGuestToast('Security verification failed. Please try again.', 'error');
                return;
            }
            const body = new FormData();
            collected.answers.forEach((answer, index) => {
                body.append(`security_questions[${index}][question_number]`, answer.number);
                body.append(`security_questions[${index}][selected_choice]`, answer.choice);
                body.append(`security_questions[${index}][custom_answer]`, answer.custom || '');
            });
            if (token) {
                body.append('cf-turnstile-response', token);
            }
            const { response, payload } = await postJson(home.dataset.claimUrl, body);
            if (payload.locked) {
                setLocked(payload.remaining_seconds || 0);
            }
            if (payload.already_account) {
                showHasEmail(payload.message);
                return;
            }
            const isWrongAnswer = Object.prototype.hasOwnProperty.call(payload, 'attempts_left');
            const isLockedOut = /maling subok/i.test(payload.errors?.claim?.[0] || '');
            if (isWrongAnswer || isLockedOut) {
                if (isLockedOut) {
                    await refreshLock();
                }
                showWrongAnswer(isWrongAnswer ? payload : { locked: true, remaining_seconds: remaining });
                return;
            }
            if (response.status === 422 || payload.success === false) {
                const message = payload.errors?.claim?.[0]
                    || payload.errors?.security_questions?.[0]
                    || payload.errors?.['cf-turnstile-response']?.[0]
                    || payload.message
                    || 'Mali ang mga security questions.';
                if (securityError) {
                    securityError.hidden = false;
                    securityError.textContent = message;
                }
                showGuestToast(message, 'error');
                if (payload.locked) {
                    refreshLock();
                }
                return;
            }
            if (!response.ok || !payload.redirect) {
                const message = payload.message || 'Unable to confirm the security questions. Please try again.';
                if (securityError) {
                    securityError.hidden = false;
                    securityError.textContent = message;
                }
                showGuestToast(message, 'error');
                return;
            }
            flashGuestToast('Security questions confirmed. Add your email to activate your account.', 'success');
            window.location.href = payload.redirect;
        } catch (error) {
            if (error && error.message === 'Verification cancelled.') {
                return;
            }
            if (securityError) {
                securityError.hidden = false;
                securityError.textContent = error?.message || 'Unable to confirm the security questions. Please try again.';
            }
            showGuestToast('Unable to confirm the security questions. Please try again.', 'error');
        } finally {
            setBusy(submitBtn, false);
        }
    });
}

function bindGuestActivation() {
    const root = document.getElementById('guestKabataanActivate');
    if (!root) {
        return;
    }
    const form = document.getElementById('guestKabataanEmailSend');
    const errorEl = document.getElementById('guestKabataanEmailError');
    const resendError = document.getElementById('guestKabataanResendError');
    const resendBtn = document.getElementById('guestKabataanResend');
    const resendTimer = document.getElementById('guestKabataanResendTimer');
    let resendTick = null;

    function emailLooksComplete(email) {
        const at = email.indexOf('@');
        if (at < 1 || (email.match(/@/g) || []).length !== 1) {
            return false;
        }
        const localPart = email.slice(0, at);
        const domain = email.slice(at + 1);
        if (!localPart || !domain || !domain.includes('.')) {
            return false;
        }
        return /^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)
            && /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain);
    }

    function emailMessage(value, strict = true) {
        const email = String(value || '').trim().toLowerCase();
        if (!email) {
            return strict ? 'Email is required.' : '';
        }
        if (email.length > 64) {
            return 'Email must not exceed 64 characters.';
        }
        if (/\s/.test(email) || (email.match(/@/g) || []).length > 1) {
            return 'Please enter a valid email address.';
        }
        const at = email.indexOf('@');
        if (at >= 0) {
            const localPart = email.slice(0, at);
            if (localPart.length < 6) {
                return 'Email username must be at least 6 characters.';
            }
            if (localPart.length > 30) {
                return 'Email username must not exceed 30 characters.';
            }
        } else if (strict && email.length < 6) {
            return 'Email username must be at least 6 characters.';
        } else if (!strict && email.length > 30) {
            return 'Email username must not exceed 30 characters.';
        }
        if (!strict && !emailLooksComplete(email)) {
            return '';
        }
        if ((email.match(/@/g) || []).length !== 1) {
            return 'Please enter a valid email address.';
        }
        const localPart = email.slice(0, at);
        const domain = email.slice(at + 1);
        if (
            !localPart
            || !domain
            || localPart.startsWith('.')
            || localPart.endsWith('.')
            || localPart.includes('..')
            || domain.startsWith('.')
            || domain.endsWith('.')
            || domain.includes('..')
            || domain.startsWith('-')
            || domain.endsWith('-')
            || !/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/i.test(localPart)
            || !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i.test(domain)
        ) {
            return 'Please enter a valid email address.';
        }
        return '';
    }

    function showEmailError(message, banner) {
        const fieldError = document.getElementById('guestKabataanEmailFieldError');
        const input = form?.querySelector('[name="email"]');
        if (fieldError) {
            fieldError.hidden = !message;
            fieldError.textContent = message || '';
        }
        input?.classList.toggle('is-invalid', Boolean(message));
        if (errorEl) {
            errorEl.hidden = !banner || !message;
            errorEl.textContent = banner && message ? message : '';
        }
    }

    const emailInput = form?.querySelector('[name="email"]');
    emailInput?.addEventListener('input', () => {
        const start = emailInput.selectionStart;
        const end = emailInput.selectionEnd;
        emailInput.value = String(emailInput.value || '').toLowerCase().slice(0, 64);
        if (start !== null && end !== null) {
            emailInput.setSelectionRange(start, end);
        }
        showEmailError(emailMessage(emailInput.value, false), false);
    });
    // Same server rules as KK Profiling (format, DNS, disposable/temporary domains, already taken).
    let emailCheckController = null;
    async function serverEmailMessage(email) {
        if (!root.dataset.checkUrl || !email) {
            return '';
        }
        emailCheckController?.abort();
        emailCheckController = new AbortController();
        const response = await fetch(root.dataset.checkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ email, current_email: root.dataset.currentEmail || '' }),
            credentials: 'same-origin',
            signal: emailCheckController.signal,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            return data.errors?.email?.[0] || data.message || 'Please enter a valid email address.';
        }
        return data.exists ? (data.message || 'This email is already taken. Please use another email.') : '';
    }

    emailInput?.addEventListener('blur', async () => {
        emailInput.value = String(emailInput.value || '').trim().toLowerCase().slice(0, 64);
        const localError = emailMessage(emailInput.value, true);
        showEmailError(localError, false);
        if (localError) {
            return;
        }
        const checked = emailInput.value;
        try {
            const message = await serverEmailMessage(checked);
            if (emailInput.value === checked) {
                showEmailError(message, false);
            }
        } catch (error) {
            // Network/abort: the submit request still validates on the server.
        }
    });

    async function challengeToken() {
        if (window.KabataanTurnstileGate?.challenge) {
            return window.KabataanTurnstileGate.challenge();
        }
        return '';
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('[name="email"]');
        if (input) {
            input.value = input.value.trim().toLowerCase();
        }
        const formatError = emailMessage(input?.value);
        if (formatError) {
            showEmailError(formatError, false);
            showGuestToast(formatError, 'error');
            return;
        }
        showEmailError('');
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn?.disabled) {
            return;
        }
        setBusy(submitBtn, true, 'Verifying');
        try {
            // Open Cloudflare right away; the email check runs alongside and closes it if the email is rejected.
            let serverError = '';
            const emailCheck = serverEmailMessage(input.value)
                .catch(() => '')
                .then((message) => {
                    serverError = message;
                    if (message && window.KabataanTurnstileGate?.isOpen?.()) {
                        window.KabataanTurnstileGate.cancel();
                    }
                    return message;
                });
            let token = '';
            try {
                token = await challengeToken();
            } catch (error) {
                if (!serverError) {
                    throw error;
                }
            }
            await emailCheck;
            if (serverError) {
                showEmailError(serverError, false);
                showGuestToast(serverError, 'error');
                return;
            }
            setBusy(submitBtn, true, 'Sending');
            if (window.KabataanTurnstileGate?.isEnabled?.() && !token) {
                showEmailError('Security verification failed. Please try again.', true);
                showGuestToast('Security verification failed. Please try again.', 'error');
                return;
            }
            const body = new FormData(form);
            if (token) {
                body.append('cf-turnstile-response', token);
            }
            const response = await fetch(root.dataset.sendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body,
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const emailError = payload.errors?.email?.[0];
                if (emailError) {
                    showEmailError(emailError, false);
                    showGuestToast(emailError, 'error');
                    return;
                }
                const message = payload.errors?.['cf-turnstile-response']?.[0] || payload.errors?.registration?.[0] || payload.message || 'Unable to send the set-password email.';
                showEmailError(message, true);
                showGuestToast(message, 'error');
                return;
            }
            flashGuestToast(payload.message || 'Set password link sent. Please check your inbox.', 'success');
            window.location.assign(payload.redirect || root.dataset.sentUrl || '/guest/activate/sent');
            return;
        } catch (error) {
            if (error && error.message === 'Verification cancelled.') {
                return;
            }
            showEmailError(error?.message || 'Unable to send the set-password email.', true);
            showGuestToast('Unable to send the set-password email. Please try again.', 'error');
        } finally {
            setBusy(submitBtn, false);
        }
    });

    function formatResendTimer(seconds) {
        const remaining = Math.max(0, seconds);
        const minutes = Math.floor(remaining / 60);
        const padded = String(remaining % 60).padStart(2, '0');
        return `Resend again in ${minutes}:${padded}`;
    }

    function startResendCooldown(seconds) {
        if (!resendBtn) {
            return;
        }
        if (resendTick) {
            window.clearInterval(resendTick);
            resendTick = null;
        }
        let remaining = Math.max(0, Math.ceil(Number(seconds) || 0));
        if (remaining <= 0) {
            resendBtn.disabled = false;
            if (resendTimer) {
                resendTimer.hidden = true;
                resendTimer.textContent = '';
            }
            return;
        }
        const paint = () => {
            resendBtn.disabled = true;
            if (resendTimer) {
                resendTimer.hidden = false;
                resendTimer.textContent = formatResendTimer(remaining);
            }
        };
        paint();
        resendTick = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                window.clearInterval(resendTick);
                resendTick = null;
                resendBtn.disabled = false;
                if (resendTimer) {
                    resendTimer.hidden = true;
                    resendTimer.textContent = '';
                }
                return;
            }
            paint();
        }, 1000);
    }

    if (resendBtn) {
        const initialCooldown = parseInt(root.dataset.cooldown || '0', 10);
        startResendCooldown(Number.isFinite(initialCooldown) ? initialCooldown : 0);
    }

    resendBtn?.addEventListener('click', async () => {
        if (!resendBtn || resendBtn.disabled) {
            return;
        }
        if (resendError) {
            resendError.hidden = true;
            resendError.textContent = '';
        }
        let cooldownAfter = 0;
        setBusy(resendBtn, true, 'Sending');
        try {
            const response = await fetch(root.dataset.resendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            const wait = Number(payload.resend_cooldown_seconds);
            if (!response.ok) {
                if (Number.isFinite(wait) && wait > 0) {
                    cooldownAfter = Math.ceil(wait);
                }
                const message = payload.errors?.email?.[0] || payload.message || 'Unable to resend the email.';
                if (resendError) {
                    resendError.hidden = false;
                    resendError.textContent = message;
                }
                showGuestToast(message, 'error');
                return;
            }
            cooldownAfter = Number.isFinite(wait) && wait > 0 ? Math.ceil(wait) : 60;
            showGuestToast(payload.message || 'Set password link sent. Please check your inbox.', 'success');
        } catch (error) {
            if (resendError) {
                resendError.hidden = false;
                resendError.textContent = error?.message || 'Unable to resend the email.';
            }
            showGuestToast('Unable to resend the email. Please try again.', 'error');
        } finally {
            setBusy(resendBtn, false);
        }
        if (cooldownAfter > 0) {
            startResendCooldown(cooldownAfter);
        }
    });
}
