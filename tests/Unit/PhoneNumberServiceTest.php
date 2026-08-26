<?php

namespace Tests\Unit;

use App\Services\PhoneNumberService;
use Tests\TestCase;

class PhoneNumberServiceTest extends TestCase
{
    private PhoneNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhoneNumberService;
    }

    public function test_normalizes_valid_philippine_mobile_formats_to_e164(): void
    {
        $expected = '+639171234567';

        $this->assertSame($expected, $this->service->normalize('09171234567'));
        $this->assertSame($expected, $this->service->normalize('+639171234567'));
        $this->assertSame($expected, $this->service->normalize('639171234567'));
        $this->assertSame($expected, $this->service->normalize('09 171 234 567'));
        $this->assertSame($expected, $this->service->normalize('09-171-234-567'));
    }

    public function test_rejects_invalid_inputs(): void
    {
        foreach (['', '123', 'abc', '09111111', '091111111', '0911111111', '091111111111'] as $input) {
            $result = $this->service->validatePhilippineMobile($input, null, false);
            $this->assertFalse($result['ok'], 'Expected invalid for: '.$input);
            $this->assertContains($result['error'], [
                PhoneNumberService::MSG_REQUIRED,
                PhoneNumberService::MSG_INVALID,
                PhoneNumberService::MSG_FAKE,
            ]);
        }
    }

    public function test_rejects_same_digit_fake_patterns(): void
    {
        $fakes = [
            '09111111111',
            '09222222222',
            '09333333333',
            '09444444444',
            '09555555555',
            '09666666666',
            '09777777777',
            '09888888888',
            '09999999999',
            '09000000000',
        ];

        foreach ($fakes as $input) {
            $result = $this->service->validatePhilippineMobile($input, null, false);
            $this->assertFalse($result['ok'], 'Expected fake for: '.$input);
            $this->assertSame(PhoneNumberService::MSG_FAKE, $result['error']);
        }
    }

    public function test_rejects_sequential_and_repeated_fake_patterns(): void
    {
        foreach (['09123456789', '09876543210', '09121212121', '09090909090'] as $input) {
            $result = $this->service->validatePhilippineMobile($input, null, false);
            $this->assertFalse($result['ok'], 'Expected fake for: '.$input);
            $this->assertSame(PhoneNumberService::MSG_FAKE, $result['error']);
        }
    }

    public function test_does_not_overblock_short_digit_runs_inside_real_looking_numbers(): void
    {
        // Contains "11" / short runs but is not a full artificial pattern.
        $result = $this->service->validatePhilippineMobile('09171234567', null, false);
        $this->assertTrue($result['ok']);
        $this->assertSame('+639171234567', $result['canonical']);
    }

    public function test_rejects_low_diversity_and_excessive_repeat_fakes(): void
    {
        foreach (['09100000000', '09111111112', '09175555555', '09170000000', '09987654321'] as $input) {
            $result = $this->service->validatePhilippineMobile($input, null, false);
            $this->assertFalse($result['ok'], 'Expected fake/invalid for: '.$input);
            $this->assertContains($result['error'], [
                PhoneNumberService::MSG_FAKE,
                PhoneNumberService::MSG_INVALID,
            ]);
        }
    }

    public function test_masks_phone_numbers_for_logging(): void
    {
        $this->assertSame('6391****567', $this->service->mask('+639171234567'));
    }

    public function test_storage_variants_cover_legacy_local_formats(): void
    {
        $variants = $this->service->storageVariants('+639171234567');

        $this->assertContains('+639171234567', $variants);
        $this->assertContains('639171234567', $variants);
        $this->assertContains('09171234567', $variants);
    }
}
