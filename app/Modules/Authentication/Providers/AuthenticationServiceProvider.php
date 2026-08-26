<?php

namespace App\Modules\Authentication\Providers;

use App\Models\User;
use App\Modules\Authentication\Services\DeviceFingerprintService;
use App\Modules\Authentication\Services\TrustedDeviceService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeviceFingerprintService::class);
        $this->app->singleton(TrustedDeviceService::class);
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'authentication');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && request()->hasSession()) {
                app(TrustedDeviceService::class)->revokeCurrentDevice($event->user, request());
            }
        });
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/auth.php');
    }
}
