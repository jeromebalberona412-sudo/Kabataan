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
                <div class="baranggay-abyip-meta-badge" id="barangayAbyipMetaBadge" style="margin-bottom: 12px; display: none; align-items: center; justify-content: center; gap: 8px;">
                    <span class="status-badge status-approved" id="barangayAbyipStatusBadge" style="padding: 3px 10px; border-radius: 9999px; font-weight: 600; font-size: 12px; background-color: #dcfce7; color: #166534;">Published</span>
                    <span id="barangayAbyipPublishedDate" style="font-size: 13px; color: #64748b;"></span>
                </div>
                <p id="barangayAbyipStatus" class="baranggay-abyip-status" role="status">Choose a fiscal year, then open the ABYIP document.</p>
                <div class="baranggay-abyip-actions" style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="baranggay-abyip-view-btn" id="barangayAbyipViewBtn" disabled>
                        View full ABYIP
                    </button>
                    <a href="#" class="baranggay-abyip-view-btn" id="barangayAbyipDownloadBtn" style="text-decoration: none; display: none; align-items: center; gap: 6px; background-color: #059669; border-color: #059669;" target="_blank" download>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download PDF
                    </a>
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
