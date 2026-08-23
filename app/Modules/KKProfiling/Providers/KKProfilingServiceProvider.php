<?php

namespace App\Modules\KKProfiling\Providers;

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
        $this->loadViewsFrom(__DIR__ . '/../Views', 'kkprofiling');

        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');
    }
}
