<?php

use App\Http\Middleware\EnsureKabataanUser;
use App\Http\Middleware\EnsureKkProfilingUpdated;
use App\Http\Middleware\EnsureStaffUser;
use App\Http\Middleware\PreventArchivedKabataanMutations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'kabataan' => EnsureKabataanUser::class,
            'kabataan.view_only_guard' => PreventArchivedKabataanMutations::class,
            'kk_profiling.update_required' => EnsureKkProfilingUpdated::class,
            'staff' => EnsureStaffUser::class,
        ]);

        $middleware->appendToGroup('auth', [
            'kabataan',
            'kabataan.view_only_guard',
            'kk_profiling.update_required',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
