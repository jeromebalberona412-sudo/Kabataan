<?php

namespace App\Console\Commands;

use App\Services\ImagePreprocessingService;
use App\Services\TesseractOcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class OcrHealthCheckCommand extends Command
{
    protected $signature = 'ocr:health {--image= : Optional probe image path}';

    protected $description = 'Diagnose Tesseract OCR availability without printing OCR text or PII';

    public function handle(TesseractOcrService $tesseract, ImagePreprocessingService $preprocessing): int
    {
        $probePath = $this->option('image');
        $createdProbe = false;

        if (! is_string($probePath) || $probePath === '' || ! is_file($probePath)) {
            $probePath = $this->createSyntheticProbeImage();
            $createdProbe = is_string($probePath) && is_file($probePath);
        }

        $health = $tesseract->healthCheck(is_string($probePath) ? $probePath : null);

        $this->line('Tesseract status: '.strtoupper((string) ($health['tesseract'] ?? 'UNAVAILABLE')));
        $this->line('Tesseract version: '.(($health['version'] ?? null) ?: 'n/a'));
        $this->line('Executable: '.(($health['path'] ?? null) ?: 'n/a'));
        $this->line('Languages: '.implode(', ', $health['languages'] ?? []));
        $this->line('Configured lang: '.(($health['configured_lang'] ?? null) ?: 'n/a'));
        $this->line('Lang available: '.(! empty($health['lang_available']) ? 'yes' : 'no'));

        if (is_string($probePath) && is_file($probePath)) {
            $meta = $preprocessing->inspectImage($probePath);
            $this->line('Probe image: VALID');
            $this->line('Image dimensions: '.(($meta['width'] ?? '?').' × '.($meta['height'] ?? '?')));
            $this->line('Image bytes: '.($meta['bytes'] ?? 0));
            $this->line('Preprocessing: '.(($health['probe']['status'] ?? null) !== 'skipped' ? 'RAN' : 'SKIPPED'));
            $this->line('OCR: '.strtoupper((string) ($health['probe']['status'] ?? 'n/a')));
            $this->line('OCR character count: '.($health['probe']['text_length'] ?? 0));
        } else {
            $this->line('Probe image: SKIPPED');
        }

        $this->line('Processing ms: '.($health['processing_ms'] ?? 0));

        if ($createdProbe && is_string($probePath) && is_file($probePath)) {
            @unlink($probePath);
        }

        return ($health['status'] ?? '') === 'available' ? self::SUCCESS : self::FAILURE;
    }

    private function createSyntheticProbeImage(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $script = <<<'PS1'
Add-Type -AssemblyName System.Drawing
$bmp = New-Object System.Drawing.Bitmap 1100,420
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.Clear([System.Drawing.Color]::White)
$font = New-Object System.Drawing.Font 'Arial',30
$brush = [System.Drawing.Brushes]::Black
$g.DrawString('REPUBLIC OF THE PHILIPPINES', $font, $brush, 40, 40)
$g.DrawString('SCHOOL ID', $font, $brush, 40, 110)
$g.DrawString('NAME: JUAN TEST', $font, $brush, 40, 180)
$g.DrawString('STUDENT NUMBER: 2026-0001', $font, $brush, 40, 250)
$path = Join-Path $env:TEMP ('kkp_ocr_health_' + [guid]::NewGuid().ToString('N') + '.png')
$bmp.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose()
Write-Output $path
PS1;

        $scriptPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_ocr_health_'.bin2hex(random_bytes(4)).'.ps1';
        file_put_contents($scriptPath, $script);

        try {
            $systemRoot = (string) (getenv('SystemRoot') ?: 'C:\\Windows');
            $powershell = $systemRoot.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
            if (! is_file($powershell)) {
                $powershell = 'powershell.exe';
            }

            $result = Process::timeout(45)->run([
                $powershell,
                '-NoLogo',
                '-NoProfile',
                '-NonInteractive',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $scriptPath,
            ]);

            $path = trim($result->output());

            return is_file($path) ? $path : null;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($scriptPath);
        }
    }
}
