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

    'detection_confidence_review_below' => (float) env('DOCUMENT_REVIEW_CONFIDENCE', 0.55),
];
