document.addEventListener('DOMContentLoaded', () => {
    const faqRoot = document.getElementById('faq');
    const faqList = document.getElementById('faqList');
    const faqSearch = document.getElementById('faqSearch');

    if (!faqRoot || !faqList) {
        return;
    }

    const fallbackFaqs = [
        {
            question: 'What is SK OnePortal?',
            answer: 'SK OnePortal is the official digital platform of the Municipality of Santa Cruz that connects Kabataan, Sangguniang Kabataan (SK) Officials, SK Federation, and the Local Youth Development Office (LYDO). It provides online access to youth programs, scholarship applications, events, announcements, surveys, profiling, and other SK-related services in one centralized system.',
        },
        {
            question: 'How do I create an account?',
            answer: 'Click the Sign Up button on the homepage and complete the registration form with accurate personal information. Verify your email address if required, then submit the form. Once your registration is approved or verified, you can sign in and access the system.',
        },
        {
            question: 'How do I sign in to my account?',
            answer: 'Click the Sign In button, enter your registered email address and password, then click Login. If you forget your password, use the Forgot Password option to receive a reset link by email.',
        },
        {
            question: 'What is KK Profiling?',
            answer: 'KK Profiling is the official youth profile form for Katipunan ng Kabataan members aged 15–30 in Santa Cruz. After you create a Kabataan account, complete KK Profiling so your barangay SK has an accurate youth record. Approved profile details can also be used when you apply for programs such as scholarships.',
        },
        {
            question: 'What services can I access through SK OnePortal?',
            answer: 'Registered users can complete the KK Profiling Form, apply for scholarship programs, join events and activities, receive announcements, answer surveys, submit required documents, track application status, and access other youth-related services offered by the Municipality of Santa Cruz.',
        },
        {
            question: 'Who can use SK OnePortal?',
            answer: 'SK OnePortal is intended for Kabataan residing in the Municipality of Santa Cruz, SK Officials, SK Federation members, the Local Youth Development Office (LYDO), and other authorized municipal personnel, depending on their assigned roles and permissions.',
        },
    ];

    let sourceFaqs = [];

    try {
        sourceFaqs = JSON.parse(faqRoot.dataset.faqs || '[]');
    } catch {
        sourceFaqs = [];
    }

    const faqsToRender = Array.isArray(sourceFaqs) && sourceFaqs.length
        ? sourceFaqs.map((item) => ({
            question: item.question || '',
            answer: item.answer || '',
        }))
        : fallbackFaqs;

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
        const normalizedQuery = (query || '').trim().toLowerCase();
        const filteredFaqs = faqsToRender.filter((item) => {
            const question = (item.question || '').toLowerCase();
            const answer = (item.answer || '').toLowerCase();
            return !normalizedQuery || question.includes(normalizedQuery) || answer.includes(normalizedQuery);
        });

        faqList.innerHTML = '';

        if (!filteredFaqs.length) {
            const empty = document.createElement('p');
            empty.className = 'faq-empty text-center text-muted py-4 mb-0';
            empty.textContent = 'No matching questions. Try keywords like registration, sign in, or services.';
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
            questionSpan.textContent = item.question;

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
            answerContent.textContent = item.answer;
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
});
