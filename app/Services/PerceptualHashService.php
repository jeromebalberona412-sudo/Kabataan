<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Average-hash perceptual hash for near-duplicate image detection.
 * Works with GD when available; falls back to Windows System.Drawing,
 * then a coarse structural fingerprint when image extensions are missing.
 */
class PerceptualHashService
{
    public function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    public function hashFromFile(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $fromGd = $this->hashWithGd($absolutePath);
        if (is_string($fromGd) && $fromGd !== '') {
            return $fromGd;
        }

        $fromWindows = $this->hashWithWindowsDrawing($absolutePath);
        if (is_string($fromWindows) && $fromWindows !== '') {
            return $fromWindows;
        }

        return $this->structuralFingerprint($absolutePath);
    }

    public function hammingDistance(string $hashA, string $hashB): ?int
    {
        $hashA = strtolower(trim($hashA));
        $hashB = strtolower(trim($hashB));

        if ($hashA === '' || $hashB === '' || strlen($hashA) !== strlen($hashB)) {
            return null;
        }

        // Structural fingerprints are compared exactly (not Hamming).
        if (str_starts_with($hashA, 'struct:') || str_starts_with($hashB, 'struct:')) {
            return hash_equals($hashA, $hashB) ? 0 : 64;
        }

        if (! preg_match('/^[0-9a-f]{16}$/', $hashA) || ! preg_match('/^[0-9a-f]{16}$/', $hashB)) {
            return null;
        }

        $a = hexdec($hashA);
        $b = hexdec($hashB);
        $xor = $a ^ $b;
        $distance = 0;

        while ($xor !== 0) {
            $distance += $xor & 1;
            $xor >>= 1;
        }

        return $distance;
    }

    private function hashWithGd(string $absolutePath): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $resized = imagecreatetruecolor(8, 8);

        if ($resized === false) {
            imagedestroy($image);

            return null;
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, 8, 8, imagesx($image), imagesy($image));
        imagedestroy($image);

        $pixels = [];
        $sum = 0;

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($resized, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = (int) round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                $pixels[] = $gray;
                $sum += $gray;
            }
        }

        imagedestroy($resized);

        return $this->bitsToHex($pixels, $sum / 64);
    }

    private function hashWithWindowsDrawing(string $absolutePath): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $powershell = $this->windowsPowerShellPath();
        if ($powershell === null) {
            return null;
        }

        $script = <<<'PS'
param([string]$ImagePath)
Add-Type -AssemblyName System.Drawing
try {
  $img = [System.Drawing.Image]::FromFile($ImagePath)
  $bmp = New-Object System.Drawing.Bitmap 8, 8
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBilinear
  $g.DrawImage($img, 0, 0, 8, 8)
  $g.Dispose()
  $img.Dispose()
  $values = @()
  $sum = 0.0
  for ($y = 0; $y -lt 8; $y++) {
    for ($x = 0; $x -lt 8; $x++) {
      $c = $bmp.GetPixel($x, $y)
      $gray = [math]::Round(($c.R * 0.299) + ($c.G * 0.587) + ($c.B * 0.114))
      $values += $gray
      $sum += $gray
    }
  }
  $bmp.Dispose()
  $avg = $sum / 64.0
  $bits = ""
  foreach ($v in $values) { $bits += $(if ($v -ge $avg) { "1" } else { "0" }) }
  $num = [Convert]::ToInt64($bits, 2)
  Write-Output ("{0:x16}" -f $num)
} catch {
  Write-Output ""
  exit 1
}
PS;

        $tempScript = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kkp_phash_'.bin2hex(random_bytes(6)).'.ps1';

        try {
            if (@file_put_contents($tempScript, $script) === false) {
                return null;
            }

            $result = Process::timeout(20)->run([
                $powershell,
                '-NoLogo',
                '-NoProfile',
                '-NonInteractive',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $tempScript,
                '-ImagePath',
                $absolutePath,
            ]);

            $output = strtolower(trim((string) $result->output()));

            if (preg_match('/^[0-9a-f]{16}$/', $output) === 1) {
                return $output;
            }
        } catch (\Throwable $exception) {
            Log::debug('Windows perceptual hash failed', ['error' => $exception->getMessage()]);
        } finally {
            if (is_file($tempScript)) {
                @unlink($tempScript);
            }
        }

        return null;
    }

    /**
     * Coarse fingerprint when no image decoder is available.
     * Catches identical/near-identical file copies, not heavily re-encoded photos.
     */
    private function structuralFingerprint(string $absolutePath): ?string
    {
        $size = (int) @filesize($absolutePath);
        if ($size <= 0) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $head = (string) fread($handle, 4096);
        $tail = '';
        if ($size > 4096) {
            fseek($handle, max(0, $size - 4096));
            $tail = (string) fread($handle, 4096);
        }
        fclose($handle);

        $mid = '';
        if ($size > 8192) {
            $midOffset = (int) floor($size / 2) - 2048;
            $handle = @fopen($absolutePath, 'rb');
            if ($handle !== false) {
                fseek($handle, max(0, $midOffset));
                $mid = (string) fread($handle, 4096);
                fclose($handle);
            }
        }

        $digest = hash('sha256', implode('|', [
            $width,
            $height,
            $mime,
            $size,
            hash('sha256', $head),
            hash('sha256', $mid),
            hash('sha256', $tail),
        ]));

        return 'struct:'.substr($digest, 0, 32);
    }

    /**
     * @param  list<int>  $pixels
     */
    private function bitsToHex(array $pixels, float $average): string
    {
        $bits = '';

        foreach ($pixels as $gray) {
            $bits .= $gray >= $average ? '1' : '0';
        }

        return sprintf('%016x', bindec($bits));
    }

    private function windowsPowerShellPath(): ?string
    {
        $candidates = [
            getenv('SystemRoot') ? getenv('SystemRoot').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe' : null,
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            'powershell.exe',
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (is_file($candidate) || $candidate === 'powershell.exe') {
                return $candidate;
            }
        }

        return null;
    }
}
