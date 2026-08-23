document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('barangayAccomplishmentsSearch');
    const grid = document.getElementById('accomplishmentsBarangayGrid');
    const resultCount = document.getElementById('accomplishmentsResultCount');
    const filterEmpty = document.getElementById('accomplishmentsFilterEmpty');

    if (searchInput && grid) {
        const cards = Array.from(grid.querySelectorAll('.accomplishments-barangay-card'));

        const filterCards = () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            cards.forEach((card) => {
                const name = card.dataset.barangayName || '';
                const isVisible = query === '' || name.includes(query);
                card.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (resultCount) {
                resultCount.textContent = query === ''
                    ? `Showing ${cards.length} barangays`
                    : `Showing ${visibleCount} of ${cards.length} barangays`;
            }

            if (filterEmpty) {
                filterEmpty.hidden = visibleCount > 0;
                grid.hidden = visibleCount === 0;
            }
        };

        searchInput.addEventListener('input', filterCards);
    }

    initBarangayProgramShowcase();
});

function initBarangayProgramShowcase() {
    const grid = document.getElementById('baProgramGrid');
    if (!grid) {
        return;
    }

    const cards = Array.from(grid.querySelectorAll('.ba-program-card'));
    const searchInput = document.getElementById('baProgramSearch');
    const categoryFilter = document.getElementById('baCategoryFilter');
    const statusFilter = document.getElementById('baStatusFilter');
    const yearFilter = document.getElementById('baYearFilter');
    const sortFilter = document.getElementById('baSortFilter');
    const emptyFilter = document.getElementById('baEmptyFilter');
    const loadMore = document.getElementById('baLoadMore');
    const pageSize = 6;
    let visibleLimit = pageSize;
    let currentView = 'grid';

    const matches = (card) => {
        const query = (searchInput?.value || '').trim().toLowerCase();
        const category = categoryFilter?.value || '';
        const status = statusFilter?.value || '';
        const year = yearFilter?.value || '';

        return (!query || (card.dataset.title || '').includes(query))
            && (!category || card.dataset.category === category)
            && (!status || card.dataset.status === status)
            && (!year || card.dataset.year === year);
    };

    const apply = () => {
        const matched = cards.filter(matches);
        const sortBy = sortFilter?.value || 'latest';

        matched.sort((a, b) => {
            if (sortBy === 'name') {
                return (a.dataset.title || '').localeCompare(b.dataset.title || '');
            }
            const aDate = a.dataset.sortDate || '';
            const bDate = b.dataset.sortDate || '';
            return sortBy === 'oldest' ? aDate.localeCompare(bDate) : bDate.localeCompare(aDate);
        });

        matched.forEach((card) => grid.appendChild(card));

        let shown = 0;
        cards.forEach((card) => {
            const isMatch = matched.includes(card);
            const canShow = isMatch && shown < visibleLimit;
            card.hidden = !canShow;
            if (canShow) {
                shown += 1;
            }
        });

        if (emptyFilter) {
            emptyFilter.hidden = matched.length > 0;
        }
        if (loadMore) {
            loadMore.hidden = shown >= matched.length;
        }
    };

    [searchInput, categoryFilter, statusFilter, yearFilter, sortFilter].forEach((el) => {
        el?.addEventListener('input', () => {
            visibleLimit = pageSize;
            apply();
        });
        el?.addEventListener('change', () => {
            visibleLimit = pageSize;
            apply();
        });
    });

    document.querySelectorAll('.ba-category-item').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.ba-category-item').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            if (categoryFilter) {
                categoryFilter.value = button.dataset.category || '';
            }
            visibleLimit = pageSize;
            apply();
        });
    });

    document.querySelectorAll('.ba-view-btn').forEach((button) => {
        button.addEventListener('click', () => {
            currentView = button.dataset.view || 'grid';
            document.querySelectorAll('.ba-view-btn').forEach((item) => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            grid.classList.toggle('is-list', currentView === 'list');
        });
    });

    loadMore?.addEventListener('click', () => {
        visibleLimit += pageSize;
        apply();
    });

    apply();
}
