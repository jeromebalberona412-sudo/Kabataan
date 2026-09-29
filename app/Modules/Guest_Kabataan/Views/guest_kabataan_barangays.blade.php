<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Choose a barangay — Guest</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Guest_Kabataan/assets/css/guest_kabataan.css',
        'app/Modules/Guest_Kabataan/assets/js/guest_kabataan.js',
    ])
</head>
<body class="guest-kabataan-body">
    @include('layout::kabataan-header', [
        'guestHeader' => true,
        'barangay' => $selected,
    ])
    @include('guest_kabataan::partials.guest_kabataan_subnav')
    @include('guest_kabataan::partials.guest_kabataan_header')

    <main class="guest-kabataan-main">
        <section class="guest-kabataan-intro">
            <p class="guest-kabataan-kicker">Guest browsing</p>
            <h1>Choose your barangay</h1>
            <p>Select a barangay to see the youth programs that are open there. Applying checks your KK Profiling before an account is created.</p>
        </section>

        <form class="guest-kabataan-search" role="search">
            <label for="guestKabataanBarangaySearch">Search barangay</label>
            <input
                type="search"
                id="guestKabataanBarangaySearch"
                placeholder="Type a barangay name"
                autocomplete="off"
            >
        </form>

        @if ($selected)
            <p class="guest-kabataan-current">
                Current barangay: <strong>{{ $selected->name }}</strong>.
                <a href="{{ route('guest_kabataan.home') }}">Back to open programs</a>
            </p>
        @endif

        <form method="POST" action="{{ route('guest_kabataan.select') }}" class="guest-kabataan-barangay-form">
            @csrf
            @error('barangay_id')
                <p class="guest-kabataan-error" role="alert">{{ $message }}</p>
            @enderror

            <ul class="guest-kabataan-barangay-list" id="guestKabataanBarangayList">
                @forelse ($barangays as $barangay)
                    <li data-guest-barangay="{{ strtolower($barangay->name) }}">
                        <button
                            type="submit"
                            name="barangay_id"
                            value="{{ $barangay->id }}"
                            class="guest-kabataan-barangay {{ $selected && (int) $selected->id === (int) $barangay->id ? 'is-current' : '' }}"
                        >
                            <span class="guest-kabataan-barangay__logo">
                                @if (! empty($barangay->logo_url))
                                    <img src="{{ $barangay->logo_url }}" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                @endif
                                <span @if (! empty($barangay->logo_url)) hidden @endif>{{ strtoupper(mb_substr($barangay->name, 0, 1)) }}</span>
                            </span>
                            <span class="guest-kabataan-barangay__copy">
                                <span class="guest-kabataan-barangay__name">{{ $barangay->name }}</span>
                                <span class="guest-kabataan-barangay__place">{{ $barangay->municipality ?: 'Santa Cruz' }}, {{ $barangay->province ?: 'Laguna' }}</span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="guest-kabataan-empty">No barangays are available yet.</li>
                @endforelse
            </ul>
            <p class="guest-kabataan-empty" id="guestKabataanBarangayEmpty" hidden>No barangay matches that search.</p>
        </form>
    </main>
</body>
</html>
