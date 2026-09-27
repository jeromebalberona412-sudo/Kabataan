<header class="guest-kabataan-header">
    <div class="guest-kabataan-header__bar">
        <a href="{{ $barangay ? route('guest_kabataan.home') : route('guest_kabataan.barangays') }}" class="guest-kabataan-header__brand">
            <img src="{{ asset('images/skoneportal_logo.webp') }}" alt="SK OnePortal" class="guest-kabataan-header__logo">
            <span class="guest-kabataan-header__title">
                Kabataan
                <small>Guest</small>
            </span>
        </a>

        @if (! empty($barangay))
            <p class="guest-kabataan-header__place">Barangay {{ $barangay->name }}</p>
        @endif

        <div class="guest-kabataan-header__actions">
            @if (! empty($barangay) && ! request()->routeIs('guest_kabataan.barangays'))
                <a href="{{ route('guest_kabataan.barangays') }}" class="guest-kabataan-header__link">Change barangay</a>
            @endif
            <a href="{{ route('sign-in') }}" class="guest-kabataan-header__link">Sign in</a>
            <button type="button" class="guest-kabataan-header__logout" id="guestKabataanLogoutBtn">Log out</button>
        </div>
    </div>
</header>

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
