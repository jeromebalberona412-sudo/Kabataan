@extends('homepage::layout')

@section('title', $barangay->name . ' ABYIP — SK OnePortal Kabataan')

@push('styles')
    @vite([
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishments.css',
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishment-show.css',
        'app/Modules/Baranggay_ABYIP/assets/css/baranggay_abyip.css',
    ])
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (window.pdfjsLib) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>
    @vite([
        'app/Modules/Baranggay_ABYIP/assets/js/baranggay_abyip.js',
    ])
@endpush

@section('content')
<div
    class="barangay-accomplishments-page kabataan-page-section barangay-accomplishments-offset ba-show baranggay-abyip-page"
    data-abyip-documents-url="{{ $documentsUrl }}"
>
    <section class="accomplishments-detail-hero baranggay-abyip-hero">
        <div class="container accomplishments-shell">
            <a href="{{ route('baranggay_abyip.index') }}" class="accomplishments-back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to all barangays
            </a>

            <div class="ba-profile baranggay-abyip-toolbar">
                @if (!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="" class="ba-profile-logo" loading="lazy">
                @else
                    <span class="ba-profile-logo ba-profile-logo-fallback" aria-hidden="true">{{ strtoupper(mb_substr($barangay->name, 0, 1)) }}</span>
                @endif
                <div class="ba-profile-copy">
                    <div class="ba-profile-title-row">
                        <h1>{{ $barangay->name }}</h1>
                    </div>
                    <p class="ba-profile-caption">Annual Barangay Youth Investment Program (ABYIP)</p>
                </div>
                <label class="baranggay-abyip-year" for="barangayAbyipYear">
                    <span>Fiscal year</span>
                    <select id="barangayAbyipYear" aria-label="ABYIP fiscal year" disabled>
                        <option value="">Loading years...</option>
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="baranggay-abyip-doc-section">
        <div class="container accomplishments-shell">
            <div class="baranggay-abyip-gate" id="barangayAbyipGate">
                <p id="barangayAbyipStatus" class="baranggay-abyip-status" role="status">Choose a fiscal year, then open the ABYIP document.</p>
                <div class="baranggay-abyip-actions">
                    <button type="button" class="baranggay-abyip-view-btn" id="barangayAbyipViewBtn" disabled>
                        View full ABYIP
                    </button>
                    <button type="button" class="baranggay-abyip-hide-btn" id="barangayAbyipHideBtn" hidden>
                        Hide
                    </button>
                </div>
            </div>
            <div id="barangayAbyipPages" class="baranggay-abyip-pages" hidden></div>
        </div>
    </section>
</div>
@endsection
