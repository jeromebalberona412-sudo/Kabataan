<div class="guest-kabataan-logout" id="guestKabataanLogout" hidden>
    <div class="guest-kabataan-logout__backdrop" data-guest-logout-close></div>
    <div class="guest-kabataan-logout__panel" role="dialog" aria-modal="true" aria-labelledby="guestKabataanLogoutTitle">
        <h2 id="guestKabataanLogoutTitle">Leave guest browsing?</h2>
        <p>You will return to the sign-in page. Open programs stay available the next time you continue as a guest.</p>
        <div class="guest-kabataan-logout__actions">
            <button type="button" class="guest-kabataan-btn guest-kabataan-btn--ghost" data-guest-logout-close>Stay</button>
            <form method="POST" action="{{ route('guest_kabataan.logout') }}">
                @csrf
                <button type="submit" class="guest-kabataan-btn guest-kabataan-btn--danger">Log out</button>
            </form>
        </div>
    </div>
</div>
