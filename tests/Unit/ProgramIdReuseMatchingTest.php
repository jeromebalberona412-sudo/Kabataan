<?php

use App\Support\SupportingDocumentTypes;

test('normalize matches known id types without confusing school and national ids', function () {
    expect(SupportingDocumentTypes::normalize('School ID'))->toBe('school_id');
    expect(SupportingDocumentTypes::normalize('  school_id  '))->toBe('school_id');
    expect(SupportingDocumentTypes::normalize('PhilSys / National ID'))->toBe('national_id');
    expect(SupportingDocumentTypes::normalize('National ID'))->toBe('national_id');
    expect(SupportingDocumentTypes::normalize("Voter's ID"))->toBe('voters_id');
    expect(SupportingDocumentTypes::normalize('PhilHealth ID'))->toBe('philhealth_id');
    expect(SupportingDocumentTypes::normalize('Certificate of Enrollment'))->toBeNull();
    expect(SupportingDocumentTypes::normalize('Valid ID'))->toBeNull();
});

test('reuse is offered only when the file question accepts images and the id type matches', function () {
    $schoolImage = [
        'id' => 'q1',
        'type' => 'file',
        'label' => 'School ID',
        'file_type' => 'image',
        'accepted_id_types' => ['school_id'],
    ];
    $nationalImage = [
        'id' => 'q2',
        'type' => 'file',
        'label' => 'National ID',
        'file_type' => 'image',
        'accepted_id_types' => ['national_id'],
    ];
    $pdfOnly = [
        'id' => 'q3',
        'type' => 'file',
        'label' => 'School ID',
        'file_type' => 'pdf',
    ];
    $inferred = [
        'id' => 'q4',
        'type' => 'file',
        'label' => 'School ID',
        'file_type' => 'image',
    ];
    $multiple = [
        'id' => 'q5',
        'type' => 'file',
        'label' => 'Valid ID',
        'file_type' => 'pdf_or_image',
        'accepted_id_types' => ['school_id', 'national_id'],
    ];

    expect(SupportingDocumentTypes::acceptedIdTypesForQuestion($schoolImage))->toBe(['school_id']);
    expect(SupportingDocumentTypes::acceptedIdTypesForQuestion($nationalImage))->toBe(['national_id']);
    expect(SupportingDocumentTypes::acceptedIdTypesForQuestion($pdfOnly))->toBe([]);
    expect(SupportingDocumentTypes::acceptedIdTypesForQuestion($inferred))->toBe(['school_id']);
    expect(SupportingDocumentTypes::acceptedIdTypesForQuestion($multiple))->toBe(['school_id', 'national_id']);
    expect(in_array('school_id', SupportingDocumentTypes::acceptedIdTypesForQuestion($nationalImage), true))->toBeFalse();
});
