<?php

namespace App\Modules\Dashboard\Providers;

use App\Support\ModulePath;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'dashboard');
    }

    protected function loadRoutes(): void
    {
        $routesFile = ModulePath::routes(__DIR__, 'web.php');

        if ($routesFile === null) {
            return;
        }

        Route::middleware('web')->group($routesFile);
    }
}
