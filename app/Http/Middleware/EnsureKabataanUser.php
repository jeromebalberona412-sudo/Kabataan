<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\KabataanAuthService;
use App\Services\KabataanEligibilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureKabataanUser
{
    public function __construct(
        private readonly KabataanAuthService $kabataanAuthService,
        private readonly KabataanEligibilityService $eligibilityService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $logData = [];

        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('sign-in'));
        }

        $authCheckStart = microtime(true);
        if (! $this->kabataanAuthService->canAccessPortal($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('sign-in')
                ->with('sign_in_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
        }

        if (in_array((string) $user->status, [User::STATUS_INACTIVE, User::STATUS_REJECTED], true)) {
            app(\App\Modules\Authentication\Services\TrustedDeviceService::class)->revokeAllForUser($user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->status === User::STATUS_INACTIVE
                ? 'Your account has been deactivated. Please contact your SK officials.'
                : 'Your KK Profiling registration has been rejected.';

            return redirect()
                ->route('sign-in')
                ->with('sign_in_error', $message);
        }
        $logData['auth_check_ms'] = round((microtime(true) - $authCheckStart) * 1000, 2);

        $eligibilityCheckStart = microtime(true);
        $viewOnly = $this->eligibilityService->isViewOnly($user);
        $logData['eligibility_check_ms'] = round((microtime(true) - $eligibilityCheckStart) * 1000, 2);

        $request->attributes->set('kabataan_view_only', $viewOnly);
        View::share('kabataanViewOnly', $viewOnly);

        $logData['total_middleware_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('EnsureKabataanUser middleware profile', $logData);

        return $next($request);
    }
}
