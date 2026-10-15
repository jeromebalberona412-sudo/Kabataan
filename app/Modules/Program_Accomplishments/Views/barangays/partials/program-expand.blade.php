<div class="ba-card-expand" hidden>
    <div class="ba-expand-info">
        <h4>Program Information</h4>
        <dl class="ba-expand-info-grid">
            <div>
                <dt>Program Name</dt>
                <dd>{{ $card['title'] ?: '—' }}</dd>
            </div>
            <div>
                <dt>Date Started</dt>
                <dd>{{ $card['start_label'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Date Completed</dt>
                <dd>{{ $card['end_label'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>{{ $card['status'] }}</dd>
            </div>
            <div class="ba-expand-info-wide">
                <dt>MS Word Report</dt>
                <dd>
                    @if (!empty($card['documents']))
                        @foreach ($card['documents'] as $document)
                            <a href="{{ $document['url'] }}" class="ba-word-file" download="{{ $document['name'] }}" rel="noopener noreferrer">
                                <span class="ba-word-file-type">{{ $document['type'] ?? 'DOC' }}</span>
                                <span class="ba-word-file-name">{{ $document['name'] }}</span>
                                @if (($document['size'] ?? '') !== '')
                                    <span class="ba-word-file-size">{{ $document['size'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    @else
                        No Word file uploaded
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    @if ($card['description'] || $card['objectives'] || $card['summary'] || $card['actual_result'])
        <div class="ba-expand-copy">
            @if ($card['description'])
                <h4>Program Description</h4>
                <p>{{ $card['description'] }}</p>
            @endif
            @if ($card['objectives'])
                <h4>Program Objective</h4>
                <p>{{ $card['objectives'] }}</p>
            @endif
            @if ($card['summary'])
                <h4>Implementation Summary</h4>
                <p>{{ $card['summary'] }}</p>
            @endif
            @if ($card['actual_result'])
                <h4>Actual Result</h4>
                <p>{{ $card['actual_result'] }}</p>
            @endif
        </div>
    @endif

    @if (!empty($card['proposal']))
        @php
            $proposal = $card['proposal'];
            $proposalItems = $proposal['line_items'] ?? [];
            $proposalTotal = collect($proposalItems)->sum(fn ($item) => (float) ($item['amount'] ?? 0));
            $proposalAllocated = (float) ($proposal['allocated'] ?? 0);
            $proposalLeft = max($proposalAllocated - $proposalTotal, 0);
        @endphp
        <div class="ba-proposal">
            <h4>Program Proposal</h4>
            <dl class="ba-expand-info-grid">
                <div class="ba-expand-info-wide"><dt>I. Title of the training</dt><dd>{{ $proposal['program_name'] ?: '—' }}</dd></div>
                <div><dt>II. Target date/time</dt><dd>{{ $proposal['implementation_date'] ?: '—' }}</dd></div>
                <div class="ba-expand-info-wide"><dt>Schedule</dt><dd>@if (!empty($proposal['schedule_start']) && !empty($proposal['schedule_end']))Must stay within the scheduled program: {{ $proposal['schedule_start'] }} to {{ $proposal['schedule_end'] }}. An earlier date inside that range is allowed.@else—@endif</dd></div>
                <div><dt>III. Venue</dt><dd>{{ $proposal['venue'] ?: '—' }}</dd></div>
                <div><dt>IV. Proponent</dt><dd>{{ $proposal['proponent'] ?: '—' }}</dd></div>
                <div><dt>V. Participants</dt><dd>{{ $proposal['participants_label'] ?: '—' }}</dd></div>
                <div><dt>VI. Source of fund</dt><dd>{{ $proposal['source_of_fund'] ?: '—' }}</dd></div>
                <div class="ba-expand-info-wide"><dt>VII. Resource persons</dt><dd>{{ $proposal['resource_persons'] ?: '—' }}</dd></div>
                <div class="ba-expand-info-wide"><dt>VIII. Rationale</dt><dd>{{ $proposal['rationale'] ?: '—' }}</dd></div>
                <div class="ba-expand-info-wide"><dt>IX. Objectives</dt><dd>{{ $proposal['objectives'] ?: '—' }}</dd></div>
            </dl>
            <h4>I. Budgetary requirements</h4>
            <div class="ba-proposal-scroll">
                <table class="ba-proposal-table">
                    <thead>
                        <tr><th>Unit</th><th>Item description</th><th>Cost</th><th>Estimated Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($proposalItems as $item)
                            <tr>
                                <td>{{ $item['unit'] ?: '—' }}</td>
                                <td>{{ $item['description'] ?: '—' }}</td>
                                <td>{{ $item['unit_cost'] }}</td>
                                <td>{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No budgetary items yet.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3">Total</td><td>₱{{ number_format($proposalTotal, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
            @if ($proposalAllocated > 0)
                <p class="ba-proposal-note">Total cannot exceed the approved allocation of ₱{{ number_format($proposalAllocated, 2) }}. Remaining if saved: ₱{{ number_format($proposalLeft, 2) }}.</p>
            @endif
        </div>
    @endif

    <p class="ba-expand-facts">
        {{ $card['implementation_label'] ?: $card['date_label'] }}
        · {{ $card['location'] }}
    </p>

    @if (count($card['photos']) > 0)
        @php
            $photos = collect($card['photos']);
            $photoCount = $photos->count();
            $visiblePhotos = $photos->take(5);
            $extraCount = max(0, $photoCount - 5);
            $gridCount = min(5, $photoCount);
        @endphp
        <div class="ba-gallery" data-gallery data-photos='@json($photos->values())'>
            <h4>Photo Gallery</h4>
            <div class="ba-fb-grid" data-count="{{ $gridCount }}">
                @foreach ($visiblePhotos as $index => $photo)
                    <button
                        type="button"
                        class="ba-fb-item"
                        data-gallery-index="{{ $index }}"
                        aria-label="Preview photo {{ $index + 1 }}"
                    >
                        <img src="{{ $photo['src'] }}" alt="{{ $photo['caption'] ?? '' }}" loading="lazy">
                        @if ($index === 4 && $extraCount > 0)
                            <span class="ba-fb-more">+{{ $extraCount }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
