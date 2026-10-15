<?php

use App\Modules\Programs\Services\KabataanProgramService;

it('maps kk profiling education labels to scholarship categories', function () {
    expect(KabataanProgramService::canonicalScholarshipEducation('High School Level'))->toBe('High School Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation('High school level'))->toBe('High School Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation('Senior High School'))->toBe('High School Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation('High School Grad'))->toBe('High School Grad')
        ->and(KabataanProgramService::canonicalScholarshipEducation('High school graduate'))->toBe('High School Grad')
        ->and(KabataanProgramService::canonicalScholarshipEducation('College Level'))->toBe('College Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation('  college   level  '))->toBe('College Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation(''))->toBe('')
        ->and(KabataanProgramService::canonicalScholarshipEducation('Elementary Level'))->toBe('Elementary Level')
        ->and(KabataanProgramService::canonicalScholarshipEducation('College Grad'))->toBe('College Grad');
});
