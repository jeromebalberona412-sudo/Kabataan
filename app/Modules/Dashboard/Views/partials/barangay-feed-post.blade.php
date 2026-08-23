@php
    $postType = strtolower((string) ($post['type'] ?? 'update'));
    $typeLabel = ucfirst($postType);
    $authorName = $post['author_name'] ?? ('SK Brgy. '.$name);
    $avatar = $post['author_avatar_url'] ?? ($post['barangay_logo_url'] ?? ($logo_url ?? null));
    $images = $post['images'] ?? [];
    if ($images === [] && ! empty($post['image_url'])) {
        $images = [$post['image_url']];
    }
    $imageCount = min(count($images), 4);
    $commentTotal = (int) ($post['comment_count'] ?? 0);
    $likes = (int) ($post['likes'] ?? 0);
    $canEngagePost = (bool) ($canEngage ?? false);
    $liked = (bool) ($post['liked'] ?? false);
    $reactionType = (string) ($post['reaction_type'] ?? ($liked ? 'like' : ''));
    $reactionEmojis = ['like' => '👍', 'love' => '❤️', 'haha' => '😂', 'wow' => '😮', 'sad' => '😢', 'angry' => '😡'];
    $reactionLabels = ['like' => 'Like', 'love' => 'Love', 'haha' => 'Haha', 'wow' => 'Wow', 'sad' => 'Sad', 'angry' => 'Angry'];
    $reactionCounts = $post['reaction_counts'] ?? [];
    $reactionFaces = [];
    foreach ($reactionEmojis as $faceType => $emoji) {
        $faceCount = (int) ($reactionCounts[$faceType] ?? 0);
        if ($faceCount > 0) {
            $reactionFaces[$faceType] = $faceCount;
        }
    }
    arsort($reactionFaces);
    $reactionFaces = array_slice($reactionFaces, 0, 3, true);
@endphp
<article class="post-card" data-post-type="{{ $postType }}" data-post-id="{{ $post['id'] }}">
    <div class="post-header">
        @if (!empty($avatar))
            <img src="{{ $avatar }}" alt="{{ $authorName }}" class="post-avatar">
        @else
            <div class="post-avatar-lg" style="background:{{ $color }};">{{ $initials ?? strtoupper(substr($name, 0, 2)) }}</div>
        @endif
        <div class="post-info">
            <h3 class="post-author">{{ $authorName }}</h3>
            <p class="post-meta">
                <span class="post-type {{ $postType }}">{{ $typeLabel }}</span>
                <span class="post-time">{{ $post['time'] ?? ($post['posted_at'] ?? '') }}</span>
            </p>
        </div>
    </div>
    <div class="post-content">
        @if (!empty($post['title']))
            <h2 class="post-title">{{ $post['title'] }}</h2>
        @endif
        @if (!empty($post['body']))
            <p class="post-text">{{ $post['body'] }}</p>
        @endif
        @if ($imageCount > 0)
            <div class="post-images post-images--{{ $imageCount }}">
                @foreach (array_slice($images, 0, 4) as $image)
                    <img src="{{ $image }}" alt="" loading="lazy">
                @endforeach
            </div>
        @endif
    </div>
    @if ($likes > 0 || $commentTotal > 0)
        <div class="post-engage">
            <div class="reaction-summary">
                @if ($likes > 0)
                    <button type="button" class="reaction-summary-left" data-open-reactions="{{ $post['id'] }}" aria-label="See who reacted">
                        <span class="reaction-faces">
                            @foreach ($reactionFaces as $faceType => $faceCount)
                                <span class="reaction-face">{{ $reactionEmojis[$faceType] }}</span>
                            @endforeach
                        </span>
                        <span class="reaction-total" data-like-count>{{ $likes }}</span>
                    </button>
                @else
                    <div class="reaction-summary-left"></div>
                @endif
                @if ($commentTotal > 0)
                    <button type="button" class="reaction-summary-comments" data-open-comments="{{ $post['id'] }}">
                        {{ $commentTotal }} {{ $commentTotal === 1 ? 'comment' : 'comments' }}
                    </button>
                @endif
            </div>
        </div>
    @endif
    <div class="post-actions">
        @if ($canEngagePost)
            <div class="reaction-wrap" data-target="post" data-post-id="{{ $post['id'] }}">
                <button type="button" class="action-btn reaction-btn{{ $liked ? ' liked' : '' }}" data-type="{{ $reactionType }}" id="feed-like-btn-{{ $post['id'] }}" aria-label="React">
                    <span class="reaction-icon">
                        @if ($reactionType !== '' && isset($reactionEmojis[$reactionType]))
                            <span class="reaction-current">{{ $reactionEmojis[$reactionType] }}</span>
                        @else
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>
                        @endif
                    </span>
                    <span class="reaction-label">{{ $reactionLabels[$reactionType] ?? 'Like' }}</span>
                </button>
                <div class="reaction-picker">
                    <div class="reaction-picker-inner">
                        @foreach ($reactionEmojis as $type => $emoji)
                            <button type="button" class="reaction-option{{ $reactionType === $type ? ' is-active' : '' }}" data-type="{{ $type }}" title="{{ $type }}">{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <button
                type="button"
                class="action-btn{{ $likes > 0 ? '' : ' is-disabled' }}"
                @if ($likes > 0)
                    data-open-reactions="{{ $post['id'] }}"
                    aria-label="See who reacted"
                @else
                    disabled
                    aria-label="Like"
                @endif
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>
                <span>Like{{ $likes > 0 ? ' ('.$likes.')' : '' }}</span>
            </button>
        @endif
        <button type="button" class="action-btn comment-btn" data-open-comments="{{ $post['id'] }}">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
            <span>{{ $canEngagePost ? 'Comment' : 'View comments' }}{{ $commentTotal > 0 ? ' ('.$commentTotal.')' : '' }}</span>
        </button>
    </div>
</article>
