document.addEventListener('DOMContentLoaded', function () {
    const ZOOM_MIN = 0.5;
    const ZOOM_MAX = 4;
    const ZOOM_STEP = 0.25;

    const root = document.getElementById('profileDocLightbox');
    const image = document.getElementById('profileDocLightboxImage');
    const viewport = document.getElementById('profileDocLightboxViewport');
    const title = document.getElementById('profileDocLightboxTitle');
    const counter = document.getElementById('profileDocLightboxCounter');
    const zoomLevel = document.getElementById('profileDocLightboxZoomLevel');
    const prevBtn = document.getElementById('profileDocLightboxPrev');
    const nextBtn = document.getElementById('profileDocLightboxNext');
    const grid = document.getElementById('profileSupportingDocsGrid');

    if (!root || !image) {
        return;
    }

    let images = [];
    let index = 0;
    let zoom = 1;

    function applyZoom() {
        image.style.transform = `scale(${zoom})`;
        if (zoomLevel) {
            zoomLevel.textContent = `${Math.round(zoom * 100)}%`;
        }
        if (viewport && zoom <= 1) {
            viewport.scrollTop = 0;
            viewport.scrollLeft = 0;
        }
    }

    function render() {
        if (!images.length) {
            return;
        }

        const current = images[index];
        image.src = current.src;
        image.alt = current.label || 'Supporting document';
        if (title) {
            title.textContent = current.label || 'Supporting document';
        }
        if (counter) {
            counter.textContent = images.length > 1 ? `${index + 1} / ${images.length}` : '';
            counter.hidden = images.length <= 1;
        }
        if (prevBtn) {
            prevBtn.hidden = images.length <= 1;
        }
        if (nextBtn) {
            nextBtn.hidden = images.length <= 1;
        }
        zoom = 1;
        applyZoom();
    }

    function closeLightbox() {
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('profile-doc-lightbox-open');
        image.removeAttribute('src');
        image.style.transform = '';
        images = [];
        index = 0;
        zoom = 1;
    }

    function openLightbox(imageList, startIndex) {
        images = (imageList || []).filter((item) => item && item.src);
        if (!images.length) {
            return;
        }

        index = Math.max(0, Math.min(startIndex || 0, images.length - 1));
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('profile-doc-lightbox-open');
        render();
    }

    function zoomIn() {
        zoom = Math.min(ZOOM_MAX, +(zoom + ZOOM_STEP).toFixed(2));
        applyZoom();
    }

    function zoomOut() {
        zoom = Math.max(ZOOM_MIN, +(zoom - ZOOM_STEP).toFixed(2));
        applyZoom();
    }

    function zoomReset() {
        zoom = 1;
        applyZoom();
    }

    function showPrev() {
        if (images.length <= 1) {
            return;
        }
        index = (index - 1 + images.length) % images.length;
        render();
    }

    function showNext() {
        if (images.length <= 1) {
            return;
        }
        index = (index + 1) % images.length;
        render();
    }

    function collectImages() {
        return Array.from(document.querySelectorAll('.kkp-profile-doc-thumb-btn[data-doc-src]')).map((btn) => ({
            src: btn.getAttribute('data-doc-src') || '',
            label: btn.getAttribute('data-doc-label') || btn.querySelector('img')?.alt || 'Supporting document',
        })).filter((item) => item.src);
    }

    root.querySelectorAll('[data-profile-doc-lightbox-close]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            closeLightbox();
        });
    });

    document.getElementById('profileDocLightboxZoomIn')?.addEventListener('click', zoomIn);
    document.getElementById('profileDocLightboxZoomOut')?.addEventListener('click', zoomOut);
    document.getElementById('profileDocLightboxZoomReset')?.addEventListener('click', zoomReset);
    prevBtn?.addEventListener('click', showPrev);
    nextBtn?.addEventListener('click', showNext);

    viewport?.addEventListener('wheel', (event) => {
        if (!root.classList.contains('is-open')) {
            return;
        }
        event.preventDefault();
        if (event.deltaY < 0) {
            zoomIn();
        } else {
            zoomOut();
        }
    }, { passive: false });

    document.addEventListener('keydown', (event) => {
        if (!root.classList.contains('is-open')) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            showPrev();
        } else if (event.key === 'ArrowRight') {
            showNext();
        } else if (event.key === '+' || event.key === '=') {
            zoomIn();
        } else if (event.key === '-') {
            zoomOut();
        } else if (event.key === '0') {
            zoomReset();
        }
    });

    (grid || document).querySelectorAll('.kkp-profile-doc-thumb-btn').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const imageList = collectImages();
            const src = btn.getAttribute('data-doc-src') || '';
            const start = imageList.findIndex((item) => item.src === src);
            openLightbox(imageList, start < 0 ? 0 : start);
        });
    });
});
