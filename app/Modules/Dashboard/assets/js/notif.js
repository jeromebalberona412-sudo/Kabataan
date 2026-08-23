/* =============================================
   SK OnePortal — Notification Popover JS
   ============================================= */

function npAlignArrow() {
    const btn = document.getElementById('notifNavBtn');
    const popover = document.getElementById('notifPopover');
    if (!btn || !popover) return;

    const btnRect = btn.getBoundingClientRect();
    const btnCenter = btnRect.left + btnRect.width / 2;

    let popRight;
    if (window.innerWidth <= 768) {
        popRight = window.innerWidth - 8;
    } else {
        popRight = popover.getBoundingClientRect().right;
    }

    const arrowRight = Math.max(10, Math.round(popRight - btnCenter - 8));
    popover.style.setProperty('--np-arrow-right', arrowRight + 'px');
}

function npFormatCount(count) {
    const n = Math.max(0, parseInt(count, 10) || 0);
    return n > 99 ? '99+' : String(n);
}

function npGetUnreadTotal() {
    const badge = document.getElementById('notifNavBadge');
    if (!badge) return 0;

    const stored = badge.getAttribute('data-unread-total');
    if (stored !== null && stored !== '') {
        return Math.max(0, parseInt(stored, 10) || 0);
    }

    return Math.max(0, parseInt(badge.textContent, 10) || 0);
}

function npUpdateBadge(unread) {
    const count = Math.max(0, parseInt(unread, 10) || 0);
    const label = count > 0 ? npFormatCount(count) : '';
    const badge = document.getElementById('notifNavBadge');
    const pill = document.getElementById('notifCountPill');

    if (badge) {
        badge.setAttribute('data-unread-total', String(count));
        badge.textContent = label;
        badge.hidden = count <= 0;
    }
    if (pill) {
        pill.setAttribute('data-unread-total', String(count));
        pill.textContent = label;
        pill.hidden = count <= 0;
    }
}

function npSyncEmptyState() {
    const list = document.getElementById('notifList');
    const empty = document.getElementById('notifEmpty');
    if (!list || !empty) return;

    const hasItems = list.querySelectorAll('.np-item').length > 0;
    list.hidden = !hasItems;
    empty.hidden = hasItems;
}

window.KabataanNotifications = {
    updateBadge: npUpdateBadge,
};

window.toggleNotifPopover = function (event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const popover = document.getElementById('notifPopover');
    const btn = document.getElementById('notifNavBtn');
    if (!popover) return;

    if (popover.classList.contains('open')) {
        closeNotifPopover();
        return;
    }

    if (typeof window.kabataanCloseHeaderOverlays === 'function') {
        window.kabataanCloseHeaderOverlays('notif');
    } else {
        document.getElementById('chatbotPopover')?.classList.remove('open');
        document.getElementById('chatbotNavBtn')?.setAttribute('aria-expanded', 'false');
        document.getElementById('kabataanHeaderUser')?.classList.remove('is-open');
        document.querySelector('.kabataan-header__avatar-btn')?.setAttribute('aria-expanded', 'false');
    }

    popover.classList.add('open');
    btn?.setAttribute('aria-expanded', 'true');
    npAlignArrow();
};

window.closeNotifPopover = function () {
    const popover = document.getElementById('notifPopover');
    const btn = document.getElementById('notifNavBtn');
    popover?.classList.remove('open');
    btn?.setAttribute('aria-expanded', 'false');
};

document.addEventListener('click', function (e) {
    const popover = document.getElementById('notifPopover');
    const btn = document.getElementById('notifNavBtn');
    if (!popover || !btn) return;
    if (popover.classList.contains('open') && !popover.contains(e.target) && !btn.contains(e.target)) {
        closeNotifPopover();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAllBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function markItemRead(item) {
        const id = item?.dataset?.id;
        if (!id || !item.classList.contains('np-unread')) return;

        try {
            const response = await fetch(`/api/kabataan/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            const data = await response.json().catch(() => ({}));
            if (typeof data.unread_count === 'number') {
                npUpdateBadge(data.unread_count);
            } else {
                npUpdateBadge(Math.max(0, npGetUnreadTotal() - 1));
            }
        } catch (err) {
            npUpdateBadge(Math.max(0, npGetUnreadTotal() - 1));
        }

        item.classList.remove('np-unread');
        item.querySelector('.np-unread-dot')?.remove();
    }

    list?.addEventListener('click', async function (e) {
        const item = e.target.closest('.np-item');
        if (!item) return;

        await markItemRead(item);
        closeNotifPopover();

        const actionUrl = (item.dataset.actionUrl || '').trim();
        if (actionUrl) {
            window.location.href = actionUrl;
        }
    });

    markAllBtn?.addEventListener('click', async function (e) {
        e.preventDefault();
        e.stopPropagation();

        try {
            await fetch('/api/kabataan/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
        } catch (err) {
            return;
        }

        list?.querySelectorAll('.np-item.np-unread').forEach((item) => {
            item.classList.remove('np-unread');
            item.querySelector('.np-unread-dot')?.remove();
        });
        npUpdateBadge(0);
    });

    npSyncEmptyState();
    npUpdateBadge(npGetUnreadTotal());

    const toast = document.getElementById('kabataanHeaderToast');
    if (toast) {
        window.setTimeout(() => {
            toast.classList.add('is-hiding');
            window.setTimeout(() => {
                toast.remove();
                document.body.classList.remove('kabataan-has-header-toast');
            }, 320);
        }, 6500);
    }
});
