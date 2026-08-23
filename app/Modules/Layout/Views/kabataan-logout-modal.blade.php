{{-- Shared logout confirmation — included from kabataan-header --}}
<div id="kabataanLogoutModal" class="kab-logout-modal" hidden role="dialog" aria-modal="true" aria-labelledby="kabLogoutTitle">
    <div class="kab-logout-modal__overlay"></div>
    <div class="kab-logout-modal__panel">
        <div class="kab-logout-modal__head">
            <h2 id="kabLogoutTitle">Confirm Logout</h2>
            <button type="button" class="kab-logout-modal__close" onclick="closeKabataanLogoutModal()" aria-label="Close">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
        <div class="kab-logout-modal__body">
            <h3>Are you sure you want to logout?</h3>
            <p>You will be redirected to the sign in page.</p>
            <div class="kab-logout-modal__actions">
                <button type="button" class="kab-logout-modal__btn kab-logout-modal__btn--cancel" onclick="closeKabataanLogoutModal()">Cancel</button>
                <button type="button" class="kab-logout-modal__btn kab-logout-modal__btn--confirm" id="kabataanConfirmLogoutBtn">Logout</button>
            </div>
        </div>
    </div>
</div>
