/**
 * SK OnePortal — Kabataan Sign In
 *
 * Submit listener execution order on #signInForm:
 *   1. sign-in.js — bubbling phase
 *      Validates fields, runs Turnstile via KabataanTurnstileGate, then submits.
 *
 * signInForm.submit() is a native call — it does NOT fire the submit event,
 * so no listener can intercept it. It is the only path that sends the POST.
 */

(function () {
    'use strict';

    var isSubmitting = false;

    var signInForm, emailInput, passwordInput,
        emailError, passwordError, submitBtn;

    function validEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    }

    function showErr(input, el, msg) {
        if (input) input.classList.add('error');
        if (el) { el.textContent = msg; el.hidden = false; el.style.display = 'block'; }
    }

    function clearErr(input, el) {
        if (input) input.classList.remove('error');
        if (el) { el.hidden = true; el.style.display = 'none'; }
    }

    function validateFields() {
        clearErr(emailInput, emailError);
        clearErr(passwordInput, passwordError);

        var ok    = true;
        var email = emailInput ? emailInput.value.trim() : '';
        var pass  = passwordInput ? passwordInput.value : '';

        if (!email) {
            showErr(emailInput, emailError, 'Email is required.');
            ok = false;
        } else if (!validEmail(email)) {
            showErr(emailInput, emailError, 'Please enter a valid email address.');
            ok = false;
        }

        if (!pass) {
            showErr(passwordInput, passwordError, 'Password is required.');
            ok = false;
        }

        return ok;
    }

    function waitForGate(maxWaitMs) {
        maxWaitMs = maxWaitMs || 8000;
        return new Promise(function (resolve, reject) {
            var start = Date.now();
            var check = function () {
                if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.challenge) {
                    resolve(window.KabataanTurnstileGate);
                    return;
                }
                if (Date.now() - start > maxWaitMs) {
                    reject(new Error('Security check failed to load. Please refresh the page.'));
                    return;
                }
                window.setTimeout(check, 50);
            };
            check();
        });
    }

    function runTurnstileThenSubmit() {
        return waitForGate().then(function (gate) {
            return gate.challenge();
        }).then(function (token) {
            window.KabataanTurnstileGate.injectToken(signInForm, token);
            isSubmitting = true;
            setSigningIn();
            signInForm.submit();
        });
    }

    function lockAuthFields() {
        if (emailInput) emailInput.readOnly = true;
        if (passwordInput) passwordInput.readOnly = true;
    }

    function unlockAuthFields() {
        if (emailInput) emailInput.readOnly = false;
        if (passwordInput) passwordInput.readOnly = false;
    }

    function setSigningIn() {
        lockAuthFields();
        if (!submitBtn) return;
        submitBtn.disabled = true;
    }

    function resetSubmitBtn() {
        unlockAuthFields();
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.classList.remove('waiting-for-turnstile');
        submitBtn.classList.remove('loading');
        var span = submitBtn.querySelector('span');
        if (span) span.textContent = 'Sign In';
    }

    function onFieldEdit() {
        if (isSubmitting) return;
        if (window.KabataanTurnstileGate && window.KabataanTurnstileGate.isOpen &&
            window.KabataanTurnstileGate.isOpen()) {
            window.KabataanTurnstileGate.cancel();
        }
        resetSubmitBtn();
    }

    function onFormSubmit(e) {
        if (isSubmitting) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        e.preventDefault();

        if (!validateFields()) return;

        if (!signInForm.dataset.turnstileEnabled) {
            isSubmitting = true;
            setSigningIn();
            signInForm.submit();
            return;
        }

        lockAuthFields();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('waiting-for-turnstile');
        }

        runTurnstileThenSubmit().catch(function (err) {
            isSubmitting = false;
            resetSubmitBtn();
            if (err && err.message && err.message.indexOf('cancelled') === -1) {
                console.warn('[Turnstile]', err.message);
            }
        });
    }

    function bindRememberDeviceModal() {
        var rememberInput = document.getElementById('remember');
        var modal = document.getElementById('rememberDeviceModal');
        var confirmBtn = document.getElementById('rememberDeviceConfirm');
        if (!rememberInput || !modal) {
            return;
        }

        var confirming = false;

        function openModal() {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            (confirmBtn || modal.querySelector('.auth-legal-modal-btn'))?.focus();
        }

        function closeModal(keepChecked) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (!keepChecked) {
                rememberInput.checked = false;
            }
        }

        rememberInput.addEventListener('click', function (e) {
            if (confirming) {
                return;
            }

            if (rememberInput.checked) {
                e.preventDefault();
                rememberInput.checked = false;
                openModal();
            }
        });

        confirmBtn?.addEventListener('click', function () {
            confirming = true;
            rememberInput.checked = true;
            closeModal(true);
            confirming = false;
        });

        modal.querySelectorAll('[data-remember-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(false);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal(false);
            }
        });
    }

    function bindInputAnimations() {
        document.querySelectorAll('.youth-input').forEach(function (input) {
            input.addEventListener('focus', function () {
                if (this.parentElement) this.parentElement.classList.add('input-focused');
            });
            input.addEventListener('blur', function () {
                if (this.parentElement) this.parentElement.classList.remove('input-focused');
            });
        });
    }

    function bindAlertDismiss() {
        document.querySelectorAll('.youth-alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.animation = 'alertSlideOut 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                setTimeout(function () {
                    if (alert.parentNode) alert.parentNode.removeChild(alert);
                }, 400);
            }, 5000);
        });
    }

    function init() {
        signInForm     = document.getElementById('signInForm');
        emailInput     = document.getElementById('email');
        passwordInput  = document.getElementById('password');
        emailError     = document.getElementById('email-error');
        passwordError  = document.getElementById('password-error');
        submitBtn      = document.getElementById('signInBtn');

        if (!signInForm || !emailInput || !passwordInput) return;

        emailInput.addEventListener('input', function () {
            clearErr(emailInput, emailError);
            onFieldEdit();
        });
        passwordInput.addEventListener('input', function () {
            clearErr(passwordInput, passwordError);
            onFieldEdit();
        });

        signInForm.addEventListener('submit', onFormSubmit, false);

        var forgotBtn = document.getElementById('forgotBtn');
        if (forgotBtn) {
            forgotBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = forgotBtn.href || '/password/reset';
            });
        }

        var serverErrEl = document.getElementById('turnstile-server-error');
        if (serverErrEl && signInForm.dataset.turnstileEnabled) {
            waitForGate().then(function (gate) {
                return gate.challenge();
            }).then(function (token) {
                window.KabataanTurnstileGate.injectToken(signInForm, token);
                if (validateFields()) {
                    isSubmitting = true;
                    setSigningIn();
                    signInForm.submit();
                }
            }).catch(function () {
                resetSubmitBtn();
            });
        }

        bindInputAnimations();
        bindAlertDismiss();
        bindRememberDeviceModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    (function injectStyles() {
        if (document.getElementById('youth-sign-in-styles')) return;
        var style = document.createElement('style');
        style.id  = 'youth-sign-in-styles';
        style.textContent = [
            '@keyframes alertSlideOut{to{opacity:0;transform:translateY(-10px)}}',
            '@keyframes errorSlideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}',
            '@keyframes errorSlideOut{to{opacity:0;transform:translateY(-8px)}}',
        ].join('');
        document.head.appendChild(style);
    }());

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('.youth-alert-success').forEach(function (el) {
                el.remove();
            });
        }
    });

}());
