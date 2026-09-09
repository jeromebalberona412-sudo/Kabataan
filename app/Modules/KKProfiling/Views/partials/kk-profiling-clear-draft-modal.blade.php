@php
    $clearDraftTitle = $clearDraftTitle ?? 'Clear all data?';
    $clearDraftConfirmLabel = $clearDraftConfirmLabel ?? 'Clear All Data';
    $clearDraftKeepEmail = !empty($clearDraftKeepEmail);
@endphp
<div
    id="kkpClearDraftModal"
    class="kkp-info-overlay"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="kkpClearDraftTitle"
>
    <div class="kkp-info-overlay-backdrop" id="kkpClearDraftBackdrop"></div>
    <div class="kkp-info-modal kkp-clear-draft-modal">
        <button type="button" class="kkp-info-modal-close" id="kkpClearDraftCloseBtn" aria-label="Close">
            &times;
        </button>
        <div class="kkp-info-modal-scroll">
            <section class="kkp-info-lang" lang="en">
                <h2 class="kkp-info-title" id="kkpClearDraftTitle">{{ $clearDraftTitle }}</h2>
                @if ($clearDraftKeepEmail)
                    <p>This will clear editable fields in your KK Profiling update form.</p>
                    <p>Region, Province, City/Municipality, Barangay, and your email address will be kept. This action cannot be undone.</p>
                @else
                    <p>This will remove all information currently saved in your unfinished KK Profiling form.</p>
                    <p>All entered fields across this registration draft will be cleared. This action cannot be undone.</p>
                @endif
            </section>
        </div>
        <div class="kkp-info-modal-footer">
            <div class="kkp-info-modal-actions">
                <button type="button" class="kkp-info-btn kkp-info-btn-secondary" id="kkpClearDraftCancelBtn">Cancel</button>
                <button type="button" class="kkp-info-btn kkp-info-btn-danger" id="kkpClearDraftConfirmBtn">{{ $clearDraftConfirmLabel }}</button>
            </div>
        </div>
    </div>
</div>
