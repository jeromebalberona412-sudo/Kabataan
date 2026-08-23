@forelse($barangayProfiles as $brgy)
<a href="{{ route('barangay', ['slug' => $brgy['slug']]) }}" class="brgy-profile-item" data-brgy-slug="{{ $brgy['slug'] }}">
    <div class="brgy-avatar">
        @if(!empty($brgy['logo_url']))
            <img src="{{ $brgy['logo_url'] }}" alt="Brgy. {{ $brgy['name'] }} logo" class="brgy-avatar-logo">
        @else
            {{ $brgy['initials'] }}
        @endif
    </div>
    <div class="brgy-info">
        <p class="brgy-name">Brgy. {{ $brgy['name'] }}</p>
        <p class="brgy-chair">{{ $brgy['chairman'] }}</p>
    </div>
    <svg style="width:16px;height:16px;color:#bbb;flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
    </svg>
</a>
@empty
<p class="brgy-profiles-empty">No barangays found for your municipality.</p>
@endforelse
