document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pw-toggle-btn[data-target]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById(btn.dataset.target || '');
            if (!target) return;

            const isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            btn.classList.toggle('pw-visible', isPassword);
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    function validatePasswordStrength(password) {
        const hasLowerCase = /[a-z]/.test(password);
        const hasUpperCase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        const hasMinLength = password.length >= 8;

        return {
            isValid: hasUpperCase && hasNumber && hasMinLength && hasLowerCase && hasSpecial,
            hasLowerCase,
            hasUpperCase,
            hasNumber,
            hasSpecial,
            hasMinLength,
        };
    }

    const passwordInput = document.getElementById('password');
    const passwordRules = document.getElementById('passwordRules');

    function updatePasswordRules(value) {
        if (!passwordRules) return;

        const state = validatePasswordStrength(value);
        const rules = [
            { id: 'rule-length', ok: state.hasMinLength },
            { id: 'rule-lowercase', ok: state.hasLowerCase },
            { id: 'rule-uppercase', ok: state.hasUpperCase },
            { id: 'rule-number', ok: state.hasNumber },
            { id: 'rule-special', ok: state.hasSpecial },
        ];

        if (!value.length) {
            passwordRules.classList.remove('active', 'is-complete');
            return;
        }

        if (state.isValid) {
            passwordRules.classList.add('is-complete');
            passwordRules.classList.remove('active');
            return;
        }

        passwordRules.classList.add('active');
        passwordRules.classList.remove('is-complete');
        rules.forEach((rule) => {
            const node = document.getElementById(rule.id);
            if (node) node.classList.toggle('ok', rule.ok);
        });
    }

    const confirmInput = document.getElementById('password_confirmation');
    const errorElement = document.getElementById('confirmPasswordError');

    function updateConfirmMatch() {
        const newPassword = passwordInput?.value || '';
        const confirmation = confirmInput?.value || '';

        if (!errorElement) return;

        if (!confirmation.length) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
            confirmInput?.classList.remove('error');
            return;
        }

        if (newPassword !== confirmation) {
            errorElement.textContent = 'Passwords do not match.';
            errorElement.style.display = 'block';
            confirmInput?.classList.add('error');
            return;
        }

        errorElement.textContent = '';
        errorElement.style.display = 'none';
        confirmInput?.classList.remove('error');
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updatePasswordRules(this.value);
            updateConfirmMatch();
        });
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', updateConfirmMatch);
        confirmInput.addEventListener('blur', updateConfirmMatch);
    }

    const changePasswordForm = document.getElementById('changePasswordForm');
    if (!changePasswordForm) return;

    changePasswordForm.addEventListener('submit', function (e) {
        const newPassword = document.getElementById('password')?.value || '';
        const passwordConfirmation = document.getElementById('password_confirmation')?.value || '';
        const submitBtn = document.getElementById('cpSubmitBtn');
        const btnText = document.getElementById('cpBtnText');

        const strength = validatePasswordStrength(newPassword);

        if (!strength.isValid) {
            e.preventDefault();
            updatePasswordRules(newPassword);
            if (passwordRules) passwordRules.classList.add('active');
            updateConfirmMatch();
            return;
        }

        updateConfirmMatch();

        if (newPassword !== passwordConfirmation) {
            e.preventDefault();
            if (errorElement) {
                errorElement.textContent = 'Passwords do not match.';
                errorElement.style.display = 'block';
            }
            confirmInput?.classList.add('error');
            confirmInput?.focus();
            return;
        }

        if (submitBtn) submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Sending…';
    });
});
