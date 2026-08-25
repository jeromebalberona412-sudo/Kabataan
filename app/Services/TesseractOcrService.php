<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TesseractOcrService
{
    public function __construct(
        private readonly ImagePreprocessingService $preprocessing,
    ) {}

    public function resolveExecutable(): ?string
    {
        $configured = trim((string) config('ocr.tesseract.path', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $fallbacks = [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
        ];

        foreach ($fallbacks as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Last resort: PATH lookup (may fail under PHP even when CMD works).
        try {
            $which = PHP_OS_FAMILY === 'Windows'
                ? Process::timeout(10)->run(['where.exe', 'tesseract'])
                : Process::timeout(10)->run(['which', 'tesseract']);
            $line = trim(explode("\n", str_replace("\r", '', $which->output()))[0] ?? '');
            if ($line !== '' && is_file($line)) {
                return $line;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->resolveExecutable() !== null;
    }

    /**
     * Privacy-safe health diagnostics (no OCR text / PII).
     *
     * @return array<string, mixed>
     */
    public function healthCheck(?string $probeImagePath = null): array
    {
        $started = microtime(true);
        $executable = $this->resolveExecutable();

        if ($executable === null) {
            return [
                'status' => 'unavailable',
                'tesseract' => 'UNAVAILABLE',
                'message' => 'Tesseract executable was not found.',
                'path' => null,
                'version' => null,
                'languages' => [],
                'processing_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        $version = $this->readVersion($executable);
        $languages = $this->listLanguages($executable);
        $configuredLang = (string) config('ocr.tesseract.lang', 'eng');
        $langOk = in_array($configuredLang, $languages, true) || in_array('eng', $languages, true);

        $probe = [
            'status' => 'skipped',
            'ocr' => null,
            'text_length' => 0,
        ];

        if (is_string($probeImagePath) && is_file($probeImagePath)) {
            $result = $this->extractText($probeImagePath);
            $probe = [
                'status' => ($result['success'] ?? false) ? 'success' : (($result['ocr_status'] ?? '') === 'ocr_failed' ? 'failed' : 'empty'),
                'ocr' => $result['ocr_status'] ?? null,
                'text_length' => (int) ($result['text_length'] ?? 0),
                'engine' => $result['engine'] ?? null,
            ];
        }

        return [
            'status' => $langOk ? 'available' : 'misconfigured',
            'tesseract' => 'AVAILABLE',
            'path' => $executable,
            'version' => $version,
            'languages' => $languages,
            'configured_lang' => $configuredLang,
            'lang_available' => $langOk,
            'probe' => $probe,
            'processing_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractText(string $imagePath): array
    {
        $started = microtime(true);
        $executable = $this->resolveExecutable();

        if ($executable === null) {
            return [
                'success' => false,
                'ocr_status' => 'tesseract_unavailable',
                'message' => 'Tesseract is not configured on this server.',
                'lines' => [],
                'full_text' => '',
                'text_length' => 0,
                'engine' => 'tesseract',
            ];
        }

        $prepared = $this->preprocessing->prepareVariants($imagePath);
        $variants = $prepared['variants'];
        $meta = $prepared['meta'];

        Log::info('OCR started', [
            'engine' => 'tesseract',
            'bytes' => $meta['bytes'] ?? null,
            'mime' => $meta['mime'] ?? null,
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'variant_count' => count($variants),
            'preprocessing' => $meta['preprocessing'] ?? null,
        ]);

        if ($variants === []) {
            return [
                'success' => false,
                'ocr_status' => 'invalid_image',
                'message' => 'Uploaded image could not be read.',
                'lines' => [],
                'full_text' => '',
                'text_length' => 0,
                'engine' => 'tesseract',
                'image' => $meta,
            ];
        }

        $lang = $this->resolveLanguage($executable);
        $psmModes = array_values(array_filter(
            array_map('intval', (array) config('ocr.tesseract.psm_modes', [6, 11, 12])),
            fn (int $mode) => $mode > 0,
        ));
        if ($psmModes === []) {
            $psmModes = [6, 11, 12];
        }

        $best = null;

        try {
            foreach ($variants as $variantPath) {
                foreach ($psmModes as $psm) {
                    $pass = $this->runTesseract($executable, $variantPath, $lang, $psm);
                    if ($best === null || $this->scoreTextSignal($pass) > $this->scoreTextSignal($best)) {
                        $best = $pass;
                    }

                    if ($this->scoreTextSignal($pass) >= 40) {
                        break 2;
                    }
                }
            }
        } finally {
            $this->preprocessing->cleanup($variants, $imagePath);
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        if ($best === null) {
            Log::info('OCR finished', [
                'engine' => 'tesseract',
                'ocr_status' => 'ocr_failed',
                'text_length' => 0,
                'processing_ms' => $elapsed,
            ]);

            return [
                'success' => false,
                'ocr_status' => 'ocr_failed',
                'message' => 'Document processing is temporarily unavailable. Please try again.',
                'lines' => [],
                'full_text' => '',
                'text_length' => 0,
                'engine' => 'tesseract',
                'processing_ms' => $elapsed,
                'image' => $meta,
            ];
        }

        $text = trim((string) ($best['full_text'] ?? ''));
        $textLength = mb_strlen($text);
        $quality = $this->evaluateTextQuality($text);
        $ocrStatus = $quality['ok']
            ? 'ocr_success'
            : ($textLength > 0 ? 'ocr_low_confidence' : 'ocr_empty');

        Log::info('OCR finished', [
            'engine' => 'tesseract',
            'ocr_status' => $ocrStatus,
            'text_length' => $textLength,
            'psm' => $best['psm'] ?? null,
            'exit_code' => $best['exit_code'] ?? null,
            'processing_ms' => $elapsed,
            'alpha' => $quality['alpha'],
            'words' => $quality['words'],
        ]);

        return [
            'success' => $ocrStatus === 'ocr_success' || $ocrStatus === 'ocr_low_confidence',
            'ocr_status' => $ocrStatus,
            'message' => match ($ocrStatus) {
                'ocr_success' => 'OK',
                'ocr_low_confidence' => 'Text could not be confidently read.',
                default => 'No useful text detected.',
            },
            'lines' => $best['lines'] ?? [],
            'full_text' => $text,
            'text_length' => $textLength,
            'average_confidence' => $best['average_confidence'] ?? ($quality['ok'] ? 0.85 : 0.4),
            'engine' => 'tesseract',
            'lang' => $lang,
            'psm' => $best['psm'] ?? null,
            'processing_ms' => $elapsed,
            'image' => $meta,
            'quality' => $quality,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runTesseract(string $executable, string $imagePath, string $lang, int $psm): array
    {
        $timeout = max(30, (int) config('ocr.tesseract.timeout', config('ocr.timeout', 120)));

        try {
            Log::info('Tesseract process started', [
                'psm' => $psm,
                'lang' => $lang,
                'bytes' => @filesize($imagePath) ?: null,
            ]);

            $result = Process::timeout($timeout)->run([
                $executable,
                $imagePath,
                'stdout',
                '-l',
                $lang,
                '--psm',
                (string) $psm,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Tesseract process exception', [
                'error' => $exception->getMessage(),
                'psm' => $psm,
            ]);

            return [
                'success' => false,
                'full_text' => '',
                'lines' => [],
                'psm' => $psm,
                'exit_code' => -1,
                'average_confidence' => 0,
            ];
        }

        $stdout = trim($result->output());
        $stderr = trim($result->errorOutput());
        $lines = [];

        foreach (preg_split('/\R+/', $stdout) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $lines[] = [
                'text' => $line,
                'confidence' => 0.85,
            ];
        }

        if (! $result->successful() && $stdout === '') {
            Log::warning('Tesseract process failed', [
                'exit' => $result->exitCode(),
                'stderr_length' => mb_strlen($stderr),
                'psm' => $psm,
            ]);
        }

        return [
            'success' => $stdout !== '',
            'full_text' => $stdout,
            'lines' => $lines,
            'psm' => $psm,
            'exit_code' => $result->exitCode(),
            'average_confidence' => $lines !== [] ? 0.85 : 0.0,
        ];
    }

    private function resolveLanguage(string $executable): string
    {
        $configured = trim((string) config('ocr.tesseract.lang', 'eng'));
        $languages = $this->listLanguages($executable);

        if ($configured !== '' && in_array($configured, $languages, true)) {
            return $configured;
        }

        return in_array('eng', $languages, true) ? 'eng' : ($languages[0] ?? 'eng');
    }

    /**
     * @return list<string>
     */
    private function listLanguages(string $executable): array
    {
        try {
            $result = Process::timeout(20)->run([$executable, '--list-langs']);
            $output = $result->output()."\n".$result->errorOutput();
            $langs = [];
            foreach (preg_split('/\R+/', $output) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_contains(strtolower($line), 'available')) {
                    continue;
                }
                if (preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/', $line)) {
                    $langs[] = $line;
                }
            }

            return array_values(array_unique($langs));
        } catch (\Throwable) {
            return ['eng'];
        }
    }

    private function readVersion(string $executable): ?string
    {
        try {
            $result = Process::timeout(20)->run([$executable, '--version']);
            $combined = trim($result->output()."\n".$result->errorOutput());
            if (preg_match('/tesseract\s+v?([^\s]+)/i', $combined, $match)) {
                return $match[1];
            }

            return $combined !== '' ? strtok($combined, "\n") : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scoreTextSignal(array $payload): int
    {
        $text = trim((string) ($payload['full_text'] ?? ''));
        if ($text === '') {
            return 0;
        }

        $quality = $this->evaluateTextQuality($text);
        $score = min(60, $quality['alpha']) + min(20, $quality['words'] * 2);

        if ($quality['has_id_keyword']) {
            $score += 25;
        }

        return $score;
    }

    /**
     * @return array{ok:bool,alpha:int,digits:int,words:int,has_id_keyword:bool}
     */
    private function evaluateTextQuality(string $text): array
    {
        $alpha = preg_match_all('/[A-Za-z]/', $text) ?: 0;
        $digits = preg_match_all('/\d/', $text) ?: 0;
        $words = count(array_filter(preg_split('/\s+/', $text) ?: [], fn ($w) => mb_strlen((string) $w) >= 2));
        $hasKeyword = (bool) preg_match(
            '/\b(republic|philippines|philsys|national\s+id|philhealth|school|student|driver|license|passport|voter|comelec|barangay|pwd|senior|identity|birth|address|name)\b/i',
            $text,
        );

        $ok = ($alpha >= 12 && $words >= 3) || ($hasKeyword && $alpha >= 8);

        return [
            'ok' => $ok,
            'alpha' => $alpha,
            'digits' => $digits,
            'words' => $words,
            'has_id_keyword' => $hasKeyword,
        ];
    }
}
