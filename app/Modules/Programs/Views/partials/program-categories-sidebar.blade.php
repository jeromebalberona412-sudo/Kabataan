@php
    $iconClassMap = [
        'education' => 'education',
        'environment' => 'others',
        'disaster' => 'disaster',
        'agriculture' => 'agriculture',
        'health' => 'health',
        'anti-drugs' => 'anti-drugs',
        'gender' => 'gender',
        'feeding' => 'others',
        'sports' => 'sports',
        'others' => 'others',
    ];
    $allowedLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $seenLetters = [];
    $programs = collect($kabataanPrograms['abyip_programs'] ?? [])
        ->filter(function ($program) use (&$seenLetters, $allowedLetters) {
            $letter = strtoupper(trim((string) ($program['letter'] ?? '')));
            if (! in_array($letter, $allowedLetters, true) || isset($seenLetters[$letter])) {
                return false;
            }
            $seenLetters[$letter] = true;

            return true;
        })
        ->values();
@endphp

@if($programs->isEmpty())
    <p style="text-align:center;color:#64748b;padding:16px;font-size:14px;">No ABYIP programs uploaded for your barangay yet.</p>
@else
    @foreach($programs as $program)
        @php
            $categoryKey = (string) ($program['category_key'] ?? 'others');
            $iconClass = $iconClassMap[$categoryKey] ?? 'others';
            $letter = strtoupper(trim((string) ($program['letter'] ?? '')));
            $rawTitle = trim((string) ($program['title'] ?? ''));
            $strippedTitle = $letter
                ? preg_replace('/^'.preg_quote($letter, '/').'\s*[.)\\-:]\s*/i', '', $rawTitle) ?: $rawTitle
                : $rawTitle;
            $title = $letter ? trim($letter.'. '.$strippedTitle) : $strippedTitle;
            $subtitle = '';
            if (! empty($program['evaluation']['can_respond'])) {
                $subtitle = 'Evaluation open';
            } elseif (! empty($program['evaluation']['has_responded'])) {
                $subtitle = 'Evaluation submitted';
            } elseif (in_array($program['type'] ?? '', ['education', 'sports'], true)) {
                $count = (int) ($program['schedule_count'] ?? 0);
                if ($count === 1) {
                    $subtitle = '1 active schedule';
                } elseif ($count > 1) {
                    $subtitle = $count.' active schedules';
                }
            } elseif (! empty($program['survey']['can_respond'])) {
                $subtitle = 'Survey open';
            } elseif (! empty($program['survey']['has_responded'])) {
                $subtitle = 'Survey submitted';
            } elseif (! empty($program['has_survey']) || ! empty($program['survey'])) {
                $subtitle = 'Survey available';
            }
        @endphp
        <div class="program-category" data-category="{{ e($categoryKey) }}" data-letter="{{ e($letter) }}" style="cursor:pointer;">
            <div class="category-icon {{ $iconClass }}">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="category-content">
                <h3>{{ $title }}</h3>
                @if($subtitle !== '')
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @if(! empty($program['evaluation']['can_respond']))
                <button type="button" class="program-eval-cta" data-eval-program="{{ (int) ($program['id'] ?? 0) }}" data-eval-id="{{ (int) ($program['evaluation']['id'] ?? 0) }}">Evaluate</button>
            @endif
            <svg class="chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
        </div>
    @endforeach
@endif
