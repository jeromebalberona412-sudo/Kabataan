<?php

namespace App\Modules\Dashboard\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementCommentReaction;
use App\Models\AnnouncementReaction;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Modules\Profile\Services\ProfileImageService;
use App\Services\BarangayLogoUrlService;
use App\Services\CloudinaryService;
use App\Services\FeedCommentRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnnouncementFeedController extends Controller
{
    private const USER_TYPE = 'kabataan';

    public function __construct(
        private readonly ProfileImageService $profileImages,
        private readonly BarangayLogoUrlService $logoUrls,
        private readonly CloudinaryService $cloudinary,
    ) {}

    public function feed(Request $request): JsonResponse
    {
        $user = Auth::user();
        $barangayId = $this->resolveBarangayId($user);
        $scopeBarangayId = (int) $request->query('barangay_id', 0);

        if ($scopeBarangayId > 0) {
            abort_unless($this->canViewBarangay($user, $scopeBarangayId), 404);

            $posts = Announcement::with([
                'barangay',
                'user',
                'images',
                'reactions',
            ])
                ->withCount(['reactions', 'comments'])
                ->active()
                ->where('barangay_id', $scopeBarangayId)
                ->whereRaw('"is_federation_wide" = false')
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get();

            $canEngage = $this->canEngageWithPostBarangay($user, $scopeBarangayId);

            return response()->json([
                'data' => $posts->map(function (Announcement $post) use ($user, $canEngage) {
                    $data = $this->formatPost($post, $user->id);
                    $data['type'] = strtolower((string) ($data['type'] ?? 'update'));
                    $data['can_engage'] = $canEngage;

                    return $data;
                })->values(),
                'current_page' => 1,
                'last_page' => 1,
                'total' => $posts->count(),
            ]);
        }

        if (! $barangayId) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'user_id' => $user->id,
            ]);
        }

        $query = Announcement::with([
            'barangay',
            'user',
            'images',
            'reactions',
        ])
            ->withCount(['reactions', 'comments'])
            ->active()
            ->where(function ($q) use ($barangayId) {
                $q->where('barangay_id', $barangayId)
                    ->orWhereRaw('"is_federation_wide" = true');
            })
            ->orderByDesc('created_at');

        if ($request->filter && $request->filter !== 'all') {
            $query->where('type', $request->filter);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        $posts = $query->paginate(10);

        return response()->json([
            'data' => collect($posts->items())->map(fn ($p) => $this->formatPost($p, $user->id)),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
            'user_id' => $user->id,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        return response()->json($this->formattedVisiblePost($user, $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function formattedVisiblePost(User $user, int $id): array
    {
        $post = Announcement::with([
            'barangay',
            'user',
            'images',
            'reactions.user',
            'comments.user',
            'comments.reactions.user',
        ])
            ->withCount('reactions')
            ->active()
            ->where('id', $id)
            ->firstOrFail();

        abort_unless($this->canViewPost($user, $post), 404);

        $data = $this->formatPost($post, $user->id);
        $data['type'] = strtolower((string) ($data['type'] ?? ''));
        $data['can_engage'] = $this->canEngageWithPost($user, $post);

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentBarangayPosts(int $barangayId, User $user): array
    {
        $posts = Announcement::with([
            'barangay',
            'user',
            'images',
            'reactions.user',
            'comments.user',
            'comments.reactions.user',
        ])
            ->withCount('reactions')
            ->active()
            ->where('barangay_id', $barangayId)
            ->whereRaw('"is_federation_wide" = false')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $canEngage = $this->canEngageWithPostBarangay($user, $barangayId);

        return $posts->map(function (Announcement $post) use ($user, $canEngage) {
            $data = $this->formatPost($post, $user->id);
            $data['type'] = strtolower((string) ($data['type'] ?? 'update'));
            $data['can_engage'] = $canEngage;

            return $data;
        })->values()->all();
    }

    public function likes(int $id): JsonResponse
    {
        Announcement::query()->active()->findOrFail($id);

        $reactions = AnnouncementReaction::query()
            ->with('user')
            ->where('community_feed_id', $id)
            ->latest()
            ->get();

        $registrations = $this->kabataanRegistrationsForReactions($reactions);

        return response()->json([
            'count' => $reactions->count(),
            'reaction_counts' => $this->countsFromReactions($reactions),
            'reactors' => $reactions->map(fn (AnnouncementReaction $reaction) => [
                'name' => $this->resolveReactorName($reaction, $registrations),
                'avatar_url' => $this->resolveReactorAvatar($reaction, null),
                'role_label' => $this->roleLabel($reaction->user_type),
                'reaction_type' => $reaction->reaction_type ?: 'like',
            ])->values(),
        ]);
    }

    public function react(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $post = Announcement::query()->active()->findOrFail($id);
        abort_unless($this->canEngageWithPost($user, $post), 403, 'You can only react to posts from your barangay.');
        $validated = $request->validate([
            'reaction_type' => ['nullable', 'string', Rule::in(AnnouncementReaction::TYPES)],
            'client_seq' => ['sometimes', 'integer', 'min:1'],
        ]);
        $type = $validated['reaction_type'] ?? 'like';
        $seq = (int) ($validated['client_seq'] ?? 0);
        $seqKey = "community-feed-reaction-seq:{$user->id}:post:{$id}";
        $lock = Cache::lock("community-feed-reaction:{$user->id}:post:{$id}", 8);

        $userReaction = $lock->block(5, function () use ($id, $user, $type, $seq, $seqKey) {
            return DB::transaction(function () use ($id, $user, $type, $seq, $seqKey) {
                $existing = AnnouncementReaction::query()
                    ->where('community_feed_id', $id)
                    ->where('user_id', $user->id)
                    ->where('user_type', self::USER_TYPE)
                    ->lockForUpdate()
                    ->first();

                if ($this->isStaleReactionSeq($seqKey, $seq)) {
                    return $existing?->reaction_type;
                }

                if ($existing && $existing->reaction_type === $type) {
                    $existing->delete();

                    return null;
                }

                if ($existing) {
                    $existing->update(['reaction_type' => $type]);

                    return $type;
                }

                AnnouncementReaction::create([
                    'community_feed_id' => $id,
                    'user_id' => $user->id,
                    'user_type' => self::USER_TYPE,
                    'reaction_type' => $type,
                ]);

                return $type;
            });
        });

        $reactions = AnnouncementReaction::with('user')
            ->where('community_feed_id', $id)
            ->latest()
            ->limit(12)
            ->get();
        $counts = $this->countsFromReactions(
            AnnouncementReaction::query()->where('community_feed_id', $id)->get()
        );
        $count = array_sum($counts);
        $post = Announcement::query()->find($id);
        $registrations = $this->kabataanRegistrationsForReactions($reactions);

        return response()->json([
            'liked' => $userReaction !== null,
            'count' => $count,
            'reaction_type' => $userReaction,
            'reaction_counts' => $counts,
            'reactions_summary' => $this->formatReactionsSummary(
                $reactions,
                $count,
                $post?->barangay_id,
                $registrations,
            ),
        ]);
    }

    public function comment(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $limiter = app(FeedCommentRateLimiter::class);
        $cooldown = $limiter->check(self::USER_TYPE, (int) $user->id);

        if (! $cooldown['allowed']) {
            return response()->json([
                'message' => $cooldown['message'],
                'retry_after' => $cooldown['retry_after'],
            ], 429);
        }

        $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
            'parent_id' => 'nullable|integer',
        ]);

        $post = Announcement::query()->active()->findOrFail($id);
        abort_unless($this->canEngageWithPost($user, $post), 403, 'You can only comment on posts from your barangay.');

        if ($request->filled('parent_id')) {
            AnnouncementComment::query()
                ->where('id', (int) $request->parent_id)
                ->where('community_feed_id', $id)
                ->firstOrFail();
        }

        $registration = KabataanRegistration::where('user_id', $user->id)->latest()->first();
        $authorName = $registration
            ? trim($registration->first_name.' '.$registration->last_name)
            : $user->name;

        $comment = AnnouncementComment::create([
            'community_feed_id' => $id,
            'parent_id' => $request->input('parent_id'),
            'user_id' => $user->id,
            'user_type' => self::USER_TYPE,
            'author_name' => $authorName !== '' ? $authorName : $user->name,
            'body' => $request->body,
        ]);

        $comment->load(['user', 'reactions']);
        $limiter->hit(self::USER_TYPE, (int) $user->id);

        return response()->json($this->formatComment($comment, $user->id), 201);
    }

    public function updateComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
        ]);

        $comment = $this->ownedComment($id, $commentId, $user);
        $comment->update(['body' => $request->body]);
        $comment->load(['user', 'reactions']);

        return response()->json($this->formatComment($comment, $user->id));
    }

    public function destroyComment(int $id, int $commentId): JsonResponse
    {
        $comment = $this->ownedComment($id, $commentId, Auth::user());
        $comment->delete();

        return response()->json(['success' => true]);
    }

    public function commentReactions(int $id, int $commentId): JsonResponse
    {
        Announcement::query()->active()->findOrFail($id);
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $reactions = AnnouncementCommentReaction::query()
            ->with('user')
            ->where('comment_id', $comment->id)
            ->latest()
            ->get();
        $registrations = $this->kabataanRegistrationsForUserIds(
            $reactions->where('user_type', self::USER_TYPE)->pluck('user_id')
        );

        return response()->json([
            'count' => $reactions->count(),
            'reaction_counts' => $this->countsFromReactions($reactions),
            'reactors' => $reactions->map(fn (AnnouncementCommentReaction $reaction) => [
                'name' => $reaction->user_type === self::USER_TYPE
                    ? $this->kabataanDisplayName($reaction->user, $registrations)
                    : ($reaction->user?->name ?: 'Member'),
                'avatar_url' => $this->resolveCommentReactorAvatar($reaction),
                'reaction_type' => $reaction->reaction_type ?: 'like',
            ])->values(),
        ]);
    }

    public function commentReact(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $post = Announcement::query()->active()->findOrFail($id);
        abort_unless($this->canEngageWithPost($user, $post), 403, 'You can only react to comments from your barangay.');
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $validated = $request->validate([
            'reaction_type' => ['required', 'string', Rule::in(AnnouncementReaction::TYPES)],
            'client_seq' => ['sometimes', 'integer', 'min:1'],
        ]);
        $type = $validated['reaction_type'];
        $seq = (int) ($validated['client_seq'] ?? 0);
        $seqKey = "community-feed-reaction-seq:{$user->id}:comment:{$comment->id}";
        $lock = Cache::lock("community-feed-reaction:{$user->id}:comment:{$comment->id}", 8);

        $userReaction = $lock->block(5, function () use ($comment, $user, $type, $seq, $seqKey) {
            return DB::transaction(function () use ($comment, $user, $type, $seq, $seqKey) {
                $existing = AnnouncementCommentReaction::query()
                    ->where('comment_id', $comment->id)
                    ->where('user_id', $user->id)
                    ->where('user_type', self::USER_TYPE)
                    ->lockForUpdate()
                    ->first();

                if ($this->isStaleReactionSeq($seqKey, $seq)) {
                    return $existing?->reaction_type;
                }

                if ($existing && $existing->reaction_type === $type) {
                    $existing->delete();

                    return null;
                }

                if ($existing) {
                    $existing->update(['reaction_type' => $type]);

                    return $type;
                }

                AnnouncementCommentReaction::create([
                    'comment_id' => $comment->id,
                    'user_id' => $user->id,
                    'user_type' => self::USER_TYPE,
                    'reaction_type' => $type,
                ]);

                return $type;
            });
        });

        $reactions = AnnouncementCommentReaction::query()->where('comment_id', $comment->id)->get();
        $counts = $this->countsFromReactions($reactions);

        return response()->json([
            'liked' => $userReaction !== null,
            'count' => array_sum($counts),
            'reaction_type' => $userReaction,
            'reaction_counts' => $counts,
        ]);
    }

    private function formatPost(Announcement $post, int $userId): array
    {
        $reactionCounts = $this->countsFromReactions(
            $post->relationLoaded('reactions') ? $post->reactions : collect()
        );
        $userReaction = $this->userReactionType(
            $post->relationLoaded('reactions') ? $post->reactions : collect(),
            $userId,
            self::USER_TYPE
        );
        $authorName = $post->user?->name
            ?? ($post->is_federation_wide ? 'SK Federation' : ('SK Brgy. '.($post->barangay?->name ?? '')));
        $commentsLoaded = $post->relationLoaded('comments');
        $registrations = $commentsLoaded
            ? $this->kabataanRegistrationsForPost($post)
            : collect();

        $imageRecords = $post->relationLoaded('images') ? $post->images : collect();
        $images = $imageRecords
            ->map(fn ($img) => $this->cloudinary->normalizeUrl($img->image_url))
            ->filter(fn ($url) => ! empty($url))
            ->values()
            ->all();

        $comments = $commentsLoaded ? $post->comments : collect();
        $reactionsSummary = $commentsLoaded && $post->relationLoaded('reactions')
            ? $this->formatReactionsSummary(
                $post->reactions,
                array_sum($reactionCounts),
                $post->barangay_id,
                $registrations,
            )
            : ['count' => array_sum($reactionCounts), 'names_label' => '', 'reactors' => []];

        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'image_url' => $images[0] ?? null,
            'images' => $images,
            'link_url' => $post->link_url,
            'is_federation_wide' => (bool) $post->is_federation_wide,
            'barangay_name' => $post->barangay?->name,
            'barangay_logo_url' => $this->logoUrls->resolve($post->barangay_id),
            'author_name' => $authorName,
            'author_avatar_url' => $this->resolvePostAuthorAvatar($post),
            'owned' => false,
            'likes' => array_sum($reactionCounts),
            'liked' => $userReaction !== null,
            'reaction_type' => $userReaction,
            'reaction_counts' => $reactionCounts,
            'reactors' => $reactionsSummary['reactors'],
            'time' => $post->created_at?->diffForHumans() ?? 'Just now',
            'reactions_summary' => $reactionsSummary,
            'comment_count' => $post->relationLoaded('comments')
                ? $comments->count()
                : (int) ($post->comments_count ?? 0),
            'comments_loaded' => $post->relationLoaded('comments'),
            'comments' => $post->relationLoaded('comments')
                ? $this->formatCommentTree($comments, $userId)
                : [],
        ];
    }

    /**
     * @param  Collection<int, AnnouncementComment>  $comments
     * @return list<array<string, mixed>>
     */
    private function formatCommentTree(Collection $comments, int $userId): array
    {
        $byParent = $comments->groupBy(fn (AnnouncementComment $comment) => $comment->parent_id ?? 0);

        return $byParent->get(0, collect())
            ->map(function (AnnouncementComment $comment) use ($byParent, $userId) {
                $comment->setRelation('replies', $byParent->get($comment->id, collect())->values());

                return $this->formatComment($comment, $userId);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatComment(AnnouncementComment $comment, int $userId): array
    {
        $reactionCounts = $this->countsFromReactions(
            $comment->relationLoaded('reactions') ? $comment->reactions : collect()
        );
        $userReaction = $this->userReactionType(
            $comment->relationLoaded('reactions') ? $comment->reactions : collect(),
            $userId,
            self::USER_TYPE
        );
        $post = $comment->relationLoaded('communityFeed') ? $comment->communityFeed : null;

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author_name' => $comment->author_name,
            'body' => $comment->body,
            'time' => $comment->created_at?->diffForHumans() ?? 'Just now',
            'user_type' => $comment->user_type,
            'owned' => (int) $comment->user_id === $userId && $comment->user_type === self::USER_TYPE,
            'author_avatar_url' => $this->resolveCommentAvatar($comment, $post?->barangay_id),
            'likes' => array_sum($reactionCounts),
            'liked' => $userReaction !== null,
            'reaction_type' => $userReaction,
            'reaction_counts' => $reactionCounts,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn ($reply) => $this->formatComment($reply, $userId))->values()->all()
                : [],
            'reply_count' => $comment->relationLoaded('replies') ? $comment->replies->count() : 0,
        ];
    }

    /**
     * @param  Collection<int, AnnouncementReaction>  $reactions
     * @return array{count: int, names_label: string, reactors: list<array{name: string, avatar_url: string, reaction_type: string}>}
     */
    private function formatReactionsSummary(
        Collection $reactions,
        int $totalCount,
        ?int $barangayId,
        Collection $registrations,
    ): array {
        $reactors = $reactions->map(fn (AnnouncementReaction $reaction) => [
            'name' => $this->resolveReactorName($reaction, $registrations),
            'avatar_url' => $this->resolveReactorAvatar($reaction, $barangayId),
            'reaction_type' => $reaction->reaction_type ?: 'like',
        ])->values();

        $count = max(0, $totalCount);
        $names = $reactors->pluck('name')->filter()->values();

        $namesLabel = match (true) {
            $count === 0 => '',
            $count === 1 => (string) $names->get(0),
            $count === 2 => $names->get(0).' and '.$names->get(1),
            default => $names->get(0).', '.$names->get(1).' and '.($count - 2).' others',
        };

        return [
            'count' => $count,
            'names_label' => $namesLabel,
            'reactors' => $reactors->all(),
        ];
    }

    private function resolvePostAuthorAvatar(Announcement $post): string
    {
        $logo = $this->logoUrls->resolve($post->barangay_id);
        if ($logo) {
            return $logo;
        }

        $photo = $this->absoluteProfilePhoto($post->user);
        if ($photo) {
            return $photo;
        }

        $name = $post->user?->name
            ?? ($post->is_federation_wide ? 'SK Federation' : ('SK '.($post->barangay?->name ?? '')));

        return $this->uiAvatarUrl($name);
    }

    private function resolveCommentAvatar(AnnouncementComment $comment, ?int $barangayId): string
    {
        if ($comment->user_type === self::USER_TYPE) {
            $photo = $this->absoluteProfilePhoto($comment->user);
            if ($photo) {
                return $photo;
            }

            return $this->uiAvatarUrl($comment->author_name ?: 'Youth Member');
        }

        if (in_array($comment->user_type, ['sk_official', 'sk_fed'], true)) {
            $logo = $this->logoUrls->resolve($comment->user?->barangay_id ?? $barangayId);
            if ($logo) {
                return $logo;
            }
            $photo = $this->absoluteProfilePhoto($comment->user);
            if ($photo) {
                return $photo;
            }
        }

        return $this->uiAvatarUrl($comment->author_name ?: 'Member');
    }

    private function absoluteProfilePhoto(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $raw = trim((string) ($user->profile_image_url ?? ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $this->cloudinary->normalizeUrl($raw) ?: $raw;
        }

        if (str_starts_with($raw, '/storage/') || str_starts_with($raw, 'storage/')) {
            return url('/'.ltrim($raw, '/'));
        }

        $normalized = $this->cloudinary->normalizeUrl($raw);

        return $normalized ?: null;
    }

    private function resolveReactorAvatar(AnnouncementReaction $reaction, ?int $barangayId): string
    {
        if ($reaction->user_type === self::USER_TYPE) {
            $photo = $this->absoluteProfilePhoto($reaction->user);
            if ($photo) {
                return $photo;
            }
        }

        if (in_array($reaction->user_type, ['sk_official', 'sk_fed'], true) && $reaction->user) {
            $logo = $this->logoUrls->resolve($reaction->user->barangay_id ?? $barangayId);
            if ($logo) {
                return $logo;
            }
        }

        return $this->uiAvatarUrl($reaction->user?->name ?? 'Member');
    }

    private function resolveCommentReactorAvatar(AnnouncementCommentReaction $reaction): string
    {
        if ($reaction->user_type === self::USER_TYPE) {
            $photo = $this->absoluteProfilePhoto($reaction->user);
            if ($photo) {
                return $photo;
            }
        }

        if (in_array($reaction->user_type, ['sk_official', 'sk_fed'], true) && $reaction->user) {
            $logo = $this->logoUrls->resolve($reaction->user->barangay_id);
            if ($logo) {
                return $logo;
            }
        }

        return $this->uiAvatarUrl($reaction->user?->name ?? 'Member');
    }

    private function resolveReactorName(AnnouncementReaction $reaction, Collection $registrations): string
    {
        if ($reaction->user_type === self::USER_TYPE && $reaction->user) {
            return $this->kabataanDisplayName($reaction->user, $registrations);
        }

        return $reaction->user?->name ?? 'Member';
    }

    private function kabataanDisplayName(?User $user, Collection $registrations): string
    {
        if (! $user) {
            return 'Youth Member';
        }

        $registration = $registrations->get($user->id);
        if ($registration) {
            $name = trim($registration->first_name.' '.$registration->last_name);

            return $name !== '' ? $name : ($user->name ?: 'Youth Member');
        }

        return $user->name ?: 'Youth Member';
    }

    /**
     * @param  Collection<int, AnnouncementReaction>  $reactions
     * @return Collection<int, KabataanRegistration>
     */
    private function kabataanRegistrationsForReactions(Collection $reactions): Collection
    {
        return $this->kabataanRegistrationsForUserIds(
            $reactions->where('user_type', self::USER_TYPE)->pluck('user_id')
        );
    }

    /**
     * @return Collection<int, KabataanRegistration>
     */
    private function kabataanRegistrationsForPost(Announcement $post): Collection
    {
        $userIds = collect();

        if ($post->relationLoaded('reactions')) {
            $userIds = $userIds->merge(
                $post->reactions->where('user_type', self::USER_TYPE)->pluck('user_id')
            );
        }

        if ($post->relationLoaded('comments')) {
            $userIds = $userIds->merge(
                $post->comments->where('user_type', self::USER_TYPE)->pluck('user_id')
            );
        }

        return $this->kabataanRegistrationsForUserIds($userIds);
    }

    private function kabataanRegistrationsForUserIds(Collection $userIds): Collection
    {
        $ids = $userIds->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return KabataanRegistration::query()
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');
    }

    private function ownedComment(int $feedId, int $commentId, User $user): AnnouncementComment
    {
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $feedId)
            ->where('id', $commentId)
            ->firstOrFail();

        if ((int) $comment->user_id !== (int) $user->id || $comment->user_type !== self::USER_TYPE) {
            abort(403, 'You cannot modify this comment.');
        }

        return $comment;
    }

    private function canViewBarangay(User $user, int $barangayId): bool
    {
        $tenantId = $this->resolveTenantId($user);
        if ($tenantId === null) {
            return false;
        }

        $barangayTenantId = DB::table('barangays')->where('id', $barangayId)->value('tenant_id');

        return $barangayTenantId !== null && (int) $barangayTenantId === $tenantId;
    }

    private function canViewPost(User $user, Announcement $post): bool
    {
        if ($post->is_federation_wide) {
            return true;
        }

        $viewerBarangayId = $this->resolveBarangayId($user);
        if ($viewerBarangayId && (int) $post->barangay_id === $viewerBarangayId) {
            return true;
        }

        $tenantId = $this->resolveTenantId($user);
        if ($tenantId === null) {
            return false;
        }

        $postTenantId = $post->relationLoaded('barangay')
            ? $post->barangay?->tenant_id
            : $post->barangay()->value('tenant_id');

        return $postTenantId !== null && (int) $postTenantId === $tenantId;
    }

    private function canEngageWithPost(User $user, Announcement $post): bool
    {
        if ($post->is_federation_wide) {
            return $this->resolveBarangayId($user) !== null;
        }

        return $this->canEngageWithPostBarangay($user, (int) $post->barangay_id);
    }

    private function canEngageWithPostBarangay(User $user, int $barangayId): bool
    {
        $viewerBarangayId = $this->resolveBarangayId($user);

        return $viewerBarangayId !== null && $viewerBarangayId === $barangayId;
    }

    private function resolveTenantId(User $user): ?int
    {
        $registration = KabataanRegistration::with('barangay')->where('user_id', $user->id)->latest()->first();
        $tenantId = $registration?->barangay?->tenant_id ?? $user->tenant_id;

        return $tenantId ? (int) $tenantId : null;
    }

    private function resolveBarangayId(User $user): ?int
    {
        $registration = KabataanRegistration::where('user_id', $user->id)->latest()->first();
        $barangayId = $registration?->barangay_id ?? $user->barangay_id;

        if (! $barangayId) {
            $registration = KabataanRegistration::where('email', $user->email)->latest()->first();
            $barangayId = $registration?->barangay_id;
        }

        return $barangayId ? (int) $barangayId : null;
    }

    /**
     * @param  Collection<int, mixed>  $reactions
     * @return array<string, int>
     */
    private function countsFromReactions(Collection $reactions): array
    {
        $counts = array_fill_keys(AnnouncementReaction::TYPES, 0);
        foreach ($reactions as $reaction) {
            $type = $reaction->reaction_type ?: 'like';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, mixed>|null  $reactions
     */
    private function userReactionType(?Collection $reactions, int $userId, string $userType): ?string
    {
        if ($reactions === null) {
            return null;
        }

        $match = $reactions->first(
            fn ($reaction) => (int) $reaction->user_id === $userId && $reaction->user_type === $userType
        );

        return $match?->reaction_type ? (string) $match->reaction_type : null;
    }

    private function isStaleReactionSeq(string $cacheKey, int $seq): bool
    {
        if ($seq <= 0) {
            return false;
        }

        $last = (int) Cache::get($cacheKey, 0);
        if ($seq < $last) {
            return true;
        }

        Cache::put($cacheKey, $seq, now()->addMinutes(10));

        return false;
    }

    private function roleLabel(?string $userType): string
    {
        return match ($userType) {
            'sk_fed' => 'SK Federation',
            'sk_official' => 'SK Official',
            self::USER_TYPE => 'Kabataan Member',
            default => 'Member',
        };
    }

    private function uiAvatarUrl(string $name): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=1a56db&color=fff&size=80';
    }
}
