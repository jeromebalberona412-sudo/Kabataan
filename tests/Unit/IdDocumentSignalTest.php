<?php

namespace Tests\Unit;

use App\Services\OCRService;
use ReflectionClass;
use Tests\TestCase;

class IdDocumentSignalTest extends TestCase
{
    private function hasSignal(string $text): bool
    {
        $service = app(OCRService::class);
        $method = (new ReflectionClass($service))->getMethod('textHasSupportingIdSignal');
        $method->setAccessible(true);

        return (bool) $method->invoke($service, $text);
    }

    private function supportsType(string $text, string $type, array $scores = []): bool
    {
        $service = app(OCRService::class);
        $method = (new ReflectionClass($service))->getMethod('textSupportsSelectedIdType');
        $method->setAccessible(true);

        return (bool) $method->invoke($service, $text, $type, $scores);
    }

    public function test_rejects_empty_and_random_words(): void
    {
        $this->assertFalse($this->hasSignal(''));
        $this->assertFalse($this->hasSignal('monkey banana tree forest cute animal photo'));
        $this->assertFalse($this->hasSignal('hello world photo selfie vacation beach sunset'));
        $this->assertFalse($this->hasSignal('JOHN SMITH MALE JANUARY 1 2000 CITY STREET'));
        $this->assertFalse($this->hasSignal('University of the Philippines campus building photo'));
        $this->assertFalse($this->hasSignal('Republic of the Philippines Department of Tourism Welcome'));
        $this->assertFalse($this->hasSignal('MEME FUNNY CAT PHOTO SHARE LIKE COMMENT'));
    }

    public function test_accepts_philsys_and_id_labels(): void
    {
        $this->assertTrue($this->hasSignal('Republic of the Philippines PhilSys National ID Given Names Juan'));
        $this->assertTrue($this->hasSignal('PHILHEALTH MEMBER PIN 12-345678901-2'));
        $this->assertTrue($this->hasSignal('COMELEC Voter\'s ID Precinct 0123A Registered Voter'));
        $this->assertTrue($this->hasSignal('SCHOOL ID STUDENT NO 2024-001 University of Laguna'));
        $this->assertTrue($this->hasSignal("LAST NAME DELA CRUZ\nGIVEN NAMES JUAN\nDATE OF BIRTH JANUARY 1, 2005\nSEX MALE\nADDRESS BRGY"));
    }

    public function test_selected_type_must_match_evidence(): void
    {
        $philsys = 'Republic of the Philippines PhilSys Given Names Juan Date of Birth SEX Male';
        $this->assertTrue($this->supportsType($philsys, 'national_id', ['national_id' => 0.7]));
        $this->assertFalse($this->supportsType($philsys, 'philhealth_id', ['philhealth_id' => 0.0]));
        $this->assertFalse($this->supportsType('monkey banana cute animal selfie photo', 'national_id', []));
        $this->assertTrue($this->supportsType('PHILHEALTH MEMBER 12-345678901-2', 'philhealth_id', []));
        $this->assertFalse($this->supportsType('Republic of the Philippines Department Welcome', 'national_id', []));
        $this->assertFalse($this->supportsType('PHILHEALTH MEMBER 12-345678901-2', 'national_id', ['national_id' => 0.1]));
    }

    public function test_enforce_rejects_api_style_false_positives(): void
    {
        $service = app(OCRService::class);
        $method = (new ReflectionClass($service))->getMethod('enforceDetectionAccuracy');
        $method->setAccessible(true);

        $garbage = $method->invoke($service, [
            'success' => true,
            'validation_error' => false,
            'needs_review' => false,
            'document_detected' => 'yes',
            'id_type' => 'national_id',
            'confidence' => 0.92,
            'raw_text' => 'monkey banana cute animal selfie vacation beach',
            'source' => 'api',
        ], 'national_id');

        $this->assertTrue((bool) $garbage['validation_error']);
        $this->assertFalse((bool) $garbage['success']);
        $this->assertSame('no', $garbage['document_detected']);

        $wrongType = $method->invoke($service, [
            'success' => true,
            'validation_error' => false,
            'document_detected' => 'yes',
            'id_type' => 'national_id',
            'confidence' => 0.8,
            'raw_text' => 'Republic of the Philippines PhilSys Given Names Juan Date of Birth SEX Male',
            'scores' => ['national_id' => 0.8, 'philhealth_id' => 0.0],
            'source' => 'api',
        ], 'philhealth_id');

        $this->assertTrue((bool) $wrongType['validation_error']);
        $this->assertFalse((bool) $wrongType['success']);

        $mismatchDetected = $method->invoke($service, [
            'success' => true,
            'validation_error' => false,
            'document_detected' => 'yes',
            'id_type' => 'philhealth_id',
            'confidence' => 0.7,
            'raw_text' => 'PHILHEALTH MEMBER PIN 12-345678901-2',
            'scores' => ['philhealth_id' => 0.7, 'national_id' => 0.0],
            'source' => 'api',
        ], 'national_id');

        $this->assertTrue((bool) $mismatchDetected['validation_error']);
        $this->assertFalse((bool) ($mismatchDetected['needs_review'] ?? false));

        $valid = $method->invoke($service, [
            'success' => true,
            'validation_error' => false,
            'document_detected' => 'yes',
            'id_type' => 'national_id',
            'confidence' => 0.8,
            'raw_text' => 'Republic of the Philippines PhilSys National ID Given Names Juan Date of Birth SEX Male',
            'scores' => ['national_id' => 0.8],
            'source' => 'api',
        ], 'national_id');

        $this->assertFalse((bool) $valid['validation_error']);
        $this->assertSame('yes', $valid['document_detected']);
        $this->assertTrue((bool) $valid['success']);
    }
}
