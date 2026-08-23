@extends('homepage::layout')

@section('title', 'Barangay ABYIP — SK OnePortal Kabataan')

@push('styles')
    @vite([
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishments.css',
        'app/Modules/Baranggay_ABYIP/assets/css/baranggay_abyip.css',
    ])
@endpush

@push('scripts')
    @vite([
        'app/Modules/Baranggay_ABYIP/assets/js/baranggay_abyip.js',
    ])
@endpush

@section('content')
<div class="barangay-accomplishments-page kabataan-page-section barangay-accomplishments-offset baranggay-abyip-page">
    <section class="accomplishments-hero">
        <div class="container accomplishments-shell">
            <div class="accomplishments-hero-row">
                <div class="accomplishments-hero-copy">
                    <h1>Barangay ABYIP</h1>
                    <p class="accomplishments-hero-text">
                        Browse Annual Barangay Youth Investment Program (ABYIP) PDFs uploaded by each barangay in Santa Cruz, Laguna.
                    </p>
                </div>

                <label class="accomplishments-search" for="barangayAbyipSearch">
                    <span class="accomplishments-search-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input
                        type="search"
                        id="barangayAbyipSearch"
                        placeholder="Search barangays..."
                        autocomplete="off"
                        aria-label="Search barangays"
                    >
                </label>
            </div>
        </div>
    </section>

    <section class="accomplishments-list-section">
        <div class="container accomplishments-shell">
            @if ($barangays->isEmpty())
                <div class="accomplishments-empty-state">
                    <h2>No barangays available yet</h2>
                    <p>Barangay listings will appear here once they are added to the system.</p>
                </div>
            @else
                <div class="accomplishments-summary">
                    <span id="barangayAbyipResultCount">Showing {{ $barangays->count() }} barangays</span>
                </div>

                <div class="kabataan-barangay-grid accomplishments-barangay-grid" id="barangayAbyipGrid">
                    @foreach ($barangays as $barangay)
                        <a
                            href="{{ route('baranggay_abyip.show', $barangay->slug) }}"
                            class="kabataan-barangay-card accomplishments-barangay-card"
                            data-barangay-name="{{ strtolower($barangay->name) }}"
                        >
                            <div class="accomplishments-card-top">
                                <span class="accomplishments-card-identity">
                                    <span class="accomplishments-card-logo-wrap">
                                        @if ($barangay->logo_url)
                                            <img src="{{ $barangay->logo_url }}" alt="" class="accomplishments-card-logo" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                        @endif
                                        <span class="accomplishments-card-logo-fallback" @if ($barangay->logo_url) hidden @endif>{{ strtoupper(mb_substr($barangay->name, 0, 1)) }}</span>
                                    </span>
                                    <span class="kabataan-barangay-name">{{ $barangay->name }}</span>
                                </span>
                                <span class="accomplishments-status-badge {{ $barangay->abyip_exists ? 'is-available' : 'is-missing' }}">
                                    {{ $barangay->abyip_exists ? 'ABYIP available' : 'No ABYIP uploaded' }}
                                </span>
                            </div>
                            <p class="accomplishments-card-copy">View the uploaded ABYIP PDF for this barangay.</p>
                            <span class="accomplishments-card-link">View ABYIP →</span>
                        </a>
                    @endforeach
                </div>

                <div class="accomplishments-empty-state accomplishments-filter-empty" id="barangayAbyipFilterEmpty" hidden>
                    <h2>No matching barangays</h2>
                    <p>Try a different search term.</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
