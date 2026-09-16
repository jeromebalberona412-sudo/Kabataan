<?php

namespace App\Providers;

use App\Services\KabataanNotificationService;
use App\Support\MailUrl;
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
        $forwardedHttps = false;
        $requestIsHttps = false;
        $host = '';

        try {
            $forwardedHttps = strtolower((string) request()->server('HTTP_X_FORWARDED_PROTO')) === 'https';
            $requestIsHttps = request()->secure() || $forwardedHttps;
            $host = strtolower((string) request()->getHost());
        } catch (\Throwable) {
            // Console, queue, and early boot have no live HTTP request.
        }

        $isProdEnv = in_array(strtolower((string) $this->app->environment()), ['production', 'productions', 'prod'], true);
        $isLocalDevHost = $host === 'localhost'
            || $host === '::1'
            || $host === '127.0.0.1'
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.')
            || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);

        $resolvedRoot = MailUrl::root();
        if ($resolvedRoot !== '' && filter_var($resolvedRoot, FILTER_VALIDATE_URL)) {
            URL::forceRootUrl($resolvedRoot);
            config(['filesystems.disks.public.url' => rtrim($resolvedRoot, '/').'/storage']);

            if (str_starts_with(strtolower($resolvedRoot), 'https://')) {
                URL::forceScheme('https');
            } elseif (str_starts_with(strtolower($resolvedRoot), 'http://') && ! $requestIsHttps && ! $isProdEnv) {
                URL::forceScheme('http');
            }
        }

        // Force HTTPS when the request is already HTTPS (or behind an HTTPS proxy),
        // or in production on a public host. Never force HTTPS on plain local/LAN
        // artisan serve — that causes "Invalid request (Unsupported SSL request)" for CSS/JS.
        if ($requestIsHttps || ($isProdEnv && ! $isLocalDevHost)) {
            URL::forceScheme('https');
        }

        View::composer(['layout::kabataan-header', 'dashboard::notification'], function ($view) {
            $headerNotifications = [];
            $unreadNotificationCount = 0;

            try {
                $user = Auth::user();
                $notificationService = app(KabataanNotificationService::class);
                $headerNotifications = $notificationService->recentForUser($user, 8);
                $unreadNotificationCount = $notificationService->unreadCountForUser($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            $view->with([
                'headerNotifications' => $headerNotifications,
                'unreadNotificationCount' => $unreadNotificationCount,
            ]);
        });

        $lifetimeDays = max(1, (int) config('kabataan_auth.remember.lifetime_days', 7));
        Auth::guard('web')->setRememberDuration($lifetimeDays * 24 * 60);
    }
}
