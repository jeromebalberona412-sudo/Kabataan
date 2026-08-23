<?php

namespace App\Modules\Dashboard\Services;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Barangay;
use App\Services\BarangayLogoUrlService;
use App\Services\CloudinaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BarangaySkProfileService
{
    /** @var list<string> */
    private const POSITION_ORDER = [
        'chairperson',
        'chairman',
        'president',
        'vice',
        'secretary',
        'treasurer',
        'auditor',
        'pio',
        'public relations',
        'sergeant',
        'councilor',
        'kagawad',
    ];

    private const CACHE_TTL = 600; // 10 minutes

    public function __construct(private readonly BarangayLogoUrlService $logoUrlService) {}

    public function findBySlug(string $slug, ?int $tenantId = null): ?Barangay
    {
        $query = Barangay::query();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->first(function (Barangay $barangay) use ($slug) {
            return Str::slug($barangay->name) === $slug;
        });
    }

    /**
     * @return list<array{id: int, name: string, slug: string, logo_url: ?string, initials: string, chairman: string}>
     */
    public function listForTenant(int $tenantId): array
    {
        return Cache::remember("barangay_sk_profiles.list.{$tenantId}", self::CACHE_TTL, function () use ($tenantId) {
            return Barangay::query()
                ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
                ->orderBy('name')
                ->get()
                ->map(function (Barangay $barangay) {
                    $officials = $this->listOfficials($barangay->id);

                    return [
                        'id' => $barangay->id,
                        'name' => $barangay->name,
                        'slug' => Str::slug($barangay->name),
                        'logo_url' => $this->logoUrlService->resolve($barangay->id),
                        'initials' => $this->buildInitials($barangay->name),
                        'chairman' => $this->resolveChairmanLabel($officials),
                    ];
                })
                ->values()
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProfile(Barangay $barangay): array
    {
        $logoUrl = $this->logoUrlService->resolve($barangay->id);
        $officials = $this->listOfficials($barangay->id);
        $posts = $this->listPosts($barangay->id, $logoUrl);

        return [
            'barangay' => $barangay,
            'slug' => Str::slug($barangay->name),
            'name' => $barangay->name,
            'logo_url' => $logoUrl,
            'initials' => $this->buildInitials($barangay->name),
            'color' => $this->colorForBarangay($barangay->name),
            'location' => trim(implode(', ', array_filter([
                'Barangay '.$barangay->name,
                $barangay->municipality,
                $barangay->province,
            ]))),
            'post_count' => $posts->count(),
            'officer_count' => $officials->count(),
            'term_label' => $this->resolveTermLabel($barangay->id),
            'officials' => $officials->values()->all(),
            'posts' => $posts->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, role: string, initials: string, sort_key: int}>
     */
    private function listOfficials(int $barangayId): Collection
    {
        return Cache::remember("barangay_sk_profiles.officials.{$barangayId}.v3", self::CACHE_TTL, function () use ($barangayId) {
            $logoUrl = $this->logoUrlService->resolve($barangayId);
            $rows = DB::table('users')
                ->join('official_profiles', 'users.id', '=', 'official_profiles.user_id')
                ->where('users.barangay_id', $barangayId)
                ->where('users.role', 'sk_official')
                ->where('users.status', 'ACTIVE')
                ->orderBy('official_profiles.position')
                ->orderBy('official_profiles.last_name')
                ->get([
                    'users.name',
                    'users.profile_image_url',
                    'official_profiles.first_name',
                    'official_profiles.last_name',
                    'official_profiles.middle_name',
                    'official_profiles.suffix',
                    'official_profiles.position',
                ]);

            return $rows->map(function ($row) use ($logoUrl) {
                $fullName = $this->buildOfficialFullName($row);
                $role = trim((string) ($row->position ?? 'SK Official'));
                $photo = trim((string) ($row->profile_image_url ?? ''));

                return [
                    'name' => $fullName,
                    'role' => $role !== '' ? $role : 'SK Official',
                    'initials' => $this->buildInitials($fullName),
                    'logo_url' => $photo !== '' ? $photo : $logoUrl,
                    'sort_key' => $this->positionSortKey($role),
                ];
            })->sortBy([
                ['sort_key', 'asc'],
                ['name', 'asc'],
            ])->values();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listPosts(int $barangayId, ?string $logoUrl = null): Collection
    {
        $cloudinary = app(CloudinaryService::class);

        return Announcement::query()
            ->with(['user', 'barangay', 'comments.user'])
            ->withCount(['reactions', 'comments'])
            ->where('barangay_id', $barangayId)
            ->whereRaw('"is_federation_wide" = false')
            ->whereRaw('"is_archived" = false')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Announcement $post) use ($barangayId, $logoUrl, $cloudinary) {
                $type = strtolower((string) $post->type);
                $typeClass = in_array($type, ['event', 'announcement', 'activity', 'program', 'update'], true)
                    ? $type
                    : 'update';
                $createdAt = $post->created_at;
                $images = $this->resolvePostImages($post->id, $cloudinary, $post->image_url);

                return [
                    'id' => $post->id,
                    'type' => $typeClass,
                    'type_class' => $typeClass,
                    'type_label' => ucfirst($typeClass),
                    'title' => $post->title ?: 'Barangay Update',
                    'text' => $post->body ?? '',
                    'body' => $post->body ?? '',
                    'author' => $post->user?->name ?? ('SK Brgy. '.($post->barangay?->name ?? '')),
                    'author_name' => $post->user?->name ?? ('SK Brgy. '.($post->barangay?->name ?? '')),
                    'logo_url' => $logoUrl,
                    'image_url' => $images[0] ?? null,
                    'images' => $images,
                    'posted_at' => $createdAt?->diffForHumans() ?? '—',
                    'date' => $createdAt?->format('M j, Y') ?? '—',
                    'time' => $createdAt?->format('g:i A') ?? '—',
                    'venue' => 'Barangay '.($post->barangay?->name ?? 'Hall'),
                    'audience' => 'All Kabataan',
                    'likes' => (int) ($post->reactions_count ?? 0),
                    'comment_count' => (int) ($post->comments_count ?? 0),
                    'comments' => $post->comments
                        ->map(fn (AnnouncementComment $comment) => $this->formatComment($comment, $barangayId))
                        ->values()
                        ->all(),
                ];
            });
    }

    /**
     * @return list<string>
     */
    private function resolvePostImages(int $postId, CloudinaryService $cloudinary, ?string $legacyUrl): array
    {
        $images = DB::table('community_feed_images')
            ->where('community_feed_id', $postId)
            ->orderBy('sort_order')
            ->pluck('image_url')
            ->map(fn ($url) => $cloudinary->normalizeUrl((string) $url))
            ->filter()
            ->values()
            ->all();

        if ($images !== []) {
            return array_values(array_unique($images));
        }

        $legacy = $cloudinary->normalizeUrl((string) ($legacyUrl ?? ''));

        return $legacy !== '' && $legacy !== null ? [$legacy] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatComment(AnnouncementComment $comment, int $barangayId): array
    {
        $logoUrl = null;

        if ($comment->user_type === 'sk_official' && $comment->user) {
            $logoUrl = $this->logoUrlService->resolve($comment->user->barangay_id ?? $barangayId);
        }

        return [
            'id' => $comment->id,
            'author_name' => $comment->author_name,
            'body' => $comment->body,
            'time' => $comment->created_at?->diffForHumans() ?? '',
            'user_type' => $comment->user_type,
            'logo_url' => $logoUrl,
            'avatar_url' => $logoUrl ?: $this->uiAvatarUrl($comment->author_name),
        ];
    }

    private function uiAvatarUrl(string $name): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=667eea&color=fff&size=80';
    }

    /**
     * @param  Collection<int, array{name: string, role: string}>  $officials
     */
    private function resolveChairmanLabel(Collection $officials): string
    {
        $chair = $officials->first(function (array $official) {
            $role = mb_strtolower($official['role'], 'UTF-8');

            return str_contains($role, 'chair')
                || str_contains($role, 'president');
        });

        return $chair['name'] ?? 'SK Officials';
    }

    private function resolveTermLabel(int $barangayId): string
    {
        $row = DB::table('official_terms')
            ->join('official_profiles', 'official_profiles.id', '=', 'official_terms.official_profile_id')
            ->join('users', 'users.id', '=', 'official_profiles.user_id')
            ->where('users.barangay_id', $barangayId)
            ->where('users.role', 'sk_official')
            ->where('official_terms.status', 'ACTIVE')
            ->selectRaw('MIN(official_terms.term_start) as term_start, MAX(official_terms.term_end) as term_end')
            ->first();

        if ($row?->term_start === null || $row?->term_end === null) {
            return '—';
        }

        $start = Carbon::parse($row->term_start);
        $end = Carbon::parse($row->term_end);

        return $start->format('Y').'–'.$end->format('Y');
    }

    private function buildOfficialFullName(object $row): string
    {
        $parts = array_filter([
            trim((string) ($row->last_name ?? '')),
            trim((string) ($row->first_name ?? '')),
            $row->middle_name ? mb_substr(trim((string) $row->middle_name), 0, 1).'.' : null,
            trim((string) ($row->suffix ?? '')),
        ]);

        if ($parts !== []) {
            $name = $parts[0];
            if (isset($parts[1])) {
                $name .= ', '.$parts[1];
            }
            if (isset($parts[2])) {
                $name .= ' '.$parts[2];
            }
            if (isset($parts[3])) {
                $name .= ' '.$parts[3];
            }

            return trim($name);
        }

        return trim((string) ($row->name ?? '')) ?: 'SK Official';
    }

    private function buildInitials(string $value): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($clean === '') {
            return 'SK';
        }

        $parts = explode(' ', $clean);

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
        }

        return strtoupper(substr($clean, 0, 2));
    }

    private function positionSortKey(string $position): int
    {
        $normalized = mb_strtolower(trim($position), 'UTF-8');

        foreach (self::POSITION_ORDER as $index => $needle) {
            if (str_contains($normalized, $needle)) {
                return $index;
            }
        }

        return count(self::POSITION_ORDER) + 1;
    }

    private function colorForBarangay(string $name): string
    {
        $palette = ['#4CAF50', '#2196F3', '#9C27B0', '#FF9800', '#009688', '#f44336', '#673AB7', '#0450a8', '#FF5722'];
        $index = abs(crc32(mb_strtolower($name, 'UTF-8'))) % count($palette);

        return $palette[$index];
    }
}
