document.addEventListener('DOMContentLoaded', () => {
    const faqRoot = document.getElementById('faq');
    const faqList = document.getElementById('faqList');
    const faqSearch = document.getElementById('faqSearch');
    const faqLangButtons = document.querySelectorAll('[data-faq-lang]');

    if (!faqRoot || !faqList) {
        return;
    }

    const fallbackFaqs = [
        {
            question: 'What is SK OnePortal?',
            questionTagalog: 'Ano ang SK OnePortal?',
            answer: 'SK OnePortal is the official digital platform of the Municipality of Santa Cruz that connects Kabataan, Sangguniang Kabataan (SK) Officials, SK Federation, and the Local Youth Development Office (LYDO). It provides online access to youth programs, scholarship applications, events, announcements, surveys, profiling, and other SK-related services in one centralized system.',
            answerTagalog: 'Ang SK OnePortal ay ang opisyal na digital platform ng Munisipyo ng Santa Cruz na nagkokonekta sa Kabataan, mga Opisyal ng Sangguniang Kabataan (SK), SK Federation, at ang Local Youth Development Office (LYDO). Nagbibigay ito ng online access sa mga programa para sa kabataan, aplikasyon ng scholarship, mga gawain, anunsyo, survey, profiling, at iba pang serbisyong kaugnay ng SK sa iisang sentralisadong sistema.',
        },
        {
            question: 'How do I create an account?',
            questionTagalog: 'Paano ako makapag-create ng account?',
            answer: 'Click the Sign Up button on the homepage and complete the registration form with accurate personal information. Verify your email address if required, then submit the form. Once your registration is approved or verified, you can sign in and access the system.',
            answerTagalog: 'Pindutin ang Sign Up na button sa homepage at kumpletuhin ang registration form gamit ang iyong tumpak na personal na impormasyon. Beripikahin ang iyong email address kung kailangan, pagkatapos ay ipadala ang form. Kapag naaprubahan o naberipika na ang iyong pagpaparehistro, maaari ka nang mag-sign in at mag-access ng sistema.',
        },
        {
            question: 'How do I sign in to my account?',
            questionTagalog: 'Paano ako mag-sign in sa aking account?',
            answer: 'Click the Sign In button, enter your registered email address and password, then click Login. If you forget your password, use the Forgot Password option to receive a reset link by email.',
            answerTagalog: 'Pindutin ang Sign In na button, ilagay ang iyong nakarehistrong email address at password, pagkatapos ay pindutin ang Login. Kung nakalimutan mo ang iyong password, gamitin ang Forgot Password na option upang makatanggap ng link sa pag-reset sa email.',
        },
        {
            question: 'What is KK Profiling?',
            questionTagalog: 'Ano ang KK Profiling?',
            answer: 'KK Profiling is the official youth profile form for Katipunan ng Kabataan members aged 15–30 in Santa Cruz. After you create a Kabataan account, complete KK Profiling so your barangay SK has an accurate youth record. Approved profile details can also be used when you apply for programs such as scholarships.',
            answerTagalog: 'Ang KK Profiling ay ang opisyal na youth profile form para sa mga Katipunan ng Kabataan na 15–30 taong gulang sa Santa Cruz. Pagkatapos mong gawin ang iyong Kabataan account, kumpletuhin ang KK Profiling para mayroon ang iyong barangay SK ng tumpak na tala ng kabataan. Maaari ring gamitin ang naaprubahan mong profile kapag nag-aapply ka sa mga programa tulad ng scholarship.',
        },
        {
            question: 'What services can I access through SK OnePortal?',
            questionTagalog: 'Anong mga serbisyo ang maaari kong i-access sa SK OnePortal?',
            answer: 'Registered users can complete KK Profiling, discover available SK programs and activities, register for supported programs, submit supported requirements online when a program allows it, receive announcements, answer surveys, and access other youth-related services offered through SK OnePortal. Online registration and document submission depend on the specific program. Anyone can also view public Barangay ABYIP documents and program accomplishments without signing in.',
            answerTagalog: 'Ang mga nakarehistrong gumagamit ay maaaring kumpletuhin ang KK Profiling, makita ang mga available na SK program at aktibidad, mag-register para sa mga suportadong programa, mag-submit ng mga suportadong requirements online kapag pinapayagan ng programa, tumanggap ng anunsyo, sumagot ng survey, at mag-access ng iba pang serbisyong kaugnay ng kabataan na inaalok ng SK OnePortal. Nakadepende sa tiyak na programa ang availability ng online registration at document submission. Makikita rin ng sinuman ang mga dokumento ng Barangay ABYIP at program accomplishments nang hindi nag-sign in.',
        },
        {
            question: 'Who can use SK OnePortal?',
            questionTagalog: 'Sino ang maaaring gumamit ng SK OnePortal?',
            answer: 'SK OnePortal is intended for Kabataan residing in the Municipality of Santa Cruz, SK Officials, SK Federation members, the Local Youth Development Office (LYDO), and other authorized municipal personnel, depending on their assigned roles and permissions.',
            answerTagalog: 'Ang SK OnePortal ay para sa mga Kabataan na naninirahan sa Munisipyo ng Santa Cruz, mga Opisyal ng SK, mga miyembro ng SK Federation, ang Local Youth Development Office (LYDO), at iba pang awtorisadong tauhan ng munisipyo, depende sa kanilang tungkulin at mga pahintulot.',
        },
    ];

    let sourceFaqs = [];

    try {
        sourceFaqs = JSON.parse(faqRoot.dataset.faqs || '[]');
    } catch {
        sourceFaqs = [];
    }

    const faqsToRender = (Array.isArray(sourceFaqs) && sourceFaqs.length
        ? sourceFaqs
        : fallbackFaqs
    ).map((item) => ({
        question: item.question || '',
        questionTagalog: item.questionTagalog || item.question || '',
        answer: item.answer || '',
        answerTagalog: item.answerTagalog || item.answer || '',
    }));

    const currentLang = () => (faqRoot.dataset.faqLanguage === 'tl' ? 'tl' : 'en');

    const applyLanguage = (lang) => {
        faqRoot.dataset.faqLanguage = lang;
        faqLangButtons.forEach((button) => {
            const isActive = button.dataset.faqLang === lang;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const closeFaqItem = (article, toggle, answer) => {
        toggle.setAttribute('aria-expanded', 'false');
        article.classList.remove('is-open');
        answer.classList.remove('is-open');
    };

    const openFaqItem = (article, toggle, answer) => {
        toggle.setAttribute('aria-expanded', 'true');
        article.classList.add('is-open');
        answer.classList.add('is-open');
    };

    const renderFaqs = (query) => {
        const lang = currentLang();
        const normalizedQuery = (query || '').trim().toLowerCase();
        const filteredFaqs = faqsToRender.filter((item) => {
            const question = (item.question || '').toLowerCase();
            const questionTl = (item.questionTagalog || '').toLowerCase();
            const answer = (item.answer || '').toLowerCase();
            const answerTl = (item.answerTagalog || '').toLowerCase();
            return !normalizedQuery
                || question.includes(normalizedQuery)
                || questionTl.includes(normalizedQuery)
                || answer.includes(normalizedQuery)
                || answerTl.includes(normalizedQuery);
        });

        faqList.innerHTML = '';

        if (!filteredFaqs.length) {
            const empty = document.createElement('p');
            empty.className = 'faq-empty text-center text-muted py-4 mb-0';
            empty.textContent = lang === 'tl'
                ? 'Walang nakitang tanong. Subukan ang mga keyword tulad ng registration, sign in, o KK Profiling.'
                : 'No matching questions. Try keywords like registration, sign in, or KK Profiling.';
            faqList.appendChild(empty);
            return;
        }

        filteredFaqs.forEach((item, index) => {
            const article = document.createElement('article');
            article.className = 'faq-item';

            const answerId = `faq-answer-${index}`;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'faq-toggle';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', answerId);

            const questionSpan = document.createElement('span');
            questionSpan.className = 'faq-question-text';
            questionSpan.textContent = lang === 'tl' ? item.questionTagalog : item.question;

            const chevron = document.createElement('span');
            chevron.className = 'faq-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            chevron.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';

            toggle.appendChild(questionSpan);
            toggle.appendChild(chevron);

            const answer = document.createElement('div');
            answer.className = 'faq-answer';
            answer.id = answerId;

            const answerInner = document.createElement('div');
            answerInner.className = 'faq-answer-inner';

            const answerContent = document.createElement('div');
            answerContent.className = 'faq-answer-content';
            answerContent.textContent = lang === 'tl' ? item.answerTagalog : item.answer;

            if (lang === 'tl') {
                answerContent.classList.add('is-tagalog');
            }

            answerInner.appendChild(answerContent);
            answer.appendChild(answerInner);

            toggle.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') === 'true';

                faqList.querySelectorAll('.faq-item.is-open').forEach((openItem) => {
                    if (openItem === article) {
                        return;
                    }

                    const openToggle = openItem.querySelector('.faq-toggle');
                    const openAnswer = openItem.querySelector('.faq-answer');
                    if (openToggle && openAnswer) {
                        closeFaqItem(openItem, openToggle, openAnswer);
                    }
                });

                if (expanded) {
                    closeFaqItem(article, toggle, answer);
                } else {
                    openFaqItem(article, toggle, answer);
                }
            });

            article.appendChild(toggle);
            article.appendChild(answer);
            faqList.appendChild(article);
        });
    };

    renderFaqs('');
    faqSearch?.addEventListener('input', () => renderFaqs(faqSearch.value));

    faqLangButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const lang = button.dataset.faqLang === 'tl' ? 'tl' : 'en';
            if (lang === currentLang()) {
                return;
            }
            applyLanguage(lang);
            renderFaqs(faqSearch?.value || '');
        });
    });
});
