<div id="applicationViewModal" class="sl-view-modal" hidden>
    <div class="sl-view-modal-overlay"></div>
    <div class="sl-view-modal-container" id="applicationViewContainer">
        <div class="sl-view-modal-header">
            <div class="sl-view-modal-header-main">
                <h2 id="applicationViewTitle">Application Details</h2>
            </div>
            <div class="sl-view-modal-header-actions">
                <button type="button" class="sl-view-modal-icon-btn sl-view-modal-maximize-btn" id="applicationViewMaximize" title="Maximize" aria-label="Maximize">
                    <svg class="sl-modal-icon-maximize" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                    <svg class="sl-modal-icon-restore" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>
                </button>
                <button type="button" class="sl-view-modal-icon-btn sl-view-modal-close" id="applicationViewClose" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="sl-view-modal-body">
            <section class="sl-view-section">
                <h3 class="sl-view-section-title">Personal Information</h3>
                <div id="applicationViewPersonalInfo" class="sl-view-personal-grid sports-view-personal-grid"></div>
            </section>
            <section class="sl-view-section">
                <h3 class="sl-view-section-title">Application Answers</h3>
                <div id="applicationViewAnswers" class="sl-view-answers sports-view-answers"></div>
            </section>
            <div id="applicationViewCancelledInfo" class="sl-view-cancelled-info sports-view-cancelled" hidden>
                <h3 class="sl-view-section-title">Cancellation Reason</h3>
                <p id="applicationViewCancelledReason" class="sports-view-cancelled__text"></p>
            </div>
        </div>
    </div>
</div>

<div id="applicationCancelModal" class="kb-type-confirm-overlay" hidden>
    <div class="kb-type-confirm-modal" id="applicationCancelModalBox">
        <div class="kb-type-confirm-header">
            <h3>Cancel Application</h3>
        </div>
        <div class="kb-type-confirm-body">
            <p class="kb-type-confirm-message">Are you sure you want to cancel this sports application?</p>
            <p class="kb-type-confirm-desc">This action cannot be undone. You may apply again later if the program is still open.</p>

            <label class="kb-type-confirm-label" for="applicationCancelReason">Cancel Reason</label>
            <textarea id="applicationCancelReason" class="kb-type-confirm-textarea" rows="4" maxlength="500" placeholder="Type your reason for cancelling this application..."></textarea>
            <p id="applicationCancelCharCount" class="kb-type-confirm-char-count">0 / 500 characters</p>

            <label class="kb-type-confirm-label" for="applicationCancelConfirm">Confirmation Required</label>
            <input type="text" id="applicationCancelConfirm" class="kb-type-confirm-input" placeholder="Type Confirm to confirm" autocomplete="off" spellcheck="false">
            <p id="applicationCancelError" class="kb-type-confirm-hint kb-type-confirm-hint-error" hidden></p>
        </div>
        <div class="kb-type-confirm-footer">
            <button type="button" class="kb-btn-cancel-confirm" id="applicationCancelDismissBtn">Cancel</button>
            <button type="button" class="kb-btn-action-confirm is-disabled" id="applicationCancelBtn" disabled>Confirm Cancel</button>
        </div>
    </div>
</div>
