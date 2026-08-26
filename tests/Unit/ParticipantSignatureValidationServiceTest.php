<?php

namespace Tests\Unit;

use App\Services\ParticipantSignatureValidationService;
use Tests\TestCase;

class ParticipantSignatureValidationServiceTest extends TestCase
{
    private ParticipantSignatureValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ParticipantSignatureValidationService::class);
    }

    public function test_empty_signature_is_required(): void
    {
        $result = $this->service->validate('');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('required', strtolower((string) $result['error']));
    }

    public function test_corrupted_payload_fails(): void
    {
        $result = $this->service->validate('data:image/png;base64,not-a-real-image');
        $this->assertFalse($result['ok']);
    }

    public function test_non_image_bytes_as_jpeg_data_url_fail(): void
    {
        $fake = 'data:image/jpeg;base64,'.base64_encode('%PDF-1.4 fake pdf content');
        $result = $this->service->validate($fake);
        $this->assertFalse($result['ok']);
    }

    public function test_valid_png_data_url_passes_structural_checks(): void
    {
        $dataUrl = $this->makeMinimalPngDataUrl(320, 120, str_repeat('X', 2000));
        $result = $this->service->validate($dataUrl);
        $this->assertTrue($result['ok'], $result['error'] ?? 'unexpected failure');
    }

    public function test_tiny_png_payload_fails_blank_gate_without_enough_bytes(): void
    {
        // Valid IHDR but tiny IDAT — treated as blank when GD is unavailable.
        $dataUrl = $this->makeMinimalPngDataUrl(320, 120, 'tiny');
        $result = $this->service->validate($dataUrl);

        if (extension_loaded('gd')) {
            // GD will fail imagecreatefromstring on this crafted non-image IDAT.
            $this->assertFalse($result['ok']);
        } else {
            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('visible text', strtolower((string) $result['error']));
        }
    }

    public function test_oversized_payload_fails(): void
    {
        config(['signature.max_bytes' => 50]);
        $dataUrl = $this->makeMinimalPngDataUrl(320, 120, str_repeat('Y', 200));
        $result = $this->service->validate($dataUrl);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('2 mb', strtolower((string) $result['error']));
    }

    public function test_white_background_with_black_ink_passes_when_gd_available(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for pixel inspection tests.');
        }

        $dataUrl = $this->makeGdSignatureDataUrl(255, 255, 255, 0, 0, 0);
        $result = $this->service->validate($dataUrl);
        $this->assertTrue($result['ok'], $result['error'] ?? 'unexpected failure');
    }

    public function test_black_background_fails_when_gd_available(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for pixel inspection tests.');
        }

        $dataUrl = $this->makeGdSignatureDataUrl(0, 0, 0, 255, 255, 255);
        $result = $this->service->validate($dataUrl);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('white background', strtolower((string) $result['error']));
    }

    public function test_blank_white_image_fails_when_gd_available(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for pixel inspection tests.');
        }

        $w = 320;
        $h = 120;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        ob_start();
        imagepng($img);
        $binary = ob_get_clean();
        imagedestroy($img);

        $result = $this->service->validate('data:image/png;base64,'.base64_encode($binary));
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('visible text', strtolower((string) $result['error']));
    }

    /**
     * Craft a PNG with valid signature + IHDR (IDAT may be dummy).
     * Enough for no-GD structural path.
     */
    private function makeMinimalPngDataUrl(int $width, int $height, string $idatPayload): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdr = $this->pngChunk('IHDR', $ihdrData);
        $idat = $this->pngChunk('IDAT', $idatPayload);
        $iend = $this->pngChunk('IEND', '');
        $binary = $signature.$ihdr.$idat.$iend;

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    private function pngChunk(string $type, string $data): string
    {
        $len = pack('N', strlen($data));
        $crc = pack('N', crc32($type.$data) & 0xFFFFFFFF);

        return $len.$type.$data.$crc;
    }

    private function makeGdSignatureDataUrl(int $bgR, int $bgG, int $bgB, int $inkR, int $inkG, int $inkB): string
    {
        $w = 320;
        $h = 120;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, $bgR, $bgG, $bgB);
        $ink = imagecolorallocate($img, $inkR, $inkG, $inkB);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        imageline($img, 40, 60, 280, 60, $ink);
        imageline($img, 60, 40, 260, 90, $ink);
        ob_start();
        imagepng($img);
        $binary = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
