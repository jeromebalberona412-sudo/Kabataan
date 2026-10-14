(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function reusableForQuestion(program, questionId) {
        const map = program?.reusable_id_documents || {};
        const payload = map[questionId] || map[String(questionId)] || null;
        if (!payload || !payload.document_type) return null;
        return payload;
    }

    function fileSettings(question) {
        const fileType = question?.file_type || 'pdf';
        const maxMb = Math.min(10, Math.max(1, Number(question?.max_size_mb) || 5));
        if (fileType === 'image') {
            return {
                fileType,
                maxMb,
                accept: 'image/jpeg,image/png,.jpg,.jpeg,.png',
                hint: `Accepted: JPG or PNG, max ${maxMb} MB`,
                dropText: 'Drag and drop an ID image here or click to browse',
            };
        }
        if (fileType === 'pdf_or_image') {
            return {
                fileType,
                maxMb,
                accept: 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png',
                hint: `Accepted: PDF, JPG, or PNG, max ${maxMb} MB`,
                dropText: 'Drag and drop a PDF or ID image here or click to browse',
            };
        }
        return {
            fileType: 'pdf',
            maxMb,
            accept: 'application/pdf,.pdf',
            hint: `Accepted: PDF only, max ${maxMb} MB`,
            dropText: 'Drag and drop PDF files here or click to browse',
        };
    }

    function isImageMeta(documentMeta) {
        const mime = String(documentMeta?.mime || '').toLowerCase();
        const name = String(documentMeta?.original_name || '').toLowerCase();
        return mime.startsWith('image/') || /\.(jpg|jpeg|png)$/.test(name) || documentMeta?.reused;
    }

    function renderReuseBlock(question, reusable) {
        const questionId = escapeHtml(String(question.id));
        const sides = Array.isArray(reusable.sides) ? reusable.sides : [];
        const thumbs = sides.map((side) => `
            <figure class="gf-id-reuse-thumb">
                <img src="${escapeHtml(side.preview_url)}" alt="${escapeHtml(reusable.document_type_label)} ${escapeHtml(side.label)}" class="gf-id-reuse-img">
                <figcaption>${escapeHtml(side.label)}</figcaption>
            </figure>
        `).join('');

        return `
            <div class="gf-id-reuse" data-id-reuse="${questionId}">
                <p class="gf-id-reuse-question">${escapeHtml(reusable.question)}</p>
                ${thumbs ? `<div class="gf-id-reuse-thumbs">${thumbs}</div>` : ''}
                <div class="gf-id-reuse-options" role="radiogroup" aria-label="ID upload option">
                    <label class="gf-id-reuse-option">
                        <input type="radio" name="id_reuse_${questionId}" value="reuse" data-id-reuse-choice="${questionId}">
                        <span>Use Previously Uploaded ID</span>
                    </label>
                    <label class="gf-id-reuse-option">
                        <input type="radio" name="id_reuse_${questionId}" value="new" data-id-reuse-choice="${questionId}">
                        <span>Upload a New ID</span>
                    </label>
                </div>
                <p class="gf-id-reuse-status" data-id-reuse-status="${questionId}" hidden></p>
            </div>
        `;
    }

    function selectedChoice(root, questionId) {
        const checked = root?.querySelector(`[data-id-reuse-choice="${questionId}"]:checked`);
        return checked ? checked.value : '';
    }

    function bind(root, question, reusable, handlers) {
        if (!root || !reusable) return;
        const questionId = String(question.id);
        const upload = root.querySelector(`[data-file-upload="${questionId}"]`);
        const status = root.querySelector(`[data-id-reuse-status="${questionId}"]`);

        const applyChoice = (choice) => {
            if (choice === 'reuse') {
                if (upload) upload.hidden = true;
                const input = upload?.querySelector('.gf-file-input');
                if (input) input.value = '';
                if (status) {
                    status.hidden = false;
                    status.textContent = `The previously uploaded ${reusable.document_type_label} will be used for this application.`;
                }
                handlers.onReuse?.(questionId, reusable);
                return;
            }
            if (choice === 'new') {
                if (upload) upload.hidden = false;
                if (status) {
                    status.hidden = true;
                    status.textContent = '';
                }
                handlers.onNew?.(questionId);
                return;
            }
            if (upload) upload.hidden = true;
            if (status) status.hidden = true;
        };

        root.querySelectorAll(`[data-id-reuse-choice="${questionId}"]`).forEach((input) => {
            input.addEventListener('change', () => applyChoice(input.value));
        });

        applyChoice(selectedChoice(root, questionId));
    }

    function restoreChoice(root, questionId, choice) {
        const input = root?.querySelector(`[data-id-reuse-choice="${questionId}"][value="${choice}"]`);
        if (!input) return;
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    global.ProgramIdReuse = {
        reusableForQuestion,
        fileSettings,
        isImageMeta,
        renderReuseBlock,
        selectedChoice,
        bind,
        restoreChoice,
    };
})(window);
