<div class="sr-modal" id="srSuccessModal" hidden role="dialog" aria-modal="true" aria-labelledby="srSuccessTitle">
    <div class="sr-modal-backdrop" data-close-modal></div>
    <div class="sr-modal-panel sr-modal-success">
        <div class="sr-success-animation" aria-hidden="true">
            <div class="sr-success-circle">
                <svg viewBox="0 0 52 52"><circle class="sr-success-circle-bg" cx="26" cy="26" r="25" fill="none"/><path class="sr-success-check" fill="none" d="M14 27l8 8 16-16"/></svg>
            </div>
        </div>
        <h2 id="srSuccessTitle">Registration Submitted Successfully!</h2>
        <p>Your sports registration has been received for review. This is a demo submission — no data was saved to the server.</p>
        <ul class="sr-success-checklist">
            <li>✓ Sport category recorded</li>
            <li>✓ Personal & sports details captured</li>
            <li>✓ Requirements marked for verification</li>
        </ul>
        <div class="sr-modal-footer sr-modal-footer-center">
            <a href="{{ route('dashboard') }}" class="sr-btn sr-btn-primary">Return to Dashboard</a>
            <button type="button" class="sr-btn sr-btn-secondary" id="srResetForm">Register Another</button>
        </div>
    </div>
</div>
