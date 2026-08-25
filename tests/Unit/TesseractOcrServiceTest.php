<?php

namespace Tests\Unit;

use App\Services\TesseractOcrService;
use Tests\TestCase;

class TesseractOcrServiceTest extends TestCase
{
    public function test_tesseract_executable_is_resolvable_on_this_environment(): void
    {
        $service = app(TesseractOcrService::class);
        $path = $service->resolveExecutable();

        if ($path === null) {
            $this->markTestSkipped('Tesseract is not installed in this environment.');
        }

        $this->assertFileExists($path);

        $health = $service->healthCheck();
        $this->assertSame('AVAILABLE', $health['tesseract']);
        $this->assertNotEmpty($health['version']);
    }
}
