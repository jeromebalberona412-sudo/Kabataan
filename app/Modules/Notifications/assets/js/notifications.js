(function () {
    'use strict';

    const list = document.getElementById('kbNotifPageList');
    const empty = document.getElementById('kbNotifPageEmpty');
    const markAllBtn = document.getElementById('kbNotifMarkAllBtn');
    const filterButtons = document.querySelectorAll('.kb-notif-filter');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function updateCounts() {
        const items = list ? Array.from(list.querySelectorAll('.kb-notif-item')) : [];
        const unread = items.filter((item) => item.classList.contains('is-unread')).length;
        const total = items.length;
        const read = Math.max(0, total - unread);

        const totalEl = document.getElementById('kbNotifTotalCount');
        const unreadEl = document.getElementById('kbNotifUnreadCount');
        const readEl = document.getElementById('kbNotifReadCount');

        if (totalEl) totalEl.textContent = String(total);
        if (unreadEl) unreadEl.textContent = String(unread);
        if (readEl) readEl.textContent = String(read);

        if (list) list.hidden = total === 0;
        if (empty) empty.hidden = total > 0;

        if (typeof window.KabataanNotifications?.updateBadge === 'function') {
            window.KabataanNotifications.updateBadge(unread);
        }
    }

    function applyFilter(filter) {
        if (!list) return;
        list.querySelectorAll('.kb-notif-item').forEach((item) => {
            const isUnread = item.classList.contains('is-unread');
            const show = filter === 'all'
                || (filter === 'unread' && isUnread)
                || (filter === 'read' && !isUnread);
            item.style.display = show ? '' : 'none';
        });
    }

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((btn) => btn.classList.remove('is-active'));
            button.classList.add('is-active');
            applyFilter(button.dataset.filter || 'all');
        });
    });

    async function markRead(item) {
        const id = item?.dataset?.id;
        if (!id || !item.classList.contains('is-unread')) return;

        try {
            await fetch(`/api/kabataan/notifications/${id}/read`, {
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
            // Continue navigation even if mark-read fails.
        }

        item.classList.remove('is-unread');
        const dot = item.querySelector('.kb-notif-item__dot');
        dot?.remove();
        updateCounts();
    }

    list?.addEventListener('click', async (event) => {
        const item = event.target.closest('.kb-notif-item');
        if (!item) return;

        await markRead(item);
        const actionUrl = (item.dataset.actionUrl || '').trim();
        if (actionUrl) {
            window.location.href = actionUrl;
        }
    });

    markAllBtn?.addEventListener('click', async () => {
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

        list?.querySelectorAll('.kb-notif-item.is-unread').forEach((item) => {
            item.classList.remove('is-unread');
            const dot = item.querySelector('.kb-notif-item__dot');
            dot?.remove();
        });
        updateCounts();
    });
    updateCounts();
})();
