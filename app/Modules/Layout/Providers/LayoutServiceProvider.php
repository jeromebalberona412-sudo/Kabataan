<?php

namespace App\Modules\Layout\Providers;

use App\Support\ModulePath;
use Illuminate\Support\ServiceProvider;

class LayoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'layout');
    }
}
