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
