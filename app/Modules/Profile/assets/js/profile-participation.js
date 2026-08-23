/**
 * Program Participation — click to open submitted application or survey
 */
(function () {
    'use strict';

    function goToProgramParticipation(item) {
        const url = item?.getAttribute('data-redirect-url');
        if (!url) return;
        window.location.href = url;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.program-item--clickable').forEach((item) => {
            item.addEventListener('click', () => goToProgramParticipation(item));
            item.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    goToProgramParticipation(item);
                }
            });
        });
    });
})();
