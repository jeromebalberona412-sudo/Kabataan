@extends('homepage::layout')

@section('title', 'Programs - SK OnePortal Kabataan')

@section('content')
@php
    $sectorMeta = [
        'education' => ['label' => 'Education', 'icon' => 'school'],
        'sports' => ['label' => 'Sports', 'icon' => 'sports'],
        'health' => ['label' => 'Health', 'icon' => 'health'],
        'agriculture' => ['label' => 'Agriculture', 'icon' => 'agri'],
        'gad' => ['label' => 'GAD', 'icon' => 'groups'],
        'anti-drugs' => ['label' => 'Anti-Drugs', 'icon' => 'shield'],
        'disaster' => ['label' => 'Disaster', 'icon' => 'warning'],
        'others' => ['label' => 'Others', 'icon' => 'apps'],
    ];

    $statusMeta = [
        'active' => ['label' => 'Active', 'note' => 'Open for registration'],
        'upcoming' => ['label' => 'Upcoming', 'note' => 'Starts soon'],
        'completed' => ['label' => 'Completed', 'note' => 'Already concluded'],
    ];

    $programs = [];
@endphp

<div class="programs-page">
    <section class="programs-hero-v2">
        <div class="programs-hero-overlay"></div>
        <div class="programs-shell programs-hero-content">
            <span class="programs-eyebrow">Kabataan Programs</span>
            <h1>Discover Programs Across Santa Cruz</h1>
            <p>Explore youth initiatives by sector, barangay, and status. Track participation and budget transparency before you join.</p>
            <div class="programs-hero-actions">
                <a href="#programs-grid" class="programs-btn programs-btn-primary">Explore Programs</a>
            </div>
        </div>
    </section>

    <section class="programs-discovery">
        <div class="programs-shell">
            <header class="programs-headline">
                <h2>Programs Discovery</h2>
                <p>Find opportunities that match your interests and schedule.</p>
            </header>

            <div class="programs-toolbar" role="region" aria-label="Program filters">
                <label class="programs-search-wrap" for="programSearch">
                    <span class="programs-search-icon">Search</span>
                    <input id="programSearch" type="text" placeholder="Search programs, sectors, barangays, or keywords" autocomplete="off">
                </label>

                <select id="sectorFilter" aria-label="Filter by sector">
                    <option value="">All Sectors</option>
                    <option value="education">Education</option>
                    <option value="sports">Sports</option>
                    <option value="health">Health</option>
                    <option value="agriculture">Agriculture</option>
                    <option value="gad">GAD</option>
                    <option value="anti-drugs">Anti-Drugs</option>
                    <option value="disaster">Disaster</option>
                    <option value="others">Others</option>
                </select>

                <select id="barangayFilter" aria-label="Filter by barangay">
                    <option value="">All Barangays</option>
                </select>

                <select id="statusFilter" aria-label="Filter by status">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                </select>

                <button id="resetFilters" type="button" class="programs-btn programs-btn-muted">Reset</button>
            </div>

            <div class="programs-results-meta">
                <p id="programCount">No programs available</p>
                <p class="programs-meta-help">Programs will appear here once they are added.</p>
            </div>

            <div class="programs-grid-v2" id="programs-grid">
                @if(count($programs) === 0)
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #666;">
                        <h3>No Programs Available</h3>
                        <p>Check back later for upcoming youth programs and activities.</p>
                    </div>
                @else
                    @foreach($programs as $program)
                        @php
                            $sector = $sectorMeta[$program['sector']] ?? ['label' => ucfirst($program['sector']), 'icon' => 'apps'];
                            $status = $statusMeta[$program['status']] ?? ['label' => ucfirst($program['status']), 'note' => ''];
                        @endphp
                        <article
                            class="program-card-v2"
                            data-name="{{ strtolower($program['title']) }}"
                            data-summary="{{ strtolower($program['summary']) }}"
                            data-barangay="{{ strtolower($program['barangay']) }}"
                            data-sector="{{ $program['sector'] }}"
                            data-status="{{ $program['status'] }}"
                        >
                            <div class="program-card-media">
                                <span class="program-badge program-badge-sector {{ $program['sector'] }}">{{ $sector['label'] }}</span>
                                <span class="program-badge program-badge-status {{ $program['status'] }}">{{ $status['label'] }}</span>
                            </div>

                            <div class="program-card-content">
                                <p class="program-barangay-chip">{{ $program['barangay'] }}</p>
                                <h3>{{ $program['title'] }}</h3>
                                <p>{{ $program['summary'] }}</p>

                                <div class="program-progress-wrap">
                                    <div class="program-progress-top">
                                        <span>Budget Utilization</span>
                                        <strong>{{ $program['budgetUtilization'] }}%</strong>
                                    </div>
                                    <div class="program-progress-track">
                                        <div class="program-progress-fill" data-budget="{{ $program['budgetUtilization'] }}"></div>
                                    </div>
                                </div>

                                <div class="program-card-meta">
                                    <span>{{ number_format($program['participants']) }} participants</span>
                                    <span>Budget: PHP {{ number_format($program['budgetTotal']) }}</span>
                                </div>

                                <div class="program-card-actions">
                                    <button type="button" data-program-action="modal" data-program-id="{{ $program['id'] }}" class="programs-btn programs-btn-primary">View Details</button>
                                    <a href="{{ route('register') }}" class="programs-btn programs-btn-ghost">Join</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>

            <div id="noProgramsMessage" class="programs-empty" hidden>
                <h3>No matching programs found</h3>
                <p>Try resetting filters or searching with broader keywords.</p>
            </div>
        </div>
    </section>

    <section id="programDetailsModal" class="program-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="program-modal-backdrop" data-close-modal="true"></div>
        <div class="program-modal-panel" role="document">
            <div class="program-modal-topbar">
                <h3 id="modalTopbarTitle">Program Details</h3>
                <button id="closeProgramModal" type="button" class="program-modal-close" aria-label="Close program details">X</button>
            </div>

            <div class="program-modal-grid">
                <article class="program-modal-main">
                    <div class="program-modal-media">
                        <div class="program-modal-media-overlay"></div>
                        <div class="program-modal-header">
                            <div class="program-modal-badges">
                                <span id="modalStatus" class="program-badge program-badge-status"></span>
                                <span id="modalSector" class="program-badge program-badge-sector"></span>
                            </div>
                            <h3 id="modalTitle"></h3>
                            <p id="modalSummary"></p>
                        </div>
                    </div>

                    <div class="program-modal-body">
                        <h4>About the Program</h4>
                        <p id="modalAbout"></p>

                        <h4>Schedule and Timeline</h4>
                        <ul id="modalTimeline" class="program-timeline"></ul>
                    </div>
                </article>

                <aside class="program-modal-side">
                    <article class="spotlight-cta-card">
                        <h4>Join This Program</h4>
                        <p>Secure your slot and receive updates for schedules, reminders, and announcements.</p>
                        <a href="{{ route('register') }}" class="programs-btn programs-btn-primary">Sign Up Now</a>
                    </article>

                    <article class="spotlight-metric-card">
                        <h4>Impact Metrics</h4>
                        <div class="spotlight-metric-row">
                            <span>Total Participants</span>
                            <strong id="modalParticipants"></strong>
                        </div>
                        <div class="spotlight-metric-row">
                            <span>Program Duration</span>
                            <strong id="modalDuration"></strong>
                        </div>
                        <div class="spotlight-metric-row">
                            <span>Capacity</span>
                            <strong id="modalCapacity"></strong>
                        </div>
                    </article>

                    <article class="spotlight-budget-card">
                        <h4>Budget Breakdown</h4>
                        <p class="spotlight-budget-total" id="modalBudgetTotal"></p>
                        <div class="program-progress-wrap compact">
                            <div class="program-progress-top">
                                <span>Budget Utilization</span>
                                <strong id="modalBudgetUtilization"></strong>
                            </div>
                            <div class="program-progress-track">
                                <div id="modalBudgetBar" class="program-progress-fill"></div>
                            </div>
                        </div>
                        <ul id="modalBudgetItems" class="spotlight-budget-list"></ul>
                    </article>
                </aside>
            </div>
        </div>
    </section>
</div>

<script id="programDataPayload" type="application/json">@json($programs)</script>
<script id="programStatusPayload" type="application/json">@json($statusMeta)</script>
<script id="programSectorPayload" type="application/json">@json($sectorMeta)</script>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const programs = JSON.parse(document.getElementById('programDataPayload')?.textContent || '[]');
        const statusMeta = JSON.parse(document.getElementById('programStatusPayload')?.textContent || '{}');
        const sectorMeta = JSON.parse(document.getElementById('programSectorPayload')?.textContent || '{}');

        const searchInput = document.getElementById('programSearch');
        const sectorFilter = document.getElementById('sectorFilter');
        const barangayFilter = document.getElementById('barangayFilter');
        const statusFilter = document.getElementById('statusFilter');
        const resetButton = document.getElementById('resetFilters');
        const countNode = document.getElementById('programCount');
        const emptyNode = document.getElementById('noProgramsMessage');
        const modal = document.getElementById('programDetailsModal');
        const modalCloseButton = document.getElementById('closeProgramModal');
        const openFeaturedModalButton = document.getElementById('openFeaturedModal');
        const cards = Array.from(document.querySelectorAll('.program-card-v2'));

        document.querySelectorAll('.program-progress-fill[data-budget]').forEach((node) => {
            const budget = Number(node.getAttribute('data-budget') || 0);
            node.style.width = `${budget}%`;
        });

        function formatNumber(value) {
            return new Intl.NumberFormat().format(value || 0);
        }

        function applyFilters() {
            const keyword = (searchInput?.value || '').trim().toLowerCase();
            const sector = sectorFilter?.value || '';
            const barangay = (barangayFilter?.value || '').toLowerCase();
            const status = statusFilter?.value || '';

            let visible = 0;
            cards.forEach((card) => {
                const haystack = `${card.dataset.name} ${card.dataset.summary} ${card.dataset.barangay} ${card.dataset.sector}`;
                const matchesKeyword = !keyword || haystack.includes(keyword);
                const matchesSector = !sector || card.dataset.sector === sector;
                const matchesBarangay = !barangay || card.dataset.barangay === barangay;
                const matchesStatus = !status || card.dataset.status === status;
                const show = matchesKeyword && matchesSector && matchesBarangay && matchesStatus;
                card.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (countNode) {
                countNode.textContent = `Showing ${visible} program${visible === 1 ? '' : 's'}`;
            }
            if (emptyNode) {
                emptyNode.hidden = visible > 0;
            }
        }

        function renderTimeline(items) {
            const timeline = document.getElementById('modalTimeline');
            if (!timeline) {
                return;
            }

            timeline.innerHTML = '';
            (items || []).forEach((item) => {
                const li = document.createElement('li');
                li.className = `program-timeline-item ${item.status}`;
                li.innerHTML = `<div><strong>${item.title}</strong><p>${item.date}</p></div><span>${(statusMeta[item.status]?.label || item.status || '').toUpperCase()}</span>`;
                timeline.appendChild(li);
            });
        }

        function renderBudgetItems(items) {
            const budgetList = document.getElementById('modalBudgetItems');
            if (!budgetList) {
                return;
            }

            budgetList.innerHTML = '';
            (items || []).forEach((item) => {
                const li = document.createElement('li');
                li.innerHTML = `<span>${item.label}</span><strong>PHP ${formatNumber(item.amount)}</strong>`;
                budgetList.appendChild(li);
            });
        }

        function openProgramModal() {
            if (!modal) {
                return;
            }

            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('program-modal-open');

            const modalGrid = modal.querySelector('.program-modal-grid');
            if (modalGrid) {
                modalGrid.scrollTop = 0;
            }
        }

        function closeProgramModal() {
            if (!modal) {
                return;
            }
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('program-modal-open');
        }

        function renderModal(programId) {
            const program = programs.find((entry) => Number(entry.id) === Number(programId));
            if (!program) {
                return;
            }

            const modalTitle = document.getElementById('modalTitle');
            const modalSummary = document.getElementById('modalSummary');
            const modalAbout = document.getElementById('modalAbout');
            const modalTopbarTitle = document.getElementById('modalTopbarTitle');
            const modalStatus = document.getElementById('modalStatus');
            const modalSector = document.getElementById('modalSector');
            const modalParticipants = document.getElementById('modalParticipants');
            const modalDuration = document.getElementById('modalDuration');
            const modalCapacity = document.getElementById('modalCapacity');
            const modalBudgetTotal = document.getElementById('modalBudgetTotal');
            const modalBudgetUtilization = document.getElementById('modalBudgetUtilization');
            const modalBudgetBar = document.getElementById('modalBudgetBar');

            if (modalTitle) modalTitle.textContent = program.title;
            if (modalTopbarTitle) modalTopbarTitle.textContent = `${program.title} Details`;
            if (modalSummary) modalSummary.textContent = program.summary;
            if (modalAbout) modalAbout.textContent = program.about;

            if (modalStatus) {
                modalStatus.textContent = statusMeta[program.status]?.label || program.status;
                modalStatus.className = `program-badge program-badge-status ${program.status}`;
            }

            if (modalSector) {
                modalSector.textContent = sectorMeta[program.sector]?.label || program.sector;
                modalSector.className = `program-badge program-badge-sector ${program.sector}`;
            }

            if (modalParticipants) modalParticipants.textContent = formatNumber(program.participants);
            if (modalDuration) modalDuration.textContent = program.duration;
            if (modalCapacity) modalCapacity.textContent = formatNumber(program.capacity);
            if (modalBudgetTotal) modalBudgetTotal.textContent = `PHP ${formatNumber(program.budgetTotal)}`;
            if (modalBudgetUtilization) modalBudgetUtilization.textContent = `${program.budgetUtilization}%`;
            if (modalBudgetBar) modalBudgetBar.style.width = `${program.budgetUtilization}%`;

            renderTimeline(program.timeline);
            renderBudgetItems(program.budgetItems);
            openProgramModal();
        }

        document.querySelectorAll('[data-program-action="modal"]').forEach((button) => {
            button.addEventListener('click', () => {
                renderModal(button.dataset.programId);
            });
        });

        openFeaturedModalButton?.addEventListener('click', () => {
            renderModal(programs[0]?.id);
        });

        modalCloseButton?.addEventListener('click', closeProgramModal);

        modal?.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.dataset.closeModal === 'true') {
                closeProgramModal();
                return;
            }

            if (target === modal) {
                closeProgramModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeProgramModal();
            }
        });

        [searchInput, sectorFilter, barangayFilter, statusFilter].forEach((el) => {
            el?.addEventListener('input', applyFilters);
            el?.addEventListener('change', applyFilters);
        });

        resetButton?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (sectorFilter) sectorFilter.value = '';
            if (barangayFilter) barangayFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            applyFilters();
        });

        applyFilters();
    });
</script>
@endpush
@endsection
