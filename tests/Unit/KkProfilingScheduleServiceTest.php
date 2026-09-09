<?php

use App\Models\KabataanRegistration;
use App\Services\KabataanProfilingHistoryService;
use App\Services\KkProfilingScheduleService;

test('form data marks the profiling year as already completed', function () {
    $service = new KkProfilingScheduleService;

    expect($service->formDataCompletedYear(['profile_updated_year' => 2026], 2026))->toBeTrue();
    expect($service->formDataCompletedYear(['profile_updated_year' => 2027], 2026))->toBeTrue();
    expect($service->formDataCompletedYear(['profile_updated_year' => 2025], 2026))->toBeFalse();
    expect($service->formDataCompletedYear([], 2026))->toBeFalse();
});

test('needsKkProfilingUpdate aliases requiresProfilingUpdate', function () {
    $service = new KkProfilingScheduleService;
    expect($service->needsKkProfilingUpdate(null))->toBeFalse();
    expect($service->requiresProfilingUpdate(null))->toBeFalse();
});

test('formDataForUpdate keeps personal fields and blanks demographics', function () {
    $registration = new KabataanRegistration([
        'last_name' => 'Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'suffix' => 'Jr',
        'email' => 'juan@example.com',
        'contact_number' => '09171234567',
        'form_data' => [
            'sex' => 'Male',
            'birthday' => '2005-01-15',
            'age' => 21,
            'purok_zone' => 'Zone 1',
            'civil_status' => 'Single',
            'education' => 'College',
            'work_status' => 'Student',
            'youth_classification' => 'In School Youth',
            'sk_voter' => 'Yes',
            'national_voter' => 'Yes',
            'sk_voted' => 'Yes',
            'kk_assembly' => 'Yes',
            'kk_times' => '1',
            'youth_age_group' => 'Child Youth',
        ],
    ]);

    $prefill = app(KabataanProfilingHistoryService::class)->formDataForUpdate($registration);

    expect($prefill['first_name'])->toBe('Juan');
    expect($prefill['last_name'])->toBe('Dela Cruz');
    expect($prefill['sex'])->toBe('Male');
    expect($prefill['birthday'])->toBe('2005-01-15');
    expect($prefill['email'])->toBe('juan@example.com');
    expect($prefill)->not->toHaveKey('civil_status');
    expect($prefill)->not->toHaveKey('education');
    expect($prefill)->not->toHaveKey('work_status');
    expect($prefill)->not->toHaveKey('youth_classification');
    expect($prefill)->not->toHaveKey('youth_age_group');
    expect($prefill)->not->toHaveKey('sk_voter');
    expect($prefill)->not->toHaveKey('national_voter');
    expect($prefill)->not->toHaveKey('sk_voted');
    expect($prefill)->not->toHaveKey('kk_assembly');
    expect($prefill)->not->toHaveKey('kk_times');
});
