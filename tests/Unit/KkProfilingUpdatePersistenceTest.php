<?php

use App\Models\KabataanProfilingHistory;
use App\Models\KabataanRegistration;
use App\Models\KkProfilingUpdate;
use App\Services\KabataanProfilingHistoryService;
use App\Services\KkProfilingScheduleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (! extension_loaded('pdo_pgsql')) {
        $this->markTestSkipped('pdo_pgsql is required for KK profiling update persistence tests.');
    }

    $envPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env';
    if (! is_file($envPath)) {
        $this->markTestSkipped('Kabataan .env not found.');
    }

    $vars = [];
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim($value, " \t\"'");
    }

    if (($vars['DB_CONNECTION'] ?? '') !== 'pgsql') {
        $this->markTestSkipped('Kabataan .env is not configured for pgsql.');
    }

    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => $vars['DB_HOST'] ?? '127.0.0.1',
        'database.connections.pgsql.port' => $vars['DB_PORT'] ?? '5432',
        'database.connections.pgsql.database' => $vars['DB_DATABASE'] ?? 'postgres',
        'database.connections.pgsql.username' => $vars['DB_USERNAME'] ?? 'postgres',
        'database.connections.pgsql.password' => $vars['DB_PASSWORD'] ?? '',
    ]);

    DB::purge('pgsql');
    DB::reconnect('pgsql');

    try {
        if (! Schema::hasTable('kabataan_profiling_history') || ! Schema::hasTable('kabataan_registrations')) {
            $this->markTestSkipped('Required tables are not available.');
        }
    } catch (Throwable $e) {
        $this->markTestSkipped('Database unavailable: '.$e->getMessage());
    }
});

test('previous year completion does not satisfy current year via form_data and history', function () {
    $registration = KabataanRegistration::query()->orderBy('id')->first();
    if (! $registration) {
        $this->markTestSkipped('No kabataan_registrations row available for persistence tests.');
    }

    $previousYear = 2098;
    $currentYear = 2099;
    $originalFormData = $registration->form_data;

    KabataanProfilingHistory::query()
        ->where('kabataan_registration_id', $registration->id)
        ->whereIn('profiling_year', [$previousYear, $currentYear])
        ->delete();

    try {
        $registration->update([
            'form_data' => array_merge(
                is_array($originalFormData) ? $originalFormData : [],
                [
                    'profile_updated_year' => $previousYear,
                    'profile_updated_at' => now()->subYear()->toIso8601String(),
                ]
            ),
        ]);

        $service = app(KkProfilingScheduleService::class);
        $service->forgetCompletionCache((int) $registration->id, $previousYear);
        $service->forgetCompletionCache((int) $registration->id, $currentYear);

        $fresh = $registration->fresh();
        expect($service->hasCompletedProfilingForYear($fresh, $previousYear))->toBeTrue();
        expect($service->hasCompletedProfilingForYear($fresh, $currentYear))->toBeFalse();

        $fresh->update([
            'form_data' => array_merge(
                is_array($fresh->form_data) ? $fresh->form_data : [],
                [
                    'profile_updated_year' => $currentYear,
                    'profile_updated_at' => now()->toIso8601String(),
                ]
            ),
        ]);

        app(KabataanProfilingHistoryService::class)->saveSnapshot($fresh->fresh(), $currentYear);
        $service->forgetCompletionCache((int) $registration->id, $currentYear);

        expect($service->hasCompletedProfilingForYear($fresh->fresh(), $currentYear))->toBeTrue();
        expect(
            KabataanProfilingHistory::query()
                ->where('kabataan_registration_id', $registration->id)
                ->where('profiling_year', $currentYear)
                ->count()
        )->toBe(1);
    } finally {
        KabataanProfilingHistory::query()
            ->where('kabataan_registration_id', $registration->id)
            ->whereIn('profiling_year', [$previousYear, $currentYear])
            ->delete();
        $registration->update(['form_data' => $originalFormData]);
        app(KkProfilingScheduleService::class)->forgetCompletionCache((int) $registration->id, $previousYear);
        app(KkProfilingScheduleService::class)->forgetCompletionCache((int) $registration->id, $currentYear);
    }
});

test('forgetCompletionCache clears stale completed flag immediately', function () {
    $registration = KabataanRegistration::query()->orderBy('id')->first();
    if (! $registration) {
        $this->markTestSkipped('No kabataan_registrations row available for persistence tests.');
    }

    $year = 2096;
    $service = app(KkProfilingScheduleService::class);
    $cacheKey = "kk_profiling_history.completed.{$registration->id}.{$year}";

    Cache::put($cacheKey, false, 30);
    expect(Cache::get($cacheKey))->toBeFalse();

    $service->forgetCompletionCache((int) $registration->id, $year);
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('kk_profiling_updates marks year completed without duplicates', function () {
    if (! Schema::hasTable('kk_profiling_updates')) {
        $this->markTestSkipped('kk_profiling_updates table is not available.');
    }

    $registration = KabataanRegistration::query()->orderBy('id')->first();
    if (! $registration) {
        $this->markTestSkipped('No kabataan_registrations row available for persistence tests.');
    }

    $previousYear = 2094;
    $currentYear = 2095;

    KkProfilingUpdate::query()
        ->where('kabataan_id', $registration->id)
        ->whereIn('year', [$previousYear, $currentYear])
        ->delete();

    try {
        KkProfilingUpdate::query()->create([
            'kabataan_id' => $registration->id,
            'year' => $previousYear,
            'status' => KkProfilingUpdate::STATUS_COMPLETED,
            'started_at' => now()->subYear(),
            'submitted_at' => now()->subYear(),
            'completed_at' => now()->subYear(),
        ]);

        $service = app(KkProfilingScheduleService::class);
        $service->forgetCompletionCache((int) $registration->id, $previousYear);
        $service->forgetCompletionCache((int) $registration->id, $currentYear);

        expect($service->hasCompletedProfilingForYear($registration, $previousYear))->toBeTrue();
        expect($service->hasCompletedProfilingForYear($registration, $currentYear))->toBeFalse();

        $service->markAnnualUpdateCompleted($registration, $currentYear);
        expect($service->hasCompletedProfilingForYear($registration, $currentYear))->toBeTrue();
        expect(
            KkProfilingUpdate::query()
                ->where('kabataan_id', $registration->id)
                ->where('year', $currentYear)
                ->where('status', KkProfilingUpdate::STATUS_COMPLETED)
                ->count()
        )->toBe(1);

        $service->markAnnualUpdateCompleted($registration, $currentYear);
        expect(
            KkProfilingUpdate::query()
                ->where('kabataan_id', $registration->id)
                ->where('year', $currentYear)
                ->count()
        )->toBe(1);
    } finally {
        KkProfilingUpdate::query()
            ->where('kabataan_id', $registration->id)
            ->whereIn('year', [$previousYear, $currentYear])
            ->delete();
        app(KkProfilingScheduleService::class)->forgetCompletionCache((int) $registration->id, $previousYear);
        app(KkProfilingScheduleService::class)->forgetCompletionCache((int) $registration->id, $currentYear);
    }
});

test('startAnnualUpdate reuses in_progress row', function () {
    if (! Schema::hasTable('kk_profiling_updates')) {
        $this->markTestSkipped('kk_profiling_updates table is not available.');
    }

    $registration = KabataanRegistration::query()->orderBy('id')->first();
    if (! $registration) {
        $this->markTestSkipped('No kabataan_registrations row available for persistence tests.');
    }

    $year = 2093;

    KkProfilingUpdate::query()
        ->where('kabataan_id', $registration->id)
        ->where('year', $year)
        ->delete();

    try {
        $service = app(KkProfilingScheduleService::class);
        $first = $service->startAnnualUpdate($registration, $year);
        $second = $service->startAnnualUpdate($registration, $year);

        expect($first)->not->toBeNull();
        expect($second)->not->toBeNull();
        expect($first->id)->toBe($second->id);
        expect($first->status)->toBe(KkProfilingUpdate::STATUS_IN_PROGRESS);
        expect(
            KkProfilingUpdate::query()
                ->where('kabataan_id', $registration->id)
                ->where('year', $year)
                ->count()
        )->toBe(1);
    } finally {
        KkProfilingUpdate::query()
            ->where('kabataan_id', $registration->id)
            ->where('year', $year)
            ->delete();
    }
});
