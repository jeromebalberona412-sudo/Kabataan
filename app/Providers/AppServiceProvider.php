<?php

namespace App\Providers;

use App\Services\KabataanNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS only when the request is already HTTPS (or behind an HTTPS proxy),
        // or in production on a non-loopback host. Never force HTTPS on plain
        // http://127.0.0.1:8002 — that breaks CSS/JS (Unsupported SSL request).
        $forwardedHttps = request()->server('HTTP_X_FORWARDED_PROTO') === 'https';
        $isProdEnv = in_array(strtolower((string) $this->app->environment()), ['production', 'productions', 'prod'], true);
        $requestIsHttps = request()->secure() || $forwardedHttps;
        $host = strtolower((string) request()->getHost());
        $isLoopbackHost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

        if ($requestIsHttps || ($isProdEnv && ! $isLoopbackHost)) {
            URL::forceScheme('https');
        }

        View::composer(['layout::kabataan-header', 'dashboard::notification'], function ($view) {
            $user = Auth::user();
            $notificationService = app(KabataanNotificationService::class);

            $view->with([
                'headerNotifications' => $notificationService->recentForUser($user, 8),
                'unreadNotificationCount' => $notificationService->unreadCountForUser($user),
            ]);
        });

        $lifetimeDays = max(1, (int) config('kabataan_auth.remember.lifetime_days', 7));
        Auth::guard('web')->setRememberDuration($lifetimeDays * 24 * 60);
    }
}
