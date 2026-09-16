<?php

namespace App\Modules\Authentication\Providers;

use App\Models\User;
use App\Modules\Authentication\Services\DeviceFingerprintService;
use App\Modules\Authentication\Services\TrustedDeviceService;
use App\Support\ModulePath;
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
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'authentication');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && request()->hasSession()) {
                app(TrustedDeviceService::class)->revokeCurrentDevice($event->user, request());
            }
        });
    }

    protected function loadRoutes(): void
    {
        $routesFile = ModulePath::routes(__DIR__, 'auth.php');

        if ($routesFile === null) {
            return;
        }

        Route::middleware('web')->group($routesFile);
    }
}
