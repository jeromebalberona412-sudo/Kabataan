/* =============================================
   SK OnePortal — Kabataan SKai Assistant
   ============================================= */

const CP_BOT_ICON_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>`;

const CP_PROFANITY = [
    'putang', 'puta', 'tangina', 'gago', 'gagu', 'bobo', 'tarantado', 'ulol', 'hayop',
    'leche', 'punyeta', 'bwisit', 'tanga', 'salot', 'kantot', 'jakol', 'bayag', 'bilat',
    'shit', 'fuck', 'fucking', 'bitch', 'asshole', 'bastard', 'damn', 'crap', 'dick',
    'pussy', 'whore', 'slut', 'nigger', 'nigga', 'motherfucker', 'bullshit',
];

const CP_PROFANITY_REPLY = "Sorry, I don't understand. Please keep our conversation respectful.";

const CP_REPLIES = [
    {
        keys: ['program', 'programa', 'programs'],
        reply: 'Ang SK Santa Cruz ay may mga programa sa Education, Sports, Health, Anti-Drugs, Agriculture, Disaster Preparedness, at Gender & Development. Alin ang gusto mong malaman?'
    },
    {
        keys: ['scholarship', 'iskolarship', 'scholar', 'tuition', 'education', 'edukasyon'],
        reply: 'Ang Scholarship Assistance Program ay bukas na! Deadline: March 31, 2026. Pumunta sa Programs sidebar at i-click ang Education para mag-apply.'
    },
    {
        keys: ['event', 'events', 'aktibidad', 'activity', 'activities'],
        reply: 'Mga paparating na events:\n• Community Clean-Up Drive — March 15, 2026\n• Youth Leadership Summit — March 20, 2026\nAbangan ang mga announcements sa feed!'
    },
    {
        keys: ['apply', 'mag-apply', 'application', 'pano', 'paano', 'how'],
        reply: 'Para mag-apply sa isang programa:\n1. Pumunta sa Programs sidebar\n2. I-click ang kategorya\n3. Basahin ang detalye\n4. I-click ang "Apply Now"\n\nKailangan mo ng valid documents para sa application.'
    },
    {
        keys: ['contact', 'kontak', 'sk', 'tanggapan', 'office', 'numero', 'number'],
        reply: 'SK Santa Cruz, Laguna\nMunicipal Hall, Santa Cruz, Laguna\nLunes–Biyernes, 8AM–5PM\n\nMaaari ka ring mag-message sa aming official Facebook page.'
    },
    {
        keys: ['sports', 'palakasan', 'basketball', 'volleyball', 'sports development'],
        reply: 'Ang Sports Development program ay naglalayong palakasin ang kabataan sa pamamagitan ng iba\'t ibang palakasan. Abangan ang mga susunod na aktibidad!'
    },
    {
        keys: ['health', 'kalusugan', 'medical', 'check-up', 'checkup'],
        reply: 'Ang Health program ay nagbibigay ng libreng medical check-up at health seminars para sa kabataan. Walang aktibong programa ngayon — abangan ang mga updates!'
    },
    {
        keys: ['anti-drug', 'anti drug', 'droga', 'drugs', 'drug'],
        reply: 'Ang Anti-Drugs program ay naglalayong turuan ang kabataan tungkol sa mga panganib ng droga. Walang aktibong programa ngayon — abangan ang mga updates!'
    },
    {
        keys: ['hello', 'hi', 'kumusta', 'hey', 'uy', 'helo', 'magandang'],
        reply: 'Kumusta! Ako si SKai. Maaari akong tumulong sa impormasyon tungkol sa mga programa, events, at serbisyo ng SK Santa Cruz. Ano ang gusto mong malaman?'
    },
    {
        keys: ['salamat', 'thank', 'thanks', 'maraming salamat'],
        reply: 'Walang anuman! Kung may iba ka pang katanungan, nandito lang ako. Mabuhay ang kabataan ng Santa Cruz!'
    },
    {
        keys: ['register', 'rehistro', 'sign up', 'signup', 'account'],
        reply: 'Para gumawa ng account sa SK OnePortal, pumunta sa login page at i-click ang "Sign Up". Kailangan mo ng valid email address at personal information.'
    },
    {
        keys: ['profile', 'account', 'impormasyon', 'information', 'edit'],
        reply: 'Maaari mong i-edit ang iyong profile sa pamamagitan ng pag-click sa iyong avatar sa kanang bahagi ng navigation bar, tapos piliin ang "My Profile".'
    },
];

const CP_DEFAULT = 'Pasensya na, hindi ko naintindihan ang iyong tanong. Subukan mong i-click ang isa sa mga quick topics sa itaas, o magtanong tungkol sa mga programa, events, o serbisyo ng SK.';

let cpStoredMessages = [];

function cpStorageKey() {
    const wrapper = document.querySelector('.chatbot-nav-wrapper');
    const userId = wrapper?.dataset?.chatUser || 'guest';
    return `sk_kabataan_skai_chat_${userId}`;
}

function cpEscapeRegex(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function cpContainsProfanity(text) {
    const lower = text.toLowerCase();
    return CP_PROFANITY.some((word) => {
        const regex = new RegExp(`\\b${cpEscapeRegex(word)}\\b`, 'i');
        return regex.test(lower) || lower.includes(word);
    });
}

function cpCensorText(text) {
    let result = text;
    CP_PROFANITY.forEach((word) => {
        const regex = new RegExp(cpEscapeRegex(word), 'gi');
        result = result.replace(regex, (match) => '*'.repeat(match.length));
    });
    return result;
}

function cpGetTime() {
    const now = new Date();
    return now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

function cpGetReply(text) {
    if (cpContainsProfanity(text)) {
        return CP_PROFANITY_REPLY;
    }

    const lower = text.toLowerCase();
    for (const item of CP_REPLIES) {
        if (item.keys.some((key) => lower.includes(key))) {
            return item.reply;
        }
    }

    return CP_DEFAULT;
}

function cpScrollBottom() {
    const msgs = document.getElementById('cpMessages');
    if (msgs) {
        msgs.scrollTop = msgs.scrollHeight;
    }
}

function cpSaveMessages() {
    try {
        localStorage.setItem(cpStorageKey(), JSON.stringify(cpStoredMessages));
    } catch (error) {
        console.warn('SKai chat storage unavailable:', error);
    }
}

function cpLoadMessages() {
    try {
        const raw = localStorage.getItem(cpStorageKey());
        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.warn('SKai chat storage read failed:', error);
        return [];
    }
}

function cpRenderStoredMessages() {
    const msgs = document.getElementById('cpMessages');
    const typing = document.getElementById('cpTyping');
    const welcome = document.getElementById('cpWelcomeMsg');
    if (!msgs || !typing) {
        return;
    }

    cpStoredMessages = cpLoadMessages();
    if (!cpStoredMessages.length) {
        return;
    }

    if (welcome) {
        welcome.remove();
    }

    msgs.querySelectorAll('.cp-msg').forEach((node) => node.remove());

    cpStoredMessages.forEach((message) => {
        cpAppendMessage(message.text, message.sender, message.time, false);
    });
}

function cpAppendMessage(text, sender, timeValue = null, persist = true) {
    const msgs = document.getElementById('cpMessages');
    const typing = document.getElementById('cpTyping');
    if (!msgs) {
        return;
    }

    const displayText = sender === 'user' ? cpCensorText(text) : text;
    const time = timeValue || cpGetTime();

    const row = document.createElement('div');
    row.className = `cp-msg ${sender}`;

    const avatarDiv = document.createElement('div');
    avatarDiv.className = `cp-msg-avatar ${sender}`;

    if (sender === 'bot') {
        avatarDiv.innerHTML = CP_BOT_ICON_SVG;
    } else {
        const img = document.createElement('img');
        img.src = document.querySelector('.kabataan-header__avatar-btn img')?.src
            || document.querySelector('.user-avatar-btn img')?.src
            || 'https://ui-avatars.com/api/?name=User&background=667eea&color=fff';
        img.alt = 'You';
        avatarDiv.appendChild(img);
    }

    const bodyDiv = document.createElement('div');
    bodyDiv.className = 'cp-msg-body';

    const bubble = document.createElement('div');
    bubble.className = 'cp-bubble';
    bubble.style.whiteSpace = 'pre-line';
    bubble.textContent = displayText;

    const timeSpan = document.createElement('span');
    timeSpan.className = 'cp-msg-time';
    timeSpan.textContent = time;

    bodyDiv.appendChild(bubble);
    bodyDiv.appendChild(timeSpan);
    row.appendChild(avatarDiv);
    row.appendChild(bodyDiv);

    msgs.insertBefore(row, typing);
    cpScrollBottom();

    if (persist) {
        cpStoredMessages.push({ text: displayText, sender, time });
        cpSaveMessages();
    }
}

function cpAlignArrow() {
    const btn = document.getElementById('chatbotNavBtn');
    const popover = document.getElementById('chatbotPopover');
    if (!btn || !popover) {
        return;
    }

    const btnRect = btn.getBoundingClientRect();
    const btnCenter = btnRect.left + btnRect.width / 2;

    let popRight;
    if (window.innerWidth <= 768) {
        popRight = window.innerWidth - 8;
    } else {
        popRight = popover.getBoundingClientRect().right;
    }

    const arrowRight = Math.max(10, Math.round(popRight - btnCenter - 8));
    popover.style.setProperty('--cp-arrow-right', `${arrowRight}px`);
}

window.toggleChatbotPopover = function toggleChatbotPopover(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const popover = document.getElementById('chatbotPopover');
    const btn = document.getElementById('chatbotNavBtn');
    if (!popover || !btn) {
        return;
    }

    const isOpen = popover.classList.contains('open');
    if (isOpen) {
        closeChatbotPopover();
        return;
    }

    if (typeof window.kabataanCloseHeaderOverlays === 'function') {
        window.kabataanCloseHeaderOverlays('chatbot');
    } else {
        document.getElementById('kabataanHeaderUser')?.classList.remove('is-open');
        document.querySelector('.kabataan-header__avatar-btn')?.setAttribute('aria-expanded', 'false');
        document.getElementById('notifPopover')?.classList.remove('open');
        document.getElementById('notifNavBtn')?.setAttribute('aria-expanded', 'false');
    }

    popover.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
    cpAlignArrow();
    document.getElementById('cpInput')?.focus();
};

window.closeChatbotPopover = function closeChatbotPopover() {
    document.getElementById('chatbotPopover')?.classList.remove('open');
    document.getElementById('chatbotNavBtn')?.setAttribute('aria-expanded', 'false');
};

function cpHideTopics() {
    const topics = document.querySelector('#chatbotPopover .cp-topics');
    if (topics) {
        topics.hidden = true;
    }
}

window.cpHandleSubmit = function cpHandleSubmit(event) {
    event.preventDefault();

    const input = document.getElementById('cpInput');
    if (!input) {
        return;
    }

    const rawText = input.value.trim();
    if (!rawText) {
        return;
    }

    cpHideTopics();

    const censoredText = cpCensorText(rawText);
    const hasProfanity = cpContainsProfanity(rawText);

    cpAppendMessage(censoredText, 'user');
    input.value = '';

    const typing = document.getElementById('cpTyping');
    if (typing) {
        typing.style.display = 'flex';
    }
    cpScrollBottom();

    setTimeout(() => {
        if (typing) {
            typing.style.display = 'none';
        }

        const reply = hasProfanity ? CP_PROFANITY_REPLY : cpGetReply(rawText);
        cpAppendMessage(reply, 'bot');
    }, 900 + Math.random() * 400);
};

window.cpSendTopic = function cpSendTopic(topic) {
    const input = document.getElementById('cpInput');
    if (!input) {
        return;
    }

    input.value = topic;
    cpHandleSubmit(new Event('submit'));
};

document.addEventListener('click', function (event) {
    const popover = document.getElementById('chatbotPopover');
    const btn = document.getElementById('chatbotNavBtn');
    if (!popover || !btn) {
        return;
    }

    if (popover.classList.contains('open') && !popover.contains(event.target) && !btn.contains(event.target)) {
        closeChatbotPopover();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const welcomeTime = document.getElementById('cpWelcomeTime');
    if (welcomeTime) {
        welcomeTime.textContent = cpGetTime();
    }

    cpRenderStoredMessages();
    if (cpStoredMessages.length) {
        cpHideTopics();
    }

    const input = document.getElementById('cpInput');
    if (!input) {
        return;
    }

    input.addEventListener('input', function () {
        const censored = cpCensorText(this.value);
        if (censored !== this.value) {
            const cursor = this.selectionStart;
            this.value = censored;
            this.setSelectionRange(censored.length, censored.length);
            if (typeof cursor === 'number') {
                this.setSelectionRange(Math.min(cursor, censored.length), Math.min(cursor, censored.length));
            }
        }
    });
});
