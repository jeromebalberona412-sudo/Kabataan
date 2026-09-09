{{-- Mandatory annual KK Profiling update modal (no dismiss / no X). --}}
@if (!empty($kkProfilingUpdateRequired) && !request()->routeIs('kkprofiling.update.show', 'kkprofiling.update'))
<div
    id="kkProfilingUpdateModal"
    class="kkpu-mandatory-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="kkProfilingUpdateModalTitle"
    data-kk-profiling-update-modal="1"
>
    <div class="kkpu-mandatory-modal__backdrop" aria-hidden="true"></div>
    <div class="kkpu-mandatory-modal__panel">
        <h2 id="kkProfilingUpdateModalTitle" class="kkpu-mandatory-modal__title">KK Profiling Update</h2>
        <p class="kkpu-mandatory-modal__year">Profiling Year: {{ (int) ($kkProfilingUpdateYear ?? now()->year) }}</p>
        <p class="kkpu-mandatory-modal__body">
            Your KK Profiling information needs to be updated for {{ (int) ($kkProfilingUpdateYear ?? now()->year) }}.
            Please complete the required information before continuing to the Kabataan Portal.
        </p>
        <p class="kkpu-mandatory-modal__body">
            This is your annual KK Profiling Update for an existing account — not a new registration.
        </p>
        <a
            href="{{ route('kkprofiling.update.show') }}"
            class="kkpu-mandatory-modal__cta"
            id="kkProfilingUpdateStartBtn"
        >
            Update your KK Profiling information
        </a>
        <p class="kkpu-mandatory-modal__hint">You can logout from the header if needed. This prompt cannot be skipped.</p>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('kkProfilingUpdateModal');
    if (!modal) return;
    document.documentElement.classList.add('kkpu-mandatory-open');
    document.body.classList.add('kkpu-mandatory-open');
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
})();
</script>
@endif
