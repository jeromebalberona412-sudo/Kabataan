<?php

namespace App\Modules\Homepage\Providers;

use App\Support\ModulePath;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HomepageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'homepage');
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
