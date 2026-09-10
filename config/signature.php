<?php

/**
 * KK Profiling participant e-signature validation.
 *
 * Signatures are PNG/JPEG data URLs from the pad (draw or validated image upload).
 * They remain stored in registration form_data / kk_survey_responses
 * as today — this config only controls image inspection thresholds.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Max decoded image size (bytes)
    |--------------------------------------------------------------------------
    */
    'max_bytes' => (int) env('KKP_SIGNATURE_MAX_BYTES', 2 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types (from getimagesize / finfo on decoded bytes)
    |--------------------------------------------------------------------------
    */
    'allowed_mimes' => ['image/png', 'image/jpeg'],

    /*
    |--------------------------------------------------------------------------
    | Dimensions
    |--------------------------------------------------------------------------
    | Canvas pad sizes vary by device; keep mins practical for the pad UI.
    */
    'min_width' => (int) env('KKP_SIGNATURE_MIN_WIDTH', 150),
    'min_height' => (int) env('KKP_SIGNATURE_MIN_HEIGHT', 60),
    'max_width' => (int) env('KKP_SIGNATURE_MAX_WIDTH', 4000),
    'max_height' => (int) env('KKP_SIGNATURE_MAX_HEIGHT', 4000),

    /*
    |--------------------------------------------------------------------------
    | Near-white threshold (per channel 0–255)
    |--------------------------------------------------------------------------
    | Pure white is 255. JPEG/canvas artifacts often land in 250–255.
    | Transparent pixels (alpha < alpha_opaque) count as white background.
    */
    'near_white_min' => (int) env('KKP_SIGNATURE_NEAR_WHITE_MIN', 250),
    'alpha_opaque' => (int) env('KKP_SIGNATURE_ALPHA_OPAQUE', 32),

    /*
    |--------------------------------------------------------------------------
    | Background sampling
    |--------------------------------------------------------------------------
    | Edge/corner samples must be predominantly near-white (≥ ratio).
    | Ink pixels (non-near-white with enough alpha) must meet min ink ratio
    | so blank white images fail.
    */
    'background_white_ratio' => (float) env('KKP_SIGNATURE_BG_WHITE_RATIO', 0.90),
    'min_ink_ratio' => (float) env('KKP_SIGNATURE_MIN_INK_RATIO', 0.0015),
    'edge_sample_step' => (int) env('KKP_SIGNATURE_EDGE_SAMPLE_STEP', 4),

    /*
    |--------------------------------------------------------------------------
    | Participant printed name (signature_name)
    |--------------------------------------------------------------------------
    */
    'name_min' => 1,
    'name_max' => 255,

    /*
    |--------------------------------------------------------------------------
    | User-facing messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'required' => 'Signature is required. Please sign.',
        'invalid' => 'Please upload a valid image.',
        'format' => 'Only PNG, JPG, and JPEG images are allowed.',
        'too_large' => 'Image size must not exceed 2 MB.',
        'too_small' => 'Signature image is too small. Please sign again.',
        'too_big_dims' => 'Signature image is too large to process.',
        'non_white_bg' => 'Please upload an image with a plain white background.',
        'blank' => 'Please upload an image containing visible text or a signature.',
        'name_required' => 'Name of Participant is required.',
        'name_max' => 'Participant name must not exceed 255 characters.',
    ],
];
