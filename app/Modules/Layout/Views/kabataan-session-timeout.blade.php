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
<div id="kabataanSessionTimeoutModal" class="session-timeout-modal" hidden role="dialog" aria-modal="true" aria-labelledby="kabataanSessionTimeoutTitle">
    <div class="session-timeout-modal__backdrop" data-session-timeout-dismiss></div>
    <div class="session-timeout-modal__panel" role="document">
        <div class="session-timeout-modal__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <h2 id="kabataanSessionTimeoutTitle" class="session-timeout-modal__title">Session Expiring Soon</h2>
        <p id="kabataanSessionTimeoutMessage" class="session-timeout-modal__message">Your session will expire soon due to inactivity.</p>
        <div class="session-timeout-modal__actions">
            <button type="button" class="session-timeout-modal__btn session-timeout-modal__btn--logout" id="kabataanSessionTimeoutLogoutBtn">Logout</button>
            <button type="button" class="session-timeout-modal__btn session-timeout-modal__btn--continue" id="kabataanSessionTimeoutContinueBtn">Continue Session</button>
        </div>
    </div>
</div>

<style>
    #kabataanSessionTimeoutModal.session-timeout-modal {
        position: fixed;
        inset: 0;
        z-index: 50000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    #kabataanSessionTimeoutModal.session-timeout-modal[hidden] {
        display: none !important;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__panel {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
        background: #fff;
        border-radius: 16px;
        padding: 28px 24px 24px;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.28);
        text-align: center;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: #eff6ff;
        color: #1d4ed8;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__icon svg {
        width: 24px;
        height: 24px;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__title {
        margin: 0 0 10px;
        font-size: 22px;
        font-weight: 700;
        color: #0f172a !important;
        background: transparent !important;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__message {
        margin: 0 0 24px;
        font-size: 15px;
        line-height: 1.55;
        color: #64748b !important;
        background: transparent !important;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__btn {
        appearance: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 132px;
        padding: 12px 20px;
        border-radius: 10px;
        border: 2px solid transparent;
        font-family: inherit;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none !important;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__btn--logout {
        background: #fff !important;
        color: #475569 !important;
        border-color: #e2e8f0 !important;
    }
    #kabataanSessionTimeoutModal .session-timeout-modal__btn--continue {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
        color: #fff !important;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.32) !important;
        min-width: 168px;
    }
    @media (max-width: 480px) {
        #kabataanSessionTimeoutModal .session-timeout-modal__actions {
            flex-direction: column-reverse;
        }
        #kabataanSessionTimeoutModal .session-timeout-modal__btn {
            width: 100%;
            min-width: 0;
        }
    }
</style>
@endif
