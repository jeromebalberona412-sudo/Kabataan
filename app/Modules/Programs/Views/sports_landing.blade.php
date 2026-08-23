<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sports Application - SK OnePortal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Layout/assets/css/programs-drawer.css',
        'app/Modules/Layout/assets/js/kabataan-header.js',
        'app/Modules/Dashboard/assets/css/chatbot.css',
        'app/Modules/Dashboard/assets/js/chatbot.js',
        'app/Modules/Dashboard/assets/css/notif.css',
        'app/Modules/Dashboard/assets/js/notif.js',
        'app/Modules/Dashboard/assets/css/dashboard.css',
        'app/Modules/Programs/assets/css/scholarship_landing.css',
        'app/Modules/Programs/assets/css/sports_landing.css',
        'app/Modules/Programs/assets/css/sports-applications-history.css',
        'app/Modules/Programs/assets/js/kabataan-programs.js',
        'app/Modules/Programs/assets/js/sports-applications-history.js',
        'app/Modules/Programs/assets/js/sports_landing.js',
    ])
</head>
<body class="sl-body sports-landing-page kabataan-app-page">
    @include('layout::kabataan-header', ['showSearch' => false, 'pageBadge' => null])

    <div id="sportsLandingContent" class="sl-container">
        <div class="sl-header sl-header--compact">
            <h1 class="sl-title">Sports Application</h1>
            <p class="sl-subtitle">Select a sport type to view open programs from your barangay SK Officials.</p>
        </div>

        <div class="sports-type-tabs" id="sportsTypeTabs" role="tablist" aria-label="Sport types">
            <button type="button" class="sports-type-tab is-active" data-sport-tab="basketball" role="tab" aria-selected="true">Basketball</button>
            <button type="button" class="sports-type-tab" data-sport-tab="volleyball" role="tab" aria-selected="false">Volleyball</button>
            <button type="button" class="sports-type-tab" data-sport-tab="other" role="tab" aria-selected="false">Other</button>
        </div>

        <div id="sportsProgramsContainer" class="sports-programs-container">
            <p class="sports-programs-loading">Loading sports programs…</p>
        </div>

        <div class="sports-history-toolbar" id="sportsHistoryToolbar" hidden>
            <div class="sports-history-toolbar__field">
                <label class="sports-history-toolbar__label" for="sportsHistoryYearFilter">Year</label>
                <select id="sportsHistoryYearFilter" class="sports-history-toolbar__select" aria-label="Filter by year">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="sports-history-toolbar__field sports-history-toolbar__field--search">
                <label class="sports-history-toolbar__label" for="sportsHistorySearch">Search</label>
                <input type="search" id="sportsHistorySearch" class="sports-history-toolbar__search" placeholder="Sport, program, team, status…" autocomplete="off">
            </div>
        </div>

        <div class="sl-card sl-card-history sports-applications-history-section" hidden>
            <div class="sl-card-header">
                <h2 class="sl-card-title">My Sports Applications</h2>
                <p class="sl-card-subtitle">All sports you have previously applied for, grouped by year and sport type.</p>
            </div>
            <div class="sl-card-body">
                <div id="sportsApplicationsHistory" class="sports-applications-history">
                    <p class="sports-history-loading">Loading your sports applications…</p>
                </div>
            </div>
        </div>
    </div>

    @include('programs::partials.sports-applications-history-modals')

    @include('programs::partials.kabataan-program-modals')

    @include('layout::programs-drawer', ['barangayName' => $barangayName ?? 'Your Barangay'])

    <script>
        window.__scheduleProgramId = @json($scheduleProgramId);
        window.__kkFieldLabels = @json($kkFieldLabels);
        window.__kabataanPrograms = @json($programsPayload ?? ['abyip_programs' => [], 'schedule_programs' => [], 'pending_evaluations' => []]);
    </script>
</body>
</html>
