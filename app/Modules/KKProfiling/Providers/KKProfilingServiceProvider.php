<?php

namespace App\Modules\KKProfiling\Providers;

use App\Support\ModulePath;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KKProfilingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'kkprofiling');

        $routesFile = ModulePath::routes(__DIR__, 'web.php');

        if ($routesFile !== null) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
