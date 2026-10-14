<div class="auth-legal-modal" id="kkpTermsModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="kkpTermsModalTitle">
    <div class="auth-legal-modal-backdrop"></div>
    <div class="auth-legal-modal-dialog" role="document">
        <header class="auth-legal-modal-header">
            <h2 class="auth-legal-modal-title" id="kkpTermsModalTitle">Terms and Conditions &amp; Privacy Policy</h2>
        </header>
        <div class="auth-legal-modal-body" id="kkpTermsScroll">
            <p class="auth-legal-scroll-notice" data-legal-scroll-notice role="status">
                <svg class="auth-legal-scroll-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 9.25a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span class="auth-legal-scroll-text" data-legal-scroll-text>Please scroll down to read the entire document before acknowledging.</span>
                <span class="auth-legal-scroll-percent" data-legal-scroll-percent hidden>0%</span>
            </p>

            <div class="auth-legal-doc">
                <h3>Katipunan ng Kabataan (KK) Profiling — Purpose and Legal Basis</h3>
                <p>The Katipunan ng Kabataan (KK) Profiling is an official data-gathering activity conducted by Sangguniang Kabataan (SK) officials to establish a comprehensive and updated database of young people aged 15 to 30 residing in each barangay. This initiative is mandated by Republic Act No. 10742, also known as the Sangguniang Kabataan Reform Act of 2015, and aims to gather accurate demographic information that can be used as a basis for developing appropriate, responsive, and data-driven youth development programs, policies, and local projects.</p>
            </div>

            <h3 class="kkp-legal-doc-heading">Terms and Conditions</h3>
            @include('authentication::partials.terms-and-conditions')

            <h3 class="kkp-legal-doc-heading">Privacy Policy</h3>
            @include('authentication::partials.privacy-policy')

            <label class="auth-legal-modal-ack auth-legal-modal-ack--locked" id="kkpTermsModalAckLabel">
                <input type="checkbox" id="kkpTermsModalAck" disabled>
                <span>I have read and agree to the Terms and Conditions and Privacy Policy.</span>
            </label>
        </div>
    </div>
</div>
