@php
    $guestBarangay = $barangay ?? ($selected ?? null);
@endphp
<nav class="guest-kabataan-subnav" aria-label="Barangay actions">
    <p class="guest-kabataan-subnav__place">{{ $guestBarangay ? 'Barangay '.$guestBarangay->name : 'Guest' }}</p>
    <div class="guest-kabataan-subnav__links">
        @if ($guestBarangay && ! request()->routeIs('guest_kabataan.barangays'))
            <a href="{{ route('guest_kabataan.barangays') }}" class="guest-kabataan-subnav__link">Change barangay</a>
        @endif
        <a href="{{ route('sign-in') }}" class="guest-kabataan-subnav__link">Sign in</a>
    </div>
</nav>
