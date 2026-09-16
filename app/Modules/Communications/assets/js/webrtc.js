/**
 * WebRTC voice/video with Supabase broadcast signaling + Laravel call records.
 * Voice: mic on, camera off (can turn on). Video: camera on (can turn off).
 * Shows visible ringing UI on both sides and a Call ended screen when not picked up.
 */
import {
    mediaConstraints,
    videoOnlyConstraints,
    prepareLocalStream
} from './media-constraints.js';

(function () {
    'use strict';

    if (window.CommsWebRTC) {
        return;
    }

    var AUDIO_RING_TIMEOUT_MS = 30000;
    var VIDEO_RING_TIMEOUT_MS = 45000;
    var CONNECTION_TIMEOUT_MS = 30000;
    var CALL_START_DELAY_MS = 0;

    var pc = null;
    var localStream = null;
    var remoteStream = null;
    var currentCall = null;
    var lastCallContext = null;
    var callTimer = null;
    var callStartedAt = null;
    var iceQueue = [];
    var startingCall = false;
    var cameraEnabled = false;
    var wantsVideo = false;
    var callConnected = false;
    var mediaConnected = false;
    var negotiating = false;
    var ringTimeout = null;
    var connectionTimeout = null;
    var endedAutoClose = null;
    var role = null; // 'caller' | 'callee'
    var lastEndedCallId = null;
    var lastIncomingCallId = null;
    var incomingSyncTimer = null;
    var pendingStartTimer = null;
    var pendingStartTick = null;
    var pendingStart = null;
    var pendingStartToken = 0;

    var ringCtx = null;
    var ringTimer = null;
    var ringOscillators = [];

    var HTTPS_PORT_BY_HTTP = {
        '8000': '8440',
        '8080': '8441',
        '8002': '8443'
    };

    var els = {
        incoming: document.getElementById('commsIncomingCall'),
        incomingPeer: document.getElementById('commsIncomingPeer'),
        incomingType: document.getElementById('commsIncomingType'),
        accept: document.getElementById('commsAcceptCall'),
        reject: document.getElementById('commsRejectCall'),
        inCall: document.getElementById('commsInCall'),
        remoteVideo: document.getElementById('commsRemoteVideo'),
        localVideo: document.getElementById('commsLocalVideo'),
        remoteAudio: document.getElementById('commsRemoteAudio'),
        peerName: document.getElementById('commsCallPeerName'),
        callStatus: document.getElementById('commsCallStatus'),
        timer: document.getElementById('commsCallTimer'),
        muteBtn: document.getElementById('commsMuteBtn'),
        cameraBtn: document.getElementById('commsCameraBtn'),
        endBtn: document.getElementById('commsEndCall'),
        ended: document.getElementById('commsCallEnded'),
        endedPeer: document.getElementById('commsCallEndedPeer'),
        endedReason: document.getElementById('commsCallEndedReason'),
        endedTitle: document.getElementById('commsCallEndedTitle'),
        endedClose: document.getElementById('commsCallEndedClose'),
        endedRedial: document.getElementById('commsCallEndedRedial'),
        callAvatar: document.getElementById('commsCallPeerAvatar'),
        callCenter: document.getElementById('commsCallCenter')
    };

    function mountCallUiToBody() {
        ['incoming', 'inCall', 'ended'].forEach(function (key) {
            var node = els[key];
            if (node && node.parentNode !== document.body) {
                document.body.appendChild(node);
            }
        });
        // Re-bind element refs after move (keeps Accept/Decline clicks reliable).
        els.accept = document.getElementById('commsAcceptCall');
        els.reject = document.getElementById('commsRejectCall');
        els.endBtn = document.getElementById('commsEndCall');
        els.endedClose = document.getElementById('commsCallEndedClose');
        els.endedRedial = document.getElementById('commsCallEndedRedial');
        els.muteBtn = document.getElementById('commsMuteBtn');
        els.cameraBtn = document.getElementById('commsCameraBtn');
        els.incoming = document.getElementById('commsIncomingCall');
        els.inCall = document.getElementById('commsInCall');
        els.ended = document.getElementById('commsCallEnded');
        els.callAvatar = document.getElementById('commsCallPeerAvatar');
        els.callCenter = document.getElementById('commsCallCenter');
        els.peerName = document.getElementById('commsCallPeerName');
        els.callStatus = document.getElementById('commsCallStatus');
        els.timer = document.getElementById('commsCallTimer');
    }

    function refreshCallChat(conversationId) {
        if (window.CommsChat) {
            if (conversationId && Number(window.CommsChat.getActiveId()) === Number(conversationId)) {
                if (typeof window.CommsChat.reloadActiveMessages === 'function') {
                    window.CommsChat.reloadActiveMessages();
                }
            } else if (typeof window.CommsChat.reloadConversations === 'function') {
                window.CommsChat.reloadConversations();
            }
        }
        if (typeof window.reloadHeaderChatMessages === 'function') {
            window.reloadHeaderChatMessages(conversationId);
        }
    }

    function routes() {
        return (window.CommsChat && window.CommsChat.routes) || {};
    }

    function getCallRoot() {
        return document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
    }

    function myIdentity() {
        var root = getCallRoot();
        return {
            id: root ? Number(root.dataset.currentUserId || 0) : 0,
            type: root ? String(root.dataset.portalUserType || '') : '',
            name: root ? String(root.dataset.currentUserName || '') : ''
        };
    }

    function subscribeCallChannel(conversationId) {
        if (!conversationId || !window.CommsRealtime || typeof window.CommsRealtime.ensureCallChannel !== 'function') {
            return Promise.resolve();
        }
        return window.CommsRealtime.ensureCallChannel(conversationId);
    }

    function signalTargets() {
        var me = myIdentity();
        var peerId = null;
        var peerType = null;
        var peers = [];
        if (!currentCall) return { peerId: peerId, peerType: peerType, peers: peers };
        var isGroup = !!(currentCall.is_group_call)
            || (Number(currentCall.caller_id) === Number(currentCall.receiver_id)
                && portalTypesMatch(currentCall.caller_type, currentCall.receiver_type));
        if (!isGroup && currentCall.receiver_id != null && currentCall.caller_id != null) {
            var idIsCaller = Number(currentCall.caller_id) === me.id;
            var idIsReceiver = Number(currentCall.receiver_id) === me.id;
            var typeIsCaller = !me.type || portalTypesMatch(currentCall.caller_type, me.type);
            var typeIsReceiver = !me.type || portalTypesMatch(currentCall.receiver_type, me.type);
            var iAmCaller = role === 'caller'
                || currentCall.direction === 'outgoing'
                || (idIsCaller && typeIsCaller && !(idIsReceiver && typeIsReceiver))
                || (idIsCaller && !idIsReceiver);
            if (iAmCaller) {
                peerId = currentCall.receiver_id;
                peerType = currentCall.receiver_type;
            } else {
                peerId = currentCall.caller_id;
                peerType = currentCall.caller_type;
            }
        }
        if (Array.isArray(currentCall.invitees)) {
            peers = currentCall.invitees;
        }
        return { peerId: peerId, peerType: peerType, peers: peers };
    }

    function portalTypesMatch(a, b) {
        a = String(a || '').toLowerCase().trim();
        b = String(b || '').toLowerCase().trim();
        if (!a || !b) return false;
        if (a === b) return true;
        var kab = { kabataan: 1, user: 1 };
        return !!(kab[a] && kab[b]);
    }

    function isMeOnCallRow(row, asReceiver) {
        var me = myIdentity();
        if (!me.id || !row) return false;
        var id = asReceiver ? Number(row.receiver_id) : Number(row.caller_id);
        var type = asReceiver ? row.receiver_type : row.caller_type;
        if (id !== me.id) return false;
        if (!type || !me.type) return true;
        return portalTypesMatch(type, me.type);
    }

    function toast(message, type) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(message, type || 'error');
            return;
        }
        window.alert(message);
    }

    function isSecureMediaContext() {
        if (window.isSecureContext) return true;
        var host = (window.location && window.location.hostname) || '';
        return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
    }

    function suggestedHttpsUrl() {
        var host = (window.location && window.location.hostname) || 'localhost';
        var httpPort = String((window.location && window.location.port) || '');
        var httpsPort = HTTPS_PORT_BY_HTTP[httpPort] || '8440';
        return 'https://' + host + ':' + httpsPort;
    }

    function resolveMediaDevices() {
        if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
            return navigator.mediaDevices;
        }

        var legacy = navigator.getUserMedia
            || navigator.webkitGetUserMedia
            || navigator.mozGetUserMedia
            || navigator.msGetUserMedia;

        if (!legacy) {
            return null;
        }

        return {
            getUserMedia: function (constraints) {
                return new Promise(function (resolve, reject) {
                    try {
                        legacy.call(navigator, constraints, resolve, reject);
                    } catch (err) {
                        reject(err);
                    }
                });
            }
        };
    }

    function mediaUnavailableMessage() {
        if (!isSecureMediaContext()) {
            return 'Voice/video needs HTTPS. Open ' + suggestedHttpsUrl()
                + ' (run npm run serve:lan), accept the certificate warning, then try again.';
        }
        return 'Camera/microphone is not available in this browser. Check site permissions and try again.';
    }

    function assertMediaReady() {
        if (!isSecureMediaContext()) {
            var err = new Error(mediaUnavailableMessage());
            err.code = 'MEDIA_INSECURE';
            throw err;
        }
        var devices = resolveMediaDevices();
        if (!devices) {
            var unavailable = new Error(mediaUnavailableMessage());
            unavailable.code = 'MEDIA_UNAVAILABLE';
            throw unavailable;
        }
        return devices;
    }

    async function getLocalStream(videoEnabled) {
        var devices = assertMediaReady();

        function mapMediaError(err, forVideo) {
            var name = (err && err.name) || '';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                return new Error('Microphone/camera permission was denied. Allow access and try again.');
            }
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                return new Error(forVideo
                    ? 'No camera or microphone was found on this device.'
                    : 'No microphone was found on this device.');
            }
            if (name === 'NotReadableError' || name === 'TrackStartError') {
                return new Error('Camera/microphone is already in use by another app.');
            }
            return new Error((err && err.message) || mediaUnavailableMessage());
        }

        try {
            var stream = await devices.getUserMedia(mediaConstraints(!!videoEnabled));
            if (videoEnabled) {
                await prepareLocalStream(stream);
            }
            return stream;
        } catch (err) {
            if (videoEnabled) {
                try {
                    var fallback = await devices.getUserMedia({
                        audio: mediaConstraints(false).audio,
                        video: { facingMode: 'user' }
                    });
                    await prepareLocalStream(fallback);
                    return fallback;
                } catch (e2) {
                    try {
                        var audioOnly = await devices.getUserMedia({
                            audio: mediaConstraints(false).audio,
                            video: false
                        });
                        toast('Camera unavailable — continuing with microphone only.', 'error');
                        cameraEnabled = false;
                        wantsVideo = false;
                        return audioOnly;
                    } catch (audioErr) {
                        throw mapMediaError(audioErr, false);
                    }
                }
            }
            try {
                return await devices.getUserMedia({ audio: true, video: false });
            } catch (retryErr) {
                throw mapMediaError(err, false);
            }
        }
    }

    function clearRingTimeout() {
        if (ringTimeout) {
            clearTimeout(ringTimeout);
            ringTimeout = null;
        }
    }

    function stopRingtone() {
        if (ringTimer) {
            clearInterval(ringTimer);
            ringTimer = null;
        }
        ringOscillators.forEach(function (osc) {
            try { osc.stop(); } catch (e) { /* ignore */ }
        });
        ringOscillators = [];
        if (ringCtx) {
            try { ringCtx.close(); } catch (e) { /* ignore */ }
            ringCtx = null;
        }
    }

    function playToneBurst(ctx, freqs, when, duration, volume, type) {
        freqs.forEach(function (freq) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = type || 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, when);
            gain.gain.exponentialRampToValueAtTime(volume, when + 0.02);
            gain.gain.setValueAtTime(volume, when + Math.max(0.05, duration - 0.05));
            gain.gain.exponentialRampToValueAtTime(0.0001, when + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(when);
            osc.stop(when + duration + 0.02);
            ringOscillators.push(osc);
        });
    }

    function startRingtone(mode) {
        stopRingtone();
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;

        try {
            ringCtx = new AudioCtx();
        } catch (e) {
            return;
        }

        if (ringCtx.state === 'suspended') {
            ringCtx.resume().catch(function () { /* ignore */ });
        }

        var incoming = mode === 'incoming';

        function scheduleCycle() {
            if (!ringCtx) return;
            var now = ringCtx.currentTime;
            if (incoming) {
                // Loud dual-tone ring for incoming
                playToneBurst(ringCtx, [440, 480], now, 0.5, 0.58, 'sine');
                playToneBurst(ringCtx, [440, 480], now, 0.5, 0.28, 'triangle');
                playToneBurst(ringCtx, [520, 560], now + 0.08, 0.4, 0.2, 'square');
                playToneBurst(ringCtx, [440, 480], now + 0.55, 0.5, 0.58, 'sine');
                playToneBurst(ringCtx, [440, 480], now + 0.55, 0.5, 0.28, 'triangle');
                playToneBurst(ringCtx, [520, 560], now + 0.63, 0.4, 0.2, 'square');
            } else {
                // Strong outbound ring while waiting for answer
                playToneBurst(ringCtx, [440, 480], now, 1.55, 0.62, 'sine');
                playToneBurst(ringCtx, [440, 480], now, 1.55, 0.3, 'triangle');
                playToneBurst(ringCtx, [350, 440], now + 0.12, 1.25, 0.24, 'square');
            }
        }

        scheduleCycle();
        ringTimer = setInterval(scheduleCycle, incoming ? 2000 : 3200);
    }

    function ringTimeoutForCall(callType) {
        return String(callType || '').toLowerCase() === 'video'
            ? VIDEO_RING_TIMEOUT_MS
            : AUDIO_RING_TIMEOUT_MS;
    }

    function clearConnectionTimeout() {
        if (connectionTimeout) {
            clearTimeout(connectionTimeout);
            connectionTimeout = null;
        }
    }

    function armRingTimeout(callType) {
        clearRingTimeout();
        var ms = ringTimeoutForCall(callType || (currentCall && currentCall.call_type) || 'voice');
        ringTimeout = setTimeout(function () {
            if (!currentCall || callConnected) return;
            finishUnanswered('missed');
        }, ms);
    }

    function armConnectionTimeout() {
        clearConnectionTimeout();
        connectionTimeout = setTimeout(function () {
            if (mediaConnected || !currentCall) return;
            toast('Call could not connect in time.', 'error');
            endCall();
        }, CONNECTION_TIMEOUT_MS);
    }

    function markCallAnswered() {
        callConnected = true;
        clearRingTimeout();
    }

    function markCallConnected() {
        callConnected = true;
        mediaConnected = true;
        clearRingTimeout();
        clearConnectionTimeout();
        unlockRemotePlayback();
        updateCameraUi();
    }

    function endedCopy(reason, endedByRole) {
        var actor = endedByRole || role;
        switch (reason) {
            case 'missed':
                return {
                    title: 'Call ended',
                    reason: role === 'caller' ? 'No answer. The call was not picked up.' : 'Missed call. You did not answer in time.'
                };
            case 'rejected':
                return {
                    title: 'Call declined',
                    reason: role === 'caller' ? 'The other person declined the call.' : 'You declined the call.'
                };
            case 'cancelled':
                return {
                    title: 'Call cancelled',
                    reason: actor === 'caller'
                        ? (role === 'caller' ? 'You cancelled the call.' : 'The caller cancelled the call.')
                        : (role === 'callee' ? 'You cancelled the call.' : 'The other person cancelled the call.')
                };
            default:
                return {
                    title: 'Call ended',
                    reason: 'The call has ended.'
                };
        }
    }

    function rememberCallContext(call) {
        if (!call) return;
        lastCallContext = {
            conversationId: call.conversation_id,
            callType: call.call_type || 'voice',
            conversation: {
                id: call.conversation_id,
                other_user: call.peer || null
            },
            peerName: (call.peer && call.peer.name) || ''
        };
    }

    function setMuteUi(muted) {
        if (!els.muteBtn) return;
        setCtrlLabel(els.muteBtn, muted ? 'Unmute' : 'Mute');
        els.muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
        els.muteBtn.classList.toggle('is-off', !!muted);
        var iconWrap = els.muteBtn.querySelector('[data-mute-icon]');
        if (!iconWrap) return;
        if (muted) {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
                '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
                '<path d="M19 10v2a7 7 0 0 1-14 0v-2"/>' +
                '<line x1="12" y1="19" x2="12" y2="23"/>' +
                '<line x1="8" y1="23" x2="16" y2="23"/>' +
                '<line x1="4" y1="4" x2="20" y2="20"/>' +
                '</svg>';
        } else {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
                '<path d="M19 10v2a7 7 0 0 1-14 0v-2"/>' +
                '<line x1="12" y1="19" x2="12" y2="23"/>' +
                '<line x1="8" y1="23" x2="16" y2="23"/>' +
                '</svg>';
        }
    }

    function setCtrlLabel(btn, label) {
        if (!btn) return;
        var labelEl = btn.querySelector('.comms-call-ctrl-label');
        if (labelEl) labelEl.textContent = label;
        btn.setAttribute('aria-label', label);
        btn.removeAttribute('title');
    }

    function setCallPeerAvatar(call) {
        if (!els.callAvatar) return;
        var peer = (call && call.peer) || {};
        var name = peer.name || 'U';
        var src = peer.profile_image_url || '';
        if (src) {
            els.callAvatar.innerHTML = '<img src="' + String(src).replace(/"/g, '&quot;') + '" alt="">';
            return;
        }
        var initial = String(name).trim().charAt(0).toUpperCase() || 'U';
        els.callAvatar.textContent = initial;
    }

    function hideEnded() {
        if (endedAutoClose) {
            clearTimeout(endedAutoClose);
            endedAutoClose = null;
        }
        if (els.ended) els.ended.hidden = true;
        if (els.endedRedial) els.endedRedial.hidden = true;
    }

    function showCallEnded(reason, peerName, callId, endedByRole) {
        if (callId != null && Number(callId) === Number(lastEndedCallId)) {
            return;
        }
        if (callId != null) lastEndedCallId = callId;

        hideIncoming();
        if (els.inCall) els.inCall.hidden = true;
        clearRingTimeout();
        stopRingtone();

        var copy = endedCopy(reason || 'ended', endedByRole);
        if (els.endedTitle) els.endedTitle.textContent = copy.title;
        if (els.endedReason) els.endedReason.textContent = copy.reason;
        if (els.endedPeer) {
            els.endedPeer.textContent = peerName
                || (lastCallContext && lastCallContext.peerName)
                || (currentCall && currentCall.peer && currentCall.peer.name)
                || '';
        }

        var canRedial = !!(lastCallContext && lastCallContext.conversationId)
            && (reason === 'missed' || reason === 'rejected' || reason === 'cancelled' || reason === 'ended');
        if (els.endedRedial) {
            els.endedRedial.hidden = !canRedial;
            els.endedRedial.textContent = role === 'callee' ? 'Call back' : 'Redial';
        }

        if (els.ended) els.ended.hidden = false;

        if (endedAutoClose) clearTimeout(endedAutoClose);
        endedAutoClose = setTimeout(function () {
            hideEnded();
        }, 12000);
    }

    function redialLastCall() {
        if (!lastCallContext || !lastCallContext.conversationId) return;
        var ctx = lastCallContext;
        hideEnded();
        lastEndedCallId = null;
        startCall(ctx.conversationId, ctx.callType || 'voice', ctx.conversation || null);
    }

    function signalPeer(payload) {
        if (!window.CommsRealtime || !currentCall) return Promise.resolve();
        var me = myIdentity();
        payload.from_user_id = me.id;
        payload.from_user_type = me.type;
        payload.from_name = me.name || payload.from_name;
        payload.call_id = payload.call_id || currentCall.id;
        payload.conversation_id = payload.conversation_id || currentCall.conversation_id;
        var targets = signalTargets();
        subscribeCallChannel(currentCall.conversation_id);
        return window.CommsRealtime.broadcastSignal(currentCall.conversation_id, payload, {
            peerId: targets.peerId,
            peerType: targets.peerType,
            peers: targets.peers
        });
    }

    function setCameraUi(enabled) {
        if (!els.cameraBtn) return;
        setCtrlLabel(els.cameraBtn, enabled ? 'Camera on' : 'Camera off');
        els.cameraBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        els.cameraBtn.classList.toggle('is-off', !enabled);
        var iconWrap = els.cameraBtn.querySelector('[data-camera-icon]');
        if (!iconWrap) return;
        if (enabled) {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '<polygon points="23 7 16 12 23 17 23 7"/>' +
                '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>' +
                '</svg>';
        } else {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
                '<polygon points="23 7 16 12 23 17 23 7"/>' +
                '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>' +
                '<line x1="4" y1="4" x2="20" y2="20"/>' +
                '</svg>';
        }
    }

    function hasLiveRemoteVideo() {
        var stream = els.remoteVideo && els.remoteVideo.srcObject;
        if (!stream || typeof stream.getVideoTracks !== 'function') return false;
        // Do not require track.enabled — remote tracks can start muted until frames arrive.
        return stream.getVideoTracks().some(function (track) {
            return !!(track && track.readyState === 'live');
        });
    }

    function scheduleRemoteVideoUiRefresh() {
        [200, 600, 1200, 2000].forEach(function (ms) {
            setTimeout(function () {
                if (!currentCall || !pc) return;
                if (remoteStream) bindRemoteMedia(remoteStream);
                unlockRemotePlayback();
                updateCameraUi();
            }, ms);
        });
    }

    function updateCameraUi() {
        if (els.cameraBtn) {
            els.cameraBtn.hidden = false;
            setCameraUi(!!cameraEnabled);
        }
        if (els.localVideo) {
            els.localVideo.hidden = !cameraEnabled;
        }
        var remoteVideoLive = hasLiveRemoteVideo();
        if (els.inCall) {
            els.inCall.classList.toggle('is-connected', !!callConnected);
            els.inCall.classList.toggle('has-remote-video', remoteVideoLive);
            els.inCall.classList.toggle('is-video-call', !!cameraEnabled || remoteVideoLive);
        }
        if (els.callCenter) {
            // Keep HUD visible; CSS moves it to a top pill when remote video is live.
            els.callCenter.hidden = false;
            els.callCenter.removeAttribute('hidden');
            els.callCenter.classList.toggle('is-compact', !!callConnected && remoteVideoLive);
        }
        syncLocalPipLayout();
    }

    function syncLocalPipLayout() {
        if (!els.inCall || !els.localVideo) return;
        var landscape = false;
        try {
            landscape = window.matchMedia('(orientation: landscape)').matches;
        } catch (e) {
            landscape = window.innerWidth > window.innerHeight;
        }
        var desktop = window.innerWidth >= 1024;
        els.inCall.classList.toggle('is-landscape', landscape);
        els.inCall.classList.toggle('is-desktop-call', desktop);

        var camLandscape = landscape;
        try {
            var track = localStream && localStream.getVideoTracks && localStream.getVideoTracks()[0];
            var settings = track && typeof track.getSettings === 'function' ? track.getSettings() : null;
            if (settings && settings.width && settings.height) {
                camLandscape = (Number(settings.width) / Number(settings.height)) >= 1.15;
            }
        } catch (e2) { /* ignore */ }
        els.localVideo.classList.toggle('is-cam-landscape', camLandscape);
        els.localVideo.classList.toggle('is-cam-portrait', !camLandscape);
    }

    function ensureRemoteAudioPlaying() {
        if (!els.remoteAudio) return;
        try {
            els.remoteAudio.muted = false;
            els.remoteAudio.removeAttribute('muted');
            els.remoteAudio.volume = 1;
            els.remoteAudio.autoplay = true;
            var playPromise = els.remoteAudio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () { /* autoplay may need gesture; accept/start already counted */ });
            }
        } catch (e) { /* ignore */ }
    }

    function unlockRemotePlayback() {
        ensureRemoteAudioPlaying();
        if (els.remoteVideo) {
            try {
                els.remoteVideo.muted = true;
                els.remoteVideo.setAttribute('muted', '');
                els.remoteVideo.playsInline = true;
                els.remoteVideo.autoplay = true;
                var vp = els.remoteVideo.play();
                if (vp && typeof vp.catch === 'function') vp.catch(function () {});
            } catch (e2) { /* ignore */ }
        }
    }

    function mergeRemoteTrack(track, inboundStream) {
        if (!remoteStream) remoteStream = new MediaStream();
        if (track && !remoteStream.getTracks().some(function (t) { return t.id === track.id; })) {
            remoteStream.addTrack(track);
            track.addEventListener('unmute', function () {
                bindRemoteMedia(remoteStream);
                scheduleRemoteVideoUiRefresh();
            });
            track.addEventListener('mute', function () {
                updateCameraUi();
            });
            track.addEventListener('ended', function () {
                try { remoteStream.removeTrack(track); } catch (err) { /* ignore */ }
                bindRemoteMedia(remoteStream);
            });
        }
        if (inboundStream && typeof inboundStream.getTracks === 'function') {
            inboundStream.getTracks().forEach(function (t) {
                if (!remoteStream.getTracks().some(function (x) { return x.id === t.id; })) {
                    remoteStream.addTrack(t);
                }
            });
        }
        return remoteStream;
    }

    function applyRemoteVideoMetrics() {
        if (!els.remoteVideo || !els.inCall) return;
        var w = Number(els.remoteVideo.videoWidth || 0);
        var h = Number(els.remoteVideo.videoHeight || 0);
        if (!w || !h) return;
        var portrait = (w / h) < 0.92;
        els.remoteVideo.classList.toggle('is-remote-portrait', portrait);
        els.remoteVideo.classList.toggle('is-remote-landscape', !portrait);
        els.inCall.classList.toggle('is-remote-portrait', portrait);
        els.inCall.classList.toggle('is-remote-landscape', !portrait);
    }

    function bindRemoteMedia(stream) {
        if (!stream) return;
        if (els.remoteVideo) {
            // Mute remote <video>; play audio on #commsRemoteAudio so autoplay is not blocked.
            els.remoteVideo.muted = true;
            els.remoteVideo.setAttribute('muted', '');
            els.remoteVideo.playsInline = true;
            els.remoteVideo.autoplay = true;
            if (els.remoteVideo.srcObject !== stream) {
                els.remoteVideo.srcObject = stream;
            }
            try {
                var vp = els.remoteVideo.play();
                if (vp && typeof vp.catch === 'function') vp.catch(function () {});
            } catch (e) { /* ignore */ }
            if (!els.remoteVideo._commsMetricsBound) {
                els.remoteVideo._commsMetricsBound = true;
                els.remoteVideo.addEventListener('loadedmetadata', applyRemoteVideoMetrics);
                els.remoteVideo.addEventListener('resize', applyRemoteVideoMetrics);
            }
            applyRemoteVideoMetrics();
        }
        if (els.remoteAudio) {
            var audioTracks = typeof stream.getAudioTracks === 'function' ? stream.getAudioTracks() : [];
            els.remoteAudio.muted = false;
            els.remoteAudio.removeAttribute('muted');
            els.remoteAudio.volume = 1;
            els.remoteAudio.autoplay = true;
            els.remoteAudio.srcObject = new MediaStream(audioTracks);
            ensureRemoteAudioPlaying();
        }
        updateCameraUi();
    }

    function setCallStatus(text) {
        if (els.callStatus) els.callStatus.textContent = text || '';
        if (els.timer) {
            els.timer.hidden = !callConnected;
        }
    }

    function stunConfig() {
        return {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
    }

    function showIncoming(call) {
        if (!els.incoming) {
            els.incoming = document.getElementById('commsIncomingCall');
            els.incomingPeer = document.getElementById('commsIncomingPeer');
            els.incomingType = document.getElementById('commsIncomingType');
            els.accept = document.getElementById('commsAcceptCall');
            els.reject = document.getElementById('commsRejectCall');
        }
        if (!els.incoming || !call) return;

        var callId = call.id != null ? Number(call.id) : null;
        if (callId && callId === Number(lastIncomingCallId)
            && els.incoming && !els.incoming.hidden
            && currentCall && Number(currentCall.id) === callId) {
            if (call._remoteOffer && currentCall) {
                currentCall._remoteOffer = call._remoteOffer;
            }
            return;
        }
        if (callId) lastIncomingCallId = callId;

        role = 'callee';
        callConnected = false;
        hideEnded();
        if (els.inCall && !els.inCall.hidden && !callConnected) {
            els.inCall.hidden = true;
        }
        currentCall = Object.assign({}, currentCall || {}, call);
        rememberCallContext(currentCall);
        if (els.incomingPeer) {
            els.incomingPeer.textContent = (call.peer && call.peer.name)
                || call.peer_name
                || call.from_name
                || 'Incoming caller';
        }
        var titleEl = document.getElementById('commsIncomingTitle');
        var isVideo = String(call.call_type || '') === 'video';
        if (titleEl) {
            titleEl.textContent = isVideo ? 'Incoming Video Call' : 'Incoming Voice Call';
        }
        if (els.incomingType) {
            els.incomingType.textContent = isVideo ? 'Video call' : 'Voice call';
        }
        els.incoming.hidden = false;
        els.incoming.removeAttribute('hidden');
        startRingtone('incoming');
        armRingTimeout(call.call_type || 'voice');
    }

    function hideIncoming() {
        if (els.incoming) els.incoming.hidden = true;
        // Ringtone may continue for outgoing ring UI; stop only if not showing in-call ringing.
    }

    function formatTimer(ms) {
        var total = Math.floor(ms / 1000);
        var m = Math.floor(total / 60);
        var s = total % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function startTimer() {
        callStartedAt = Date.now();
        clearInterval(callTimer);
        callTimer = setInterval(function () {
            if (els.timer) els.timer.textContent = formatTimer(Date.now() - callStartedAt);
        }, 1000);
    }

    async function ensurePeerConnection(videoEnabled) {
        if (pc) return pc;

        if (typeof RTCPeerConnection === 'undefined') {
            throw new Error('WebRTC is not supported in this browser.');
        }

        wantsVideo = !!videoEnabled;
        cameraEnabled = !!videoEnabled;
        localStream = await getLocalStream(!!videoEnabled);

        pc = new RTCPeerConnection(stunConfig());
        pc.onicecandidate = function (ev) {
            if (!ev.candidate || !currentCall) return;
            signalPeer({
                type: 'ice',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                candidate: ev.candidate
            });
        };
        pc.ontrack = function (ev) {
            var merged = mergeRemoteTrack(
                ev.track,
                ev.streams && ev.streams[0] ? ev.streams[0] : null
            );
            bindRemoteMedia(merged);
            if (ev.track && ev.track.kind === 'audio') {
                ensureRemoteAudioPlaying();
            }
            if (ev.track && ev.track.kind === 'video') {
                updateCameraUi();
                scheduleRemoteVideoUiRefresh();
            }
            unlockRemotePlayback();
        };
        pc.oniceconnectionstatechange = function () {
            var st = pc && pc.iceConnectionState;
            if (st === 'connected' || st === 'completed') {
                markCallConnected();
                setCallStatus('Connected');
                unlockRemotePlayback();
                updateCameraUi();
            }
            if (st === 'failed') {
                if (!currentCall) return;
                toast('Call connection failed.', 'error');
                endCall();
            }
        };
        pc.onconnectionstatechange = function () {
            if (pc && pc.connectionState === 'connected') {
                markCallConnected();
                unlockRemotePlayback();
                updateCameraUi();
            }
            if (pc && pc.connectionState === 'failed') {
                if (!currentCall) return;
                toast('Call connection failed.', 'error');
                endCall();
            }
        };

        localStream.getTracks().forEach(function (track) {
            pc.addTrack(track, localStream);
        });
        var kinds = {};
        pc.getTransceivers().forEach(function (t) {
            var kind = t.receiver && t.receiver.track && t.receiver.track.kind;
            if (kind) kinds[kind] = true;
        });
        if (!kinds.video) {
            try { pc.addTransceiver('video', { direction: 'recvonly' }); } catch (e) { /* ignore */ }
        }
        if (!kinds.audio) {
            try { pc.addTransceiver('audio', { direction: 'recvonly' }); } catch (e2) { /* ignore */ }
        }
        if (els.localVideo) {
            els.localVideo.srcObject = localStream;
            syncLocalPipLayout();
        }
        if (els.muteBtn) setMuteUi(false);
        updateCameraUi();
        return pc;
    }

    async function renegotiateForCamera() {
        if (!pc || !currentCall || negotiating) return;
        if (pc.signalingState !== 'stable') return;
        negotiating = true;
        try {
            var offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });
            await pc.setLocalDescription(offer);
            await signalPeer({
                type: 'offer',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                sdp: offer,
                call_type: (currentCall && currentCall.call_type) || 'voice',
                renegotiate: true
            });
        } catch (e) {
            /* ignore renegotiation errors */
        } finally {
            negotiating = false;
        }
    }

    async function attachLocalVideoTrack(newTrack) {
        if (!localStream.getVideoTracks().some(function (t) { return t.id === newTrack.id; })) {
            localStream.addTrack(newTrack);
        }
        var sender = pc.getSenders().find(function (s) {
            return s.track && s.track.kind === 'video';
        });
        if (sender) {
            await sender.replaceTrack(newTrack);
            return true;
        }
        var videoTx = pc.getTransceivers().find(function (t) {
            return (t.receiver && t.receiver.track && t.receiver.track.kind === 'video')
                || (t.sender && (!t.sender.track || t.sender.track.kind === 'video'));
        });
        if (videoTx && videoTx.sender) {
            await videoTx.sender.replaceTrack(newTrack);
            try { videoTx.direction = 'sendrecv'; } catch (e) { /* ignore */ }
            return true;
        }
        pc.addTrack(newTrack, localStream);
        return true;
    }

    async function setCameraEnabled(enabled) {
        if (!pc || !localStream) return;
        enabled = !!enabled;

        var videoTracks = localStream.getVideoTracks();
        if (enabled) {
            var needsRenegotiate = false;
            if (!videoTracks.length || videoTracks.every(function (t) { return t.readyState === 'ended'; })) {
                var devices = assertMediaReady();
                var camStream = await devices.getUserMedia(videoOnlyConstraints());
                await prepareLocalStream(camStream);
                var newTrack = camStream.getVideoTracks()[0];
                if (!newTrack) {
                    throw new Error('No camera was found on this device.');
                }
                // Voice→video always needs SDP renegotiation so the peer receives frames.
                needsRenegotiate = await attachLocalVideoTrack(newTrack);
                if (els.localVideo) {
                    els.localVideo.srcObject = localStream;
                    els.localVideo.hidden = false;
                    try {
                        var lp = els.localVideo.play();
                        if (lp && typeof lp.catch === 'function') lp.catch(function () {});
                    } catch (ePlay) { /* ignore */ }
                }
            } else {
                videoTracks.forEach(function (t) { t.enabled = true; });
                if (els.localVideo) {
                    els.localVideo.srcObject = localStream;
                    els.localVideo.hidden = false;
                    try {
                        var lp2 = els.localVideo.play();
                        if (lp2 && typeof lp2.catch === 'function') lp2.catch(function () {});
                    } catch (ePlay2) { /* ignore */ }
                }
            }
            cameraEnabled = true;
            updateCameraUi();
            if (needsRenegotiate) {
                await renegotiateForCamera();
            }
        } else {
            videoTracks.forEach(function (t) { t.enabled = false; });
            cameraEnabled = false;
            updateCameraUi();
        }

        if (currentCall) {
            signalPeer({
                type: 'camera',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                enabled: cameraEnabled
            });
        }
    }

    function showInCall(call, statusText) {
        if (!els.inCall) return;
        hideEnded();
        if (els.incoming) els.incoming.hidden = true;
        els.inCall.hidden = false;
        if (els.peerName) {
            els.peerName.textContent = (call.peer && call.peer.name) || 'Call';
        }
        setCallPeerAvatar(call);
        setCallStatus(statusText || (callConnected ? 'Connected' : 'Calling...'));
        if (els.timer) els.timer.textContent = callConnected ? (els.timer.textContent || '00:00') : '00:00';
        if (callConnected && !callStartedAt) {
            startTimer();
        }
        setMuteUi(false);
        updateCameraUi();
        syncLocalPipLayout();
        unlockRemotePlayback();
    }

    async function cleanupMediaOnly() {
        clearPendingStart();
        clearRingTimeout();
        clearConnectionTimeout();
        if (ringTimer || ringCtx) stopRingtone();
        clearInterval(callTimer);
        callTimer = null;
        callStartedAt = null;
        cameraEnabled = false;
        wantsVideo = false;
        callConnected = false;
        mediaConnected = false;
        negotiating = false;
        if (els.endBtn) setCtrlLabel(els.endBtn, 'End');
        if (els.muteBtn) {
            els.muteBtn.hidden = false;
            setMuteUi(false);
        }
        if (els.cameraBtn) els.cameraBtn.hidden = false;
        if (localStream) {
            localStream.getTracks().forEach(function (t) { t.stop(); });
            localStream = null;
        }
        if (pc) {
            try { pc.close(); } catch (e) { /* ignore */ }
            pc = null;
        }
        iceQueue = [];
        remoteStream = null;
        if (els.remoteVideo) els.remoteVideo.srcObject = null;
        if (els.localVideo) els.localVideo.srcObject = null;
        if (els.remoteAudio) els.remoteAudio.srcObject = null;
        if (els.inCall) {
            els.inCall.hidden = true;
            els.inCall.classList.remove('is-connected', 'has-remote-video', 'is-video-call', 'is-remote-portrait', 'is-remote-landscape');
        }
        if (els.remoteVideo) {
            els.remoteVideo.classList.remove('is-remote-portrait', 'is-remote-landscape');
        }
        if (window.CommsRealtime && typeof window.CommsRealtime.teardownCallChannel === 'function') {
            window.CommsRealtime.teardownCallChannel();
        }
        if (els.callCenter) {
            els.callCenter.classList.remove('is-compact');
            els.callCenter.hidden = false;
        }
        if (els.incoming) els.incoming.hidden = true;
    }

    async function updateCallStatus(callId, status) {
        var r = routes();
        if (!r.callStatus || !window.Comms) return null;
        return window.Comms.api(window.Comms.route(r.callStatus, callId), {
            method: 'POST',
            body: JSON.stringify({ status: status }),
            dedupe: false
        }).then(function (data) { return data.call; });
    }

    async function finishUnanswered(reason) {
        if (!currentCall || callConnected) return;
        var callSnapshot = currentCall;
        rememberCallContext(callSnapshot);
        var peerName = (callSnapshot.peer && callSnapshot.peer.name) || '';
        var status = reason === 'rejected' ? 'rejected'
            : (reason === 'cancelled' ? 'cancelled' : 'missed');

        clearRingTimeout();
        stopRingtone();

        // Hangup signal first so the peer ends in realtime; status follows in background.
        var signalPromise = signalPeer({
            type: 'hangup',
            reason: status,
            ended_by_role: role || (status === 'cancelled' ? 'caller' : 'callee'),
            call_id: callSnapshot.id,
            conversation_id: callSnapshot.conversation_id,
            caller_id: callSnapshot.caller_id,
            caller_type: callSnapshot.caller_type,
            receiver_id: callSnapshot.receiver_id,
            receiver_type: callSnapshot.receiver_type
        });
        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded(status, peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
        Promise.all([
            signalPromise.catch(function () {}),
            callSnapshot.id ? updateCallStatus(callSnapshot.id, status).catch(function () {}) : Promise.resolve()
        ]);
    }

    function clearPendingStart() {
        if (pendingStartTimer) {
            clearTimeout(pendingStartTimer);
            pendingStartTimer = null;
        }
        if (pendingStartTick) {
            clearInterval(pendingStartTick);
            pendingStartTick = null;
        }
        pendingStart = null;
    }

    async function cancelPendingStart() {
        if (!pendingStart) return false;
        pendingStartToken += 1;
        clearPendingStart();
        role = null;
        currentCall = null;
        await cleanupMediaOnly();
        hideEnded();
        if (els.inCall) els.inCall.hidden = true;
        toast('Call cancelled.', 'info');
        return true;
    }

    function beginCallCountdown(conversationId, callType, conversation) {
        if (startingCall || pendingStart) return;
        if (currentCall) {
            var inCallVisible = els.inCall && !els.inCall.hidden;
            var incomingVisible = els.incoming && !els.incoming.hidden;
            if (inCallVisible || incomingVisible || callConnected) return;
            currentCall = null;
        }
        try {
            assertMediaReady();
        } catch (err) {
            toast(err.message || 'Unable to start call.', 'error');
            return;
        }
        if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
            toast('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.', 'error');
            return;
        }

        role = 'caller';
        callConnected = false;
        lastEndedCallId = null;
        pendingStartToken += 1;
        var token = pendingStartToken;
        pendingStart = {
            conversationId: conversationId,
            callType: callType,
            conversation: conversation,
            token: token
        };

        var peer = (conversation && conversation.other_user) || { name: 'Contact' };

        if (CALL_START_DELAY_MS <= 0) {
            showInCall({ peer: peer }, 'Starting…');
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            clearPendingStart();
            placeCall(conversationId, callType, conversation);
            return;
        }

        var remaining = Math.max(1, Math.ceil(CALL_START_DELAY_MS / 1000));
        showInCall({ peer: peer }, 'Starting in ' + remaining + 's — tap Cancel to stop');
        if (els.muteBtn) els.muteBtn.hidden = true;
        if (els.cameraBtn) els.cameraBtn.hidden = true;
        setCtrlLabel(els.endBtn, 'Cancel');

        setCallStatus('Starting in ' + remaining + 's — tap Cancel to stop');
        pendingStartTick = setInterval(function () {
            if (!pendingStart || pendingStart.token !== token) {
                clearInterval(pendingStartTick);
                pendingStartTick = null;
                return;
            }
            remaining -= 1;
            if (remaining <= 0) {
                setCallStatus('Starting…');
                return;
            }
            setCallStatus('Starting in ' + remaining + 's — tap Cancel to stop');
        }, 1000);

        pendingStartTimer = setTimeout(function () {
            var ctx = pendingStart;
            clearPendingStart();
            // Cancelled during the wait — do not place the call.
            if (!ctx || ctx.token !== token || token !== pendingStartToken) {
                return;
            }
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            placeCall(ctx.conversationId, ctx.callType, ctx.conversation);
        }, CALL_START_DELAY_MS);
    }

    async function placeCall(conversationId, callType, conversation) {
        if (!window.Comms || startingCall) return;
        startingCall = true;
        var r = routes();
        try {
            assertMediaReady();
            if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
                throw new Error('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.');
            }

            var data = await window.Comms.api(window.Comms.route(r.startCall, conversationId), {
                method: 'POST',
                body: JSON.stringify({ call_type: callType }),
                dedupe: false
            });
            role = 'caller';
            callConnected = false;
            lastEndedCallId = null;
            currentCall = data.call;
            wantsVideo = callType === 'video';
            if (conversation && conversation.other_user) {
                currentCall.peer = conversation.other_user;
            }
            rememberCallContext(currentCall);
            await subscribeCallChannel(conversationId);
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            showInCall(Object.assign({}, currentCall, {
                peer: (conversation && conversation.other_user) || currentCall.peer
            }), 'Calling...');
            startRingtone('outgoing');
            armRingTimeout(callType);
            var callerDisplayName = (myIdentity().name)
                || (conversation && conversation.other_user && conversation.other_user.name)
                || 'Caller';
            await signalPeer({
                type: 'incoming-call',
                call_id: currentCall.id,
                conversation_id: conversationId,
                call_type: callType,
                peer_name: callerDisplayName,
                from_name: callerDisplayName,
                caller_id: currentCall.caller_id,
                caller_type: currentCall.caller_type,
                receiver_id: currentCall.receiver_id,
                receiver_type: currentCall.receiver_type
            });
            setCallStatus('Ringing...');
            await ensurePeerConnection(wantsVideo);
            var offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });
            await pc.setLocalDescription(offer);
            await signalPeer({
                type: 'offer',
                call_id: currentCall.id,
                conversation_id: conversationId,
                sdp: offer,
                call_type: callType,
                peer_name: callerDisplayName,
                from_name: callerDisplayName,
                caller_id: currentCall.caller_id,
                caller_type: currentCall.caller_type,
                receiver_id: currentCall.receiver_id,
                receiver_type: currentCall.receiver_type
            });
            setCallStatus('Ringing...');
            refreshCallChat(conversationId);
        } catch (err) {
            toast(err.message || 'Unable to start call.', 'error');
            if (currentCall && currentCall.id) {
                try { await updateCallStatus(currentCall.id, 'cancelled'); } catch (e) { /* ignore */ }
            }
            currentCall = null;
            await cleanupMediaOnly();
            hideEnded();
        } finally {
            startingCall = false;
        }
    }

    async function startCall(conversationId, callType, conversation) {
        beginCallCountdown(conversationId, callType, conversation);
    }

    function waitForRemoteOffer(ms) {
        return new Promise(function (resolve) {
            if (currentCall && currentCall._remoteOffer) {
                resolve(true);
                return;
            }
            var started = Date.now();
            var timer = setInterval(function () {
                if (currentCall && currentCall._remoteOffer) {
                    clearInterval(timer);
                    resolve(true);
                    return;
                }
                if (Date.now() - started >= ms) {
                    clearInterval(timer);
                    resolve(false);
                }
            }, 50);
        });
    }

    async function acceptIncoming() {
        if (!currentCall || startingCall) return;
        startingCall = true;
        clearRingTimeout();
        stopRingtone();
        hideIncoming();
        try {
            assertMediaReady();
            await subscribeCallChannel(currentCall.conversation_id);
            markCallAnswered();
            showInCall(currentCall, 'Connecting...');
            armConnectionTimeout();
            // Tell the caller immediately — do not wait for getUserMedia or SDP.
            signalPeer({
                type: 'call-accepted',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                caller_id: currentCall.caller_id,
                caller_type: currentCall.caller_type,
                receiver_id: currentCall.receiver_id,
                receiver_type: currentCall.receiver_type
            });
            updateCallStatus(currentCall.id, 'accepted').then(function (accepted) {
                if (accepted && currentCall && Number(currentCall.id) === Number(accepted.id)) {
                    currentCall = Object.assign({}, currentCall, accepted);
                }
            }).catch(function () {});
            wantsVideo = currentCall.call_type === 'video';
            await ensurePeerConnection(wantsVideo);
            if (!currentCall._remoteOffer) {
                await waitForRemoteOffer(8000);
            }
            if (currentCall._remoteOffer) {
                await pc.setRemoteDescription(currentCall._remoteOffer);
                var answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await signalPeer({
                    type: 'answer',
                    call_id: currentCall.id,
                    conversation_id: currentCall.conversation_id,
                    sdp: answer,
                    caller_id: currentCall.caller_id,
                    caller_type: currentCall.caller_type,
                    receiver_id: currentCall.receiver_id,
                    receiver_type: currentCall.receiver_type
                });
                while (iceQueue.length) {
                    await pc.addIceCandidate(iceQueue.shift());
                }
                setCallStatus('Connected');
                startTimer();
                markCallConnected();
                refreshCallChat(currentCall.conversation_id);
            } else {
                toast('Call signal was incomplete. Ask the caller to try again.', 'error');
                await updateCallStatus(currentCall.id, 'ended');
                var peerName = (currentCall.peer && currentCall.peer.name) || '';
                var incompleteId = currentCall.id;
                var incompleteConv = currentCall.conversation_id;
                currentCall = null;
                await cleanupMediaOnly();
                showCallEnded('ended', peerName, incompleteId);
                refreshCallChat(incompleteConv);
            }
        } catch (err) {
            toast(err.message || 'Unable to accept call.', 'error');
            if (currentCall && currentCall.id) {
                try { await updateCallStatus(currentCall.id, 'rejected'); } catch (e) { /* ignore */ }
            }
            var peer = (currentCall && currentCall.peer && currentCall.peer.name) || '';
            var failId = currentCall && currentCall.id;
            var failConv = currentCall && currentCall.conversation_id;
            currentCall = null;
            await cleanupMediaOnly();
            showCallEnded('rejected', peer, failId);
            refreshCallChat(failConv);
        } finally {
            startingCall = false;
        }
    }

    async function rejectIncoming() {
        if (!currentCall) return;
        await finishUnanswered('rejected');
    }

    async function endCall() {
        if (pendingStart) {
            await cancelPendingStart();
            setCtrlLabel(els.endBtn, 'End');
            return;
        }

        if (!currentCall) {
            await cleanupMediaOnly();
            return;
        }

        if (!callConnected) {
            await finishUnanswered(role === 'caller' ? 'cancelled' : 'rejected');
            return;
        }

        var peerName = (currentCall.peer && currentCall.peer.name) || '';
        var callSnapshot = currentCall;
        rememberCallContext(callSnapshot);
        stopRingtone();
        // Signal peer immediately so End feels realtime; do not wait on API first.
        var signalPromise = signalPeer({
            type: 'hangup',
            reason: 'ended',
            ended_by_role: role || 'caller',
            call_id: callSnapshot.id,
            conversation_id: callSnapshot.conversation_id,
            caller_id: callSnapshot.caller_id,
            caller_type: callSnapshot.caller_type,
            receiver_id: callSnapshot.receiver_id,
            receiver_type: callSnapshot.receiver_type
        });
        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded('ended', peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
        Promise.all([
            signalPromise.catch(function () {}),
            updateCallStatus(callSnapshot.id, 'ended').catch(function () {})
        ]);
    }

    function sameCall(payload) {
        if (!payload || payload.call_id == null || !currentCall) return true;
        return Number(currentCall.id) === Number(payload.call_id);
    }

    function applyPeerAnswered() {
        stopRingtone();
        markCallAnswered();
        setCallStatus('Connecting...');
        armConnectionTimeout();
        updateCameraUi();
    }

    async function applyPeerAnswerSdp(payload) {
        if (!payload) return;

        // Mid-call camera renegotiation answers apply for either role.
        var isRenego = !!(payload.renegotiate) || (callConnected && pc && pc.signalingState === 'have-local-offer');
        if (role !== 'caller' && !isRenego) return;

        if (pc && payload.sdp && pc.signalingState === 'have-local-offer') {
            try {
                await pc.setRemoteDescription(payload.sdp);
                while (iceQueue.length) {
                    await pc.addIceCandidate(iceQueue.shift());
                }
            } catch (e) { /* ignore duplicate/stale SDP */ }
        }

        if (isRenego && callConnected) {
            unlockRemotePlayback();
            updateCameraUi();
            scheduleRemoteVideoUiRefresh();
            return;
        }

        if (role !== 'caller') return;
        applyPeerAnswered();
        if (els.timer && !callStartedAt) {
            startTimer();
        }
        markCallConnected();
        setCallStatus('Connected');
        refreshCallChat(currentCall && currentCall.conversation_id);
    }

    async function handleSignal(payload) {
        if (!payload || !payload.type) return;

        if (payload.type === 'incoming-call' || payload.type === 'offer') {
            var meOffer = myIdentity();
            var iAmOfferCaller = Number(payload.caller_id) === meOffer.id
                && (!meOffer.type || portalTypesMatch(payload.caller_type, meOffer.type));
            if (iAmOfferCaller) return;

            // Mid-call renegotiation (e.g. peer turned camera on during voice call).
            if (payload.type === 'offer' && payload.sdp && currentCall && pc && callConnected
                && Number(currentCall.id) === Number(payload.call_id)) {
                try {
                    if (pc.signalingState === 'have-local-offer') {
                        try { await pc.setLocalDescription({ type: 'rollback' }); } catch (rbErr) { /* ignore */ }
                    }
                    if (pc.signalingState === 'stable') {
                        await pc.setRemoteDescription(payload.sdp);
                        var answer = await pc.createAnswer();
                        await pc.setLocalDescription(answer);
                        await signalPeer({
                            type: 'answer',
                            call_id: currentCall.id,
                            conversation_id: currentCall.conversation_id,
                            sdp: answer,
                            renegotiate: true
                        });
                        unlockRemotePlayback();
                        updateCameraUi();
                        scheduleRemoteVideoUiRefresh();
                    } else {
                        currentCall._remoteOffer = payload.sdp;
                    }
                } catch (renegoErr) {
                    currentCall._remoteOffer = payload.sdp;
                }
                return;
            }

            if (role === 'caller' && sameCall(payload)) return;
            if (payload.type === 'offer' && currentCall && currentCall._remoteOffer
                && Number(currentCall.id) === Number(payload.call_id)) {
                currentCall._remoteOffer = payload.sdp;
                return;
            }
            lastEndedCallId = null;
            role = 'callee';
            currentCall = Object.assign({}, currentCall || {}, {
                id: payload.call_id,
                conversation_id: payload.conversation_id || (window.CommsChat && window.CommsChat.getActiveId()),
                call_type: payload.call_type || 'voice',
                peer: {
                    name: payload.peer_name || payload.from_name
                        || (currentCall && currentCall.peer && currentCall.peer.name)
                        || 'Incoming caller'
                },
                caller_id: payload.caller_id,
                caller_type: payload.caller_type,
                receiver_id: payload.receiver_id,
                receiver_type: payload.receiver_type,
                _remoteOffer: payload.type === 'offer' ? payload.sdp : (currentCall && currentCall._remoteOffer)
            });
            subscribeCallChannel(currentCall.conversation_id);
            showIncoming(currentCall);
            return;
        }

        if (payload.type === 'call-accepted' || payload.type === 'accepted') {
            var meAccept = myIdentity();
            var iAmCallerAccept = currentCall
                && Number(currentCall.caller_id) === meAccept.id
                && portalTypesMatch(currentCall.caller_type, meAccept.type);
            if ((!iAmCallerAccept && role !== 'caller') || !sameCall(payload)) return;
            role = 'caller';
            applyPeerAnswered();
            return;
        }

        if (payload.type === 'hangup') {
            if (currentCall && payload.call_id != null && Number(currentCall.id) !== Number(payload.call_id)) {
                return;
            }
            var reason = payload.reason || 'ended';
            var peerName = (currentCall && currentCall.peer && currentCall.peer.name) || '';
            var hangupId = (currentCall && currentCall.id) || payload.call_id;
            var hangupConv = (currentCall && currentCall.conversation_id) || payload.conversation_id;
            currentCall = null;
            await cleanupMediaOnly();
            showCallEnded(reason, peerName, hangupId, payload.ended_by_role);
            refreshCallChat(hangupConv);
            return;
        }

        if (payload.type === 'camera') {
            if (remoteStream) {
                bindRemoteMedia(remoteStream);
            }
            unlockRemotePlayback();
            updateCameraUi();
            scheduleRemoteVideoUiRefresh();
            return;
        }

        if (payload.type === 'answer') {
            if (!sameCall(payload)) return;
            await applyPeerAnswerSdp(payload);
            unlockRemotePlayback();
            updateCameraUi();
            return;
        }

        if (payload.type === 'ice' && payload.candidate) {
            if (!sameCall(payload)) return;
            if (!pc || !pc.remoteDescription) {
                iceQueue.push(payload.candidate);
            } else {
                try { await pc.addIceCandidate(payload.candidate); } catch (e) { /* ignore */ }
            }
        }
    }

    function handleCallRow(payload) {
        var row = (payload && payload.new) || null;
        if (!row) return;
        var isReceiver = isMeOnCallRow(row, true);
        var isCaller = isMeOnCallRow(row, false);
        if (!isReceiver && !isCaller) return;

        if (row.status === 'ringing' && isReceiver) {
            role = 'callee';
            currentCall = Object.assign({}, currentCall || {}, {
                id: row.id,
                conversation_id: row.conversation_id,
                call_type: row.call_type,
                caller_id: row.caller_id,
                caller_type: row.caller_type,
                receiver_id: row.receiver_id,
                receiver_type: row.receiver_type,
                peer: (currentCall && currentCall.peer) || { name: 'Incoming caller' }
            });
            subscribeCallChannel(row.conversation_id);
            showIncoming(currentCall);
            return;
        }

        if (row.status === 'accepted') {
            if (isCaller) {
                role = 'caller';
                if (!currentCall || Number(currentCall.id) !== Number(row.id)) {
                    currentCall = Object.assign({}, currentCall || {}, {
                        id: row.id,
                        conversation_id: row.conversation_id,
                        call_type: row.call_type,
                        caller_id: row.caller_id,
                        caller_type: row.caller_type,
                        receiver_id: row.receiver_id,
                        receiver_type: row.receiver_type
                    });
                }
                applyPeerAnswered();
            }
            return;
        }

        if (['rejected', 'cancelled', 'ended', 'missed'].indexOf(row.status) !== -1) {
            if (currentCall && Number(currentCall.id) === Number(row.id)) {
                var peerName = (currentCall.peer && currentCall.peer.name) || '';
                var rowId = row.id;
                var rowConv = row.conversation_id;
                var endedBy = isCaller
                    ? (row.status === 'cancelled' ? 'caller' : null)
                    : (row.status === 'cancelled' ? 'caller' : (row.status === 'rejected' ? 'callee' : null));
                currentCall = null;
                lastIncomingCallId = null;
                cleanupMediaOnly().then(function () {
                    showCallEnded(row.status, peerName, rowId, endedBy);
                    refreshCallChat(rowConv);
                });
            } else if (isReceiver && els.incoming && !els.incoming.hidden
                && Number(lastIncomingCallId) === Number(row.id)) {
                hideIncoming();
                stopRingtone();
                lastIncomingCallId = null;
                refreshCallChat(row.conversation_id);
            } else {
                refreshCallChat(row.conversation_id);
            }
        }
    }

    function syncIncomingFromServer(conversationId) {
        if (role === 'caller') return;
        if (els.incoming && !els.incoming.hidden) return;
        if (callConnected && currentCall) return;
        var r = routes();
        if (!r.calls || !window.Comms || typeof window.Comms.api !== 'function') return;

        window.clearTimeout(incomingSyncTimer);
        incomingSyncTimer = window.setTimeout(function () {
            window.Comms.api(r.calls, { method: 'GET', dedupe: false }).then(function (data) {
                if (role === 'caller') return;
                if (els.incoming && !els.incoming.hidden) return;
                var me = myIdentity();
                var list = (data && data.calls) || [];
                var ringing = null;
                for (var i = 0; i < list.length; i += 1) {
                    var c = list[i];
                    if (!c || String(c.status) !== 'ringing') continue;
                    if (Number(c.receiver_id) !== me.id) continue;
                    if (c.receiver_type && me.type && !portalTypesMatch(c.receiver_type, me.type)) continue;
                    if (conversationId && Number(c.conversation_id) !== Number(conversationId)) continue;
                    ringing = c;
                    break;
                }
                if (!ringing) {
                    for (var j = 0; j < list.length; j += 1) {
                        var row = list[j];
                        if (!row || String(row.status) !== 'ringing') continue;
                        if (Number(row.receiver_id) !== me.id) continue;
                        if (conversationId && Number(row.conversation_id) !== Number(conversationId)) continue;
                        ringing = row;
                        break;
                    }
                }
                if (ringing) {
                    role = 'callee';
                    showIncoming(ringing);
                    subscribeCallChannel(ringing.conversation_id);
                }
            }).catch(function () { /* ignore */ });
        }, 150);
    }

    function showSecureContextBanner() {
        var root = document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
        if (!root || resolveMediaDevices()) return;
        if (document.querySelector('.comms-secure-banner')) return;

        var banner = document.createElement('div');
        banner.className = 'comms-secure-banner';
        banner.setAttribute('role', 'alert');
        banner.innerHTML = '<strong>Calls need HTTPS.</strong> '
            + 'Open <a href="' + suggestedHttpsUrl() + '">' + suggestedHttpsUrl() + '</a> '
            + '(run <code>npm run serve:lan</code>), accept the certificate once, then voice/video will work.';
        if (root.id === 'commsApp') {
            root.classList.add('has-secure-banner');
            root.insertBefore(banner, root.firstChild);
        } else {
            document.body.appendChild(banner);
        }
    }

    mountCallUiToBody();

    document.addEventListener('click', function (e) {
        var acceptBtn = e.target && e.target.closest('#commsAcceptCall');
        var rejectBtn = e.target && e.target.closest('#commsRejectCall');
        var endBtn = e.target && e.target.closest('#commsEndCall');
        var endedClose = e.target && e.target.closest('#commsCallEndedClose, #commsCallEndedCloseSide');
        var endedRedial = e.target && e.target.closest('#commsCallEndedRedial');
        if (acceptBtn) {
            e.preventDefault();
            acceptIncoming();
            return;
        }
        if (rejectBtn) {
            e.preventDefault();
            rejectIncoming();
            return;
        }
        if (endBtn) {
            e.preventDefault();
            endCall();
            return;
        }
        if (endedRedial) {
            e.preventDefault();
            redialLastCall();
            return;
        }
        if (endedClose) {
            e.preventDefault();
            hideEnded();
        }
    });

    if (els.muteBtn) {
        els.muteBtn.addEventListener('click', function () {
            if (!localStream) return;
            localStream.getAudioTracks().forEach(function (t) {
                t.enabled = !t.enabled;
                setMuteUi(!t.enabled);
            });
        });
    }
    if (els.cameraBtn) {
        els.cameraBtn.hidden = false;
        els.cameraBtn.addEventListener('click', function () {
            if (!localStream || !pc) return;
            setCameraEnabled(!cameraEnabled).catch(function (err) {
                toast((err && err.message) || 'Unable to toggle camera.', 'error');
            });
        });
    }

    showSecureContextBanner();

    window.addEventListener('orientationchange', syncLocalPipLayout);
    window.addEventListener('resize', syncLocalPipLayout);
    if (window.screen && screen.orientation && typeof screen.orientation.addEventListener === 'function') {
        screen.orientation.addEventListener('change', syncLocalPipLayout);
    }

    function releaseCallOnUnload() {
        if (pendingStart) {
            clearPendingStart();
            return;
        }
        if (!currentCall || !currentCall.id) return;
        var callId = currentCall.id;
        var status = callConnected ? 'ended' : (role === 'caller' ? 'cancelled' : 'rejected');
        var r = routes();
        if (!r.callStatus) return;
        var url = window.Comms && typeof window.Comms.route === 'function'
            ? window.Comms.route(r.callStatus, callId)
            : null;
        if (!url) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        var body = JSON.stringify({ status: status });
        try {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body,
                keepalive: true,
                credentials: 'same-origin'
            }).catch(function () {});
        } catch (e) { /* ignore */ }
        currentCall = null;
        callConnected = false;
        role = null;
    }

    window.addEventListener('pagehide', releaseCallOnUnload);
    window.addEventListener('beforeunload', releaseCallOnUnload);

    window.CommsWebRTC = {
        startCall: startCall,
        handleSignal: handleSignal,
        handleCallRow: handleCallRow,
        syncIncomingFromServer: syncIncomingFromServer,
        endCall: endCall,
        canUseMedia: function () {
            try {
                return !!resolveMediaDevices();
            } catch (e) {
                return false;
            }
        },
        suggestedHttpsUrl: suggestedHttpsUrl
    };
})();
