<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sk_officials_app_url' => env('SK_OFFICIALS_APP_URL', 'http://localhost:8000'),

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'sk_oneportal/kabataan_posts'),
        'profile_upload_preset' => env('CLOUDINARY_PROFILE_UPLOAD_PRESET', 'kabataan_profile_images'),
        'profile_folder' => env('CLOUDINARY_PROFILE_FOLDER', 'kabataan_profile_images'),
        'supporting_docs_upload_preset' => env('CLOUDINARY_SUPPORTING_DOCS_UPLOAD_PRESET', 'Supporting_Documents'),
        'supporting_docs_folder' => env('CLOUDINARY_SUPPORTING_DOCS_FOLDER', 'Supporting_Documents'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'enabled' => filter_var(env('TURNSTILE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'timeout' => env('TURNSTILE_TIMEOUT', 10),
    ],

];
