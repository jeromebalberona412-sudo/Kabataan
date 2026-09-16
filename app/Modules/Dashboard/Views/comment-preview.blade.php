<div class="cp-shell" id="commentPreviewShell" hidden>
    <article class="cp-modal" id="commentPreviewModal" role="dialog" aria-modal="true" aria-labelledby="cpTitle">
        <header class="cp-header">
            <h1 class="cp-title" id="cpTitle">Post</h1>
            <button type="button" class="cp-close" id="cpClose" aria-label="Close">&times;</button>
        </header>

        <div class="cp-scroll" id="cpScroll">
            <div class="cp-post" id="cpPost"></div>
            <div class="cp-engage" id="cpEngage"></div>
            <div class="cp-sort">
                <button type="button" class="cp-sort-btn" id="cpSortBtn">Most relevant</button>
                <div class="cp-sort-menu" id="cpSortMenu">
                    <button type="button" data-sort="relevant">Most relevant</button>
                    <button type="button" data-sort="newest">Newest</button>
                    <button type="button" data-sort="oldest">Oldest</button>
                </div>
            </div>
            <div class="cp-comments" id="cpComments"></div>
        </div>

        <footer class="cp-composer" id="cpComposer">
            <img src="{{ $userAvatarUrl ?? asset('images/SK_OnePortal_logo.png') }}" alt="You" class="cp-composer-avatar" id="cpComposerAvatar">
            <div class="cp-composer-box">
                <input type="text" id="cpCommentInput" class="cp-composer-input" maxlength="1000" placeholder="Comment as {{ $user->name ?? 'Kabataan' }}" autocomplete="off">
                <button type="button" class="cp-send-btn" id="cpSendBtn" aria-label="Send comment" disabled>
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                </button>
            </div>
        </footer>
    </article>
</div>

<div id="cpReactionViewer" class="cp-viewer" hidden>
    <div class="cp-viewer-overlay" id="cpViewerOverlay"></div>
    <div class="cp-viewer-panel" role="dialog" aria-label="People who reacted">
        <div class="cp-viewer-header">
            <div class="cp-viewer-tabs" id="cpViewerTabs"></div>
            <button type="button" class="cp-viewer-close" id="cpViewerClose" aria-label="Close">&times;</button>
        </div>
        <div class="cp-viewer-list" id="cpViewerList"></div>
    </div>
</div>

@once('kabataan-feed-toast')
<div id="feedToast" class="feed-toast" role="status" aria-live="polite"></div>
@endonce

@once('kabataan-comment-action-modals')
<div id="editCommentModal" class="program-modal comment-action-modal">
    <div class="modal-overlay" data-close-edit-comment></div>
    <div class="modal-container comment-action-container">
        <div class="modal-header">
            <h2 id="editCommentModalTitle">Edit Comment</h2>
            <button type="button" class="modal-close" data-close-edit-comment aria-label="Close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
        </div>
        <div class="modal-body">
            <textarea id="editCommentBody" class="edit-comment-textarea" maxlength="1000" rows="1" placeholder="Write a comment..." autocomplete="off"></textarea>
            <span class="edit-comment-counter" id="editCommentCounter">0 / 1000</span>
        </div>
        <div class="modal-footer-btns comment-action-footer">
            <button type="button" class="btn-secondary" data-close-edit-comment>Cancel</button>
            <button type="button" class="btn-primary" id="confirmEditCommentBtn">
                <span class="btn-label">Save</span>
                <span class="btn-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</div>

<div id="deleteCommentModal" class="program-modal comment-action-modal">
    <div class="modal-overlay" data-close-delete-comment></div>
    <div class="modal-container comment-action-container">
        <div class="modal-header">
            <h2 id="deleteCommentModalTitle">Delete Comment</h2>
            <button type="button" class="modal-close" data-close-delete-comment aria-label="Close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
        </div>
        <div class="modal-body">
            <p id="deleteCommentModalBody" style="font-size:14px;color:#555;line-height:1.65;margin:0;">Delete this comment? This cannot be undone.</p>
        </div>
        <div class="modal-footer-btns comment-action-footer">
            <button type="button" class="btn-secondary" data-close-delete-comment>Cancel</button>
            <button type="button" class="btn-danger" id="confirmDeleteCommentBtn">
                <span class="btn-label">Delete</span>
                <span class="btn-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</div>
@endonce
