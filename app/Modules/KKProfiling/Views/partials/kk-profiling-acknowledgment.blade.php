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
            @if ($ackFormId) form="{{ $ackFormId }}" @endif
        >
        <span class="kkp-ack-text">
            <label for="kkpProfilingAcknowledgment" class="kkp-form-check-label">I hereby acknowledge and agree to the KK Profiling</label>
            <button
                type="button"
                class="kkp-terms-link"
                id="kkpTermsOpenBtn"
                aria-haspopup="dialog"
                aria-controls="kkpTermsModal"
            >Terms and Conditions</button>
        </span>
    </div>
    <div
        class="kkp-field-error"
        id="kkpProfilingAcknowledgmentError"
        @if (! $errors->has('profiling_acknowledgment')) hidden @endif
    >{{ $errors->first('profiling_acknowledgment') }}</div>
</div>
