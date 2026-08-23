document.addEventListener('DOMContentLoaded', function() {
    // Modal open/close functions for program categories
    window.openEducationModal = function() {
        const modal = document.getElementById('educationModal');
        if (modal) modal.classList.add('active');
    };

    window.closeEducationModal = function() {
        const modal = document.getElementById('educationModal');
        if (modal) modal.classList.remove('active');
    };

    window.openAntiDrugsModal = function() {
        const modal = document.getElementById('antiDrugsModal');
        if (modal) modal.classList.add('active');
    };

    window.closeAntiDrugsModal = function() {
        const modal = document.getElementById('antiDrugsModal');
        if (modal) modal.classList.remove('active');
    };

    window.openAgricultureModal = function() {
        const modal = document.getElementById('agricultureModal');
        if (modal) modal.classList.add('active');
    };

    window.closeAgricultureModal = function() {
        const modal = document.getElementById('agricultureModal');
        if (modal) modal.classList.remove('active');
    };

    window.openDisasterModal = function() {
        const modal = document.getElementById('disasterModal');
        if (modal) modal.classList.add('active');
    };

    window.closeDisasterModal = function() {
        const modal = document.getElementById('disasterModal');
        if (modal) modal.classList.remove('active');
    };

    window.openSportsModal = function() {
        const modal = document.getElementById('sportsModal');
        if (modal) modal.classList.add('active');
    };

    window.closeSportsModal = function() {
        const modal = document.getElementById('sportsModal');
        if (modal) modal.classList.remove('active');
    };

    window.openGenderModal = function() {
        const modal = document.getElementById('genderModal');
        if (modal) modal.classList.add('active');
    };

    window.closeGenderModal = function() {
        const modal = document.getElementById('genderModal');
        if (modal) modal.classList.remove('active');
    };

    window.openHealthModal = function() {
        const modal = document.getElementById('healthModal');
        if (modal) modal.classList.add('active');
    };

    window.closeHealthModal = function() {
        const modal = document.getElementById('healthModal');
        if (modal) modal.classList.remove('active');
    };

    window.openOthersModal = function() {
        const modal = document.getElementById('othersModal');
        if (modal) modal.classList.add('active');
    };

    window.closeOthersModal = function() {
        const modal = document.getElementById('othersModal');
        if (modal) modal.classList.remove('active');
    };

    // Terms toggle function
    window.toggleTerms = function() {
        const termsContent = document.getElementById('termsContent');
        const chevron = document.querySelector('.chevron-icon');
        if (termsContent) {
            termsContent.classList.toggle('expanded');
            if (chevron) {
                chevron.style.transform = termsContent.classList.contains('expanded') ? 'rotate(180deg)' : 'rotate(0)';
            }
        }
    };

    // Apply button toggle
    window.toggleApplyButton = function() {
        const checkbox = document.getElementById('agreeTerms');
        const button = document.getElementById('applyNowBtn');
        if (checkbox && button) {
            button.disabled = !checkbox.checked;
        }
    };

    // Global handler for category clicks
    window.handleCategoryClick = function(categoryId) {
        console.log('Category clicked:', categoryId);
        if (window.programsModule && window.programsModule.openCategoryModal) {
            window.programsModule.openCategoryModal(categoryId);
        } else {
            console.error('programsModule not available');
        }
    };

    // Comment toggle functionality
    const commentButtons = document.querySelectorAll('.comment-btn');
    commentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postCard = this.closest('.post-card');
            const commentsSection = postCard.querySelector('.comments-section');
            
            if (commentsSection.style.display === 'none' || !commentsSection.style.display) {
                commentsSection.style.display = 'block';
            } else {
                commentsSection.style.display = 'none';
            }
        });
    });

    // Program category click handlers
    const programCategories = document.querySelectorAll('.program-category');
    programCategories.forEach(category => {
        category.addEventListener('click', function() {
            const categoryType = this.dataset.category;
            window.programsModule.openCategoryModal(categoryType);
        });
    });

    // Program modals are now handled by the programs module

    // Modal close buttons (static modals only — dynamic chrome modals handle their own close)
    document.querySelectorAll('.program-modal:not(.program-modal--chrome) .modal-close').forEach((button) => {
        button.addEventListener('click', function () {
            const modal = this.closest('.program-modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close modal when clicking overlay (skip chrome-enhanced modals — they replace overlay listeners)
    document.querySelectorAll('.program-modal:not(.program-modal--chrome) .modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', function () {
            const modal = this.closest('.program-modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Scholarship form submission is now handled by the programs module

    // Send comment functionality
    const sendCommentButtons = document.querySelectorAll('.send-comment-btn');
    sendCommentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const wrapper = this.closest('.comment-input-wrapper');
            const input = wrapper.querySelector('.comment-input');
            const commentText = input.value.trim();
            
            if (commentText) {
                // Create new comment element
                const commentsSection = this.closest('.comments-section');
                const newComment = createCommentElement(commentText);
                
                // Insert before input wrapper
                commentsSection.insertBefore(newComment, wrapper);
                
                // Clear input
                input.value = '';
                
                // Update comment count
                const postCard = this.closest('.post-card');
                updateCommentCount(postCard);
            }
        });
    });

    // Enter key to send comment
    const commentInputs = document.querySelectorAll('.comment-input');
    commentInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const sendButton = this.nextElementSibling;
                if (sendButton) {
                    sendButton.click();
                }
            }
        });
    });

    // Helper function to create comment element
    function createCommentElement(text) {
        const div = document.createElement('div');
        div.className = 'comment-item';
        div.innerHTML = `
            <img src="https://ui-avatars.com/api/?name=You&background=667eea&color=fff" alt="You">
            <div class="comment-content">
                <p class="comment-author">You</p>
                <p class="comment-text">${escapeHtml(text)}</p>
                <span class="comment-time">Just now</span>
            </div>
        `;
        return div;
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Helper function to update comment count
    function updateCommentCount(postCard) {
        const commentBtn = postCard.querySelector('.comment-btn span');
        if (commentBtn) {
            const currentText = commentBtn.textContent;
            const match = currentText.match(/\d+/);
            if (match) {
                const count = parseInt(match[0]) + 1;
                commentBtn.textContent = `Comment (${count})`;
            }
        }
    }

    // Like button functionality
    const likeButtons = document.querySelectorAll('.action-btn:first-child');
    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const span = this.querySelector('span');
            if (span) {
                const currentText = span.textContent;
                const match = currentText.match(/\d+/);
                if (match) {
                    const count = parseInt(match[0]);
                    const isLiked = this.classList.contains('liked');
                    
                    if (isLiked) {
                        span.textContent = `Like (${count - 1})`;
                        this.classList.remove('liked');
                        this.style.color = '#666';
                    } else {
                        span.textContent = `Like (${count + 1})`;
                        this.classList.add('liked');
                        this.style.color = '#667eea';
                    }
                }
            }
        });
    });

    // View details button functionality
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.programsModule.openCategoryModal('education');
        });
    });

    console.log('Dashboard initialized successfully!');

    document.querySelectorAll('#programsDrawerSidebar .brgy-profile-item').forEach((item) => {
        item.addEventListener('click', () => {
            if (typeof window.kabataanCloseProgramsDrawer === 'function') {
                window.kabataanCloseProgramsDrawer();
            }
        });
    });

    const openParam = new URLSearchParams(window.location.search).get('open');
    if (openParam === 'sports' && typeof window.openSportsModal === 'function') {
        window.openSportsModal();
    }
});


// Feed rendering is handled inline in dashboard::index (loadFeed + /api/feed).
