@php
    $ackFormId = $acknowledgmentFormId ?? null;
@endphp
<div class="kkp-acknowledgment {{ $acknowledgmentClass ?? '' }}" id="kkpProfilingAcknowledgmentWrap">
    <button
        type="button"
        class="kkp-terms-link"
        id="kkpTermsOpenBtn"
        aria-haspopup="dialog"
        aria-controls="kkpTermsModal"
    >Terms and Conditions &amp; Privacy Policy</button>
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
        <label for="kkpProfilingAcknowledgment" class="kkp-form-check-label">I have read and agree to the Terms and Conditions and Privacy Policy.</label>
    </div>
    <div
        class="kkp-field-error"
        id="kkpProfilingAcknowledgmentError"
        @if (! $errors->has('profiling_acknowledgment')) hidden @endif
    >{{ $errors->first('profiling_acknowledgment') }}</div>
</div>
