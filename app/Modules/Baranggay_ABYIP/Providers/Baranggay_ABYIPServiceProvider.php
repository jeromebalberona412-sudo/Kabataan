<?php

namespace App\Modules\Baranggay_ABYIP\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Baranggay_ABYIPServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViewsFrom($this->moduleViewsPath(), 'baranggay_abyip');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/web.php');
    }

    private function moduleViewsPath(): string
    {
        $views = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Views';
        $viewsLower = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'views';

        return is_dir($views) ? $views : $viewsLower;
    }
}
