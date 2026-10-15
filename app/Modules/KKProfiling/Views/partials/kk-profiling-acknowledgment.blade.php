@php
    $ackFormId = $acknowledgmentFormId ?? null;
@endphp
<div class="kkp-acknowledgment {{ $acknowledgmentClass ?? '' }}" id="kkpProfilingAcknowledgmentWrap">
    <div class="kkp-form-check kkp-ack-row">
        <input
            type="checkbox"
            id="kkpProfilingAcknowledgment"
            name="profiling_acknowledgment"
            value="1"
            class="kkp-form-check-input"
            autocomplete="off"
            aria-required="true"
            aria-describedby="kkpProfilingAcknowledgmentError"
            disabled
            @if ($ackFormId) form="{{ $ackFormId }}" @endif
        >
        <span class="kkp-form-check-label kkp-ack-sentence">I have read and agree to the <button type="button" class="kkp-terms-link" id="kkpTermsOpenBtn" aria-haspopup="dialog" aria-controls="kkpTermsModal">Terms and Conditions and Privacy Policy</button>.</span>
    </div>
    <div
        class="kkp-field-error"
        id="kkpProfilingAcknowledgmentError"
        @if (! $errors->has('profiling_acknowledgment')) hidden @endif
    >{{ $errors->first('profiling_acknowledgment') }}</div>
</div>
