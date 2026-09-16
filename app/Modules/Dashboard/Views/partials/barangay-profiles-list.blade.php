@forelse($barangayProfiles as $brgy)
<a href="{{ route('barangay', ['slug' => $brgy['slug']]) }}" class="brgy-profile-item" data-brgy-slug="{{ $brgy['slug'] }}">
    <div class="brgy-avatar{{ !empty($brgy['logo_url']) ? ' has-logo' : '' }}" aria-hidden="true">
        @if(!empty($brgy['logo_url']))
            <img class="brgy-avatar-logo" src="{{ $brgy['logo_url'] }}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            <span class="brgy-avatar-letter" hidden>{{ $brgy['initials'] ?: 'SK' }}</span>
        @else
            <span class="brgy-avatar-letter">{{ $brgy['initials'] ?: 'SK' }}</span>
        @endif
    </div>
    <div class="brgy-info">
        <p class="brgy-name">Brgy. {{ $brgy['name'] }}</p>
        <p class="brgy-chair">SK Officials</p>
    </div>
    <svg class="brgy-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
    </svg>
</a>
@empty
<p class="brgy-profiles-empty">No barangays found for your municipality.</p>
@endforelse
