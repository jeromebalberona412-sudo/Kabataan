<div
    id="kkpLongNameModal"
    class="kkp-info-overlay"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="kkpLongNameTitle"
>
    <div class="kkp-info-overlay-backdrop" id="kkpLongNameBackdrop"></div>
    <div class="kkp-info-modal kkp-clear-draft-modal kkp-long-name-modal">
        <button type="button" class="kkp-info-modal-close" id="kkpLongNameCloseBtn" aria-label="Close">
            &times;
        </button>
        <div class="kkp-info-modal-scroll">
            <section class="kkp-info-lang" lang="en">
                <h2 class="kkp-info-title" id="kkpLongNameTitle">Continue with a longer legal name?</h2>
                <p>You have entered more than 50 characters. Are you sure you want to proceed and add more characters to your legal name?</p>
                <p>Maximum allowed is 150 characters.</p>
                <label class="kkp-long-name-confirm-label" for="kkpLongNameConfirmInput">
                    Type <strong>yes</strong> to continue
                </label>
                <input
                    type="text"
                    id="kkpLongNameConfirmInput"
                    class="kkp-long-name-confirm-input"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="yes"
                    maxlength="8"
                >
                <p class="kkp-long-name-confirm-hint" id="kkpLongNameConfirmHint" hidden>
                    Please type <strong>yes</strong> exactly to continue.
                </p>
            </section>
        </div>
        <div class="kkp-info-modal-footer">
            <div class="kkp-info-modal-actions">
                <button type="button" class="kkp-info-btn kkp-info-btn-secondary" id="kkpLongNameCancelBtn">Cancel</button>
            </div>
        </div>
    </div>
</div>
