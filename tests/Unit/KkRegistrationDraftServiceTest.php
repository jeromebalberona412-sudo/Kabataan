<?php

use App\Models\Barangay;
use App\Services\KkRegistrationDraftService;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

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

test('completed wizard token is readable after finalize even if cache fails', function () {
    $service = app(KkRegistrationDraftService::class);
    $registration = new \App\Models\KabataanRegistration;
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
