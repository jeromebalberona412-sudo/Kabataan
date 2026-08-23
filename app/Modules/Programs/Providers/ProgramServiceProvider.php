<?php

namespace App\Modules\Programs\Providers;

use Illuminate\Support\ServiceProvider;

class ProgramServiceProvider extends ServiceProvider
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
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../Views', 'programs');
    }
}
