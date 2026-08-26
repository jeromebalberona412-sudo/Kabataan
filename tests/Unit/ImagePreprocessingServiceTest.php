<?php

namespace Tests\Unit;

use App\Services\ImagePreprocessingService;
use Tests\TestCase;

class ImagePreprocessingServiceTest extends TestCase
{
    public function test_inspect_image_reports_bytes_and_dimensions_with_gd(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_inspect_'.uniqid('', true).'.jpg';
        $image = imagecreatetruecolor(640, 400);
        $color = imagecolorallocate($image, 90, 120, 150);
        imagefilledrectangle($image, 0, 0, 639, 399, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        try {
            $meta = app(ImagePreprocessingService::class)->inspectImage($path);

            $this->assertIsInt($meta['bytes']);
            $this->assertGreaterThan(1000, $meta['bytes']);
            $this->assertSame(640, $meta['width']);
            $this->assertSame(400, $meta['height']);

            $prepared = app(ImagePreprocessingService::class)->prepareVariants($path);
            $this->assertGreaterThanOrEqual(1, count($prepared['variants']));
            app(ImagePreprocessingService::class)->cleanup($prepared['variants'], $path);
        } finally {
            @unlink($path);
        }
    }
}
