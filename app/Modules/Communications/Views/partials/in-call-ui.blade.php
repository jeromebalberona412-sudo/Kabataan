<style>
/* Federations-style in-call controls — inline so dashboard pages cannot miss them. */
#commsInCall.comms-call-overlay {
    position: fixed;
    inset: 0;
    z-index: 10070;
    background: #0b0b0b;
    color: #fff;
}
#commsInCall .comms-call-stage {
    position: relative;
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: #0b0b0b;
}
#commsInCall .comms-remote-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center center;
    background: #0b0b0b;
}
#commsInCall.is-remote-portrait .comms-remote-video {
    object-fit: contain;
    object-position: center center;
}
#commsInCall .comms-local-video {
    position: absolute;
    right: max(0.75rem, env(safe-area-inset-right, 0px));
    bottom: max(6.75rem, calc(env(safe-area-inset-bottom, 0px) + 5.75rem));
    top: auto;
    width: min(34vw, 132px);
    max-height: 40vh;
    aspect-ratio: 3 / 4;
    object-fit: contain;
    object-position: center center;
    border-radius: 14px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    background: #111827;
    z-index: 3;
    transform: scaleX(-1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
    transition: width 0.18s ease, bottom 0.18s ease, right 0.18s ease, aspect-ratio 0.18s ease;
}
#commsInCall .comms-local-video.is-cam-landscape {
    aspect-ratio: 16 / 9;
    width: min(42vw, 168px);
}
#commsInCall.is-desktop-call .comms-local-video {
    width: min(18vw, 220px);
    max-height: 34vh;
    bottom: max(6.5rem, calc(env(safe-area-inset-bottom, 0px) + 5.5rem));
}
#commsInCall.is-desktop-call .comms-local-video.is-cam-landscape {
    aspect-ratio: 16 / 9;
    width: min(22vw, 280px);
}
@media (orientation: landscape) and (max-height: 520px) {
    #commsInCall .comms-local-video {
        right: max(0.75rem, env(safe-area-inset-right, 0px));
        bottom: max(5rem, calc(env(safe-area-inset-bottom, 0px) + 4.25rem));
        width: min(24vw, 150px);
        max-height: 42vh;
    }
    #commsInCall .comms-local-video.is-cam-landscape {
        aspect-ratio: 16 / 9;
        width: min(28vw, 180px);
    }
}
#commsInCall .comms-call-center {
    position: absolute;
    left: 50%;
    top: 42%;
    transform: translate(-50%, -50%);
    text-align: center;
    z-index: 2;
    width: min(92vw, 420px);
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}
#commsInCall .comms-call-meta {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    min-width: 0;
}
#commsInCall.is-connected.has-remote-video .comms-call-center,
#commsInCall.is-connected.is-video-call.has-remote-video .comms-call-center {
    top: max(0.65rem, env(safe-area-inset-top, 0px));
    left: max(0.65rem, env(safe-area-inset-left, 0px));
    right: auto;
    transform: none;
    width: auto;
    max-width: min(72vw, 320px);
    flex-direction: row;
    align-items: center;
    gap: 0.55rem;
    text-align: left;
    padding: 0.4rem 0.7rem 0.4rem 0.4rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.28);
}
#commsInCall.is-connected.has-remote-video .comms-call-avatar {
    width: 34px;
    height: 34px;
    margin: 0;
    font-size: 0.8rem;
    box-shadow: none;
    flex: 0 0 auto;
}
#commsInCall.is-connected.has-remote-video .comms-call-meta {
    align-items: flex-start;
}
#commsInCall.is-connected.has-remote-video .comms-call-peer-name {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.15;
    max-width: 12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#commsInCall.is-connected.has-remote-video .comms-call-status,
#commsInCall.is-connected.has-remote-video .comms-call-timer {
    margin: 0;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.82);
}
#commsInCall .comms-call-controls {
    position: absolute;
    left: 50%;
    bottom: max(2.75rem, calc(env(safe-area-inset-bottom, 0px) + 1.5rem));
    transform: translateX(-50%);
    display: flex;
    gap: 1.15rem;
    align-items: flex-end;
    justify-content: center;
    z-index: 4;
    max-width: calc(100vw - 1.5rem);
}
#commsInCall .comms-call-ctrl {
    appearance: none !important;
    -webkit-appearance: none !important;
    position: relative;
    box-sizing: border-box;
    width: 56px !important;
    height: 56px !important;
    min-width: 56px;
    min-height: 56px;
    margin: 0;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 999px !important;
    background: rgba(255, 255, 255, 0.16) !important;
    color: #fff !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex: 0 0 56px;
    overflow: visible;
    box-shadow: none;
}
#commsInCall .comms-call-ctrl:hover,
#commsInCall .comms-call-ctrl:focus-visible {
    background: rgba(255, 255, 255, 0.26) !important;
    outline: none;
    color: #fff !important;
}
#commsInCall .comms-call-ctrl.is-off {
    background: rgba(255, 255, 255, 0.92) !important;
    color: #0f172a !important;
}
#commsInCall .comms-call-ctrl--end,
#commsInCall .comms-call-ctrl--end.is-off,
#commsInCall .comms-call-ctrl--end:hover,
#commsInCall .comms-call-ctrl--end:focus-visible {
    background: #ef4444 !important;
    color: #fff !important;
}
#commsInCall .comms-call-ctrl-icon,
#commsInCall .comms-call-ctrl svg {
    display: block !important;
    width: 22px !important;
    height: 22px !important;
    flex-shrink: 0;
    pointer-events: none;
}
#commsInCall .comms-end-phone-icon {
    transform: rotate(135deg);
}
#commsInCall .comms-call-ctrl-label {
    position: absolute !important;
    left: 50%;
    bottom: calc(100% + 10px);
    top: auto;
    transform: translateX(-50%);
    white-space: nowrap;
    font-size: 0.72rem !important;
    font-weight: 650;
    line-height: 1.2;
    padding: 0.28rem 0.55rem;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.92);
    color: #fff;
    opacity: 0;
    pointer-events: none;
}
#commsInCall .comms-call-ctrl:hover .comms-call-ctrl-label,
#commsInCall .comms-call-ctrl:focus-visible .comms-call-ctrl-label {
    opacity: 1;
}
@media (hover: none) {
    #commsInCall .comms-call-ctrl-label {
        opacity: 1;
        bottom: auto;
        top: calc(100% + 8px);
        background: transparent;
        padding: 0;
        font-size: 0.68rem !important;
        color: rgba(255, 255, 255, 0.92);
    }
}
</style>
<div class="comms-call-overlay" id="commsInCall" hidden>
    <div class="comms-call-stage">
        <video id="commsRemoteVideo" class="comms-remote-video" autoplay playsinline muted></video>
        <video id="commsLocalVideo" class="comms-local-video" autoplay playsinline muted></video>
        <audio id="commsRemoteAudio" autoplay playsinline></audio>

        <div class="comms-call-center" id="commsCallCenter">
            <div class="comms-call-avatar" id="commsCallPeerAvatar" aria-hidden="true">U</div>
            <div class="comms-call-meta">
                <div class="comms-call-peer-name" id="commsCallPeerName"></div>
                <div class="comms-call-status" id="commsCallStatus">Calling...</div>
                <div class="comms-call-timer" id="commsCallTimer" hidden>00:00</div>
            </div>
        </div>

        <div class="comms-call-controls">
            <button type="button" class="comms-call-ctrl" id="commsMuteBtn" aria-label="Mute" aria-pressed="false">
                <span class="comms-call-ctrl-icon" data-mute-icon aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="23"/>
                        <line x1="8" y1="23" x2="16" y2="23"/>
                    </svg>
                </span>
                <span class="comms-call-ctrl-label">Mute</span>
            </button>
            <button type="button" class="comms-call-ctrl" id="commsCameraBtn" aria-label="Camera" aria-pressed="false">
                <span class="comms-call-ctrl-icon" data-camera-icon aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="23 7 16 12 23 17 23 7"/>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                    </svg>
                </span>
                <span class="comms-call-ctrl-label">Camera</span>
            </button>
            <button type="button" class="comms-call-ctrl comms-call-ctrl--end" id="commsEndCall" aria-label="End call">
                <svg class="comms-end-phone-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
                </svg>
                <span class="comms-call-ctrl-label">End</span>
            </button>
        </div>
    </div>
</div>
