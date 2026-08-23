<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementCommentReaction;
use App\Models\AnnouncementImage;
use App\Models\AnnouncementReaction;

test('kabataan community feed models use the shared community_feeds tables', function () {
    expect((new Announcement)->getTable())->toBe('community_feeds')
        ->and((new AnnouncementComment)->getTable())->toBe('community_feed_comments')
        ->and((new AnnouncementReaction)->getTable())->toBe('community_feed_reactions')
        ->and((new AnnouncementImage)->getTable())->toBe('community_feed_images')
        ->and((new AnnouncementCommentReaction)->getTable())->toBe('community_feed_comment_reactions');
});

test('kabataan reaction types match the community feed contract', function () {
    expect(AnnouncementReaction::TYPES)->toBe(['like', 'love', 'haha', 'wow', 'sad', 'angry']);
});
