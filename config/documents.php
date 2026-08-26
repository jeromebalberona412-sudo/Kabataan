<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supporting document verification
    |--------------------------------------------------------------------------
    |
    | Privacy-preserving ID/document checks for KK Profiling Step 2.
    | Do not put service-role keys or secrets in frontend code.
    |
    */

    'fingerprint_secret' => env('DOCUMENT_FINGERPRINT_SECRET', env('APP_KEY')),

    'max_upload_kilobytes' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 5120),

    // Absolute byte cap used by IdImageQualityService (aligned with Laravel max:10240 KB).
    'max_upload_bytes' => (int) env('DOCUMENT_MAX_UPLOAD_BYTES', 10 * 1024 * 1024),

    'allowed_mimes' => ['image/jpeg', 'image/png'],

    'allowed_extensions' => ['jpg', 'jpeg', 'png'],

    'retention_days' => (int) env('DOCUMENT_RETENTION_DAYS', 0),

    'temp_disk' => env('DOCUMENT_TEMP_DISK', 'local'),

    'temp_root' => env('DOCUMENT_TEMP_ROOT', 'kk_document_temp'),

    /*
    | Facial / selfie matching is disabled by default (Data Privacy Act minimization).
    | Existing OCR pipelines remain available without biometric comparison.
    */
    'selfie_verification_enabled' => (bool) env('DOCUMENT_SELFIE_VERIFICATION_ENABLED', false),

    'duplicate' => [
        'fingerprint_match' => 'high',
        'phash_hamming_threshold' => (int) env('DOCUMENT_PHASH_THRESHOLD', 8),
        'possible_phash_threshold' => (int) env('DOCUMENT_PHASH_POSSIBLE_THRESHOLD', 12),
    ],

    // Front vs back of the same upload must not be the same (or near-same) photo.
    'front_back' => [
        'phash_hamming_threshold' => (int) env('DOCUMENT_FRONT_BACK_PHASH_THRESHOLD', 5),
        'ocr_text_similarity_percent' => (float) env('DOCUMENT_FRONT_BACK_OCR_SIMILARITY', 85),
    ],

    'detection_confidence_review_below' => (float) env('DOCUMENT_REVIEW_CONFIDENCE', 0.55),

    /*
    | Pre-OCR image quality gates (capture/upload). Soft thresholds — when uncertain,
    | OCR still runs and the user reviews results.
    */
    'quality' => [
        'enabled' => (bool) env('DOCUMENT_QUALITY_CHECKS', true),
        'min_width' => (int) env('DOCUMENT_MIN_WIDTH', 480),
        'min_height' => (int) env('DOCUMENT_MIN_HEIGHT', 300),
        'min_pixels' => (int) env('DOCUMENT_MIN_PIXELS', 200000),
        'min_brightness' => (float) env('DOCUMENT_MIN_BRIGHTNESS', 35),
        'max_brightness' => (float) env('DOCUMENT_MAX_BRIGHTNESS', 230),
        'min_contrast' => (float) env('DOCUMENT_MIN_CONTRAST', 18),
        'min_sharpness' => (float) env('DOCUMENT_MIN_SHARPNESS', 40),
        'max_glare_ratio' => (float) env('DOCUMENT_MAX_GLARE_RATIO', 0.28),
    ],

    /*
    | Live camera smart scanning (client-side detection only — OCR runs after capture).
    */
    'camera' => [
        'auto_capture_enabled' => (bool) env('DOCUMENT_CAMERA_AUTO_CAPTURE', true),
        // How long the ID must remain stable before auto-capture.
        'auto_capture_stability_ms' => (int) env('DOCUMENT_CAMERA_STABILITY_MS', 1000),
        // How often to sample the guide region for lightweight detection (not OCR).
        'sample_interval_ms' => (int) env('DOCUMENT_CAMERA_SAMPLE_MS', 220),
        // After this long without auto-capture, show manual/upload help.
        'help_after_ms' => (int) env('DOCUMENT_CAMERA_HELP_AFTER_MS', 12000),
        'min_edge_score' => (float) env('DOCUMENT_CAMERA_MIN_EDGE', 12),
        'min_contrast' => (float) env('DOCUMENT_CAMERA_MIN_CONTRAST', 16),
        'min_brightness' => (float) env('DOCUMENT_CAMERA_MIN_BRIGHTNESS', 40),
        'max_brightness' => (float) env('DOCUMENT_CAMERA_MAX_BRIGHTNESS', 220),
        'max_motion' => (float) env('DOCUMENT_CAMERA_MAX_MOTION', 14),
    ],
];
