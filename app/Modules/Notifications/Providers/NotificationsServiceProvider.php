<?php

namespace App\Modules\Notifications\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(__DIR__.'/../Views', 'notifications');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
