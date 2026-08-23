/**
 * Scholarship Application Preview — full submitted application layout
 */
(function (global) {
    'use strict';

    const STEPS = [
        { id: 'personal', label: 'Personal', sectionId: 'schPreviewPersonal' },
        { id: 'education', label: 'Education', sectionId: 'schPreviewEducation' },
        { id: 'background', label: 'Background', sectionId: 'schPreviewBackground' },
        { id: 'additional', label: 'Additional', sectionId: 'schPreviewAdditional' },
        { id: 'requirements', label: 'Requirements', sectionId: 'schPreviewRequirements' },
        { id: 'review', label: 'Review', sectionId: null },
        { id: 'confirm', label: 'Confirm', sectionId: null },
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatRespondentDisplay(application) {
        if (application?.respondent_display) {
            return String(application.respondent_display);
        }

        const respondentNumber = application?.respondent_number;
        if (respondentNumber) {
            const match = String(respondentNumber).match(/(\d+)$/);
            if (match) {
                const n = (parseInt(match[1], 10) % 100) || 1;
                return String(n).padStart(2, '0');
            }
        }

        return '—';
    }

    function formatCurrency(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (!digits) return '—';
        return global.ScholarshipSystemFields?.formatCurrencyDisplay(digits)
            || `₱${Number(digits).toLocaleString('en-PH')}`;
    }

    function personalMap(personalInfo) {
        const map = {};
        (personalInfo || []).forEach((item) => {
            if (item?.label) map[item.label] = item.value;
        });
        return map;
    }

    function getInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function fieldHtml(label, value, colSpan) {
        const raw = String(value ?? '').trim();
        const val = raw ? (global.ScholarshipSystemFields?.toUpperAnswer?.(raw) || raw.toUpperCase()) : '—';
        return `
            <div class="sch-preview-field" ${colSpan ? `style="grid-column: span ${colSpan}"` : ''}>
                <label>${escapeHtml(label)}</label>
                <span>${escapeHtml(val)}</span>
            </div>`;
    }

    function sectionCard(title, bodyHtml, sectionId) {
        return `
            <article class="sch-preview-section-card" ${sectionId ? `id="${sectionId}"` : ''}>
                <div class="sch-preview-section-head">
                    <h2>${escapeHtml(title)}</h2>
                    <span class="sch-preview-section-badge">Completed</span>
                </div>
                <div class="sch-preview-section-body">${bodyHtml}</div>
            </article>`;
    }

    function getSystemLabel(fieldId) {
        const field = global.ScholarshipSystemFields?.getAllFields?.().find((f) => f.id === fieldId);
        return field?.label || fieldId.replace(/_/g, ' ');
    }

    function renderPersonalSection(personalInfo, kkLabels) {
        const byLabel = personalMap(personalInfo);
        const fullName = byLabel['Full Name']
            || [byLabel['First Name'], byLabel['Middle Name'], byLabel['Last Name'], byLabel['Suffix']]
                .filter((v) => v && v !== '—').join(' ');

        const fields = [
            ['First Name', byLabel['First Name']],
            ['Middle Name', byLabel['Middle Name']],
            ['Last Name', byLabel['Last Name']],
            ['Suffix', byLabel['Suffix']],
            ['Birth Date', byLabel['Birthday'] || byLabel['Birth Date']],
            ['Birth Place', byLabel['Birth Place']],
            ['Age', byLabel['Age']],
            ['Sex', byLabel['Sex']],
            ['Civil Status', byLabel['Civil Status']],
            ['Religion', byLabel['Religion']],
            ['Contact Number', byLabel['Contact Number']],
            ['Email Address', byLabel['Email Address']],
            ['Purok / Zone', byLabel['Purok / Zone']],
            ['Province', byLabel['Province']],
            ['City/Municipality', byLabel['City/Municipality'] || byLabel['City']],
            ['Barangay', byLabel['Barangay']],
        ];

        const extra = (personalInfo || [])
            .filter((item) => !fields.some(([label]) => label === item.label))
            .filter((item) => !['Program Name', 'Application Period', 'Application Date', 'Status', 'Committee'].includes(item.label));

        const grid = fields
            .filter(([, value]) => value && String(value).trim() !== '' && value !== '—')
            .map(([label, value]) => fieldHtml(label, value))
            .join('');

        const extraGrid = extra.map((item) => fieldHtml(item.label, item.value)).join('');

        return sectionCard('Personal Information', `
            ${fullName ? `<p style="font-size:15px;font-weight:700;color:#111827;margin:0 0 16px;">${escapeHtml(fullName)}</p>` : ''}
            <div class="sch-preview-grid">${grid || '<p class="sch-preview-empty-section">No personal information recorded.</p>'}${extraGrid}</div>
            <div class="sch-preview-note">These information are automatically taken from your approved KK Profiling.</div>
        `, 'schPreviewPersonal');
    }

    function renderEducationSection(systemFields, kkEducation) {
        const SF = global.ScholarshipSystemFields;
        const edu = kkEducation || systemFields._kk_education || '';
        const blocks = [];

        (SF?.SCHOOL_BLOCKS || []).forEach((block) => {
            if (!SF?.isSchoolBlockVisible?.(block.id, edu)) {
                return;
            }

            const prefix = block.id === 'senior_high' ? 'senior_high' : block.id;
            const schoolLabel = block.id === 'elementary'
                ? 'Elementary School'
                : (block.id === 'secondary' ? 'Secondary School' : 'Senior High School');

            blocks.push(`
                <div class="sch-preview-subsection">
                    <h3>${escapeHtml(block.title)}</h3>
                    <div class="sch-preview-grid sch-preview-grid-2">
                        ${fieldHtml(schoolLabel, systemFields[`${prefix}_school`])}
                        ${fieldHtml('Address', systemFields[`${prefix}_address`])}
                        ${fieldHtml('Year Graduated', systemFields[`${prefix}_year_graduated`])}
                    </div>
                </div>`);
        });

        if (!blocks.length) {
            blocks.push('<p class="sch-preview-empty-section">No educational background entries for your profile level.</p>');
        }

        return sectionCard('Educational Background', blocks.join(''), 'schPreviewEducation');
    }

    function renderBackgroundSection(systemFields) {
        const SF = global.ScholarshipSystemFields;
        const groups = [
            { title: 'Mother', prefix: 'mother' },
            { title: 'Father', prefix: 'father' },
            { title: 'Guardian', prefix: 'guardian' },
        ];

        const groupHtml = groups.map(({ title, prefix }) => {
            const firstName = systemFields[`${prefix}_first_name`];
            const lastName = systemFields[`${prefix}_last_name`];
            const occupation = SF?.resolveOccupationFromValues
                ? SF.resolveOccupationFromValues(prefix, systemFields)
                : systemFields[`${prefix}_occupation`];

            if (!firstName && !lastName && !occupation && !systemFields[`${prefix}_contact_number`]) {
                return '';
            }

            return `
                <div class="sch-preview-subsection">
                    <h3>${title}</h3>
                    <div class="sch-preview-grid">
                        ${fieldHtml('First Name', firstName)}
                        ${fieldHtml('Last Name', lastName)}
                        ${prefix === 'guardian' ? fieldHtml('Relation', systemFields.guardian_relation) : ''}
                        ${fieldHtml('Occupation', occupation)}
                        ${fieldHtml('Contact No.', systemFields[`${prefix}_contact_number`])}
                    </div>
                </div>`;
        }).join('');

        const income = systemFields.annual_family_gross_income || '—';

        return sectionCard('Background Information', `
            ${groupHtml || '<p class="sch-preview-empty-section">No family background recorded.</p>'}
            <div class="sch-preview-subsection" style="margin-top:16px;">
                <div class="sch-preview-grid">
                    ${fieldHtml('Family Monthly Income', income, 3)}
                </div>
            </div>
        `, 'schPreviewBackground');
    }

    function renderAdditionalSection(systemFields, kkEducation) {
        const edu = kkEducation || '';
        const SF = global.ScholarshipSystemFields;
        const fieldIds = [
            'strand', 'strand_abbreviation', 'year_level', 'units_enrolled',
            'expected_graduation_year', 'graduating', 'semester_of_graduation',
            'school_name', 'school_abbreviation', 'school_address',
            'receiving_gov_aid', 'gov_aid_program_name', 'family_on_scholarship',
        ];

        const visibleFields = fieldIds.filter((id) => {
            const field = SF?.getAllFields?.().find((f) => f.id === id);
            if (!field) return Boolean(systemFields[id]);
            return SF.isFieldVisible(field, systemFields, edu);
        });

        if (!visibleFields.length) {
            return sectionCard('Additional Information',
                '<p class="sch-preview-empty-section">No additional information required for your education level.</p>',
                'schPreviewAdditional');
        }

        const grid = visibleFields.map((id) => {
            const value = id === 'annual_family_gross_income'
                ? formatCurrency(systemFields[id])
                : systemFields[id];
            const label = id === 'strand'
                ? (global.ScholarshipSystemFields?.getFieldDisplayLabel?.(
                    global.ScholarshipSystemFields.getAllFields().find((f) => f.id === id),
                    edu,
                ) || getSystemLabel(id))
                : getSystemLabel(id);
            return fieldHtml(label, value);
        }).join('');

        return sectionCard('Additional Information', `<div class="sch-preview-grid">${grid}</div>`, 'schPreviewAdditional');
    }

    function renderRequirementsSection(answers) {
        const files = (answers || []).filter((a) => a.question_type === 'file' && a.answer);

        if (!files.length) {
            return sectionCard('Requirements',
                '<p class="sch-preview-empty-section">No uploaded requirements found.</p>',
                'schPreviewRequirements');
        }

        const list = files.map((answer) => {
            const file = answer.answer;
            const url = file.preview_url || file.download_url || '#';
            return `
                <a class="sch-preview-file" href="${escapeHtml(url)}" target="_blank" rel="noopener">
                    <span class="sch-preview-file-icon">PDF</span>
                    <span>
                        <div class="sch-preview-file-name">${escapeHtml(answer.question_label || file.original_name || 'Document')}</div>
                        <div class="sch-preview-file-meta">${escapeHtml(file.original_name || '')}${file.size_display ? ` · ${escapeHtml(file.size_display)}` : ''}</div>
                    </span>
                </a>`;
        }).join('');

        return sectionCard('Requirements', `<div class="sch-preview-files">${list}</div>`, 'schPreviewRequirements');
    }

    function renderNav(activeSection) {
        return STEPS.filter((s) => s.sectionId).map((step, index) => `
            <li class="sch-preview-nav-item ${activeSection === step.sectionId ? 'is-active' : ''}">
                <button type="button" data-scroll-to="${step.sectionId}">
                    <span class="sch-preview-nav-num">${index + 1}</span>
                    <span>${escapeHtml(step.label === 'Education' ? 'Educational Background' : step.label === 'Background' ? 'Background Information' : step.label === 'Additional' ? 'Additional Information' : step.label === 'Requirements' ? 'Uploading of Requirements' : 'Personal Information')}</span>
                </button>
            </li>`).join('');
    }

    function getKkEducation(personalInfo, systemFields) {
        const map = personalMap(personalInfo);
        return map['Educational Attainment'] || map['Education'] || systemFields.education || '';
    }

    function render(application, program) {
        const shell = document.getElementById('scholarshipPreviewShell');
        if (!shell) return;

        const personalInfo = application.personal_info || [];
        const systemFields = application.system_field_answers || {};
        const answers = application.answers || [];
        const personalMapData = personalMap(personalInfo);
        const fullName = personalMapData['Full Name']
            || [personalMapData['First Name'], personalMapData['Middle Name'], personalMapData['Last Name'], personalMapData['Suffix']]
                .map((part) => String(part || '').trim())
                .filter((part) => part && !['none', 'n/a', 'na', 'null', '-', '—'].includes(part.toLowerCase()))
                .join(' ');
        const profileImageUrl = String(program?.profile_image_url || '').trim();
        const avatarHtml = profileImageUrl
            ? `<img src="${escapeHtml(profileImageUrl)}" alt="${escapeHtml(fullName || 'Applicant')}" class="sch-preview-avatar sch-preview-avatar-img">`
            : `<div class="sch-preview-avatar">${escapeHtml(getInitials(fullName))}</div>`;
        const kkEducation = getKkEducation(personalInfo, systemFields);
        const respondentNo = formatRespondentDisplay(application);
        const statusKey = String(application.status || '').toLowerCase();
        const isApproved = statusKey === 'approved';
        const isPending = statusKey === 'pending';
        const isRejected = statusKey === 'rejected';
        const statusLabel = application.status_display || application.status || '—';
        const statusClass = isApproved
            ? 'sch-preview-status-badge is-approved'
            : (isRejected ? 'sch-preview-status-badge is-rejected' : (isPending ? 'sch-preview-status-badge is-pending' : 'sch-preview-status-badge'));
        const showCancel = isPending && application.can_cancel;

        let asideNotice = '';
        if (isRejected) {
            asideNotice = `<div class="sch-preview-warning">Your application was not approved. Please check your email or contact your SK officials for details.</div>`;
        } else if (isApproved) {
            asideNotice = `<div class="sch-preview-success">Your application has been approved. Please monitor announcements from your SK officials.</div>`;
        } else if (isPending) {
            asideNotice = `<div class="sch-preview-warning">Your application has been submitted. You may no longer edit your responses unless you cancel and re-apply.</div>`;
        }

        shell.innerHTML = `
            <div class="sch-preview-shell">
                <div class="sch-preview-top">
                    <h1>Scholarship Application – Preview</h1>
                    <p class="sch-preview-subtitle">Review of your submitted application for ${escapeHtml(application.program_name || program?.program_name || 'this program')}.</p>
                </div>

                <div class="sch-preview-layout">
                    <aside class="sch-preview-sidebar">
                        <div class="sch-preview-profile">
                            ${avatarHtml}
                            <div class="sch-preview-profile-name">${escapeHtml(fullName || 'Kabataan Applicant')}</div>
                            <div class="sch-preview-profile-id">Respondent No. ${escapeHtml(respondentNo)}</div>
                        </div>
                        <ul class="sch-preview-nav" id="schPreviewNav">${renderNav('schPreviewPersonal')}</ul>
                    </aside>

                    <main class="sch-preview-main" id="schPreviewMain">
                        ${renderPersonalSection(personalInfo)}
                        ${renderEducationSection(systemFields, kkEducation)}
                        ${renderBackgroundSection(systemFields)}
                        ${renderAdditionalSection(systemFields, kkEducation)}
                        ${renderRequirementsSection(answers)}
                    </main>

                    <aside class="sch-preview-aside">
                        <div class="sch-preview-summary-card">
                            <h3>Application Summary</h3>
                            <div class="sch-preview-summary-row">
                                <span class="sch-preview-summary-label">Program</span>
                                <span class="sch-preview-summary-value">${escapeHtml(application.program_name || '—')}</span>
                            </div>
                            <div class="sch-preview-summary-row">
                                <span class="sch-preview-summary-label">Respondent Number</span>
                                <span class="sch-preview-summary-value">${escapeHtml(respondentNo)}</span>
                            </div>
                            <div class="sch-preview-summary-row">
                                <span class="sch-preview-summary-label">Date Submitted</span>
                                <span class="sch-preview-summary-value">${escapeHtml(application.submitted_at || '—')}</span>
                            </div>
                            <div class="sch-preview-summary-row">
                                <span class="sch-preview-summary-label">Status</span>
                                <span class="${statusClass}">${escapeHtml(statusLabel)}</span>
                            </div>
                            ${asideNotice}
                        </div>
                        ${showCancel ? `
                        <div class="sch-preview-actions-card">
                            <button type="button" class="sch-preview-action-btn is-danger" id="schPreviewCancelBtn">Cancel Application</button>
                        </div>` : ''}
                    </aside>
                </div>
            </div>`;

        shell.hidden = false;

        const landing = document.getElementById('scholarshipLandingContent');
        if (landing) landing.hidden = true;

        bindPreviewEvents(application);
    }

    function bindPreviewEvents(application) {
        document.querySelectorAll('[data-scroll-to]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-scroll-to');
                const target = document.getElementById(targetId);
                if (!target) return;

                document.querySelectorAll('.sch-preview-nav-item').forEach((item) => item.classList.remove('is-active'));
                btn.closest('.sch-preview-nav-item')?.classList.add('is-active');
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        const cancelBtn = document.getElementById('schPreviewCancelBtn');
        if (cancelBtn && application.can_cancel) {
            cancelBtn.addEventListener('click', () => {
                if (typeof global.openScholarshipCancelModal === 'function') {
                    global.openScholarshipCancelModal(application.id);
                }
            });
        }

        const sections = STEPS.filter((s) => s.sectionId).map((s) => document.getElementById(s.sectionId)).filter(Boolean);
        const navItems = document.querySelectorAll('.sch-preview-nav-item');

        if (sections.length && navItems.length) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    navItems.forEach((item) => item.classList.remove('is-active'));
                    const btn = document.querySelector(`[data-scroll-to="${entry.target.id}"]`);
                    btn?.closest('.sch-preview-nav-item')?.classList.add('is-active');
                });
            }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });

            sections.forEach((section) => observer.observe(section));
        }
    }

    global.ScholarshipApplicationPreview = { render, formatRespondentDisplay };
})(window);
