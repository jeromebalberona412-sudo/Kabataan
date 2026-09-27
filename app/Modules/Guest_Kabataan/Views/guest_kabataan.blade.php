<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Open programs — {{ $barangay->name }}</title>
    @vite([
        'app/Modules/Guest_Kabataan/assets/css/guest_kabataan.css',
        'app/Modules/Guest_Kabataan/assets/js/guest_kabataan.js',
    ])
</head>
<body class="guest-kabataan-body">
    @include('guest_kabataan::partials.guest_kabataan_header', ['barangay' => $barangay])

    <main class="guest-kabataan-main">
        <section class="guest-kabataan-intro">
            <p class="guest-kabataan-kicker">Barangay {{ $barangay->name }}</p>
            <h1>Open programs</h1>
            <p>These are the programs currently open in this barangay. You can read the details here. Applying is available after you sign in with a Kabataan account.</p>
        </section>

        @if ($programs === [])
            <section class="guest-kabataan-panel">
                <h2>No open programs</h2>
                <p>Barangay {{ $barangay->name }} has no programs open right now. You can choose another barangay or sign in if you already have an account.</p>
                <a href="{{ route('guest_kabataan.barangays') }}" class="guest-kabataan-btn">Choose another barangay</a>
            </section>
        @else
            <form class="guest-kabataan-search" role="search">
                <label for="guestKabataanProgramSearch">Search programs</label>
                <input
                    type="search"
                    id="guestKabataanProgramSearch"
                    placeholder="Search by program or category"
                    autocomplete="off"
                >
            </form>
            <ul class="guest-kabataan-program-list" id="guestKabataanProgramList">
                @foreach ($programs as $program)
                    <li
                        class="guest-kabataan-program"
                        data-guest-program="{{ strtolower($program['name'].' '.$program['category'].' '.$program['type'].' '.$program['sport']) }}"
                    >
                        <div class="guest-kabataan-program__top">
                            <p class="guest-kabataan-program__category">{{ $program['category'] }}</p>
                            <span class="guest-kabataan-program__status">Open</span>
                        </div>
                        <h2>{{ $program['name'] }}</h2>
                        <dl class="guest-kabataan-program__facts">
                            @if ($program['type'] !== '')
                                <div>
                                    <dt>Type</dt>
                                    <dd>{{ $program['type'] }}</dd>
                                </div>
                            @endif
                            @if ($program['sport'] !== '')
                                <div>
                                    <dt>Sport</dt>
                                    <dd>{{ $program['sport'] }}</dd>
                                </div>
                            @endif
                            @if ($program['committee'] !== '')
                                <div>
                                    <dt>Committee</dt>
                                    <dd>{{ $program['committee'] }}</dd>
                                </div>
                            @endif
                            @if ($program['start'] !== '' || $program['end'] !== '')
                                <div>
                                    <dt>Schedule</dt>
                                    <dd>
                                        {{ $program['start'] !== '' ? $program['start'] : 'Open' }}
                                        @if ($program['end'] !== '')
                                            – {{ $program['end'] }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            @if ($program['slots'] !== null)
                                <div>
                                    <dt>Slots left</dt>
                                    <dd>{{ $program['slots'] }} of {{ $program['capacity'] }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if ($program['announcement'] !== '')
                            <p class="guest-kabataan-program__announcement">{{ $program['announcement'] }}</p>
                        @endif
                        <p class="guest-kabataan-program__note">Sign in to apply. Guest access is for viewing open programs only.</p>
                    </li>
                @endforeach
            </ul>
            <p class="guest-kabataan-empty" id="guestKabataanProgramEmpty" hidden>No open program matches that search.</p>
        @endif
    </main>
</body>
</html>
