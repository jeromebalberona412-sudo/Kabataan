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
        // Force HTTPS on Hostinger/production so asset() and redirects stay on https
        $appUrl = (string) config('app.url');
        $forwardedHttps = request()->server('HTTP_X_FORWARDED_PROTO') === 'https';
        $isProdEnv = in_array(strtolower((string) $this->app->environment()), ['production', 'productions', 'prod'], true);

        if ($isProdEnv || $forwardedHttps || str_starts_with($appUrl, 'https://')) {
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
