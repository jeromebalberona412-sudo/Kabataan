<?php

use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Services\KkProfilingScheduleService;
use App\Services\KkRegistrationDraftService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    session()->flush();
});

function makeDraftBarangay(int $id = 1, string $name = 'Alipit'): Barangay
{
    $barangay = new Barangay;
    $barangay->id = $id;
    $barangay->name = $name;

    return $barangay;
}

test('partial step 1 draft persists and survives session resolve', function () {
    $service = app(KkRegistrationDraftService::class);
    $barangay = makeDraftBarangay();

    $wizard = $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'age' => '19',
        'email' => 'juan.test@gmail.com',
    ]);

    expect($wizard['token'])->not->toBeEmpty()
        ->and($wizard['step1_data']['first_name'])->toBe('Juan')
        ->and($wizard['step1_data']['last_name'])->toBe('Dela Cruz')
        ->and($wizard['current_step'])->toBe(1);

    $resolved = $service->resolveWizard();

    expect($resolved)->not->toBeNull()
        ->and($resolved['step1_data']['first_name'])->toBe('Juan')
        ->and($resolved['email'])->toBe('juan.test@gmail.com');
});

test('partial step 1 draft merges additional fields without advancing step', function () {
    $service = app(KkRegistrationDraftService::class);
    $barangay = makeDraftBarangay();

    $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $wizard = $service->saveStep1Partial($barangay, [
        'contact_number' => '09123456789',
        'purok_zone' => 'Zone 1',
    ]);

    expect($wizard['step1_data']['first_name'])->toBe('Juan')
        ->and($wizard['step1_data']['contact_number'])->toBe('09123456789')
        ->and($wizard['step1_data']['purok_zone'])->toBe('Zone 1')
        ->and($wizard['current_step'])->toBe(1);
});

test('clear session draft removes unfinished profiling data only', function () {
    $service = app(KkRegistrationDraftService::class);
    $barangay = makeDraftBarangay();

    session(['unrelated_user_flag' => 'keep-me']);

    $wizard = $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $token = $wizard['token'];
    expect(Storage::disk('local')->exists('kk_wizard_pending/'.$token.'.json'))->toBeTrue();

    $service->clearSessionDraft();

    expect(session(KkRegistrationDraftService::SESSION_KEY))->toBeNull()
        ->and($service->resolveWizard())->toBeNull()
        ->and(session('unrelated_user_flag'))->toBe('keep-me')
        ->and(Storage::disk('local')->exists('kk_wizard_pending/'.$token.'.json'))->toBeFalse();
});

test('partial step 1 draft clears deleted fields so refresh cannot restore them', function () {
    $service = app(KkRegistrationDraftService::class);
    $barangay = makeDraftBarangay();

    $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'middle_name' => 'Santos',
        'contact_number' => '09123456789',
    ]);

    $wizard = $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
        'last_name' => null,
        'middle_name' => '',
        'contact_number' => '09123456789',
    ]);

    expect($wizard['step1_data'])->toHaveKey('first_name')
        ->and($wizard['step1_data']['first_name'])->toBe('Juan')
        ->and($wizard['step1_data'])->not->toHaveKey('last_name')
        ->and($wizard['step1_data'])->not->toHaveKey('middle_name')
        ->and($wizard['step1_data']['contact_number'])->toBe('09123456789');

    $resolved = $service->resolveWizard();

    expect($resolved['step1_data'])->not->toHaveKey('last_name')
        ->and($resolved['step1_data'])->not->toHaveKey('middle_name');
});

test('completed step 1 still advances current step while preserving fields', function () {
    $service = app(KkRegistrationDraftService::class);
    $barangay = makeDraftBarangay();

    $service->saveStep1Partial($barangay, [
        'first_name' => 'Juan',
    ]);

    $wizard = $service->createOrUpdateStep1($barangay, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.complete@gmail.com',
        'age' => 19,
    ]);

    expect($wizard['current_step'])->toBeGreaterThanOrEqual(2)
        ->and($wizard['step1_data']['email'])->toBe('juan.complete@gmail.com');
});

test('wizard status does not restore step 3 before step 2 is completed', function () {
    $service = app(KkRegistrationDraftService::class);

    $payload = $service->wizardStatusPayload([
        'token' => 'draft-token',
        'current_step' => 3,
        'step1_data' => ['email' => 'juan.test@example.com'],
        'step2_data' => [],
    ]);

    expect($payload['current_step'])->toBe(2)
        ->and($payload['has_completed_step2'])->toBeFalse();
});

test('optional step 2 completion permits restoring step 3 without uploaded documents', function () {
    $service = app(KkRegistrationDraftService::class);

    $payload = $service->wizardStatusPayload([
        'token' => 'draft-token',
        'current_step' => 3,
        'step1_data' => ['email' => 'juan.test@example.com'],
        'step2_data' => ['supporting_documents_required' => false],
    ]);

    expect($payload['current_step'])->toBe(3)
        ->and($payload['has_completed_step2'])->toBeTrue()
        ->and($payload['step2'])->toBeNull();
});

test('stored supporting document pairs count as a completed step 2', function () {
    $service = app(KkRegistrationDraftService::class);

    $wizard = [
        'token' => 'draft-token',
        'current_step' => 3,
        'step1_data' => ['email' => 'juan.test@example.com'],
        'step2_data' => [
            'documents' => [
                'national_id' => [
                    'sides' => [
                        'front' => ['path' => 'pending/front.jpg'],
                        'back' => ['path' => 'pending/back.jpg'],
                    ],
                ],
            ],
        ],
    ];

    expect($service->hasCompletedStep2($wizard))->toBeTrue()
        ->and($service->wizardStatusPayload($wizard)['has_completed_step2'])->toBeTrue();
});

test('active schedule boolean values are normalized without treating false strings as true', function ($databaseValue, $expected) {
    Schema::shouldReceive('hasTable')
        ->once()
        ->with('kk_profiling_schedules')
        ->andReturn(true);
    Schema::shouldReceive('hasColumn')
        ->once()
        ->with('kk_profiling_schedules', 'supporting_documents_required')
        ->andReturn(true);
    Schema::shouldReceive('hasColumn')
        ->once()
        ->with('kk_profiling_schedules', 'profiling_year')
        ->andReturn(true);

    $query = Mockery::mock();
    $query->shouldReceive('where')->times(4)->andReturnSelf();
    $query->shouldReceive('orderByDesc')->twice()->andReturnSelf();
    $query->shouldReceive('first')
        ->once()
        ->andReturn((object) ['supporting_documents_required' => $databaseValue]);

    DB::shouldReceive('table')
        ->once()
        ->with('kk_profiling_schedules')
        ->andReturn($query);
    Cache::shouldReceive('remember')
        ->once()
        ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

    expect(app(KkProfilingScheduleService::class)->supportingDocumentsRequired(1))->toBe($expected);
})->with([
    'PostgreSQL true' => ['t', true],
    'PostgreSQL false' => ['f', false],
    'true string' => ['true', true],
    'false string' => ['false', false],
    'checked checkbox value' => ['1', true],
    'unchecked checkbox value' => ['0', false],
]);

test('completed wizard token is readable after finalize even if cache fails', function () {
    $service = app(KkRegistrationDraftService::class);
    $registration = new KabataanRegistration;
    $registration->id = 99;
    $registration->email = 'youth.setpassword@gmail.com';
    $registration->barangay_id = 1;
    $registration->evaluation_status = 'pending';

    $service->rememberCompletedWizardToken('abc123token', $registration);

    $resolved = $service->resolveCompletedByWizardToken('abc123token');

    expect($resolved)->not->toBeNull()
        ->and($resolved['email'])->toBe('youth.setpassword@gmail.com')
        ->and($resolved['registration_id'])->toBe(99)
        ->and($resolved['barangay_id'])->toBe(1);
});
