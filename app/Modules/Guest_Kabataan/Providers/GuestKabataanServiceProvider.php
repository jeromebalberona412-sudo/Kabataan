<?php

namespace App\Modules\Guest_Kabataan\Providers;

use App\Support\ModulePath;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GuestKabataanServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(ModulePath::views(__DIR__), 'guest_kabataan');

        $routesFile = ModulePath::routes(__DIR__, 'guest_kabataan.php');
        if ($routesFile !== null) {
            Route::middleware('web')->group($routesFile);
        }
    }
}
