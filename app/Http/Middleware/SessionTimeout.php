<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Authentication\Services\TrustedDeviceService;
use App\Support\MailUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public const EXPIRED_MESSAGE = 'Your session has expired due to inactivity. Please log in again.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) config('session.timeout', 120));
        $timeoutSeconds = $timeoutMinutes * 60;
        $now = now()->getTimestamp();
        $lastActivity = $request->session()->get(self::SESSION_KEY);

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) >= $timeoutSeconds) {
            return $this->expireSession($request);
        }

        if ($lastActivity === null || ! $this->shouldSkipActivityRefresh($request)) {
            $request->session()->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }

    protected function shouldSkipActivityRefresh(Request $request): bool
    {
        return false;
    }

    protected function expireSession(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $message = self::EXPIRED_MESSAGE;

        if ($user instanceof User) {
            try {
                app(TrustedDeviceService::class)->revokeCurrentDevice($user, $request);
            } catch (\Throwable $exception) {
                report($exception);
            }

            Log::info('Kabataan user session expired due to inactivity.', [
                'user_id' => $user->getKey(),
                'route' => $request->route()?->getName(),
                'path' => '/'.$request->path(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->wantsJsonResponse($request)) {
            return response()->json([
                'authenticated' => false,
                'session_expired' => true,
                'message' => $message,
                'redirect' => MailUrl::sameOrigin(route('sign-in')),
            ], 401);
        }

        return redirect()
            ->route('sign-in')
            ->with('sign_in_error', $message);
    }

    protected function wantsJsonResponse(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || str_contains((string) $request->header('Accept', ''), 'application/json');
    }
}
