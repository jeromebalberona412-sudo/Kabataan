@extends('program_accomplishments::layout')

@section('title', $barangay->name . ' Accomplishments — SK OnePortal Kabataan')

@push('styles')
    @vite([
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishments.css',
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishment-show.css',
        'app/Modules/Program_Accomplishments/assets/css/program-card-expand.css',
    ])
@endpush

@push('scripts')
    @vite([
        'app/Modules/Program_Accomplishments/assets/js/barangay-accomplishments.js',
        'app/Modules/Program_Accomplishments/assets/js/program-card-expand.js',
    ])
@endpush

@section('content')
<div class="barangay-accomplishments-page kabataan-page-section barangay-accomplishments-offset ba-show">
    <section class="accomplishments-detail-hero">
        <div class="container accomplishments-shell">
            <a href="{{ route('program_accomplishments.barangays') }}" class="accomplishments-back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to all barangays
            </a>

            <div class="ba-profile">
                @if (!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="" class="ba-profile-logo" loading="lazy">
                @else
                    <span class="ba-profile-logo ba-profile-logo-fallback" aria-hidden="true">{{ strtoupper(mb_substr($barangay->name, 0, 1)) }}</span>
                @endif
                <div class="ba-profile-copy">
                    <div class="ba-profile-title-row">
                        <h1>{{ $barangay->name }}</h1>
                    </div>
                    <p class="ba-profile-caption">Municipality of Santa Cruz, Laguna.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="accomplishments-detail-body">
        <div class="container accomplishments-shell">
            @if ($accomplishment && ($accomplishment->status ?? '') === 'pending')
                <div class="accomplishments-pending-banner" role="note" aria-label="Accomplishments status">
                    <svg class="accomplishments-pending-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span class="accomplishments-pending-text">This Accomplishments is pending review and may be updated.</span>
                </div>
            @endif

            @if ($accomplishment === null && $programCards->isEmpty())
                <div class="no-doc accomplishments-empty-state">
                    <h2>No Accomplishment uploaded yet</h2>
                    <p>
                        {{ $barangay->name }} has not published an Accomplishments document for public viewing yet.
                        Check back later or contact your barangay SK officials.
                    </p>
                </div>
            @endif

            @if ($programCards->isNotEmpty())
                <div class="ba-stats">
                    <article class="ba-stat-card">
                        <span class="ba-stat-icon ba-stat-icon-blue" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <div>
                            <h2>{{ $stats['total'] }}</h2>
                            <p>Total Accomplishments</p>
                            <small>{{ $stats['total'] }} Published Programs.</small>
                        </div>
                    </article>
                    <article class="ba-stat-card">
                        <span class="ba-stat-icon ba-stat-icon-green" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <div>
                            <h2>{{ number_format($stats['beneficiaries']) }}</h2>
                            <p>Total Beneficiaries</p>
                            <small>{{ number_format($stats['beneficiaries']) }} Across all programs.</small>
                        </div>
                    </article>
                    <article class="ba-stat-card">
                        <span class="ba-stat-icon ba-stat-icon-purple" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <div>
                            <h2>{{ $stats['latest'] ?: '—' }}</h2>
                            <p>Latest Update</p>
                            <small>Most recent accomplishment.</small>
                        </div>
                    </article>
                </div>

                <div class="ba-layout">
                    <div class="ba-main">
                        <div class="ba-toolbar">
                            <label class="ba-search" for="baProgramSearch">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="search" id="baProgramSearch" placeholder="Search accomplishments..." autocomplete="off" aria-label="Search accomplishments">
                            </label>
                            <div class="ba-toolbar-filters">
                                <label class="visually-hidden" for="baCategoryFilter">Programs</label>
                                <select id="baCategoryFilter" class="ba-select">
                                    <option value="">All Programs</option>
                                    @foreach ($categoryCounts as $category => $count)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                                <label class="visually-hidden" for="baStatusFilter">Status</label>
                                <select id="baStatusFilter" class="ba-select">
                                    <option value="">All Status</option>
                                    <option value="Completed">Completed</option>
                                </select>
                                <label class="visually-hidden" for="baYearFilter">Year</label>
                                <select id="baYearFilter" class="ba-select">
                                    <option value="">All Years</option>
                                    @foreach ($years as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                                <label class="visually-hidden" for="baSortFilter">Sort</label>
                                <select id="baSortFilter" class="ba-select">
                                    <option value="latest">Sort by: Latest</option>
                                    <option value="oldest">Sort by: Oldest</option>
                                    <option value="name">Sort by: Name</option>
                                </select>
                                <div class="ba-view-toggle" role="group" aria-label="View mode">
                                    <button type="button" class="ba-view-btn is-active" data-view="grid" aria-pressed="true" aria-label="Grid view">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                    </button>
                                    <button type="button" class="ba-view-btn" data-view="list" aria-pressed="false" aria-label="List view">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="3"/><rect x="3" y="10.5" width="18" height="3"/><rect x="3" y="16" width="18" height="3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="ba-program-grid" id="baProgramGrid">
                            @foreach ($programCards as $card)
                                <article
                                    class="ba-program-card"
                                    data-id="{{ $card['id'] }}"
                                    data-title="{{ strtolower($card['title']) }}"
                                    data-category="{{ $card['category'] }}"
                                    data-status="{{ $card['status'] }}"
                                    data-year="{{ $card['year'] }}"
                                    data-sort-date="{{ $card['sort_date'] }}"
                                >
                                    <div class="ba-program-cover">
                                        @if (!empty($card['cover']))
                                            <img src="{{ $card['cover'] }}" alt="" loading="lazy">
                                        @else
                                            <span class="ba-program-cover-fallback" aria-hidden="true"></span>
                                        @endif
                                        <span class="ba-program-status">{{ $card['status'] }}</span>
                                    </div>
                                    <div class="ba-program-body">
                                        <div class="ba-program-title-row">
                                            <h3>{{ $card['title'] }}</h3>
                                        </div>
                                        <p class="ba-program-meta">
                                            {{ $card['date_label'] }}
                                            · {{ $card['location'] }}
                                            · {{ $card['duration'] }}
                                        </p>
                                        <div class="ba-program-metrics">
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                                <strong>{{ number_format($card['beneficiaries']) }}</strong>
                                                Beneficiaries
                                            </span>
                                        </div>
                                        <button type="button" class="ba-view-details" aria-expanded="false">
                                            <span>View Details</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    </div>
                                    @include('program_accomplishments::barangays.partials.program-expand', ['card' => $card])
                                </article>
                            @endforeach
                        </div>

                        <p class="ba-empty-filter" id="baEmptyFilter" hidden>No accomplishments match your filters.</p>
                        <button type="button" class="ba-load-more" id="baLoadMore" hidden>
                            Load More
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </div>

                    <aside class="ba-sidebar">
                        <section class="ba-side-card">
                            <h2>Programs</h2>
                            <ul class="ba-category-list">
                                <li>
                                    <button type="button" class="ba-category-item is-active" data-category="">
                                        All
                                        <span>{{ $programCards->count() }}</span>
                                    </button>
                                </li>
                                @foreach ($categoryCounts as $category => $count)
                                    <li>
                                        <button type="button" class="ba-category-item" data-category="{{ $category }}">
                                            {{ $category }}
                                            <span>{{ $count }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                        <section class="ba-side-card">
                            <h2>Documents</h2>
                            @php
                                $sidebarDocuments = $programCards->flatMap(fn ($item) => $item['documents'] ?? [])->unique('id')->values();
                            @endphp
                            @if ($sidebarDocuments->isEmpty())
                                <p>No public documents uploaded.</p>
                            @else
                                <ul class="ba-doc-list">
                                    @foreach ($sidebarDocuments as $document)
                                        <li>
            @if (($document['type'] ?? '') === 'PDF')
                                            <span class="ba-doc-type is-pdf">PDF</span>
                                        @else
                                            <span class="ba-doc-type is-word">{{ $document['type'] ?? 'DOC' }}</span>
                                        @endif
                                            <span class="ba-doc-copy">
                                                <strong>{{ $document['name'] }}</strong>
                                                @if (($document['size'] ?? '') !== '')
                                                    <small>{{ $document['size'] }}</small>
                                                @endif
                                            </span>
                                            <a href="{{ $document['url'] }}" class="ba-doc-download" download="{{ $document['name'] }}" rel="noopener" aria-label="Download {{ $document['name'] }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                        <section class="ba-side-card">
                            <h2>About This Page</h2>
                            <p>Published SK program accomplishments for {{ $barangay->name }}, including beneficiaries, photos, and supporting documents.</p>
                            <a href="{{ route('homepage') }}#programs" class="ba-side-link">
                                Learn more about SK programs
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        </section>
                    </aside>
                </div>
            @endif

            @if ($accomplishment)
                <div class="accomplishments-budget-cards">
                    <article class="accomplishments-budget-pill">
                        <span>Estimated Budget</span>
                        <strong>₱{{ number_format((float) $accomplishment->estimated_budget, 2) }}</strong>
                    </article>
                    <article class="accomplishments-budget-pill">
                        <span>SK Fund (10%)</span>
                        <strong>₱{{ number_format((float) $accomplishment->sk_fund, 2) }}</strong>
                    </article>
                    <article class="accomplishments-budget-pill">
                        <span>Total Expenditure</span>
                        <strong>₱{{ number_format((float) $accomplishment->total_expenditure, 2) }}</strong>
                    </article>
                </div>

                <div class="accomplishments-table-wrap" tabindex="0" aria-label="PPA table, scroll horizontally to view all columns">
                    <table class="accomplishments-table">
                        <thead>
                            <tr>
                                <th class="accomplishments-col-ppa">PPA</th>
                                <th class="accomplishments-col-description">Description</th>
                                <th class="accomplishments-col-expected">Expected Result</th>
                                <th class="accomplishments-col-indicator">Performance Indicator</th>
                                <th class="accomplishments-col-period">Period</th>
                                <th class="accomplishments-col-mooe">MOOE</th>
                                <th class="accomplishments-col-co">CO</th>
                                <th class="accomplishments-col-total">Total</th>
                                <th class="accomplishments-col-person">Person Responsible</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accomplishment->items as $item)
                                @if ($item->row_type === 'section')
                                    <tr class="accomplishments-row-section">
                                        <td colspan="9">{{ $item->label }}</td>
                                    </tr>
                                @elseif ($item->row_type === 'subsection')
                                    <tr class="accomplishments-row-subsection">
                                        <td colspan="9">{{ $item->label }}</td>
                                    </tr>
                                @else
                                    <tr class="accomplishments-row-item">
                                        <td class="accomplishments-col-ppa accomplishments-cell">{{ $item->ppa ?: '—' }}</td>
                                        <td class="accomplishments-col-description accomplishments-cell accomplishments-longtext">{{ $item->description ?: '—' }}</td>
                                        <td class="accomplishments-col-expected accomplishments-cell accomplishments-longtext">{{ $item->expected_result ?: '—' }}</td>
                                        <td class="accomplishments-col-indicator accomplishments-cell accomplishments-longtext">{{ $item->performance_indicator ?: '—' }}</td>
                                        <td class="accomplishments-col-period accomplishments-cell">{{ $item->period ?: '—' }}</td>
                                        <td class="accomplishments-col-mooe accomplishments-cell accomplishments-num">₱{{ number_format((float) $item->mooe, 2) }}</td>
                                        <td class="accomplishments-col-co accomplishments-cell accomplishments-num">₱{{ number_format((float) $item->co, 2) }}</td>
                                        <td class="accomplishments-col-total accomplishments-cell accomplishments-num">₱{{ number_format((float) $item->total, 2) }}</td>
                                        <td class="accomplishments-col-person accomplishments-cell">{{ $item->person_responsible ?: '—' }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                    <div class="accomplishments-scroll-hint" aria-hidden="true">
                        <span>Scroll horizontally →</span>
                    </div>
                </div>

                <div class="accomplishments-signatories">
                    <article>
                        <span>{{ $accomplishment->chairperson_title }}</span>
                        <strong>{{ $accomplishment->chairperson_name ?: 'Not yet on file' }}</strong>
                        <small>Prepared by</small>
                    </article>
                    <article>
                        <span>{{ $accomplishment->approved_by_title }}</span>
                        <strong>{{ $accomplishment->approved_by_name ?: 'Not yet on file' }}</strong>
                        <small>Approved by</small>
                    </article>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="program-photo-lightbox" id="programPhotoLightbox" hidden>
    <button type="button" class="program-photo-lightbox-close" id="programPhotoLightboxClose" aria-label="Close photo preview">×</button>
    <button type="button" class="program-photo-lightbox-nav is-prev" id="programPhotoLightboxPrev" aria-label="Previous photo" hidden>‹</button>
    <img src="" alt="" id="programPhotoLightboxImage">
    <button type="button" class="program-photo-lightbox-nav is-next" id="programPhotoLightboxNext" aria-label="Next photo" hidden>›</button>
</div>
@endsection
