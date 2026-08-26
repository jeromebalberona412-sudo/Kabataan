<?php

namespace Tests\Unit;

use App\Services\OCRService;
use Tests\TestCase;

class IdFrontBackUniquenessTest extends TestCase
{
    public function test_ocr_text_similarity_detects_same_side(): void
    {
        $service = app(OCRService::class);

        $text = 'Republic of the Philippines PhilSys Given Names Juan Dela Cruz Date of Birth January 1 2005 SEX Male';

        $this->assertTrue($service->frontAndBackOcrTextTooSimilar($text, $text));
        $this->assertTrue($service->frontAndBackOcrTextTooSimilar(
            $text,
            'Republic of the Philippines PhilSys Given Names Juan Dela Cruz Date of Birth January 1 2005 SEX Male Address Brgy'
        ));
        $this->assertFalse($service->frontAndBackOcrTextTooSimilar(
            $text,
            'COMELEC Voter Certification Precinct 0123A Registered Voter Juan Dela Cruz'
        ));
        $this->assertFalse($service->frontAndBackOcrTextTooSimilar('short', 'short also'));
    }

    public function test_byte_identical_files_are_flagged_when_available(): void
    {
        $service = app(OCRService::class);
        $dir = sys_get_temp_dir();
        $pathA = $dir.DIRECTORY_SEPARATOR.'kkp_same_a_'.uniqid('', true).'.jpg';
        $pathB = $dir.DIRECTORY_SEPARATOR.'kkp_same_b_'.uniqid('', true).'.jpg';

        // Same bytes (no GD required) must always be rejected as identical front/back.
        file_put_contents($pathA, str_repeat('FAKEJPEGCONTENTFORIDTEST', 1200));
        copy($pathA, $pathB);

        try {
            $this->assertTrue($service->frontAndBackAreSameImage($pathA, $pathB));
        } finally {
            @unlink($pathA);
            @unlink($pathB);
        }
    }

    public function test_gd_near_duplicate_reencoded_jpegs_are_flagged(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $service = app(OCRService::class);
        $dir = sys_get_temp_dir();
        $pathA = $dir.DIRECTORY_SEPARATOR.'kkp_near_a_'.uniqid('', true).'.jpg';
        $pathB = $dir.DIRECTORY_SEPARATOR.'kkp_near_b_'.uniqid('', true).'.jpg';
        $pathC = $dir.DIRECTORY_SEPARATOR.'kkp_near_c_'.uniqid('', true).'.jpg';

        $this->writePatternJpeg($pathA, 640, 400, 95, 30);
        $this->writePatternJpeg($pathB, 640, 400, 55, 30); // same pattern, lower quality
        $this->writePatternJpeg($pathC, 640, 400, 90, 180); // different pattern seed

        try {
            $this->assertTrue(
                $service->frontAndBackAreSameImage($pathA, $pathB),
                'Re-encoded copies of the same ID photo must be rejected.'
            );
            $this->assertFalse(
                $service->frontAndBackAreSameImage($pathA, $pathC),
                'Visually different photos must still be allowed as front/back.'
            );
        } finally {
            @unlink($pathA);
            @unlink($pathB);
            @unlink($pathC);
        }
    }

    private function writePatternJpeg(string $path, int $width, int $height, int $quality, int $seed): void
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x += 8) {
                $tone = (($x + $y + $seed) * 37) % 200;
                $color = imagecolorallocate($image, 40 + $tone, 60 + ($tone % 80), 90 + ($tone % 100));
                imagefilledrectangle($image, $x, $y, $x + 7, $y + 7, $color);
            }
        }
        // Fake ID-like text block so quality/OCR paths have structure.
        $white = imagecolorallocate($image, 245, 245, 245);
        imagefilledrectangle($image, 40, 40, $width - 40, 120, $white);
        imagejpeg($image, $path, $quality);
        imagedestroy($image);
    }
}
