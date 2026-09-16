<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\KabataanRegistration;
use App\Modules\Dashboard\Services\BarangaySkProfileService;
use App\Modules\Profile\Services\ProfileImageService;
use App\Modules\Programs\Services\KabataanProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BarangaySkProfileService $barangaySkProfileService,
    ) {}

    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in');
        }

        $user = Auth::user();

        $registration = Cache::remember(
            "kabataan_reg_user_{$user->id}",
            60,
            fn () => KabataanRegistration::with('barangay')->where('user_id', $user->id)->latest()->first()
        );

        $barangayName = $registration?->barangay?->name ?? 'Santa Cruz';

        $tenantId = (int) ($user->tenant_id ?? $registration?->barangay?->tenant_id ?? 0);

        // Cache barangay profiles for faster loading
        $barangayProfiles = Cache::remember(
            "kabataan_brgy_profiles_tenant_{$tenantId}",
            30, // 30 minutes cache
            fn () => $this->barangaySkProfileService->listForTenant($tenantId)
        );

        // Cache user data for faster profile loading
        $userAvatarUrl = Cache::remember(
            "kabataan_user_avatar_{$user->id}",
            60,
            fn () => app(ProfileImageService::class)->resolveDisplayUrl($user)
        );

        $kabataanPrograms = app(KabataanProgramService::class)->getDashboardPayload($user);

        $barangayId = (int) ($registration?->barangay_id ?? $user->barangay_id ?? 0);

        $viewData = [
            'user' => $user,
            'userAvatarUrl' => $userAvatarUrl,
            'barangayName' => $barangayName,
            'barangayProfiles' => $barangayProfiles,
            'kabataanPrograms' => $kabataanPrograms,
            'commentPreviewPost' => null,
            'feedYearsWithPosts' => $this->yearsWithPosts($barangayId),
        ];

        return view('dashboard::dashboard', $viewData)->withHeaders([
            'Cache-Control' => 'private, max-age=300', // Allow 5 minutes browser caching
            'Pragma' => 'private',
        ]);
    }

    public function comments(Request $request, int $id)
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in');
        }

        $post = app(AnnouncementFeedController::class)->formattedVisiblePost(Auth::user(), $id);
        $response = $this->index($request);
        if ($response instanceof View) {
            return $response->with('commentPreviewPost', $post);
        }
        if (isset($response->original) && $response->original instanceof View) {
            $response->original->with('commentPreviewPost', $post);
        }

        return $response;
    }

    public function barangay(Request $request, string $slug)
    {
        return $this->barangayPage($slug);
    }

    public function barangayComments(Request $request, string $slug, int $post)
    {
        return $this->barangayPage($slug, $post);
    }

    private function barangayPage(string $slug, ?int $postId = null)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('sign-in');
        }

        $registration = KabataanRegistration::with('barangay')->where('user_id', $user->id)->latest()->first();
        $tenantId = (int) ($user->tenant_id ?? $registration?->barangay?->tenant_id ?? 0);
        $barangay = $this->barangaySkProfileService->findBySlug($slug, $tenantId > 0 ? $tenantId : null);

        if ($barangay === null) {
            abort(404);
        }

        $profile = $this->barangaySkProfileService->buildProfile($barangay);
        $viewerBarangayId = (int) ($registration?->barangay_id ?? $user->barangay_id ?? 0);
        if ($viewerBarangayId === 0 && ! empty($user->email)) {
            $emailReg = KabataanRegistration::where('email', $user->email)->latest()->first();
            $viewerBarangayId = (int) ($emailReg?->barangay_id ?? 0);
        }
        $canEngage = $viewerBarangayId > 0 && $viewerBarangayId === (int) $barangay->id;
        $posts = app(AnnouncementFeedController::class)->presentBarangayPosts((int) $barangay->id, $user);

        $commentPreviewPost = null;
        if ($postId !== null) {
            $commentPreviewPost = collect($posts)->first(fn (array $post) => (int) $post['id'] === $postId);
            abort_unless($commentPreviewPost !== null, 404);
        }

        return view('dashboard::barangay', [
            'user' => $user,
            'userAvatarUrl' => app(ProfileImageService::class)->resolveDisplayUrl($user),
            'slug' => $profile['slug'],
            'name' => $profile['name'],
            'color' => $profile['color'],
            'logo_url' => $profile['logo_url'],
            'initials' => $profile['initials'],
            'location' => $profile['location'],
            'term_label' => $profile['term_label'],
            'term_start' => $profile['term_start'] ?? null,
            'term_end' => $profile['term_end'] ?? null,
            'post_count' => count($posts),
            'officer_count' => $profile['officer_count'],
            'officials' => $profile['officials'],
            'posts' => $posts,
            'canEngage' => $canEngage,
            'barangayId' => (int) $barangay->id,
            'commentPreviewPost' => $commentPreviewPost,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ]);
    }

    /**
     * @return list<int>
     */
    private function yearsWithPosts(int $barangayId): array
    {
        if ($barangayId <= 0) {
            return [(int) date('Y')];
        }

        $yearSelect = match (Schema::getConnection()->getDriverName()) {
            'pgsql' => 'DISTINCT EXTRACT(YEAR FROM created_at)::integer as year',
            'sqlite' => "DISTINCT CAST(strftime('%Y', created_at) AS INTEGER) as year",
            default => 'DISTINCT YEAR(created_at) as year',
        };

        return Announcement::query()
            ->active()
            ->where(function ($q) use ($barangayId) {
                $q->where('barangay_id', $barangayId)
                    ->orWhereRaw('"is_federation_wide" = true');
            })
            ->selectRaw($yearSelect)
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn ($year): int => (int) $year)
            ->filter(static fn (int $year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();
    }
}
