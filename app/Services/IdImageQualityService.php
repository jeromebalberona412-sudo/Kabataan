<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Server-side ID image quality checks before OCR.
 * Rejects obviously unusable captures; does not prove ID authenticity.
 */
class IdImageQualityService
{
    public function __construct(
        private readonly ImagePreprocessingService $preprocessing,
    ) {}

    /**
     * @return array{ok: bool, code: ?string, message: ?string, metrics: array<string, mixed>}
     */
    public function validatePath(string $imagePath, string $side = 'front'): array
    {
        $imagePath = $this->normalizePath($imagePath);
        $sideLabel = $side === 'back' ? 'Back' : 'Front';
        $metrics = [
            'side' => $side,
            'bytes' => 0,
            'mime' => null,
            'width' => null,
            'height' => null,
            'brightness' => null,
            'contrast' => null,
            'sharpness' => null,
        ];

        if (! is_file($imagePath)) {
            return $this->fail('missing_file', 'Image file is missing. Please capture or upload again.', $metrics);
        }

        $bytes = (int) filesize($imagePath);
        $metrics['bytes'] = $bytes;
        $maxBytes = (int) config('documents.max_upload_bytes', 10 * 1024 * 1024);

        if ($bytes <= 0) {
            return $this->fail('empty_file', 'Please upload a valid image file.', $metrics);
        }

        if ($bytes > $maxBytes) {
            return $this->fail('too_large', 'Image file is too large. Please upload a smaller image.', $metrics);
        }

        $mime = @mime_content_type($imagePath) ?: null;
        $metrics['mime'] = $mime;
        $allowedMimes = config('documents.allowed_mimes', ['image/jpeg', 'image/png']);

        if (! is_string($mime) || ! in_array(strtolower($mime), array_map('strtolower', $allowedMimes), true)) {
            return $this->fail('invalid_mime', 'Please upload a valid image file (JPG or PNG).', $metrics);
        }

        $inspected = $this->preprocessing->inspectImage($imagePath);
        $width = isset($inspected['width']) ? (int) $inspected['width'] : null;
        $height = isset($inspected['height']) ? (int) $inspected['height'] : null;

        if (($width === null || $height === null || $width < 1 || $height < 1) && function_exists('getimagesize')) {
            $size = @getimagesize($imagePath);
            if (is_array($size)) {
                $width = (int) ($size[0] ?? 0);
                $height = (int) ($size[1] ?? 0);
            }
        }

        $metrics['width'] = $width;
        $metrics['height'] = $height;

        $minWidth = (int) config('documents.quality.min_width', 480);
        $minHeight = (int) config('documents.quality.min_height', 300);
        $minPixels = (int) config('documents.quality.min_pixels', 200_000);

        if ($width !== null && $height !== null && $width > 0 && $height > 0) {
            if ($width < $minWidth || $height < $minHeight || ($width * $height) < $minPixels) {
                return $this->fail(
                    'too_small',
                    "{$sideLabel} image resolution is too low. Please capture the ID closer.",
                    $metrics
                );
            }
        }

        $sampled = $this->sampleQualityMetrics($imagePath);
        if ($sampled !== null) {
            $metrics['brightness'] = $sampled['brightness'] ?? null;
            $metrics['contrast'] = $sampled['contrast'] ?? null;
            $metrics['sharpness'] = $sampled['sharpness'] ?? null;
            $metrics['glare'] = $sampled['glare'] ?? null;

            $minBrightness = (float) config('documents.quality.min_brightness', 35);
            $maxBrightness = (float) config('documents.quality.max_brightness', 230);
            $minContrast = (float) config('documents.quality.min_contrast', 18);
            $minSharpness = (float) config('documents.quality.min_sharpness', 40);
            $maxGlare = (float) config('documents.quality.max_glare_ratio', 0.28);

            $brightness = $metrics['brightness'];
            $contrast = $metrics['contrast'];
            $sharpness = $metrics['sharpness'];
            $glare = $metrics['glare'];

            if (is_numeric($brightness) && (float) $brightness < $minBrightness) {
                return $this->fail(
                    'too_dark',
                    "{$sideLabel} image is too dark. Please improve the lighting and try again.",
                    $metrics
                );
            }

            if (is_numeric($brightness) && (float) $brightness > $maxBrightness) {
                return $this->fail(
                    'too_bright',
                    "{$sideLabel} image is overexposed. Please avoid direct glare and try again.",
                    $metrics
                );
            }

            if (is_numeric($glare) && (float) $glare >= $maxGlare && is_numeric($brightness) && (float) $brightness >= 190) {
                return $this->fail(
                    'too_bright',
                    "{$sideLabel} image has excessive glare. Please avoid reflections and try again.",
                    $metrics
                );
            }

            if (is_numeric($contrast) && (float) $contrast < $minContrast) {
                return $this->fail(
                    'low_contrast',
                    "{$sideLabel} image has low contrast. Please retake with clearer lighting.",
                    $metrics
                );
            }

            if (is_numeric($sharpness) && (float) $sharpness < $minSharpness) {
                return $this->fail(
                    'too_blurry',
                    "{$sideLabel} image is too blurry. Please retake the photo.",
                    $metrics
                );
            }
        }

        return [
            'ok' => true,
            'code' => null,
            'message' => null,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{ok: bool, code: ?string, message: ?string, metrics: array<string, mixed>}
     */
    public function validateUpload(UploadedFile $file, string $side = 'front'): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return $this->fail('missing_file', 'Image file is missing. Please capture or upload again.', [
                'side' => $side,
            ]);
        }

        return $this->validatePath($path, $side);
    }

    /**
     * @return array{brightness:?float,contrast:?float,sharpness:?float,glare:?float}|null
     */
    private function sampleQualityMetrics(string $imagePath): ?array
    {
        // Prefer GD when available (faster/more reliable than PowerShell on Windows).
        if (extension_loaded('gd')) {
            $sampled = $this->sampleWithGd($imagePath);
            if ($sampled !== null) {
                return $sampled;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->sampleWithWindowsDrawing($imagePath);
        }

        return null;
    }

    /**
     * @return array{brightness:?float,contrast:?float,sharpness:?float,glare:?float}|null
     */
    private function sampleWithWindowsDrawing(string $imagePath): ?array
    {
        $script = <<<'PS1'
param([string]$InputPath)
Add-Type -AssemblyName System.Drawing
$img = [System.Drawing.Image]::FromFile($InputPath)
try {
  $sampleW = [Math]::Min(160, $img.Width)
  $sampleH = [Math]::Max(1, [int][Math]::Round($sampleW * ($img.Height / [double]$img.Width)))
  $bmp = New-Object System.Drawing.Bitmap $sampleW, $sampleH
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.DrawImage($img, 0, 0, $sampleW, $sampleH)
  $g.Dispose()

  $sum = 0.0
  $sumSq = 0.0
  $count = 0
  $lapSum = 0.0
  $lapSumSq = 0.0
  $lapCount = 0
  $brightCount = 0

  for ($y = 0; $y -lt $sampleH; $y++) {
    for ($x = 0; $x -lt $sampleW; $x++) {
      $c = $bmp.GetPixel($x, $y)
      $yVal = (0.299 * $c.R) + (0.587 * $c.G) + (0.114 * $c.B)
      $sum += $yVal
      $sumSq += ($yVal * $yVal)
      $count++
      if ($yVal -ge 245) { $brightCount++ }
    }
  }

  for ($y = 1; $y -lt ($sampleH - 1); $y++) {
    for ($x = 1; $x -lt ($sampleW - 1); $x++) {
      $c = $bmp.GetPixel($x, $y)
      $u = $bmp.GetPixel($x, $y - 1)
      $d = $bmp.GetPixel($x, $y + 1)
      $l = $bmp.GetPixel($x - 1, $y)
      $r = $bmp.GetPixel($x + 1, $y)
      $cy = (0.299 * $c.R) + (0.587 * $c.G) + (0.114 * $c.B)
      $uy = (0.299 * $u.R) + (0.587 * $u.G) + (0.114 * $u.B)
      $dy = (0.299 * $d.R) + (0.587 * $d.G) + (0.114 * $d.B)
      $ly = (0.299 * $l.R) + (0.587 * $l.G) + (0.114 * $l.B)
      $ry = (0.299 * $r.R) + (0.587 * $r.G) + (0.114 * $r.B)
      $lap = [Math]::Abs((4 * $cy) - $uy - $dy - $ly - $ry)
      $lapSum += $lap
      $lapSumSq += ($lap * $lap)
      $lapCount++
    }
  }

  $bmp.Dispose()
  $mean = if ($count -gt 0) { $sum / $count } else { 0 }
  $variance = if ($count -gt 0) { ($sumSq / $count) - ($mean * $mean) } else { 0 }
  if ($variance -lt 0) { $variance = 0 }
  $contrast = [Math]::Sqrt($variance)
  $sharp = if ($lapCount -gt 0) { ($lapSumSq / $lapCount) - (($lapSum / $lapCount) * ($lapSum / $lapCount)) } else { 0 }
  if ($sharp -lt 0) { $sharp = 0 }
  $glare = if ($count -gt 0) { $brightCount / $count } else { 0 }
  Write-Output (("brightness={0:F2};contrast={1:F2};sharpness={2:F2};glare={3:F4}") -f $mean, $contrast, $sharp, $glare)
} finally {
  $img.Dispose()
}
PS1;

        $scriptPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_id_quality_'.bin2hex(random_bytes(4)).'.ps1';
        file_put_contents($scriptPath, $script);

        try {
            $result = Process::timeout(8)->run([
                $this->powershellPath(),
                '-NoLogo',
                '-NoProfile',
                '-NonInteractive',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $scriptPath,
                '-InputPath',
                $imagePath,
            ]);

            if (! $result->successful()) {
                Log::info('ID image quality sampling skipped', [
                    'exit' => $result->exitCode(),
                ]);

                return null;
            }

            $out = trim($result->output());
            if (! preg_match(
                '/brightness=([\d.]+);contrast=([\d.]+);sharpness=([\d.]+);glare=([\d.]+)/',
                $out,
                $m
            )) {
                return null;
            }

            return [
                'brightness' => (float) $m[1],
                'contrast' => (float) $m[2],
                'sharpness' => (float) $m[3],
                'glare' => (float) $m[4],
            ];
        } catch (\Throwable $exception) {
            Log::warning('ID image quality sampling failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($scriptPath);
        }
    }

    /**
     * @return array{brightness:?float,contrast:?float,sharpness:?float,glare:?float}|null
     */
    private function sampleWithGd(string $imagePath): ?array
    {
        $info = @getimagesize($imagePath);
        if (! is_array($info)) {
            return null;
        }

        $mime = $info['mime'] ?? '';
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($imagePath),
            'image/png' => @imagecreatefrompng($imagePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imagePath) : false,
            default => false,
        };

        if ($src === false) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $sampleW = min(160, $width);
        $sampleH = max(1, (int) round($sampleW * ($height / max(1, $width))));
        $sample = imagecreatetruecolor($sampleW, $sampleH);
        imagecopyresampled($sample, $src, 0, 0, 0, 0, $sampleW, $sampleH, $width, $height);
        imagedestroy($src);

        $sum = 0.0;
        $sumSq = 0.0;
        $count = 0;
        $brightCount = 0;
        $gray = [];

        for ($y = 0; $y < $sampleH; $y++) {
            for ($x = 0; $x < $sampleW; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $yVal = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
                $gray[$y][$x] = $yVal;
                $sum += $yVal;
                $sumSq += $yVal * $yVal;
                $count++;
                if ($yVal >= 245) {
                    $brightCount++;
                }
            }
        }

        $lapSum = 0.0;
        $lapSumSq = 0.0;
        $lapCount = 0;

        for ($y = 1; $y < $sampleH - 1; $y++) {
            for ($x = 1; $x < $sampleW - 1; $x++) {
                $lap = abs(
                    (4 * $gray[$y][$x])
                    - $gray[$y - 1][$x]
                    - $gray[$y + 1][$x]
                    - $gray[$y][$x - 1]
                    - $gray[$y][$x + 1]
                );
                $lapSum += $lap;
                $lapSumSq += $lap * $lap;
                $lapCount++;
            }
        }

        imagedestroy($sample);

        $mean = $count > 0 ? $sum / $count : 0.0;
        $variance = $count > 0 ? ($sumSq / $count) - ($mean * $mean) : 0.0;
        $contrast = sqrt(max(0.0, $variance));
        $sharpMean = $lapCount > 0 ? $lapSum / $lapCount : 0.0;
        $sharp = $lapCount > 0 ? ($lapSumSq / $lapCount) - ($sharpMean * $sharpMean) : 0.0;

        return [
            'brightness' => round($mean, 2),
            'contrast' => round($contrast, 2),
            'sharpness' => round(max(0.0, $sharp), 2),
            'glare' => $count > 0 ? round($brightCount / $count, 4) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{ok: bool, code: string, message: string, metrics: array<string, mixed>}
     */
    private function fail(string $code, string $message, array $metrics): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'metrics' => $metrics,
        ];
    }

    private function powershellPath(): string
    {
        $systemRoot = (string) (getenv('SystemRoot') ?: 'C:\\Windows');
        $candidate = $systemRoot.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        return is_file($candidate) ? $candidate : 'powershell.exe';
    }

    private function normalizePath(string $path): string
    {
        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
    }
}
