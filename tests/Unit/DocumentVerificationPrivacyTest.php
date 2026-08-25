<?php

use App\Services\DocumentFingerprintService;
use App\Services\PerceptualHashService;

uses(Tests\TestCase::class);

test('document fingerprint is stable for normalized equivalent signals', function () {
    config(['documents.fingerprint_secret' => 'test-document-secret']);

    $service = new DocumentFingerprintService;

    $a = $service->fingerprint([
        'document_type' => 'national_id',
        'document_number' => '1234-5678-9012',
        'full_name' => 'Juan  Dela Cruz',
        'birthdate' => '2005-01-15',
    ]);

    $b = $service->fingerprint([
        'document_type' => 'national_id',
        'document_number' => '1234-5678-9012',
        'full_name' => 'juan dela cruz',
        'birthdate' => '2005-01-15',
    ]);

    expect($a)->not->toBeNull()
        ->and($a)->toBe($b)
        ->and(strlen((string) $a))->toBe(64);
});

test('document fingerprint differs for different document numbers', function () {
    config(['documents.fingerprint_secret' => 'test-document-secret']);

    $service = new DocumentFingerprintService;

    $a = $service->fingerprint([
        'document_type' => 'school_id',
        'document_number' => 'AAA-001',
        'full_name' => 'Synthetic Test User',
    ]);

    $b = $service->fingerprint([
        'document_type' => 'school_id',
        'document_number' => 'AAA-002',
        'full_name' => 'Synthetic Test User',
    ]);

    expect($a)->not->toBe($b);
});

test('perceptual hash matches identical synthetic images and differs for solid colors', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension is required.');
    }

    $service = new PerceptualHashService;
    $dir = sys_get_temp_dir();

    $pathA = $dir.DIRECTORY_SEPARATOR.'kkp_phash_a_'.uniqid('', true).'.png';
    $pathB = $dir.DIRECTORY_SEPARATOR.'kkp_phash_b_'.uniqid('', true).'.png';
    $pathC = $dir.DIRECTORY_SEPARATOR.'kkp_phash_c_'.uniqid('', true).'.png';

    $make = function (string $path, int $r, int $g, int $b): void {
        $img = imagecreatetruecolor(64, 64);
        $color = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, 63, 63, $color);
        imagepng($img, $path);
        imagedestroy($img);
    };

    $make($pathA, 40, 80, 160);
    $make($pathB, 40, 80, 160);
    $make($pathC, 220, 40, 40);

    $hashA = $service->hashFromFile($pathA);
    $hashB = $service->hashFromFile($pathB);
    $hashC = $service->hashFromFile($pathC);

    @unlink($pathA);
    @unlink($pathB);
    @unlink($pathC);

    expect($hashA)->not->toBeNull()
        ->and($hashA)->toBe($hashB)
        ->and($service->hammingDistance((string) $hashA, (string) $hashB))->toBe(0)
        ->and($service->hammingDistance((string) $hashA, (string) $hashC))->toBeGreaterThan(0);
});
