<?php

namespace App\Services;

/**
 * Simple average-hash perceptual hash for near-duplicate image detection.
 * Similarity signal only — not cryptographic identity proof.
 */
class PerceptualHashService
{
    public function hashFromFile(string $absolutePath): ?string
    {
        if (! is_file($absolutePath) || ! function_exists('imagecreatefromstring')) {
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

        $average = $sum / 64;
        $bits = '';

        foreach ($pixels as $gray) {
            $bits .= $gray >= $average ? '1' : '0';
        }

        return sprintf('%016x', bindec($bits));
    }

    public function hammingDistance(string $hashA, string $hashB): ?int
    {
        if (! preg_match('/^[0-9a-f]{16}$/i', $hashA) || ! preg_match('/^[0-9a-f]{16}$/i', $hashB)) {
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
}
