<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Creates temporary OCR-friendly image variants without modifying originals.
 * Uses System.Drawing on Windows when PHP GD/Imagick are unavailable.
 */
class ImagePreprocessingService
{
    /**
     * @return array{variants: list<string>, meta: array<string, mixed>}
     */
    public function prepareVariants(string $imagePath): array
    {
        $imagePath = $this->normalizePath($imagePath);
        $meta = $this->inspectImage($imagePath);

        if (! is_file($imagePath)) {
            return [
                'variants' => [],
                'meta' => array_merge($meta, [
                    'valid' => false,
                    'preprocessing' => 'failed',
                    'reason' => 'missing_file',
                ]),
            ];
        }

        $variants = [$imagePath];

        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $prepared = $this->prepareWithGd($imagePath);
            foreach ($prepared as $path) {
                if (is_string($path) && is_file($path) && ! in_array($path, $variants, true)) {
                    $variants[] = $path;
                }
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $prepared = $this->prepareWithWindowsDrawing($imagePath);
            foreach ($prepared as $path) {
                if (is_string($path) && is_file($path) && ! in_array($path, $variants, true)) {
                    $variants[] = $path;
                }
            }
        }

        return [
            'variants' => $variants,
            'meta' => array_merge($meta, [
                'valid' => true,
                'preprocessing' => count($variants) > 1 ? 'success' : 'passthrough',
                'variant_count' => count($variants),
            ]),
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    public function cleanup(array $paths, string $originalPath): void
    {
        $originalPath = $this->normalizePath($originalPath);

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $path = $this->normalizePath($path);
            if ($path !== $originalPath && is_file($path) && str_contains(basename($path), 'kkp_ocr_prep_')) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectImage(string $imagePath): array
    {
        $imagePath = $this->normalizePath($imagePath);
        $bytes = is_file($imagePath) ? (int) filesize($imagePath) : 0;
        $mime = is_file($imagePath) ? (@mime_content_type($imagePath) ?: null) : null;
        $width = null;
        $height = null;

        if (is_file($imagePath) && function_exists('getimagesize')) {
            $dimensions = @getimagesize($imagePath);
            if (is_array($dimensions)) {
                $width = (int) ($dimensions[0] ?? 0) ?: null;
                $height = (int) ($dimensions[1] ?? 0) ?: null;
            }
        }

        if (($width === null || $height === null) && is_file($imagePath) && PHP_OS_FAMILY === 'Windows') {
            $dims = $this->readDimensionsWithWindows($imagePath);
            $width = $dims['width'] ?? null;
            $height = $dims['height'] ?? null;
        }

        return [
            'path_basename' => is_file($imagePath) ? basename($imagePath) : null,
            'bytes' => $bytes,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'extension' => strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION)),
        ];
    }

    /**
     * @return list<string>
     */
    private function prepareWithGd(string $imagePath): array
    {
        $binary = @file_get_contents($imagePath);
        if ($binary === false || $binary === '') {
            return [];
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return [];
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($image);

            return [];
        }

        $maxSide = 2000;
        $scale = min(1.0, $maxSide / max($srcW, $srcH));
        if (max($srcW, $srcH) < 900) {
            $scale = max($scale, 1200 / max($srcW, $srcH));
        }

        $width = max(1, (int) round($srcW * $scale));
        $height = max(1, (int) round($srcH * $scale));
        $token = bin2hex(random_bytes(6));
        $outDir = sys_get_temp_dir();
        $standard = $outDir.DIRECTORY_SEPARATOR."kkp_ocr_prep_{$token}_std.jpg";
        $contrast = $outDir.DIRECTORY_SEPARATOR."kkp_ocr_prep_{$token}_hi.jpg";

        $resized = imagecreatetruecolor($width, $height);
        if ($resized === false) {
            imagedestroy($image);

            return [];
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $srcW, $srcH);
        imagedestroy($image);

        $okStd = @imagejpeg($resized, $standard, 90);

        // Mild contrast grayscale variant for stubborn OCR.
        if (function_exists('imagefilter')) {
            imagefilter($resized, IMG_FILTER_GRAYSCALE);
            imagefilter($resized, IMG_FILTER_CONTRAST, -18);
            imagefilter($resized, IMG_FILTER_BRIGHTNESS, 8);
        }
        $okHi = @imagejpeg($resized, $contrast, 95);
        imagedestroy($resized);

        $out = [];
        if ($okStd && is_file($standard) && filesize($standard) > 0) {
            $out[] = $standard;
        }
        if ($okHi && is_file($contrast) && filesize($contrast) > 0) {
            $out[] = $contrast;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function prepareWithWindowsDrawing(string $imagePath): array
    {
        $outDir = sys_get_temp_dir();
        $token = bin2hex(random_bytes(6));
        $standard = $outDir.DIRECTORY_SEPARATOR."kkp_ocr_prep_{$token}_std.jpg";
        $contrast = $outDir.DIRECTORY_SEPARATOR."kkp_ocr_prep_{$token}_hi.jpg";

        $script = <<<'PS1'
param(
  [string]$InputPath,
  [string]$StandardPath,
  [string]$ContrastPath,
  [int]$MaxSide = 2000
)
Add-Type -AssemblyName System.Drawing
$img = [System.Drawing.Image]::FromFile($InputPath)
try {
  $scale = [Math]::Min(1.0, [double]$MaxSide / [double][Math]::Max($img.Width, $img.Height))
  if ([Math]::Max($img.Width, $img.Height) -lt 900) {
    $scale = [Math]::Max($scale, 1200.0 / [double][Math]::Max($img.Width, $img.Height))
  }
  $width = [Math]::Max(1, [int][Math]::Round($img.Width * $scale))
  $height = [Math]::Max(1, [int][Math]::Round($img.Height * $scale))

  $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
  $encoder = [System.Drawing.Imaging.Encoder]::Quality
  $params = New-Object System.Drawing.Imaging.EncoderParameters(1)
  $params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter($encoder, 90L)

  # Standard: resized color JPEG for OCR.
  $std = New-Object System.Drawing.Bitmap $width, $height
  $g1 = [System.Drawing.Graphics]::FromImage($std)
  $g1.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g1.DrawImage($img, 0, 0, $width, $height)
  $std.Save($StandardPath, $codec, $params)
  $g1.Dispose(); $std.Dispose()

  # High-contrast grayscale variant (fast ColorMatrix; original untouched).
  $params2 = New-Object System.Drawing.Imaging.EncoderParameters(1)
  $params2.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter($encoder, 95L)
  $hi = New-Object System.Drawing.Bitmap $width, $height
  $g2 = [System.Drawing.Graphics]::FromImage($hi)
  $g2.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $cm = New-Object System.Drawing.Imaging.ColorMatrix
  # Grayscale + mild contrast boost
  $cm.Matrix00 = 0.404; $cm.Matrix01 = 0.404; $cm.Matrix02 = 0.404
  $cm.Matrix10 = 0.792; $cm.Matrix11 = 0.792; $cm.Matrix12 = 0.792
  $cm.Matrix20 = 0.154; $cm.Matrix21 = 0.154; $cm.Matrix22 = 0.154
  $cm.Matrix33 = 1; $cm.Matrix44 = 1
  $cm.Matrix40 = -0.08; $cm.Matrix41 = -0.08; $cm.Matrix42 = -0.08
  $ia = New-Object System.Drawing.Imaging.ImageAttributes
  $ia.SetColorMatrix($cm)
  $g2.DrawImage($img, (New-Object System.Drawing.Rectangle 0, 0, $width, $height), 0, 0, $img.Width, $img.Height, [System.Drawing.GraphicsUnit]::Pixel, $ia)
  $hi.Save($ContrastPath, $codec, $params2)
  $ia.Dispose(); $g2.Dispose(); $hi.Dispose()
  Write-Output 'OK'
} finally {
  $img.Dispose()
}
PS1;

        $scriptPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_ocr_prep_'.bin2hex(random_bytes(4)).'.ps1';
        file_put_contents($scriptPath, $script);

        try {
            $result = Process::timeout(60)->run([
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
                '-StandardPath',
                $standard,
                '-ContrastPath',
                $contrast,
                '-MaxSide',
                '2000',
            ]);

            if (! $result->successful()) {
                Log::info('OCR preprocessing skipped', [
                    'exit' => $result->exitCode(),
                    'bytes' => is_file($imagePath) ? filesize($imagePath) : null,
                ]);

                return [];
            }
        } catch (\Throwable $exception) {
            Log::warning('OCR preprocessing failed', ['error' => $exception->getMessage()]);

            return [];
        } finally {
            @unlink($scriptPath);
        }

        $out = [];
        if (is_file($standard) && filesize($standard) > 0) {
            $out[] = $standard;
        }
        if (is_file($contrast) && filesize($contrast) > 0) {
            $out[] = $contrast;
        }

        return $out;
    }

    /**
     * @return array{width:?int,height:?int}
     */
    private function readDimensionsWithWindows(string $imagePath): array
    {
        $script = <<<'PS1'
param([string]$InputPath)
Add-Type -AssemblyName System.Drawing
try {
  $img = [System.Drawing.Image]::FromFile($InputPath)
  Write-Output (($img.Width).ToString() + 'x' + ($img.Height).ToString())
  $img.Dispose()
} catch {
  Write-Output '0x0'
}
PS1;
        $scriptPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_ocr_dims_'.bin2hex(random_bytes(4)).'.ps1';
        file_put_contents($scriptPath, $script);

        try {
            $result = Process::timeout(20)->run([
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
            $out = trim($result->output());
            if (preg_match('/^(\d+)x(\d+)$/', $out, $m)) {
                return ['width' => (int) $m[1], 'height' => (int) $m[2]];
            }
        } catch (\Throwable) {
            // ignore
        } finally {
            @unlink($scriptPath);
        }

        return ['width' => null, 'height' => null];
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
