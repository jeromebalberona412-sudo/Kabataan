<?php

namespace App\Modules\Programs\Providers;

use App\Support\ModulePath;
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
        $routesFile = ModulePath::routes(__DIR__, 'web.php');

        if ($routesFile !== null) {
            $this->loadRoutesFrom($routesFile);
        }

        $this->loadViewsFrom(ModulePath::views(__DIR__), 'programs');
    }
}
