{{-- Mobile programs drawer — include on Kabataan pages that use kabataan-header --}}
<div id="programsDrawerBackdrop" class="programs-drawer-backdrop" aria-hidden="true"></div>
<aside class="programs-sidebar" id="programsDrawerSidebar" aria-label="Programs menu">
    <div class="sidebar-card" data-tour="programs">
        <div class="programs-drawer-head">
            <div class="programs-drawer-head__text">
                <h2 class="sidebar-title">Programs in Your Barangay</h2>
                <p class="sidebar-subtitle">Available programs in Barangay {{ $barangayName ?? 'Your Barangay' }}</p>
            </div>
            <button type="button" class="programs-drawer-close" data-programs-drawer-close aria-label="Close programs">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
        <div class="program-categories" id="programCategoriesDrawerContainer">
            <p class="programs-drawer-loading">Loading programs…</p>
        </div>
    </div>

    <div class="sidebar-card programs-drawer-barangay-card" style="margin-top:16px;" data-tour="barangay-profiles">
        <h2 class="sidebar-title">Barangay SK Profiles</h2>
        <p class="sidebar-subtitle">Browse SK officials from each barangay.</p>
        @if (!empty($barangayProfiles) && is_iterable($barangayProfiles))
            <div class="barangay-profiles-list">
                @include('dashboard::partials.barangay-profiles-list', ['barangayProfiles' => $barangayProfiles])
            </div>
        @else
            <a href="{{ route('dashboard') }}" class="programs-drawer-barangay-link">
                Open Barangay SK Profiles on Home
            </a>
        @endif
    </div>
</aside>
