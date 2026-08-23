/**
 * Scholarship Quick Guidelines — SK Officials configured steps only (no hardcoded defaults).
 */
(function (global) {
    'use strict';

    let modalEl = null;
    let isMaximized = false;
    let activeSteps = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderRichText(html) {
        return String(html || '');
    }

    function renderStepCard(step, index) {
        const stepNum = index + 1;
        const listHtml = step.list
            ? `<ul class="sch-qg-doc-list">
                ${step.list.map((item) => `
                    <li>
                        <span class="sch-qg-doc-en">${renderRichText(item.en)}</span>
                        <em class="sch-qg-doc-tl">${escapeHtml(item.tl)}</em>
                    </li>
                `).join('')}
               </ul>`
            : '';

        return `
            <article class="sch-qg-step-card">
                <h3 class="sch-qg-step-heading">Step #${stepNum}</h3>
                <p class="sch-qg-step-en">${renderRichText(step.en)}</p>
                <p class="sch-qg-step-tl"><em>${escapeHtml(step.tl)}</em></p>
                ${listHtml}
            </article>
        `;
    }

    function renderModalContent() {
        const gridHtml = activeSteps.map((step, index) => renderStepCard(step, index)).join('');

        return `
            <div class="sch-qg-shell kabataan-modal-box ${isMaximized ? 'modal-maximized' : ''}" role="dialog" aria-modal="true" aria-labelledby="schQgTitle">
                <div class="sch-qg-modal-header modal-header">
                    <h2 class="modal-title" id="schQgTitle">QUICK GUIDE</h2>
                    <div class="modal-window-controls">
                        <button type="button" class="modal-toggle-btn" id="schQgMaximizeBtn" aria-label="${isMaximized ? 'Restore down' : 'Maximize'}">${isMaximized ? '⧉' : '□'}</button>
                        <button type="button" class="modal-close" data-close-sch-qg aria-label="Close">&times;</button>
                    </div>
                </div>

                <div class="sch-qg-body kabataan-modal-body">
                    <p class="sch-qg-page-subtitle">Scholarship Application · SK OnePortal Kabataan</p>
                    <div class="sch-qg-grid">${gridHtml}</div>
                </div>

                <footer class="sch-qg-footer">
                    <p>&copy; Copyright SK OnePortal Santa Cruz. All Rights Reserved.</p>
                </footer>
            </div>
        `;
    }

    function ensureModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.id = 'schQuickGuidelinesModal';
        modalEl.className = 'sch-qg-modal kabataan-modal-backdrop';
        modalEl.hidden = true;
        document.body.appendChild(modalEl);
        return modalEl;
    }

    function toggleMaximize() {
        isMaximized = !isMaximized;
        if (modalEl) {
            modalEl.classList.toggle('modal-maximized', isMaximized);
        }
        const shell = modalEl?.querySelector('.sch-qg-shell');
        if (shell) {
            shell.classList.toggle('modal-maximized', isMaximized);
        }
        const btn = modalEl?.querySelector('#schQgMaximizeBtn');
        if (btn) {
            btn.textContent = isMaximized ? '⧉' : '□';
            btn.setAttribute('aria-label', isMaximized ? 'Restore down' : 'Maximize');
        }
    }

    function bindModalEvents() {
        if (!modalEl) return;

        modalEl.querySelectorAll('[data-close-sch-qg]').forEach((el) => {
            el.addEventListener('click', close);
        });

        modalEl.querySelector('#schQgMaximizeBtn')?.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleMaximize();
        });

        modalEl.addEventListener('click', (event) => {
            if (event.target === modalEl) {
                close();
            }
        });

        const shell = modalEl.querySelector('.sch-qg-shell');
        shell?.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.removeEventListener('keydown', onKeyDown);
        document.addEventListener('keydown', onKeyDown);
    }

    function onKeyDown(event) {
        if (event.key === 'Escape' && modalEl && !modalEl.hidden) {
            close();
        }
    }

    function open(steps) {
        const useSteps = Array.isArray(steps) && steps.length
            ? steps
            : (Array.isArray(activeSteps) && activeSteps.length ? activeSteps : []);
        if (!useSteps.length) {
            return;
        }
        activeSteps = useSteps;
        const modal = ensureModal();
        modal.innerHTML = renderModalContent();
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        bindModalEvents();
    }

    function close() {
        if (!modalEl) return;
        modalEl.hidden = true;
        modalEl.classList.remove('modal-maximized');
        isMaximized = false;
        activeSteps = [];
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKeyDown);
    }

    function setSteps(steps) {
        activeSteps = Array.isArray(steps) && steps.length ? steps : [];
    }

    function bindTriggers(root) {
        (root || document).querySelectorAll('[data-open-sch-quick-guidelines]').forEach((btn) => {
            if (btn.dataset.schQgBound === '1') return;
            btn.dataset.schQgBound = '1';
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const customSteps = btn.dataset.schQgSteps;
                if (customSteps) {
                    try {
                        open(JSON.parse(customSteps));
                        return;
                    } catch (error) {
                        // fall through to activeSteps
                    }
                }
                open(activeSteps);
            });
        });
    }

    global.ScholarshipQuickGuidelines = {
        open,
        close,
        setSteps,
        bindTriggers,
    };

    document.addEventListener('DOMContentLoaded', () => bindTriggers(document));
})(window);
