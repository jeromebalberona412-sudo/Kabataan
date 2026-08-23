{{-- Shared signature pad + confirmation modals for KK Profiling forms --}}
<div class="kkp-sig-pad-overlay" id="kkpSignaturePadOverlay" style="display:none;">
    <div class="kkp-sig-pad-modal">
        <div class="kkp-sig-pad-header">
            <h3 class="kkp-sig-pad-title">Please Sign Here</h3>
            <button type="button" class="kkp-sig-pad-close" id="kkpSignaturePadClose" aria-label="Close">&times;</button>
        </div>
        <div class="kkp-sig-pad-body">
            <div class="kkp-sig-canvas-wrap">
                <canvas id="kkpSignaturePadCanvas" class="kkp-sig-canvas"></canvas>
                <div class="kkp-sig-canvas-placeholder" id="kkpSignatureCanvasPlaceholder">
                    Sign here with your mouse or finger
                </div>
            </div>
        </div>
        <div class="kkp-sig-pad-footer">
            <button type="button" class="kkp-sig-btn-clear" id="kkpSignaturePadClear">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Clear
            </button>
            <button type="button" class="kkp-sig-btn-save" id="kkpSignaturePadSave">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Save Signature
            </button>
        </div>
    </div>
</div>

<div class="kkp-sig-confirm-overlay" id="kkpSigConfirmOverlay" style="display:none;">
    <div class="kkp-sig-confirm-modal">
        <h3 class="kkp-sig-confirm-title">Save Signature?</h3>
        <p class="kkp-sig-confirm-message">Are you sure you want to save this signature?</p>
        <div class="kkp-sig-confirm-actions">
            <button type="button" class="kkp-sig-confirm-cancel" id="kkpSigConfirmCancel">Cancel</button>
            <button type="button" class="kkp-sig-confirm-save" id="kkpSigConfirmSave">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Save Signature
            </button>
        </div>
    </div>
</div>
