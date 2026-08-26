{{-- Optional inactivity warning (server SessionTimeout remains authoritative) --}}
@php
    $kabataanTimeoutMinutes = max(1, (int) config('session.timeout', 120));
    $kabataanWarningMinutes = max(0, (int) config('session.timeout_warning_minutes', 5));
    $kabataanLastActivityAt = (int) session(\App\Http\Middleware\SessionTimeout::SESSION_KEY, now()->timestamp);
@endphp

<script>
    window.kabataanSignInRoute = @json(route('sign-in'));
    window.kabataanSessionContinueRoute = @json(route('kabataan.session.continue'));
    window.kabataanSessionTimeoutConfig = {
        timeoutMinutes: {{ $kabataanTimeoutMinutes }},
        warningMinutes: {{ $kabataanWarningMinutes }},
        lastActivityAt: {{ $kabataanLastActivityAt }}
    };
</script>

@if($kabataanWarningMinutes > 0 && $kabataanWarningMinutes < $kabataanTimeoutMinutes)
<div id="kabataanSessionTimeoutModal" class="kab-logout-modal" hidden role="dialog" aria-modal="true" aria-labelledby="kabataanSessionTimeoutTitle">
    <div class="kab-logout-modal__overlay" data-session-timeout-dismiss></div>
    <div class="kab-logout-modal__panel">
        <div class="kab-logout-modal__head">
            <h2 id="kabataanSessionTimeoutTitle">Session Expiring Soon</h2>
        </div>
        <div class="kab-logout-modal__body">
            <h3>Still there?</h3>
            <p id="kabataanSessionTimeoutMessage">Your session will expire soon due to inactivity.</p>
            <div class="kab-logout-modal__actions">
                <button type="button" class="kab-logout-modal__btn kab-logout-modal__btn--cancel" id="kabataanSessionTimeoutLogoutBtn">Logout</button>
                <button type="button" class="kab-logout-modal__btn kab-logout-modal__btn--confirm" id="kabataanSessionTimeoutContinueBtn">Continue Session</button>
            </div>
        </div>
    </div>
</div>
@endif
