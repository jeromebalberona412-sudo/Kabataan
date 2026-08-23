<?php

namespace App\Modules\Layout\Providers;

use Illuminate\Support\ServiceProvider;

class LayoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Views', 'layout');
    }
}
