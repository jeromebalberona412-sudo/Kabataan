/**
 * Kabataan dashboard feed videos — layout/play patterns aligned with SK Officials Community Feed.
 */
(function () {
    'use strict';

    const videoAlbumCache = new Map();
    let videoLightboxItems = [];
    let videoLightboxIndex = 0;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function prefersFineHover() {
        return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    }

    function driveThumbnailUrl(fileId) {
        const id = String(fileId || '').trim();
        if (!id) return '';
        return `https://drive.google.com/thumbnail?id=${encodeURIComponent(id)}&sz=w1000`;
    }

    function videoPreviewSrc(video) {
        if (!video) return '';
        if (video.provider === 'cloudinary' && video.video_url) return String(video.video_url);
        const fileId = video.external_id || video.google_drive_file_id || '';
        if (video.preview_url) {
            const base = String(video.preview_url);
            return base.includes('autoplay=')
                ? base
                : (base.includes('?') ? `${base}&autoplay=1` : `${base}?autoplay=1`);
        }
        if (!fileId) return '';
        return `https://drive.google.com/file/d/${fileId}/preview?autoplay=1`;
    }

    function buildCloudinaryVideoHtml(video, key) {
        if (!video?.video_url) return '';
        const src = escapeHtml(video.video_url);
        const id = escapeHtml(String(key || video.id || ''));
        return `<div class="cf-cloud-video" data-cloud-video data-video-key="${id}" data-cloud-src="${src}">
            <div class="cf-cloud-video-frame cf-cloud-video-poster">
                <button type="button" class="cf-drive-play-btn" aria-label="Play video">
                    <span class="cf-drive-play-icon" aria-hidden="true">▶</span>
                    <span>Play video</span>
                </button>
            </div>
        </div>`;
    }

    function buildGoogleDriveVideoHtml(video, postId) {
        if (!video?.preview_url && !(video?.external_id || video?.google_drive_file_id)) return '';
        const fileId = video.external_id || video.google_drive_file_id || '';
        const previewBase = String(video.preview_url || `https://drive.google.com/file/d/${fileId}/preview`);
        const previewWithAutoplay = previewBase.includes('?')
            ? `${previewBase}&autoplay=1`
            : `${previewBase}?autoplay=1`;
        const preview = escapeHtml(previewWithAutoplay);
        const openUrl = escapeHtml(video.open_url || video.media_url || video.video_url || '');
        const thumb = escapeHtml(driveThumbnailUrl(fileId) || '');
        const id = escapeHtml(String(postId || fileId));
        return `<div class="cf-drive-video" data-drive-video data-post-id="${id}" data-preview-src="${preview}" data-file-id="${escapeHtml(fileId)}">
            <div class="cf-drive-video-frame cf-drive-video-poster">
                ${thumb ? `<img class="cf-drive-poster-img" src="${thumb}" alt="" loading="lazy">` : ''}
                <button type="button" class="cf-drive-play-btn" aria-label="Play video">
                    <span class="cf-drive-play-icon" aria-hidden="true">▶</span>
                    <span>Play video</span>
                </button>
            </div>
            <div class="cf-drive-video-fallback" hidden>
                <p>Video unavailable. Please check the Google Drive sharing permissions.</p>
                ${openUrl ? `<a href="${openUrl}" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link">Open in Google Drive</a>` : ''}
            </div>
            ${openUrl ? `<a href="${openUrl}" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link cf-drive-open-link--below">Open in Google Drive</a>` : ''}
        </div>`;
    }

    function buildVideoAlbumGrid(videos, postId) {
        const list = Array.isArray(videos) ? videos.filter(Boolean) : [];
        const count = list.length;
        const cacheKey = String(postId || '');
        if (cacheKey) videoAlbumCache.set(cacheKey, list);

        const visible = Math.min(4, count);
        const slots = [];
        for (let i = 0; i < visible; i += 1) {
            const overlay = (i === 3 && count > 4) ? `+${count - 4}` : null;
            slots.push({ index: i, overlay });
        }

        let gridClass = 'post-video-grid';
        if (count === 1) gridClass += ' video-grid-1';
        else if (count === 2) gridClass += ' video-grid-2';
        else if (count === 3) gridClass += ' video-grid-3';
        else gridClass += ' video-grid-4';

        const tiles = slots.map(({ index, overlay }) => {
            const video = list[index];
            const fileId = video.external_id || video.google_drive_file_id || '';
            const thumb = video.provider === 'cloudinary' && video.video_url
                ? escapeHtml(video.video_url)
                : escapeHtml(driveThumbnailUrl(fileId) || '');
            const preview = escapeHtml(videoPreviewSrc(video));
            const isCloud = video.provider === 'cloudinary' && video.video_url;
            const overlayHtml = overlay
                ? `<span class="post-media-more">${escapeHtml(overlay)}</span>`
                : '<span class="post-video-play" aria-hidden="true"></span>';
            const mediaHtml = isCloud
                ? `<video src="${escapeHtml(video.video_url)}" preload="metadata" muted playsinline></video>`
                : (thumb
                    ? `<img src="${thumb}" alt="" loading="lazy">`
                    : '<div class="post-video-tile-fallback"></div>');

            return `<button type="button"
                class="post-video-tile${overlay ? ' post-media-more-tile' : ''}"
                data-video-album-tile
                data-post-id="${escapeHtml(cacheKey)}"
                data-index="${index}"
                data-has-more="${overlay ? '1' : '0'}"
                data-preview-src="${preview}"
                data-is-cloud="${isCloud ? '1' : '0'}"
                data-cloud-src="${isCloud ? escapeHtml(video.video_url) : ''}"
                aria-label="${overlay ? `Play more videos (${count} total)` : `Play video ${index + 1}`}">
                <span class="post-video-tile-inner">${mediaHtml}${overlayHtml}</span>
            </button>`;
        }).join('');

        return `<div class="${gridClass}" data-video-album data-post-id="${escapeHtml(cacheKey)}" data-video-count="${count}">
            <div class="post-video-grid-tiles">${tiles}</div>
        </div>`;
    }

    function buildPostVideosHtml(post) {
        const videos = Array.isArray(post?.videos) ? post.videos.filter(Boolean) : [];
        if (videos.length > 1) {
            return buildVideoAlbumGrid(videos, post.id);
        }
        if (videos.length === 1) {
            const video = videos[0];
            if (video.provider === 'google_drive' || video.preview_url) {
                return buildGoogleDriveVideoHtml(video, `${post.id}-0`);
            }
            return buildCloudinaryVideoHtml(video, `${post.id}-0`);
        }
        if (post?.google_drive_video) {
            return buildGoogleDriveVideoHtml(post.google_drive_video, post.id);
        }
        return '';
    }

    function stopAllFeedMediaExcept(keep) {
        document.querySelectorAll('[data-video-album-tile].is-playing').forEach((tile) => {
            if (keep && tile === keep) return;
            restoreAlbumTilePoster(tile);
        });
        document.querySelectorAll('[data-drive-video].is-playing, [data-cloud-video].is-playing').forEach((wrap) => {
            if (keep && wrap === keep) return;
            if (wrap.matches('[data-drive-video]')) restoreDriveVideoPoster(wrap);
            else restoreCloudVideoPoster(wrap);
        });
    }

    function restoreAlbumTilePoster(tile) {
        if (!tile) return;
        const album = tile.closest('[data-video-album]');
        const inner = tile.querySelector('.post-video-tile-inner') || tile;
        if (tile.dataset.posterHtml) {
            inner.innerHTML = tile.dataset.posterHtml;
        }
        tile.dataset.playing = '0';
        tile.classList.remove('is-playing', 'is-controls-visible', 'is-video-fullscreen');
        delete tile.dataset.activeVideoIndex;
        if (album && !album.querySelector('[data-video-album-tile].is-playing')) {
            album.classList.remove('has-inline-playing');
        }
    }

    function restoreDriveVideoPoster(wrap) {
        if (!wrap || !wrap.dataset.posterHtml) return;
        const frame = wrap.querySelector('.cf-drive-video-frame');
        if (!frame) return;
        frame.classList.add('cf-drive-video-poster');
        frame.classList.remove('is-controls-visible');
        frame.innerHTML = wrap.dataset.posterHtml;
        delete frame.dataset.controlsRevealBound;
        bindVideoControlsReveal(frame);
        wrap.classList.remove('is-playing');
        const playBtn = frame.querySelector('.cf-drive-play-btn');
        if (playBtn) playBtn.addEventListener('click', () => playDriveVideoWrap(wrap));
    }

    function restoreCloudVideoPoster(wrap) {
        if (!wrap) return;
        const frame = wrap.querySelector('.cf-cloud-video-frame');
        if (!frame) return;
        frame.className = 'cf-cloud-video-frame cf-cloud-video-poster';
        frame.innerHTML = `<button type="button" class="cf-drive-play-btn" aria-label="Play video">
            <span class="cf-drive-play-icon" aria-hidden="true">▶</span>
            <span>Play video</span>
        </button>`;
        wrap.classList.remove('is-playing');
        const playBtn = frame.querySelector('.cf-drive-play-btn');
        if (playBtn) playBtn.addEventListener('click', () => playCloudVideoWrap(wrap));
    }

    function driveVideoItemFromWrap(wrap) {
        const fileId = wrap.dataset.fileId || '';
        return {
            provider: 'google_drive',
            preview_url: String(wrap.dataset.previewSrc || '').replace(/([?&])autoplay=1&?/, '$1').replace(/[?&]$/, ''),
            external_id: fileId,
            google_drive_file_id: fileId,
            open_url: fileId ? `https://drive.google.com/file/d/${fileId}/view` : '',
        };
    }

    function playMediaInAlbumTile(tile, videoIndex, videos) {
        if (!tile || !videos.length) return;
        const index = Math.max(0, Math.min(videos.length - 1, Number(videoIndex) || 0));
        const video = videos[index];
        const inner = tile.querySelector('.post-video-tile-inner') || tile;
        const isCloud = video.provider === 'cloudinary' && video.video_url;
        const src = videoPreviewSrc(video);

        stopAllFeedMediaExcept(tile);

        if (!tile.dataset.posterHtml) {
            tile.dataset.posterHtml = inner.innerHTML;
        }

        tile.dataset.playing = '1';
        tile.dataset.activeVideoIndex = String(index);
        tile.classList.add('is-playing');
        tile.classList.remove('is-controls-visible', 'is-video-fullscreen');
        tile.closest('[data-video-album]')?.classList.add('has-inline-playing');

        if (isCloud && src) {
            inner.innerHTML = `<video src="${escapeHtml(src)}" playsinline preload="metadata" controlslist="nodownload noplaybackrate noremoteplayback" disablepictureinpicture autoplay></video>
                <button type="button" class="post-video-expand-btn cf-video-expand-btn" data-video-expand aria-label="Watch fullscreen">Fullscreen</button>`;
            const el = inner.querySelector('video');
            el?.addEventListener('play', () => stopAllFeedMediaExcept(tile));
            el?.addEventListener('click', (e) => {
                e.stopPropagation();
                if (el.paused) el.play().catch(() => {});
                else el.pause();
            });
        } else if (src) {
            inner.innerHTML = `<iframe
                src="${escapeHtml(src)}"
                title="Google Drive video ${index + 1}"
                allow="autoplay; fullscreen"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
            <span class="cf-video-chrome-mask" aria-hidden="true"></span>
            <button type="button" class="post-video-expand-btn cf-video-expand-btn" data-video-expand aria-label="Watch fullscreen">Fullscreen</button>`;
        } else {
            inner.innerHTML = `${tile.dataset.posterHtml || ''}`;
            tile.dataset.playing = '0';
            tile.classList.remove('is-playing');
            tile.closest('[data-video-album]')?.classList.remove('has-inline-playing');
        }

        inner.querySelector('[data-video-expand]')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openVideoLightbox(videos, index);
        });
    }

    function playDriveVideoWrap(wrap) {
        const frame = wrap.querySelector('.cf-drive-video-frame');
        const fallback = wrap.querySelector('.cf-drive-video-fallback');
        const previewSrc = wrap.dataset.previewSrc;
        if (!frame || !previewSrc) return;

        if (!prefersFineHover()) {
            openVideoLightbox([driveVideoItemFromWrap(wrap)], 0);
            return;
        }

        stopAllFeedMediaExcept(wrap);
        if (!wrap.dataset.posterHtml) {
            wrap.dataset.posterHtml = frame.innerHTML;
        }
        wrap.classList.add('is-playing');
        wrap.classList.remove('is-fallback');
        frame.classList.remove('cf-drive-video-poster');
        frame.innerHTML = `<iframe src="${escapeHtml(previewSrc)}" title="Google Drive video" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
            <span class="cf-video-chrome-mask" aria-hidden="true"></span>
            <button type="button" class="cf-video-expand-btn" aria-label="Fullscreen" title="Fullscreen"></button>`;
        frame.querySelector('.cf-video-expand-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openVideoLightbox([driveVideoItemFromWrap(wrap)], 0);
        });
        const iframe = frame.querySelector('iframe');
        iframe?.addEventListener('error', () => {
            wrap.classList.add('is-fallback');
            wrap.classList.remove('is-playing');
            if (fallback) fallback.hidden = false;
            frame.hidden = true;
        });
    }

    function playCloudVideoWrap(wrap) {
        const frame = wrap.querySelector('.cf-cloud-video-frame');
        const src = wrap.dataset.cloudSrc;
        if (!frame || !src) return;

        if (!prefersFineHover()) {
            openVideoLightbox([{ provider: 'cloudinary', video_url: src }], 0);
            return;
        }

        stopAllFeedMediaExcept(wrap);
        wrap.classList.add('is-playing');
        frame.classList.remove('cf-cloud-video-poster');
        frame.innerHTML = `<video src="${escapeHtml(src)}" playsinline preload="metadata" controlslist="nodownload noplaybackrate noremoteplayback" disablepictureinpicture autoplay></video>
            <button type="button" class="cf-video-expand-btn" aria-label="Fullscreen" title="Fullscreen"></button>`;
        const el = frame.querySelector('video');
        el?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (el.paused) el.play().catch(() => {});
            else el.pause();
        });
        frame.querySelector('.cf-video-expand-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openVideoLightbox([{ provider: 'cloudinary', video_url: src }], 0);
        });
    }

    function renderVideoLightboxFrame() {
        const frame = document.getElementById('videoLightboxFrame');
        const counter = document.getElementById('videoLightboxCounter');
        const prevBtn = document.getElementById('videoLightboxPrev');
        const nextBtn = document.getElementById('videoLightboxNext');
        if (!frame || !videoLightboxItems.length) return;

        const index = ((videoLightboxIndex % videoLightboxItems.length) + videoLightboxItems.length) % videoLightboxItems.length;
        videoLightboxIndex = index;
        const video = videoLightboxItems[index];
        const isCloud = video?.provider === 'cloudinary' && video?.video_url;
        const src = videoPreviewSrc(video);

        if (isCloud && src) {
            frame.className = 'video-lightbox-frame is-cloud-embed';
            frame.innerHTML = `<video src="${escapeHtml(src)}" controls playsinline autoplay preload="metadata" controlslist="nodownload"></video>`;
        } else if (src) {
            frame.className = 'video-lightbox-frame is-drive-embed';
            frame.innerHTML = `<iframe
                src="${escapeHtml(src)}"
                title="Video ${index + 1}"
                allow="autoplay; fullscreen"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>`;
        } else {
            frame.className = 'video-lightbox-frame';
            frame.innerHTML = '<p style="color:#fff;text-align:center;padding:24px;">Video unavailable.</p>';
        }

        if (counter) {
            counter.textContent = `${index + 1} / ${videoLightboxItems.length}`;
            counter.hidden = videoLightboxItems.length < 2;
        }
        const multi = videoLightboxItems.length > 1;
        if (prevBtn) prevBtn.hidden = !multi;
        if (nextBtn) nextBtn.hidden = !multi;
    }

    function openVideoLightbox(videos, startIndex = 0) {
        const list = Array.isArray(videos) ? videos.filter(Boolean) : [];
        if (!list.length) return;
        stopAllFeedMediaExcept(null);
        videoLightboxItems = list;
        videoLightboxIndex = Math.max(0, Math.min(list.length - 1, Number(startIndex) || 0));
        const lb = document.getElementById('videoLightbox');
        if (!lb) return;
        lb.classList.add('active', 'is-video-fullscreen');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        renderVideoLightboxFrame();
    }

    function closeVideoLightbox() {
        const lb = document.getElementById('videoLightbox');
        const frame = document.getElementById('videoLightboxFrame');
        if (frame) frame.innerHTML = '';
        lb?.classList.remove('active', 'is-video-fullscreen');
        lb?.setAttribute('aria-hidden', 'true');
        if (!document.body.classList.contains('feed-commenting')) {
            document.body.style.overflow = '';
        }
        videoLightboxItems = [];
        videoLightboxIndex = 0;
    }

    function videoLightboxPrev() {
        if (videoLightboxItems.length < 2) return;
        videoLightboxIndex = (videoLightboxIndex - 1 + videoLightboxItems.length) % videoLightboxItems.length;
        renderVideoLightboxFrame();
    }

    function videoLightboxNext() {
        if (videoLightboxItems.length < 2) return;
        videoLightboxIndex = (videoLightboxIndex + 1) % videoLightboxItems.length;
        renderVideoLightboxFrame();
    }

    function bindVideoControlsReveal(target) {
        if (!target || target.dataset.controlsRevealBound === '1') return;
        target.dataset.controlsRevealBound = '1';
        let hideTimer = null;
        const show = () => {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            target.classList.add('is-controls-visible');
        };
        const scheduleHide = () => {
            if (hideTimer) clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                if (!target.matches(':hover, :focus-within')) {
                    target.classList.remove('is-controls-visible');
                }
            }, 900);
        };
        target.addEventListener('pointerenter', show);
        target.addEventListener('pointerdown', show);
        target.addEventListener('focusin', show);
        target.addEventListener('pointerleave', scheduleHide);
        target.addEventListener('focusout', scheduleHide);
    }

    function bindVideoAlbumGrids(root = document) {
        root.querySelectorAll('[data-video-album]').forEach((album) => {
            if (album.dataset.bound === '1') return;
            album.dataset.bound = '1';
            const postId = String(album.dataset.postId || '');
            const videos = videoAlbumCache.get(postId) || [];

            album.querySelectorAll('[data-video-album-tile]').forEach((tile) => {
                bindVideoControlsReveal(tile);
                tile.addEventListener('click', (e) => {
                    if (e.target.closest('.cf-video-expand-btn')) return;
                    const startIndex = parseInt(tile.dataset.index, 10) || 0;

                    if (videos.length > 1 || tile.dataset.hasMore === '1') {
                        openVideoLightbox(videos, startIndex);
                        return;
                    }

                    if (tile.dataset.playing === '1') {
                        openVideoLightbox(videos, startIndex);
                        return;
                    }
                    playMediaInAlbumTile(tile, startIndex, videos);
                });
            });
        });
    }

    function bindDriveVideoEmbeds(root = document) {
        root.querySelectorAll('[data-drive-video]').forEach((wrap) => {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';
            const frame = wrap.querySelector('.cf-drive-video-frame');
            const playBtn = wrap.querySelector('.cf-drive-play-btn');
            if (frame) bindVideoControlsReveal(frame);
            if (!playBtn) return;
            playBtn.addEventListener('click', () => playDriveVideoWrap(wrap));
        });

        root.querySelectorAll('[data-cloud-video]').forEach((wrap) => {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';
            const frame = wrap.querySelector('.cf-cloud-video-frame');
            const playBtn = wrap.querySelector('.cf-drive-play-btn');
            if (frame) bindVideoControlsReveal(frame);
            if (!playBtn) return;
            playBtn.addEventListener('click', () => playCloudVideoWrap(wrap));
        });
    }

    function bindFeedVideos(root = document) {
        bindVideoAlbumGrids(root);
        bindDriveVideoEmbeds(root);
    }

    function bindLightboxChrome() {
        document.getElementById('videoLightboxClose')?.addEventListener('click', closeVideoLightbox);
        document.getElementById('videoLightboxPrev')?.addEventListener('click', videoLightboxPrev);
        document.getElementById('videoLightboxNext')?.addEventListener('click', videoLightboxNext);
        document.getElementById('videoLightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'videoLightbox') closeVideoLightbox();
        });
        document.addEventListener('keydown', (e) => {
            const lb = document.getElementById('videoLightbox');
            if (!lb?.classList.contains('active')) return;
            if (e.key === 'Escape') closeVideoLightbox();
            else if (e.key === 'ArrowLeft') videoLightboxPrev();
            else if (e.key === 'ArrowRight') videoLightboxNext();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindLightboxChrome);
    } else {
        bindLightboxChrome();
    }

    window.KabataanFeedVideos = {
        buildPostVideosHtml,
        bindFeedVideos,
        openVideoLightbox,
        closeVideoLightbox,
    };
})();
