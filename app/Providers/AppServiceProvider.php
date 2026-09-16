<?php

namespace App\Providers;

use App\Services\KabataanNotificationService;
use App\Support\MailUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureUrlDefaults();
        $this->warnProductionMisconfiguration();
        $this->shareHeaderNotifications();

        $lifetimeDays = max(1, (int) config('kabataan_auth.remember.lifetime_days', 7));
        Auth::guard('web')->setRememberDuration($lifetimeDays * 24 * 60);
    }

    private function configureUrlDefaults(): void
    {
        $forwardedHttps = false;
        $requestIsHttps = false;
        $host = '';

        if (! $this->app->runningInConsole()) {
            try {
                $forwardedHttps = strtolower((string) request()->server('HTTP_X_FORWARDED_PROTO')) === 'https';
                $requestIsHttps = request()->secure() || $forwardedHttps;
                $host = strtolower((string) request()->getHost());
            } catch (\Throwable) {
                // Early HTTP boot without a captured request.
            }
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

        if ($requestIsHttps || ($isProdEnv && ! $isLocalDevHost)) {
            URL::forceScheme('https');
        }
    }

    private function warnProductionMisconfiguration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if (trim((string) config('app.key')) === '') {
            Log::critical('APP_KEY is missing. Set the existing production APP_KEY in .env. Do not generate a new key.');
        }

        if (config('database.default') === 'sqlite') {
            Log::critical('Production is using SQLite. Set DB_CONNECTION to mysql (or pgsql) with Hostinger credentials.');
        }

        $root = MailUrl::root();
        if (MailUrl::isLoopback($root) || MailUrl::isPrivateLan($root)) {
            Log::critical('Production APP_URL is still a local address. Set APP_URL and KABATAAN_APP_URL to https://kabataan.skoneportal.com');
        }

        if (! is_file(public_path('build/manifest.json'))) {
            Log::critical('Vite manifest missing at public/build/manifest.json. Run npm run build and deploy public/build.');
        }
    }

    private function shareHeaderNotifications(): void
    {
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
    }
}
