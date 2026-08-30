<?php

$envPython = env('OCR_PYTHON_PATH');
$ocrRoot = env('OCR_ROOT');

if (! is_string($ocrRoot) || $ocrRoot === '' || ! @is_dir($ocrRoot)) {
    $ocrRoot = dirname(base_path()).DIRECTORY_SEPARATOR.'python';
}

$defaultPython = null;

if (! is_string($envPython) || $envPython === '') {
    if (PHP_OS_FAMILY === 'Windows') {
        if (@is_file($ocrRoot.'/.venv312/Scripts/python.exe')) {
            $defaultPython = $ocrRoot.'/.venv312/Scripts/python.exe';
        } elseif (@is_file($ocrRoot.'/.venv/Scripts/python.exe')) {
            $defaultPython = $ocrRoot.'/.venv/Scripts/python.exe';
        }
    } else {
        if (@is_file($ocrRoot.'/.venv312/bin/python')) {
            $defaultPython = $ocrRoot.'/.venv312/bin/python';
        } elseif (@is_file($ocrRoot.'/.venv/bin/python')) {
            $defaultPython = $ocrRoot.'/.venv/bin/python';
        }
    }
}

return [
    'root' => $ocrRoot,

    'python' => is_string($envPython) && $envPython !== '' ? $envPython : ($defaultPython ?? 'python3'),

    'script' => $ocrRoot.DIRECTORY_SEPARATOR.'ocr.py',

    'pipeline_script' => $ocrRoot.DIRECTORY_SEPARATOR.'validate_school_id.py',

    'philippine_pipeline_script' => $ocrRoot.DIRECTORY_SEPARATOR.'validate_philippine_id.py',

    'philippine_pipeline_enabled' => (bool) env('OCR_PHILIPPINE_PIPELINE_ENABLED', false),

    'pipeline_enabled' => (bool) env('OCR_PIPELINE_ENABLED', false),

    'pipeline_timeout' => (int) env('OCR_PIPELINE_TIMEOUT', 600),

    'timeout' => (int) env('OCR_TIMEOUT', 120),

    'min_confidence' => (float) env('OCR_MIN_CONFIDENCE', 0.45),

    'min_lines' => (int) env('OCR_MIN_LINES', 2),

    'windows_script' => $ocrRoot.DIRECTORY_SEPARATOR.'ocr_windows.ps1',

    'trust_school_id_municipal_match' => (bool) env('OCR_TRUST_SCHOOL_ID_MUNICIPAL', false),

    'trust_complete_upload_match' => (bool) env('OCR_TRUST_COMPLETE_UPLOAD', false),

    // Primary Step 2 engine: gemini (vision). Groq kept as optional legacy. Tesseract precheck remains.
    'provider' => strtolower((string) env('OCR_PROVIDER', 'gemini')),

    'legacy_engines_enabled' => (bool) env('OCR_LEGACY_ENGINES_ENABLED', false),

    'api_url' => rtrim((string) env('OCR_API_URL', 'http://127.0.0.1:8001'), '/'),

    'api_enabled' => (bool) env('OCR_API_ENABLED', false),

    'api_key' => env('OCR_API_KEY'),

    'gemini' => [
        'enabled' => (bool) env('OCR_GEMINI_ENABLED', true),
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        // New AI Studio keys reject gemini-2.5-flash; use 3.6 Flash.
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.5-flash'))
        ))),
        'timeout' => (int) env('GEMINI_TIMEOUT', 18),
        // Front-only by default to keep requests small/fast.
        'analyze_back' => (bool) env('GEMINI_ANALYZE_BACK', false),
        // Extra Tesseract on the back is slow on Windows; keep off unless needed.
        'local_back_check' => (bool) env('GEMINI_LOCAL_BACK_CHECK', false),
        // Gemini 3 thinking tokens count toward maxOutputTokens — keep headroom but stay lean.
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 512),
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'MINIMAL'),
        'image_max_edge' => (int) env('GEMINI_IMAGE_MAX_EDGE', 640),
        'image_jpeg_quality' => (int) env('GEMINI_IMAGE_JPEG_QUALITY', 55),
        'rate_limit_max_retries' => (int) env('GEMINI_RATE_LIMIT_MAX_RETRIES', 1),
        'cache_ttl_hours' => (int) env('GEMINI_VERIFY_CACHE_TTL_HOURS', env('GROQ_VERIFY_CACHE_TTL_HOURS', 12)),
    ],

    // Legacy Groq config retained for installs that still set OCR_PROVIDER=groq.
    'groq' => [
        'enabled' => (bool) env('OCR_GROQ_ENABLED', false),
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => rtrim((string) env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'model' => env('GROQ_MODEL', env('GROQ_VISION_MODEL', 'qwen/qwen3.6-27b')),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GROQ_VISION_FALLBACK_MODELS', 'qwen/qwen3.8-27b'))
        ))),
        'timeout' => (int) env('GROQ_TIMEOUT', 20),
        'reasoning_effort' => env('GROQ_REASONING_EFFORT', 'none'),
        'analyze_back' => (bool) env('GROQ_ANALYZE_BACK', false),
        'max_completion_tokens' => (int) env('GROQ_MAX_COMPLETION_TOKENS', 160),
        'image_max_edge' => (int) env('GROQ_IMAGE_MAX_EDGE', 768),
        'image_jpeg_quality' => (int) env('GROQ_IMAGE_JPEG_QUALITY', 62),
        'rate_limit_max_retries' => (int) env('GROQ_RATE_LIMIT_MAX_RETRIES', 3),
        'cache_ttl_hours' => (int) env('GROQ_VERIFY_CACHE_TTL_HOURS', 12),
    ],

    // Local shortcut is off by default so Gemini always runs for supporting docs.
    // Only used when OCR_PROVIDER is not gemini (see OCRService).
    'prefer_local_before_groq' => (bool) env('OCR_PREFER_LOCAL_BEFORE_GROQ', env('OCR_PREFER_LOCAL_BEFORE_AI', false)),
    'prefer_local_before_ai' => (bool) env('OCR_PREFER_LOCAL_BEFORE_AI', env('OCR_PREFER_LOCAL_BEFORE_GROQ', false)),
    'tesseract_precheck_enabled' => (bool) env('OCR_TESSERACT_PRECHECK', true),

    // Floor for treating a Step 2 ID scan as readable (stricter to reject random photos).
    'min_detect_confidence' => (float) env('OCR_MIN_DETECT_CONFIDENCE', 0.50),

    // Prefer keeping the user's selected type unless OCR clearly disagrees.
    'auto_correct_min_confidence' => (float) env('OCR_AUTO_CORRECT_MIN_CONFIDENCE', 0.55),

    // When Step 1 name is present, require an identity signal for full success.
    'require_name_signal_for_success' => (bool) env('OCR_REQUIRE_NAME_SIGNAL', true),

    'supported_philippine_ids' => [
        'national_id',
        'philhealth_id',
        'voters_id',
    ],

    'tesseract' => [
        'path' => env('TESSERACT_PATH'),
        'lang' => env('TESSERACT_LANG', 'eng'),
        'timeout' => (int) env('TESSERACT_TIMEOUT', 90),
        'psm_modes' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('TESSERACT_PSM_MODES', '6,11,12'))
        ))),
    ],

    'diagnostics_enabled' => (bool) env('OCR_DIAGNOSTICS_ENABLED', false),
];
