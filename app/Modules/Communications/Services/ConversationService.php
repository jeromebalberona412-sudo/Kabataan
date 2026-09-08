<?php

namespace App\Modules\Communications\Services;

use App\Models\Barangay;
use App\Models\User;
use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\ConversationParticipant;
use App\Modules\Communications\Models\Message;
use App\Services\BarangayLogoUrlService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ConversationService
{
    /** @var array<int, string|null> */
    protected array $logoCache = [];

    public function __construct(
        protected ParticipantTypeResolver $types,
        protected BarangayLogoUrlService $barangayLogos
    ) {}

    public function isParticipant(Conversation $conversation, Authenticatable $user, ?string $userType = null): bool
    {
        $userType ??= $this->types->portalType();

        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', (int) $user->id)
            ->where('user_type', $userType)
            ->exists();
    }

    public function findOrCreatePrivate(Authenticatable $authUser, int $otherUserId): Conversation
    {
        $authType = $this->types->portalType();
        $other = User::query()->findOrFail($otherUserId);

        if ((int) $other->id === (int) $authUser->id) {
            abort(422, 'Cannot start a conversation with yourself.');
        }

        if (! $this->canMessage($authUser, $other)) {
            abort(403, 'You are not allowed to message this account.');
        }

        $otherType = $this->types->fromUser($other);

        $existingId = $this->findPrivateConversationId(
            (int) $authUser->id,
            $authType,
            (int) $other->id,
            $otherType
        );

        if ($existingId) {
            return Conversation::query()->findOrFail($existingId);
        }

        return DB::transaction(function () use ($authUser, $authType, $other, $otherType) {
            $conversation = Conversation::query()->create([
                'conversation_type' => 'private',
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => (int) $authUser->id,
                'user_type' => $authType,
                'joined_at' => now(),
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => (int) $other->id,
                'user_type' => $otherType,
                'joined_at' => now(),
            ]);

            return $conversation;
        });
    }

    public function listForUser(Authenticatable $user, ?string $search = null): Collection
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $conversationIds = ConversationParticipant::query()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->pluck('conversation_id');

        if ($conversationIds->isEmpty()) {
            return collect();
        }

        $conversationRelations = ['participants.user'];
        if (Schema::hasTable('barangays')) {
            $conversationRelations[] = 'participants.user.barangay';
        }
        if (Schema::hasTable('official_profiles')) {
            $conversationRelations[] = 'participants.user.officialProfile';
        }

        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with($conversationRelations)
            ->get();

        $lastQuery = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->orderByDesc('id');

        if (Schema::hasTable('message_hides')) {
            $lastQuery->whereDoesntHave('hides', function ($hideQuery) use ($userId, $userType) {
                $hideQuery->where('user_id', $userId)->where('user_type', $userType);
            });
        }

        $lastMessages = $lastQuery
            ->get()
            ->unique('conversation_id')
            ->keyBy('conversation_id');

        $myParticipants = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->get()
            ->keyBy('conversation_id');

        $items = $conversations->map(function (Conversation $conversation) use ($userId, $userType, $lastMessages, $myParticipants) {
            $other = $conversation->participants
                ->first(fn (ConversationParticipant $p) => ! ((int) $p->user_id === $userId && $p->user_type === $userType));

            $otherUser = $other?->user;
            $last = $lastMessages->get($conversation->id);
            $mine = $myParticipants->get($conversation->id);
            $unread = $this->unreadCountForParticipant($conversation->id, $mine, $userId, $userType);
            $lastBody = $last?->body;
            if ($last && $last->deleted_for_all_at) {
                $lastBody = 'This message was deleted';
            } elseif ($last && (! $lastBody || trim((string) $lastBody) === '')) {
                $lastBody = match ((string) $last->message_type) {
                    'image' => 'Sent a photo',
                    'file' => 'Sent a file',
                    default => $lastBody,
                };
            } elseif ($last && in_array((string) $last->message_type, ['image', 'file'], true) && is_string($lastBody) && trim($lastBody) !== '') {
                $lastBody = ((string) $last->message_type === 'image' ? '📷 ' : '📎 ').$lastBody;
            }

            return [
                'id' => $conversation->id,
                'conversation_type' => $conversation->conversation_type,
                'other_user' => $this->serializeUser($otherUser, $other?->user_type),
                'last_message' => $last ? [
                    'id' => $last->id,
                    'body' => $lastBody,
                    'message_type' => $last->deleted_for_all_at ? 'deleted' : $last->message_type,
                    'sender_id' => $last->sender_id,
                    'deleted_for_all' => $last->deleted_for_all_at !== null,
                    'created_at' => optional($last->created_at)?->toIso8601String(),
                ] : null,
                'unread_count' => $unread,
                'updated_at' => optional($last?->created_at ?? $conversation->updated_at)?->toIso8601String(),
            ];
        });

        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $items = $items->filter(function (array $item) use ($q) {
                $name = mb_strtolower((string) ($item['other_user']['name'] ?? ''));

                return str_contains($name, $q);
            })->values();
        }

        return $items->sortByDesc(fn (array $item) => $item['updated_at'] ?? '')->values();
    }

    public function showPayload(Conversation $conversation, Authenticatable $user): array
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $showRelations = ['participants.user'];
        if (Schema::hasTable('barangays')) {
            $showRelations[] = 'participants.user.barangay';
        }
        if (Schema::hasTable('official_profiles')) {
            $showRelations[] = 'participants.user.officialProfile';
        }
        $conversation->load($showRelations);

        $other = $conversation->participants
            ->first(fn (ConversationParticipant $p) => ! ((int) $p->user_id === $userId && $p->user_type === $userType));

        return [
            'id' => $conversation->id,
            'conversation_type' => $conversation->conversation_type,
            'other_user' => $this->serializeUser($other?->user, $other?->user_type),
        ];
    }

    public function markRead(Conversation $conversation, Authenticatable $user): void
    {
        $userType = $this->types->portalType();

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', (int) $user->id)
            ->where('user_type', $userType)
            ->update(['last_read_at' => now()]);
    }

    public function unreadTotalForUser(Authenticatable $user): int
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $participants = ConversationParticipant::query()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->get(['conversation_id', 'last_read_at']);

        if ($participants->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($participants as $participant) {
            $query = Message::query()
                ->where('conversation_id', $participant->conversation_id)
                ->where(function (Builder $q) use ($userId, $userType) {
                    $q->where('sender_id', '!=', $userId)
                        ->orWhere('sender_type', '!=', $userType);
                });

            if ($participant->last_read_at) {
                $query->where('created_at', '>', $participant->last_read_at);
            }

            $total += (int) $query->count();
        }

        return $total;
    }

    public function searchUsers(Authenticatable $authUser, string $query): Collection
    {
        $q = trim($query);
        $limit = (int) config('communications.search_limit', 20);
        $viewerType = $this->types->portalType();
        $viewerBarangayId = (int) ($authUser->barangay_id ?? 0);

        if ($q === '' && $viewerType !== ParticipantTypeResolver::KABATAAN) {
            return collect();
        }

        $builder = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', (int) $authUser->id);

        $searchWith = [];
        if (Schema::hasTable('barangays')) {
            $searchWith[] = 'barangay';
        }
        if (Schema::hasTable('official_profiles')) {
            $searchWith[] = 'officialProfile';
        }
        if ($searchWith !== []) {
            $builder->with($searchWith);
        }

        if ($q !== '') {
            $like = $builder->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $builder->where(function (Builder $inner) use ($q, $like) {
                $inner->where('name', $like, '%'.$q.'%')
                    ->orWhere('email', $like, '%'.$q.'%');
            });
        }

        // Narrow search by portal messaging rules (barangay-scoped where required).
        if ($viewerType === ParticipantTypeResolver::KABATAAN) {
            if ($viewerBarangayId <= 0) {
                return collect();
            }
            $builder->where('role', 'sk_official')
                ->where('barangay_id', $viewerBarangayId);
        } elseif ($viewerType === ParticipantTypeResolver::SK_OFFICIAL) {
            $builder->where(function (Builder $scope) use ($viewerBarangayId) {
                $scope->where('role', 'sk_fed')
                    ->orWhere('role', 'sk_official')
                    ->orWhere(function (Builder $kabataan) use ($viewerBarangayId) {
                        $kabataan->whereIn('role', ['kabataan', 'user']);
                        if ($viewerBarangayId > 0) {
                            $kabataan->where('barangay_id', $viewerBarangayId);
                        } else {
                            $kabataan->whereRaw('1 = 0');
                        }
                    });
            });
        } else {
            $builder->whereIn('role', $this->types->searchableRoles());
        }

        return $builder->orderBy('name')
            ->limit($limit)
            ->get()
            ->filter(fn (User $user) => $this->canMessage($authUser, $user))
            ->values()
            ->map(fn (User $user) => $this->serializeUser($user, $this->types->fromUser($user)));
    }

    /**
     * Federations directory listing for quick message/call.
     * Filters: conversations | chairpersons | officials | federation
     *
     * @return array{users: Collection, groups: array<int, array{title: string, users: array}>}
     */
    public function directory(Authenticatable $authUser, string $filter = 'officials', ?int $barangayId = null): array
    {
        $filter = strtolower(trim($filter));
        if (! in_array($filter, ['chairpersons', 'officials', 'federation'], true)) {
            $filter = 'officials';
        }

        $query = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', (int) $authUser->id);

        $directoryWith = [];
        if (Schema::hasTable('barangays')) {
            $directoryWith[] = 'barangay';
        }
        if (Schema::hasTable('official_profiles')) {
            $directoryWith[] = 'officialProfile';
        }
        if ($directoryWith !== []) {
            $query->with($directoryWith);
        }

        if ($filter === 'federation') {
            $query->where('role', 'sk_fed');
        } else {
            $query->where('role', 'sk_official');

            if ($barangayId) {
                $query->where('barangay_id', $barangayId);
            }

            if ($filter === 'chairpersons') {
                $query->whereHas('officialProfile', function (Builder $profile) {
                    $profile->whereIn('position', ['Chairperson', 'Chairman']);
                });
            }
        }

        $users = $query->orderBy('name')
            ->limit(300)
            ->get()
            ->filter(fn (User $user) => $this->canMessage($authUser, $user))
            ->values();

        $mapped = $users->map(fn (User $user) => $this->serializeUser($user, $this->types->fromUser($user)));

        $groups = [];
        if ($filter === 'officials' || $filter === 'chairpersons') {
            $grouped = $mapped->groupBy(fn (array $row) => $row['barangay_name'] ?: 'No barangay');
            foreach ($grouped->sortKeys() as $title => $rows) {
                $groups[] = [
                    'title' => (string) $title,
                    'users' => $rows->values()->all(),
                ];
            }
        } else {
            $groups[] = [
                'title' => 'SK Federation',
                'users' => $mapped->values()->all(),
            ];
        }

        return [
            'users' => $mapped,
            'groups' => $groups,
        ];
    }

    /**
     * @return Collection<int, array{id:int,name:string}>
     */
    public function barangayOptions(): Collection
    {
        $barangayClass = Barangay::class;
        if (! class_exists($barangayClass)) {
            return collect();
        }

        return $barangayClass::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->values();
    }

    /**
     * Barangay messaging rules:
     * - Kabataan: only SK Officials of the same barangay
     * - SK Official: kabataan of same barangay, any SK Official, any SK Federation
     * - SK Federation: active kabataan / sk_official / sk_fed accounts
     */
    public function canMessage(Authenticatable $from, User $to): bool
    {
        if ((int) $from->id === (int) $to->id) {
            return false;
        }

        if (strtoupper((string) $to->status) !== User::STATUS_ACTIVE) {
            return false;
        }

        $toType = $this->types->normalizeSearchRole((string) $to->role);
        if ($toType === null) {
            return false;
        }

        $fromType = $this->types->portalType();
        $fromBarangayId = (int) ($from->barangay_id ?? 0);
        $toBarangayId = (int) ($to->barangay_id ?? 0);

        return match ($fromType) {
            ParticipantTypeResolver::KABATAAN => $toType === ParticipantTypeResolver::SK_OFFICIAL
                && $fromBarangayId > 0
                && $fromBarangayId === $toBarangayId,
            ParticipantTypeResolver::SK_OFFICIAL => match ($toType) {
                ParticipantTypeResolver::KABATAAN => $fromBarangayId > 0
                    && $fromBarangayId === $toBarangayId,
                ParticipantTypeResolver::SK_OFFICIAL,
                ParticipantTypeResolver::SK_FED => true,
                default => false,
            },
            ParticipantTypeResolver::SK_FED => in_array($toType, [
                ParticipantTypeResolver::KABATAAN,
                ParticipantTypeResolver::SK_OFFICIAL,
                ParticipantTypeResolver::SK_FED,
            ], true),
            default => false,
        };
    }

    public function assertCanMessagePeer(Conversation $conversation, Authenticatable $user): void
    {
        $userType = $this->types->portalType();
        $other = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($q) use ($user, $userType) {
                $q->where('user_id', '!=', (int) $user->id)
                    ->orWhere('user_type', '!=', $userType);
            })
            ->first();

        if (! $other) {
            abort(403, 'Conversation peer not found.');
        }

        $peer = User::query()->find($other->user_id);
        if (! $peer || ! $this->canMessage($user, $peer)) {
            abort(403, 'You are not allowed to message this account.');
        }
    }

    public function isSearchableUser(User $user, ?Authenticatable $viewer = null): bool
    {
        if ($viewer) {
            return $this->canMessage($viewer, $user);
        }

        if (strtoupper((string) $user->status) !== User::STATUS_ACTIVE) {
            return false;
        }

        return $this->types->normalizeSearchRole((string) $user->role) !== null;
    }

    public function serializeUser(?User $user, ?string $type = null): ?array
    {
        if (! $user) {
            return null;
        }

        $type ??= $this->types->normalizeSearchRole((string) $user->role) ?? $this->types->fromUser($user);

        $avatar = $this->resolveAvatarUrl($user, (string) $type);
        $presence = $this->resolvePresence($user);

        if (Schema::hasTable('barangays') && ! $user->relationLoaded('barangay')) {
            $user->loadMissing('barangay');
        }
        if (Schema::hasTable('official_profiles') && ! $user->relationLoaded('officialProfile')) {
            $user->loadMissing('officialProfile');
        }

        $position = trim((string) (
            $user->officialProfile?->position
            ?: $user->officialProfile?->federation_position
            ?: ''
        ));
        if ($position === '') {
            $position = null;
        }

        return [
            'id' => (int) $user->id,
            'name' => $this->resolveDisplayName($user, (string) $type),
            'role' => (string) $user->role,
            'user_type' => $type,
            'user_type_label' => $this->types->label($type),
            'profile_image_url' => $avatar,
            'online_status' => $presence['online_status'],
            'is_online' => $presence['is_online'],
            'last_seen' => $presence['last_seen'],
            'barangay_id' => (int) ($user->barangay_id ?? 0) ?: null,
            'barangay_name' => $user->barangay?->name,
            'position' => $position,
        ];
    }

    /**
     * Prefer fresh last_seen (2 min window). Sticky "online" without a recent last_seen is treated offline.
     *
     * @return array{online_status: string, is_online: bool, last_seen: ?string}
     */
    protected function resolvePresence(User $user): array
    {
        $lastSeen = null;
        if (Schema::hasColumn('users', 'last_seen') && $user->last_seen) {
            try {
                $lastSeen = $user->last_seen instanceof Carbon
                    ? $user->last_seen
                    : Carbon::parse($user->last_seen);
            } catch (\Throwable) {
                $lastSeen = null;
            }
        }

        $isOnline = false;
        if ($lastSeen) {
            $isOnline = $lastSeen->gte(now()->subMinutes(2));
        }

        return [
            'online_status' => $isOnline ? 'online' : 'offline',
            'is_online' => $isOnline,
            'last_seen' => $lastSeen?->toIso8601String(),
        ];
    }

    /**
     * SK officials: first + full middle name + last (+ suffix). Others: users.name.
     */
    public function resolveDisplayName(User $user, ?string $type = null): string
    {
        $type ??= $this->types->fromUser($user);
        $fallback = trim((string) ($user->name ?? ''));

        if (! in_array((string) $type, ['sk_official', 'sk_fed'], true)
            && ! in_array((string) ($user->role ?? ''), ['sk_official', 'sk_fed'], true)) {
            return $fallback !== '' ? $fallback : 'User';
        }

        if (Schema::hasTable('official_profiles') && ! $user->relationLoaded('officialProfile')) {
            $user->loadMissing('officialProfile');
        }

        $profile = $user->officialProfile;
        if (! $profile) {
            return $fallback !== '' ? $fallback : 'SK Official';
        }

        $parts = array_values(array_filter([
            trim((string) ($profile->first_name ?? '')),
            trim((string) ($profile->middle_name ?? '')),
            trim((string) ($profile->last_name ?? '')),
            trim((string) ($profile->suffix ?? '')),
        ], static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return $fallback !== '' ? $fallback : 'SK Official';
        }

        return implode(' ', $parts);
    }

    /**
     * Kabataan → profile photo; Officials/Federation → barangay logo.
     */
    protected function resolveAvatarUrl(User $user, string $type): ?string
    {
        if ($type === 'kabataan') {
            $profile = '';
            if (Schema::hasColumn('users', 'profile_image_url')) {
                $profile = trim((string) ($user->profile_image_url ?? ''));
            }
            if ($profile === '' && Schema::hasColumn('users', 'profile_image')) {
                $profile = trim((string) ($user->profile_image ?? ''));
            }

            return $profile !== '' ? $this->normalizeMediaUrl($profile) : null;
        }

        if (in_array($type, ['sk_official', 'sk_fed'], true)) {
            $barangayId = (int) ($user->barangay_id ?? 0);
            if ($barangayId > 0) {
                if (! array_key_exists($barangayId, $this->logoCache)) {
                    $this->logoCache[$barangayId] = $this->barangayLogos->resolve($barangayId);
                }
                if (! empty($this->logoCache[$barangayId])) {
                    return $this->logoCache[$barangayId];
                }
            }

            if ($type === 'sk_fed') {
                return asset('images/SK_OnePortal_logo.png');
            }
        }

        return null;
    }

    protected function normalizeMediaUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        if (str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        try {
            return Storage::disk('public')->url($value);
        } catch (\Throwable $e) {
            return asset($value);
        }
    }

    protected function findPrivateConversationId(int $aId, string $aType, int $bId, string $bType): ?int
    {
        $rows = DB::table('conversation_participants as cp1')
            ->join('conversation_participants as cp2', 'cp1.conversation_id', '=', 'cp2.conversation_id')
            ->join('conversations as c', 'c.id', '=', 'cp1.conversation_id')
            ->where('c.conversation_type', 'private')
            ->where('cp1.user_id', $aId)
            ->where('cp1.user_type', $aType)
            ->where('cp2.user_id', $bId)
            ->where('cp2.user_type', $bType)
            ->select('c.id')
            ->limit(1)
            ->value('id');

        return $rows ? (int) $rows : null;
    }

    protected function unreadCountForParticipant(
        int $conversationId,
        ?ConversationParticipant $mine,
        int $userId,
        string $userType
    ): int {
        $query = Message::query()
            ->where('conversation_id', $conversationId)
            ->where(function (Builder $q) use ($userId, $userType) {
                $q->where('sender_id', '!=', $userId)
                    ->orWhere('sender_type', '!=', $userType);
            });

        if ($mine?->last_read_at) {
            $query->where('created_at', '>', $mine->last_read_at);
        }

        return (int) $query->count();
    }
}
