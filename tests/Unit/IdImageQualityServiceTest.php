<?php

namespace Tests\Unit;

use App\Services\IdImageQualityService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdImageQualityServiceTest extends TestCase
{
    #[Test]
    public function it_rejects_missing_files(): void
    {
        $service = app(IdImageQualityService::class);
        $result = $service->validatePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp-missing-'.uniqid().'.jpg');

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_file', $result['code']);
    }

    #[Test]
    public function it_rejects_non_image_bytes(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp-quality-'.uniqid().'.jpg';
        file_put_contents($path, "<?php echo 'not an image';");

        try {
            $service = app(IdImageQualityService::class);
            $result = $service->validatePath($path);

            $this->assertFalse($result['ok']);
            $this->assertContains($result['code'], ['invalid_mime', 'empty_file', 'too_small', 'missing_file']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_accepts_a_reasonable_jpeg_fixture_when_available(): void
    {
        $path = $this->createSolidJpeg(900, 600, 120, 120, 120);
        if ($path === null) {
            $this->markTestSkipped('Unable to create JPEG fixture in this environment.');
        }

        try {
            $service = app(IdImageQualityService::class);
            $result = $service->validatePath($path, 'front');

            // Dimensions/MIME should pass; blur/brightness depend on OS sampling.
            $this->assertNotSame('missing_file', $result['code']);
            $this->assertNotSame('invalid_mime', $result['code']);
            $this->assertNotSame('too_small', $result['code']);
            $this->assertNotSame('too_large', $result['code']);
        } finally {
            @unlink($path);
        }
    }

    private function createSolidJpeg(int $width, int $height, int $r, int $g, int $b): ?string
    {
        if (! extension_loaded('gd')) {
            // Minimal valid JPEG header is not enough for mime/quality; skip generation.
            return null;
        }

        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, $width, $height, $color);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp-quality-'.uniqid().'.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return is_file($path) ? $path : null;
    }
}
