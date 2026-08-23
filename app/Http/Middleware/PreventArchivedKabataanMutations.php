<?php

namespace App\Http\Middleware;

use App\Services\KabataanEligibilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventArchivedKabataanMutations
{
    public function __construct(
        private readonly KabataanEligibilityService $eligibilityService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->eligibilityService->isViewOnly($user)) {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => KabataanEligibilityService::VIEW_ONLY_MESSAGE,
            ], 403);
        }

        return redirect()
            ->back()
            ->with('view_only_error', KabataanEligibilityService::VIEW_ONLY_MESSAGE);
    }
}
