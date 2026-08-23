/**
 * Programs Module — category modals and application redirects (frontend only)
 */

const PRESURVEY_SLUGS = {
    'anti-drugs': 'anti-drugs',
    agriculture: 'agriculture',
    disaster: 'disaster-preparedness',
    gad: 'gad',
    gender: 'gad',
    health: 'health',
    others: 'others',
};

const PRESURVEY_CHECKBOXES = {
    'anti-drugs': 'agreeTermsAntiDrugs',
    agriculture: 'agreeTermsAgriculture',
    disaster: 'agreeTermsDisaster',
    gad: 'agreeTermsGender',
    gender: 'agreeTermsGender',
    health: 'agreeTermsHealth',
    others: 'agreeTermsOthers',
};

const PRESURVEY_CLOSE_FN = {
    'anti-drugs': 'closeAntiDrugsModal',
    agriculture: 'closeAgricultureModal',
    disaster: 'closeDisasterModal',
    gad: 'closeGenderModal',
    gender: 'closeGenderModal',
    health: 'closeHealthModal',
    others: 'closeOthersModal',
};

window.programsModule = {
    async fetchCategories() {
        return [];
    },

    async fetchByCategory() {
        return null;
    },

    async fetchProgram() {
        return null;
    },

    async openCategoryModal(categoryId) {
        if (categoryId === 'education') {
            const modal = document.getElementById('educationModal');
            if (modal) modal.classList.add('active');
            return;
        }
        if (categoryId === 'sports') {
            if (typeof openSportsModal === 'function') openSportsModal();
            return;
        }
        const openers = {
            'anti-drugs': 'openAntiDrugsModal',
            agriculture: 'openAgricultureModal',
            disaster: 'openDisasterModal',
            gad: 'openGenderModal',
            gender: 'openGenderModal',
            health: 'openHealthModal',
            others: 'openOthersModal',
        };
        const fnName = openers[categoryId];
        if (fnName && typeof window[fnName] === 'function') {
            window[fnName]();
            return;
        }
        this.showNoProgramModal(categoryId);
    },

    showNoProgramModal(categoryId) {
        const modal = document.getElementById('noProgramModal');
        const modalTitle = document.getElementById('noProgramModalTitle');
        if (!modal || !modalTitle) return;

        const categoryElement = document.querySelector(`[data-category="${categoryId}"]`);
        const categoryName = categoryElement?.querySelector('.category-content h3')?.textContent || 'Programs';
        modalTitle.textContent = categoryName;
        modal.classList.add('active');
    },

    openApplicationForm(programType) {
        if (programType === 'sports') {
            window.location.href = '/sports/apply';
            return;
        }
        window.location.href = '/scholarship/apply';
    },
};

/**
 * Redirect to program survey landing (no terms checkbox required when survey is open).
 */
window.goToPreSurvey = function (programKey) {
    const categoryMap = {
        'anti-drugs': 'anti-drugs',
        agriculture: 'agriculture',
        disaster: 'disaster',
        gad: 'gender',
        gender: 'gender',
        health: 'health',
        others: 'others',
        environment: 'environment',
        feeding: 'others',
    };

    const categoryKey = categoryMap[programKey] || programKey;
    const programs = window.kabataanPrograms?.getData?.()?.abyip_programs || [];
    const program = programs.find((item) => item.category_key === categoryKey || item.modal_key === categoryKey);

    if (program && typeof window.goToProgramSurvey === 'function') {
        window.goToProgramSurvey(program.id);
        return;
    }

    setTimeout(() => {
        window.location.href = '/programs/survey';
    }, 650);
};

window.goToScholarshipApplication = function () {
    const checkbox = document.getElementById('agreeTerms');
    if (!checkbox?.checked) return;
    if (typeof closeEducationModal === 'function') closeEducationModal();
    setTimeout(() => {
        window.location.href = typeof window.scholarshipApplyUrl !== 'undefined'
            ? window.scholarshipApplyUrl
            : '/scholarship/apply';
    }, 650);
};

window.goToSportsApplication = function () {
    if (typeof closeSportsModal === 'function') closeSportsModal();
    if (typeof openSportsModal === 'function') openSportsModal();
};
