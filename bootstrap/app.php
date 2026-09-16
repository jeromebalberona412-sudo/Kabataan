<?php

use App\Http\Middleware\EnsureKabataanUser;
use App\Http\Middleware\EnsureKkProfilingUpdated;
use App\Http\Middleware\EnsureStaffUser;
use App\Http\Middleware\PreventArchivedKabataanMutations;
use App\Http\Middleware\SessionTimeout;
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
            'session.timeout' => SessionTimeout::class,
        ]);

        $middleware->appendToGroup('auth', [
            'session.timeout',
            'kabataan',
            'kabataan.view_only_guard',
            'kk_profiling.update_required',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e): bool {
            $message = $e->getMessage();

            if (
                $e instanceof \Illuminate\Foundation\ViteManifestNotFoundException
                || str_contains($message, 'Vite manifest')
                || str_contains($message, 'Unable to locate file in Vite manifest')
            ) {
                \Illuminate\Support\Facades\Log::error(
                    'Production Vite assets are missing. Run npm run build and deploy public/build/.',
                    ['exception' => $message]
                );
            }

            return true;
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->is('forgot-password*') || $request->is('reset-password*')) {
                if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json([
                        'ok' => false,
                        'message' => 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.',
                        'csrf_mismatch' => true,
                    ], 419);
                }

                return redirect()->route('sign-in')
                    ->with('sign_in_error', 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.');
            }
        });
    })->create();
