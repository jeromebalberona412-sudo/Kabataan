document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ba-view-details').forEach((button) => {
        button.addEventListener('click', () => {
            const card = button.closest('.ba-program-card');
            const panel = card?.querySelector('.ba-card-expand');
            if (!panel) {
                return;
            }

            const label = button.querySelector('span');
            const willOpen = panel.hidden;
            document.querySelectorAll('.ba-card-expand').forEach((other) => {
                other.hidden = true;
            });
            document.querySelectorAll('.ba-view-details').forEach((other) => {
                other.setAttribute('aria-expanded', 'false');
                const otherLabel = other.querySelector('span');
                if (otherLabel) {
                    otherLabel.textContent = 'View Details';
                }
            });

            panel.hidden = !willOpen;
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (label) {
                label.textContent = willOpen ? 'Hide Details' : 'View Details';
            }
        });
    });

    const lightbox = document.getElementById('programPhotoLightbox');
    const lightboxImage = document.getElementById('programPhotoLightboxImage');
    const lightboxClose = document.getElementById('programPhotoLightboxClose');
    const prevBtn = document.getElementById('programPhotoLightboxPrev');
    const nextBtn = document.getElementById('programPhotoLightboxNext');
    let sources = [];
    let index = 0;

    const showCurrent = () => {
        const photo = sources[index];
        if (!lightbox || !lightboxImage || !photo) {
            return;
        }
        lightboxImage.src = photo.src;
        lightboxImage.alt = '';
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        const many = sources.length > 1;
        if (prevBtn) prevBtn.hidden = !many;
        if (nextBtn) nextBtn.hidden = !many;
    };

    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        document.body.style.overflow = '';
        if (lightboxImage) {
            lightboxImage.src = '';
        }
    };

    const step = (dir) => {
        if (sources.length === 0) {
            return;
        }
        index = (index + dir + sources.length) % sources.length;
        showCurrent();
    };

    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
        let photos = [];
        try {
            photos = JSON.parse(gallery.getAttribute('data-photos') || '[]');
        } catch (error) {
            photos = [];
        }

        gallery.addEventListener('click', (event) => {
            const item = event.target.closest('[data-gallery-index]');
            if (!item) {
                return;
            }
            sources = photos;
            index = Number(item.dataset.galleryIndex) || 0;
            if (event.target.closest('.ba-fb-more') && photos.length > 5) {
                index = 5;
            }
            showCurrent();
        });
    });

    prevBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        step(-1);
    });
    nextBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        step(1);
    });
    lightboxClose?.addEventListener('click', closeLightbox);
    lightbox?.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (!lightbox || lightbox.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closeLightbox();
        }
        if (event.key === 'ArrowLeft') {
            step(-1);
        }
        if (event.key === 'ArrowRight') {
            step(1);
        }
    });
});
