<?php

namespace App\Services;

/**
 * Server-side inspection for KK Profiling canvas e-signatures (PNG/JPEG data URLs).
 *
 * Does not claim legal authenticity — only format, size, white background, and visible ink.
 * Pixel analysis requires PHP GD; without GD, structural checks + payload-size gate still run.
 */
class ParticipantSignatureValidationService
{
    /**
     * @return array{ok: bool, error: ?string}
     */
    public function validate(?string $value): array
    {
        $messages = config('signature.messages', []);

        if ($value === null || trim($value) === '') {
            return $this->fail($messages['required'] ?? 'Signature is required. Please upload your signature.');
        }

        $parsed = $this->parseDataUrl(trim($value));
        if ($parsed === null) {
            return $this->fail($messages['invalid'] ?? 'Please upload a valid image.');
        }

        [$declaredMime, $binary] = $parsed;
        $maxBytes = (int) config('signature.max_bytes', 2 * 1024 * 1024);

        if (strlen($binary) > $maxBytes) {
            return $this->fail($messages['too_large'] ?? 'Image size must not exceed 2 MB.');
        }

        $structural = $this->inspectImageBinary($binary);
        if ($structural === null) {
            return $this->fail($messages['invalid'] ?? 'Please upload a valid image.');
        }

        [$mime, $width, $height] = $structural;
        $allowed = array_map('strtolower', config('signature.allowed_mimes', ['image/png', 'image/jpeg']));

        if (! in_array($mime, $allowed, true)) {
            return $this->fail($messages['format'] ?? 'Only PNG, JPG, and JPEG images are allowed.');
        }

        if ($declaredMime !== '' && ! $this->mimesCompatible($declaredMime, $mime)) {
            return $this->fail($messages['format'] ?? 'Only PNG, JPG, and JPEG images are allowed.');
        }

        $minW = (int) config('signature.min_width', 150);
        $minH = (int) config('signature.min_height', 60);
        $maxW = (int) config('signature.max_width', 4000);
        $maxH = (int) config('signature.max_height', 4000);

        if ($width < $minW || $height < $minH) {
            return $this->fail($messages['too_small'] ?? 'Signature image is too small. Please sign again.');
        }

        if ($width > $maxW || $height > $maxH) {
            return $this->fail($messages['too_big_dims'] ?? 'Signature image is too large to process.');
        }

        // Full white-background / ink inspection requires GD.
        if (! extension_loaded('gd')) {
            if (strlen($binary) < 1200) {
                return $this->fail($messages['blank'] ?? 'Please upload an image containing visible text or a signature.');
            }

            return ['ok' => true, 'error' => null];
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $this->fail($messages['invalid'] ?? 'Please upload a valid image.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            $analysis = $this->analyzePixels($image, $width, $height);

            if ($analysis['sample_total'] < 1) {
                return $this->fail($messages['invalid'] ?? 'Please upload a valid image.');
            }

            $bgRatio = $analysis['sample_white'] / $analysis['sample_total'];
            $requiredBg = (float) config('signature.background_white_ratio', 0.90);

            if ($bgRatio < $requiredBg) {
                return $this->fail($messages['non_white_bg'] ?? 'Please upload an image with a plain white background.');
            }

            $inkRatio = $analysis['ink'] / max(1, $analysis['sample_total']);
            $minInk = (float) config('signature.min_ink_ratio', 0.0015);

            if ($inkRatio < $minInk) {
                return $this->fail($messages['blank'] ?? 'Please upload an image containing visible text or a signature.');
            }
        } finally {
            imagedestroy($image);
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseDataUrl(string $value): ?array
    {
        if (preg_match('#^data:(image/(?:png|jpe?g));base64,(.+)$#is', $value, $m)) {
            $binary = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
            if ($binary === false || $binary === '') {
                return null;
            }

            return [strtolower($m[1]), $binary];
        }

        if (preg_match('#^[A-Za-z0-9+/=\s]+$#', $value) && strlen($value) > 64) {
            $binary = base64_decode(preg_replace('/\s+/', '', $value), true);
            if ($binary === false || $binary === '') {
                return null;
            }

            return ['', $binary];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int, 2: int}|null mime, width, height
     */
    private function inspectImageBinary(string $binary): ?array
    {
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($binary);
            if ($info !== false && ! empty($info[0]) && ! empty($info[1]) && ! empty($info['mime'])) {
                return [strtolower((string) $info['mime']), (int) $info[0], (int) $info[1]];
            }
        }

        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n") && strlen($binary) >= 24) {
            $width = unpack('N', substr($binary, 16, 4))[1] ?? 0;
            $height = unpack('N', substr($binary, 20, 4))[1] ?? 0;
            if ($width > 0 && $height > 0) {
                return ['image/png', (int) $width, (int) $height];
            }
        }

        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            $dims = $this->jpegDimensions($binary);
            if ($dims !== null) {
                return ['image/jpeg', $dims[0], $dims[1]];
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function jpegDimensions(string $binary): ?array
    {
        $len = strlen($binary);
        $offset = 2;

        while ($offset + 4 < $len) {
            if (ord($binary[$offset]) !== 0xFF) {
                $offset++;
                continue;
            }

            $marker = ord($binary[$offset + 1]);
            $offset += 2;

            if (in_array($marker, [0xD8, 0xD9, 0x01], true) || ($marker >= 0xD0 && $marker <= 0xD7)) {
                continue;
            }

            if ($offset + 2 > $len) {
                break;
            }

            $segLen = (ord($binary[$offset]) << 8) | ord($binary[$offset + 1]);
            if ($segLen < 2 || $offset + $segLen > $len) {
                break;
            }

            if (in_array($marker, [0xC0, 0xC2], true) && $segLen >= 7) {
                $height = (ord($binary[$offset + 3]) << 8) | ord($binary[$offset + 4]);
                $width = (ord($binary[$offset + 5]) << 8) | ord($binary[$offset + 6]);

                if ($width > 0 && $height > 0) {
                    return [$width, $height];
                }
            }

            $offset += $segLen;
        }

        return null;
    }

    private function mimesCompatible(string $declared, string $actual): bool
    {
        $declared = strtolower($declared);
        $actual = strtolower($actual);

        if ($declared === $actual) {
            return true;
        }

        $jpeg = ['image/jpeg', 'image/jpg'];

        return in_array($declared, $jpeg, true) && in_array($actual, $jpeg, true);
    }

    /**
     * @return array{sample_total: int, sample_white: int, ink: int}
     */
    private function analyzePixels(\GdImage $image, int $width, int $height): array
    {
        $nearWhiteMin = (int) config('signature.near_white_min', 250);
        $alphaOpaque = (int) config('signature.alpha_opaque', 32);
        $step = max(1, (int) config('signature.edge_sample_step', 4));

        // Dense photos: sample more sparsely for performance.
        if ($width * $height > 800_000) {
            $step = max($step, 2);
        }

        $sampleTotal = 0;
        $sampleWhite = 0;
        $ink = 0;

        // Transparent pixels are NOT white background (requirement: plain white).
        $isNearWhite = static function (int $r, int $g, int $b, int $a) use ($nearWhiteMin, $alphaOpaque): bool {
            if ($a < $alphaOpaque) {
                return false;
            }

            return $r >= $nearWhiteMin && $g >= $nearWhiteMin && $b >= $nearWhiteMin;
        };

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgba = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgba);
                $r = (int) ($colors['red'] ?? 0);
                $g = (int) ($colors['green'] ?? 0);
                $b = (int) ($colors['blue'] ?? 0);
                $a255 = (int) round((1 - (($colors['alpha'] ?? 0) / 127)) * 255);

                $sampleTotal++;
                if ($isNearWhite($r, $g, $b, $a255)) {
                    $sampleWhite++;
                } else {
                    $ink++;
                }
            }
        }

        return [
            'sample_total' => $sampleTotal,
            'sample_white' => $sampleWhite,
            'ink' => $ink,
        ];
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
