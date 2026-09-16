<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => 'connected',
            'cache' => 'connected',
        ],
    ];

    try {
        DB::connection()->getPdo();
        $health['services']['database'] = 'connected';
    } catch (\Throwable) {
        $health['services']['database'] = 'disconnected';
        $health['status'] = 'degraded';
        $health['services']['database_error'] = 'Database connection failed. Check DB_* settings.';
    }

    try {
        Cache::store()->get('health_check', 'ok');
        $health['services']['cache'] = 'connected';
    } catch (\Throwable) {
        $health['services']['cache'] = 'disconnected';
        $health['status'] = 'degraded';
        $health['services']['cache_error'] = 'Cache store failed. Check CACHE_STORE and related tables/permissions.';
    }

    return response()->json($health, $health['status'] === 'ok' ? 200 : 503);
})->name('health');

// Homepage is registered by HomepageServiceProvider at /
