<?php

namespace App\Http\Middleware;

use App\Models\KabataanRegistration;
use App\Services\KkProfilingScheduleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureKkProfilingUpdated
{
    /**
     * Dashboard is allowed so the mandatory modal can render.
     * Other Kabataan routes redirect to dashboard while the update is incomplete.
     */
    private const ALLOWED_WHILE_REQUIRED = [
        'dashboard',
        'kkprofiling.update.show',
        'kkprofiling.update',
        'kkprofiling.resend-update-verification',
        'logout',
    ];

    public function __construct(private readonly KkProfilingScheduleService $scheduleService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $next($request);
        }

        $registration = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $requiresUpdate = $this->scheduleService->needsKkProfilingUpdate($registration);
        $targetYear = $registration
            ? ($this->scheduleService->targetProfilingYearForRegistration($registration)
                ?? $this->scheduleService->expectedProfilingYear())
            : $this->scheduleService->expectedProfilingYear();

        $request->session()->put('kk_profiling_update_required', $requiresUpdate);
        $request->session()->put('kk_profiling_update_year', $requiresUpdate ? $targetYear : null);

        view()->share('kkProfilingUpdateRequired', $requiresUpdate);
        view()->share('kkProfilingUpdateYear', $requiresUpdate ? $targetYear : null);

        if (! $requiresUpdate || $request->routeIs(...self::ALLOWED_WHILE_REQUIRED)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'KK Profiling update is required before continuing.',
                'redirect' => route('dashboard'),
                'kk_profiling_update_required' => true,
                'year' => $targetYear,
            ], 403);
        }

        return redirect()->route('dashboard');
    }
}
