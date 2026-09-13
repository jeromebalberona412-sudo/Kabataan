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
        // or in production on a public host. Never force HTTPS on plain local/LAN
        // artisan serve (http://127.0.0.1 / http://192.168.x.x) — that causes
        // "Invalid request (Unsupported SSL request)" for CSS/JS.
        $forwardedHttps = request()->server('HTTP_X_FORWARDED_PROTO') === 'https';
        $isProdEnv = in_array(strtolower((string) $this->app->environment()), ['production', 'productions', 'prod'], true);
        $requestIsHttps = request()->secure() || $forwardedHttps;
        $host = strtolower((string) request()->getHost());
        $isLocalDevHost = $host === 'localhost'
            || $host === '::1'
            || $host === '127.0.0.1'
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.')
            || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);

        if ($requestIsHttps || ($isProdEnv && ! $isLocalDevHost)) {
            URL::forceScheme('https');
        }

        $mailRoot = \App\Support\MailUrl::root();
        if ($mailRoot !== '' && filter_var($mailRoot, FILTER_VALIDATE_URL)) {
            URL::forceRootUrl($mailRoot);
            if (str_starts_with(strtolower($mailRoot), 'https://')) {
                URL::forceScheme('https');
            } elseif (str_starts_with(strtolower($mailRoot), 'http://')) {
                URL::forceScheme('http');
            }
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
