<?php

return [
    'portal_user_type' => env('COMMUNICATIONS_PORTAL_USER_TYPE', 'kabataan'),

    'message_max_length' => (int) env('COMMUNICATIONS_MESSAGE_MAX_LENGTH', 1000),

    'messages_per_page' => 40,

    'search_limit' => 50,

    'reaction_emojis' => ['👍', '❤️', '😆', '😮', '😢', '🙏'],

    'faq' => [
        'max_questions' => 10,
        'question_max_length' => 50,
        'response_max_length' => 500,
        'cooldown_seconds' => 10,
        'match_threshold' => 0.55,
    ],

    'attachments' => [
        'image_max_kb' => (int) env('COMMUNICATIONS_IMAGE_MAX_KB', 25600),
        'file_max_kb' => (int) env('COMMUNICATIONS_FILE_MAX_KB', 10240),
        'batch_max_images' => 50,
        'batch_max_bytes' => 25 * 1024 * 1024,
        'allow_svg' => (bool) env('COMMUNICATIONS_ALLOW_SVG', false),
        'image_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'file_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'file_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'application/csv',
        ],
    ],

    'rate_limit' => [
        'send_per_minute' => 60,
        'call_start_per_minute' => 10,
        'search_per_minute' => 30,
        'react_per_minute' => 60,
        'upload_per_minute' => 20,
    ],
];
