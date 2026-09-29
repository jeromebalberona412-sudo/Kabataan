@php
    $securityQuestions = \App\Support\KabataanSecurityQuestionCatalog::all();
@endphp
<fieldset class="guest-kabataan-secq" id="guestKabataanSecurity">
    <legend class="guest-kabataan-sr-only">Security questions</legend>
    <p class="guest-kabataan-secq__lead">Pumili at sagutan ang eksaktong 3 tanong na sinagot mo sa KK Profiling.</p>
    <p class="guest-kabataan-secq__count" id="guestKabataanSecqPicked" aria-live="polite">Napili: 0 ng 3</p>
    <div class="guest-kabataan-secq__list">
        @foreach ($securityQuestions as $question)
            <article class="guest-kabataan-secq__item" data-question="{{ $question['number'] }}" data-kind="{{ $question['kind'] }}">
                <label class="guest-kabataan-secq__pick">
                    <input type="checkbox" class="guest-kabataan-secq__check">
                    <span>{{ $question['number'] }}. {{ $question['text'] }}</span>
                </label>
                <div class="guest-kabataan-secq__body" hidden>
                    @if ($question['kind'] !== 'own')
                        <div class="guest-kabataan-secq__choices" role="radiogroup" aria-label="{{ $question['text'] }}">
                            @foreach ($question['choices'] as $choice)
                                <label class="guest-kabataan-secq__choice">
                                    <input type="radio" name="guest_secq_{{ $question['number'] }}" value="{{ $choice }}">
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                            <label class="guest-kabataan-secq__choice">
                                <input type="radio" name="guest_secq_{{ $question['number'] }}" value="Iba pa">
                                <span>Iba pa</span>
                            </label>
                        </div>
                    @endif
                    <label class="guest-kabataan-secq__custom" @if ($question['kind'] !== 'own') hidden @endif>
                        <span class="guest-kabataan-secq__custom-label">Ilagay ang iyong sagot</span>
                        <input class="guest-kabataan-secq__input" type="text" maxlength="{{ $question['kind'] === 'number' ? 2 : 15 }}" autocomplete="off" @if ($question['kind'] === 'number') inputmode="numeric" @endif>
                    </label>
                    <span class="guest-kabataan-secq__error" role="alert" hidden></span>
                </div>
            </article>
        @endforeach
    </div>
</fieldset>
