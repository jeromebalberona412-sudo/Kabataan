<?php

namespace App\Http\Middleware;

use App\Models\KabataanRegistration;
use App\Services\KkProfilingScheduleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureKkProfilingUpdated
{
    private const ALLOWED_WHILE_REQUIRED = [
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

        $requiresUpdate = $this->scheduleService->requiresProfilingUpdate($registration);
        $request->session()->put('kk_profiling_update_required', $requiresUpdate);

        if (! $requiresUpdate || $request->routeIs(...self::ALLOWED_WHILE_REQUIRED)) {
            return $next($request);
        }

        return redirect()->route('kkprofiling.update.show');
    }
}
