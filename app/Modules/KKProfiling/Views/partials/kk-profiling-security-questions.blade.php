@php
    $securityQuestions = \App\Support\KabataanSecurityQuestionCatalog::all();
@endphp

<div
    class="kkp-info-overlay kkp-secq-overlay"
    id="kkpSecurityQuestionsModal"
    hidden
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="kkpSecurityQuestionsTitle"
>
    <div class="kkp-info-overlay-backdrop" id="kkpSecurityQuestionsBackdrop"></div>
    <div class="kkp-info-modal kkp-secq-modal">
        <button type="button" class="kkp-info-modal-close" id="kkpSecurityQuestionsCloseBtn" aria-label="Close">&times;</button>
        <div class="kkp-info-modal-scroll">
            <h2 class="kkp-info-title" id="kkpSecurityQuestionsTitle">Security questions</h2>
            <p class="kkp-secq-lead">Pumili at sagutan ang eksaktong 3 tanong. Itago ang mga sagot. Kailangan mo ang mga ito kung idadagdag mo ang email at i-claim ang KK Profiling mo.</p>
            <p class="kkp-secq-count-label" id="kkpSecurityQuestionsPicked" aria-live="polite">Napili: 0 ng 3</p>
            <p class="kkp-secq-form-error" id="kkpSecurityQuestionsError" role="alert" hidden></p>

            <div class="kkp-secq-list">
                @foreach ($securityQuestions as $question)
                    <article class="kkp-secq" data-question="{{ $question['number'] }}" data-kind="{{ $question['kind'] }}">
                        <label class="kkp-secq-pick">
                            <input type="checkbox" class="kkp-secq-check">
                            <span>{{ $question['number'] }}. {{ $question['text'] }}</span>
                        </label>
                        <div class="kkp-secq-body" hidden>
                            @if ($question['kind'] !== 'own')
                                <div class="kkp-secq-choices" role="radiogroup" aria-label="{{ $question['text'] }}">
                                    @foreach ($question['choices'] as $choice)
                                        <label class="kkp-secq-choice">
                                            <input type="radio" name="kkp_secq_{{ $question['number'] }}" value="{{ $choice }}">
                                            <span>{{ $choice }}</span>
                                        </label>
                                    @endforeach
                                    <label class="kkp-secq-choice">
                                        <input type="radio" name="kkp_secq_{{ $question['number'] }}" value="Iba pa">
                                        <span>Iba pa</span>
                                    </label>
                                </div>
                            @endif
                            <div class="kkp-secq-custom" @if ($question['kind'] !== 'own') hidden @endif>
                                <label class="kkp-secq-custom-label" for="kkpSecqCustom{{ $question['number'] }}">Ilagay ang iyong sagot</label>
                                <input
                                    id="kkpSecqCustom{{ $question['number'] }}"
                                    class="kkp-secq-custom-input"
                                    type="text"
                                    maxlength="{{ $question['kind'] === 'number' ? 2 : 15 }}"
                                    autocomplete="off"
                                    @if ($question['kind'] === 'number') inputmode="numeric" @endif
                                >
                                @if ($question['kind'] !== 'number')
                                    <span class="kkp-secq-remaining" data-remaining>15</span>
                                @endif
                                <span class="kkp-secq-field-error" role="alert" hidden></span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
        <div class="kkp-info-modal-actions">
            <button type="button" class="kkp-info-btn kkp-info-btn-secondary" id="kkpSecurityQuestionsBackBtn">Back</button>
            <button type="button" class="kkp-info-btn kkp-info-btn-primary" id="kkpSecurityQuestionsOkBtn" disabled>OK, continue</button>
        </div>
    </div>
</div>
