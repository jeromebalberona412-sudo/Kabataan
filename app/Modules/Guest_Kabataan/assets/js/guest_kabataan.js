function filterList(input, itemSelector, emptyEl) {
    if (!input) {
        return;
    }

    const items = Array.from(document.querySelectorAll(itemSelector));
    const apply = () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        items.forEach((item) => {
            const haystack = item.getAttribute('data-guest-barangay')
                || item.getAttribute('data-guest-program')
                || '';
            const show = query === '' || haystack.includes(query);
            item.hidden = !show;
            if (show) {
                visible += 1;
            }
        });
        if (emptyEl) {
            emptyEl.hidden = visible !== 0 || items.length === 0;
        }
    };

    input.addEventListener('input', apply);
    input.form?.addEventListener('submit', (event) => {
        event.preventDefault();
    });
}

function bindLogout() {
    const modal = document.getElementById('guestKabataanLogout');
    const openBtn = document.getElementById('guestKabataanLogoutBtn');
    if (!modal || !openBtn) {
        return;
    }

    const close = () => {
        modal.hidden = true;
        openBtn.focus();
    };

    openBtn.addEventListener('click', () => {
        modal.hidden = false;
        modal.querySelector('[data-guest-logout-close]')?.focus();
    });

    modal.querySelectorAll('[data-guest-logout-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
}

filterList(
    document.getElementById('guestKabataanBarangaySearch'),
    '[data-guest-barangay]',
    document.getElementById('guestKabataanBarangayEmpty'),
);

filterList(
    document.getElementById('guestKabataanProgramSearch'),
    '[data-guest-program]',
    document.getElementById('guestKabataanProgramEmpty'),
);

bindLogout();
