<?php

namespace App\Modules\Homepage\Providers;

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
        $this->loadViewsFrom(__DIR__ . '/../Views', 'homepage');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');
    }
}
