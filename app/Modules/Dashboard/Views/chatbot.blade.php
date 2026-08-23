{{-- SKai Assistant — included in Kabataan header --}}
@php
    $chatUserId = auth()->id() ?? 'guest';
@endphp
<div class="chatbot-nav-wrapper" data-chat-user="{{ $chatUserId }}">
    <button type="button"
            class="kabataan-header__icon-btn kab-ai-assistant-btn chatbot-nav-btn"
            id="chatbotNavBtn"
            title="SKai"
            aria-label="SKai"
            aria-expanded="false"
            onclick="toggleChatbotPopover(event)">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 8V4H8"></path>
            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
            <path d="M2 14h2"></path>
            <path d="M20 14h2"></path>
            <path d="M15 13v2"></path>
            <path d="M9 13v2"></path>
        </svg>
        <span class="kab-ai-glow-effect" aria-hidden="true"></span>
    </button>

    <div class="chatbot-popover" id="chatbotPopover" role="dialog" aria-label="SKai Assistant">
        <div class="cp-inner">
            <div class="cp-header">
                <div class="cp-bot-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 8V4H8"></path>
                        <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                        <path d="M2 14h2"></path>
                        <path d="M20 14h2"></path>
                        <path d="M15 13v2"></path>
                        <path d="M9 13v2"></path>
                    </svg>
                </div>
                <div class="cp-header-info">
                    <div class="cp-header-name">SKai</div>
                    <div class="cp-header-status">
                        <span class="cp-status-dot"></span>
                        Online
                    </div>
                </div>
            </div>

            <div class="cp-topics">
                <button type="button" class="cp-topic-btn" onclick="cpSendTopic('Programs')">Programs</button>
                <button type="button" class="cp-topic-btn" onclick="cpSendTopic('Events')">Events</button>
                <button type="button" class="cp-topic-btn" onclick="cpSendTopic('Scholarship')">Scholarship</button>
                <button type="button" class="cp-topic-btn" onclick="cpSendTopic('How to apply')">How to Apply</button>
                <button type="button" class="cp-topic-btn" onclick="cpSendTopic('Contact SK')">Contact SK</button>
            </div>

            <div class="cp-messages" id="cpMessages">
                <div class="cp-msg bot" id="cpWelcomeMsg">
                    <div class="cp-msg-avatar bot">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 8V4H8"></path>
                            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                            <path d="M2 14h2"></path>
                            <path d="M20 14h2"></path>
                            <path d="M15 13v2"></path>
                            <path d="M9 13v2"></path>
                        </svg>
                    </div>
                    <div class="cp-msg-body">
                        <div class="cp-bubble">Kumusta! Ako si SKai. Paano kita matutulungan ngayon?</div>
                        <span class="cp-msg-time" id="cpWelcomeTime"></span>
                    </div>
                </div>
                <div class="cp-typing" id="cpTyping" style="display:none;">
                    <div class="cp-msg-avatar bot">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 8V4H8"></path>
                            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                            <path d="M2 14h2"></path>
                            <path d="M20 14h2"></path>
                            <path d="M15 13v2"></path>
                            <path d="M9 13v2"></path>
                        </svg>
                    </div>
                    <div class="cp-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>

            <div class="cp-input-area">
                <form class="cp-form" id="cpForm" onsubmit="cpHandleSubmit(event)">
                    <input type="text" class="cp-input" id="cpInput" placeholder="Mag-type ng mensahe..." autocomplete="off" maxlength="300">
                    <button type="submit" class="cp-send-btn" id="skaiSendBtn" aria-label="Send">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
