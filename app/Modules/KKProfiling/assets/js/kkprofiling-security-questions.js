(function () {
    const modal = document.getElementById('kkpSecurityQuestionsModal');
    if (!modal) {
        return;
    }

    const TEXT_MESSAGE = 'Ang sagot ay dapat binubuo lamang ng mga titik at hanggang 15 karakter. Walang espasyo.';
    const NUMBER_MESSAGE = 'Maglagay lamang ng numerong 0 hanggang 99.';
    const COUNT_MESSAGE = 'Pumili at sagutan ang eksaktong 3 tanong.';
    const letterPattern = /^\p{L}{1,15}$/u;

    const errorEl = document.getElementById('kkpSecurityQuestionsError');
    const pickedEl = document.getElementById('kkpSecurityQuestionsPicked');
    const okBtn = document.getElementById('kkpSecurityQuestionsOkBtn');
    const backBtn = document.getElementById('kkpSecurityQuestionsBackBtn');
    const closeBtn = document.getElementById('kkpSecurityQuestionsCloseBtn');
    const backdrop = document.getElementById('kkpSecurityQuestionsBackdrop');
    let pending = null;

    function showError(message) {
        if (!errorEl) {
            return;
        }
        if (!message) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = message;
    }

    function fieldError(card, message) {
        const slot = card.querySelector('.kkp-secq-field-error');
        if (!slot) {
            return;
        }
        if (!message) {
            slot.hidden = true;
            slot.textContent = '';
            return;
        }
        slot.hidden = false;
        slot.textContent = message;
    }

    function selectedCards() {
        return Array.from(modal.querySelectorAll('.kkp-secq-check:checked'))
            .map((input) => input.closest('.kkp-secq'))
            .filter(Boolean);
    }

    function updatePicked() {
        const count = selectedCards().length;
        if (pickedEl) {
            pickedEl.textContent = `Napili: ${count} ng 3`;
        }
        modal.querySelectorAll('.kkp-secq-check').forEach((input) => {
            input.disabled = count >= 3 && !input.checked;
        });
        if (okBtn) {
            okBtn.disabled = count !== 3;
        }
    }

    function resetCard(card) {
        card.classList.remove('is-selected');
        const body = card.querySelector('.kkp-secq-body');
        if (body) {
            body.hidden = true;
        }
        card.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.checked = false;
        });
        const customWrap = card.querySelector('.kkp-secq-custom');
        const custom = card.querySelector('.kkp-secq-custom-input');
        if (card.dataset.kind !== 'own' && customWrap) {
            customWrap.hidden = true;
        }
        if (custom) {
            custom.value = '';
        }
        const remaining = card.querySelector('[data-remaining]');
        if (remaining) {
            remaining.textContent = '15';
        }
        fieldError(card, '');
    }

    function setOpen(open) {
        if (!open) {
            const active = document.activeElement;
            if (active && modal.contains(active) && typeof active.blur === 'function') {
                active.blur();
            }
        }
        modal.hidden = !open;
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('kkp-secq-open', open);
        if (open) {
            updatePicked();
        }
    }

    function finish(result) {
        const resolve = pending;
        pending = null;
        setOpen(false);
        if (resolve) {
            resolve(result);
        }
    }

    function openModal() {
        if (pending) {
            return pending;
        }
        showError('');
        setOpen(true);
        modal.querySelector('.kkp-info-modal-scroll')?.scrollTo(0, 0);
        return new Promise((resolve) => {
            pending = resolve;
        });
    }

    function restrictCustom(card) {
        const input = card.querySelector('.kkp-secq-custom-input');
        if (!input) {
            return;
        }
        if (card.dataset.kind === 'number') {
            const raw = input.value;
            const cleaned = raw.replace(/[^\d].*$/, '').replace(/\D/g, '').slice(0, 2);
            input.value = cleaned;
            fieldError(card, raw !== cleaned ? NUMBER_MESSAGE : '');
            return;
        }
        const raw = input.value;
        const cleaned = raw.replace(/[^\p{L}]/gu, '').slice(0, 15);
        input.value = cleaned;
        const hadInvalid = raw.trim() !== '' && (/[^\p{L}]/u.test(raw.trim()) || [...raw.trim()].length > 15);
        const remaining = card.querySelector('[data-remaining]');
        if (remaining) {
            remaining.textContent = String(15 - input.value.length);
        }
        fieldError(card, hadInvalid ? TEXT_MESSAGE : '');
    }

    function validateCard(card) {
        fieldError(card, '');
        const kind = card.dataset.kind;
        if (kind === 'own') {
            const value = card.querySelector('.kkp-secq-custom-input')?.value.trim() || '';
            if (!letterPattern.test(value)) {
                fieldError(card, TEXT_MESSAGE);
                return null;
            }
            return { selectedChoice: 'Iba pa', customAnswer: value };
        }

        const selected = card.querySelector('input[type="radio"]:checked');
        if (!selected) {
            fieldError(card, 'Pumili ng sagot.');
            return null;
        }
        if (selected.value !== 'Iba pa') {
            return { selectedChoice: selected.value, customAnswer: '' };
        }

        const custom = card.querySelector('.kkp-secq-custom-input')?.value.trim() || '';
        if (kind === 'number') {
            if (!/^\d{1,2}$/.test(custom) || Number(custom) > 99) {
                fieldError(card, NUMBER_MESSAGE);
                return null;
            }
            return { selectedChoice: 'Iba pa', customAnswer: String(Number(custom)) };
        }
        if (!letterPattern.test(custom)) {
            fieldError(card, TEXT_MESSAGE);
            return null;
        }
        return { selectedChoice: 'Iba pa', customAnswer: custom };
    }

    function confirmAnswers() {
        showError('');
        const cards = selectedCards();
        if (cards.length !== 3) {
            showError(COUNT_MESSAGE);
            return;
        }

        const answers = [];
        let invalid = null;
        cards.forEach((card) => {
            const answer = validateCard(card);
            if (!answer) {
                invalid = invalid || card;
                return;
            }
            answers.push({
                questionNumber: card.dataset.question,
                selectedChoice: answer.selectedChoice,
                customAnswer: answer.customAnswer,
            });
        });

        if (invalid || answers.length !== 3) {
            showError('Sagutan ang lahat ng 3 napiling tanong.');
            invalid?.scrollIntoView({ block: 'nearest' });
            return;
        }

        finish(answers);
    }

    modal.querySelectorAll('.kkp-secq-check').forEach((input) => {
        input.addEventListener('change', () => {
            const card = input.closest('.kkp-secq');
            if (!card) {
                return;
            }
            if (input.checked && selectedCards().length > 3) {
                input.checked = false;
                showError(COUNT_MESSAGE);
                updatePicked();
                return;
            }
            showError('');
            if (!input.checked) {
                resetCard(card);
            } else {
                card.classList.add('is-selected');
                const body = card.querySelector('.kkp-secq-body');
                if (body) {
                    body.hidden = false;
                }
                if (card.dataset.kind === 'own') {
                    card.querySelector('.kkp-secq-custom-input')?.focus();
                }
            }
            updatePicked();
        });
    });

    modal.querySelectorAll('.kkp-secq').forEach((card) => {
        card.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const customWrap = card.querySelector('.kkp-secq-custom');
                const custom = card.querySelector('.kkp-secq-custom-input');
                const showCustom = radio.checked && radio.value === 'Iba pa';
                if (customWrap) {
                    customWrap.hidden = !showCustom;
                }
                if (!showCustom && custom) {
                    custom.value = '';
                    const remaining = card.querySelector('[data-remaining]');
                    if (remaining) {
                        remaining.textContent = '15';
                    }
                }
                fieldError(card, '');
                if (showCustom) {
                    custom?.focus();
                }
            });
        });

        card.querySelector('.kkp-secq-custom-input')?.addEventListener('input', () => {
            restrictCustom(card);
        });
    });

    okBtn?.addEventListener('click', confirmAnswers);
    [backBtn, closeBtn, backdrop].forEach((el) => {
        el?.addEventListener('click', () => finish(null));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            finish(null);
        }
    });

    window.kkpOpenSecurityQuestions = function () {
        return openModal();
    };

    window.kkpReopenSecurityQuestions = function (message) {
        showError(message || '');
        setOpen(true);
        if (pending) {
            return pending;
        }
        return new Promise((resolve) => {
            pending = resolve;
        });
    };
}());
