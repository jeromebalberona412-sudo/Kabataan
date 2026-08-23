<?php

use App\Services\KkProfilingScheduleService;

test('form data marks the profiling year as already completed', function () {
    $service = new KkProfilingScheduleService;

    expect($service->formDataCompletedYear(['profile_updated_year' => 2026], 2026))->toBeTrue();
    expect($service->formDataCompletedYear(['profile_updated_year' => 2027], 2026))->toBeTrue();
    expect($service->formDataCompletedYear(['profile_updated_year' => 2025], 2026))->toBeFalse();
    expect($service->formDataCompletedYear([], 2026))->toBeFalse();
});
