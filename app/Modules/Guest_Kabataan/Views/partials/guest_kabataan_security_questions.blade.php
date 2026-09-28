@php
    $securityQuestions = \App\Support\KabataanSecurityQuestionCatalog::all();
@endphp
<fieldset class="guest-kabataan-secq" id="guestKabataanSecurity">
    <legend>Security questions</legend>
    <p>Pumili at sagutan ang eksaktong 3 tanong na sinagot mo sa KK Profiling.</p>
    <p id="guestKabataanSecqPicked">Napili: 0 ng 3</p>
    @foreach ($securityQuestions as $question)
        <article class="guest-kabataan-secq__item" data-question="{{ $question['number'] }}" data-kind="{{ $question['kind'] }}">
            <label class="guest-kabataan-secq__pick">
                <input type="checkbox" class="guest-kabataan-secq__check">
                <span>{{ $question['number'] }}. {{ $question['text'] }}</span>
            </label>
            <div class="guest-kabataan-secq__body" hidden>
                @if ($question['kind'] !== 'own')
                    @foreach ($question['choices'] as $choice)
                        <label><input type="radio" name="guest_secq_{{ $question['number'] }}" value="{{ $choice }}"> {{ $choice }}</label>
                    @endforeach
                    <label><input type="radio" name="guest_secq_{{ $question['number'] }}" value="Iba pa"> Iba pa</label>
                @endif
                <label class="guest-kabataan-secq__custom" @if ($question['kind'] !== 'own') hidden @endif>
                    Ilagay ang iyong sagot
                    <input class="guest-kabataan-secq__input" maxlength="{{ $question['kind'] === 'number' ? 2 : 15 }}" autocomplete="off" @if ($question['kind'] === 'number') inputmode="numeric" @endif>
                </label>
                <span class="guest-kabataan-error guest-kabataan-secq__error" role="alert" hidden></span>
            </div>
        </article>
    @endforeach
</fieldset>
