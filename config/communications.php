<?php

return [
    'portal_user_type' => env('COMMUNICATIONS_PORTAL_USER_TYPE', 'kabataan'),

    'message_max_length' => 5000,

    'messages_per_page' => 40,

    'search_limit' => 20,

    'reaction_emojis' => ['👍', '❤️', '😆', '😮', '😢', '🙏'],

    'attachments' => [
        'image_max_kb' => (int) env('COMMUNICATIONS_IMAGE_MAX_KB', 5120),
        'file_max_kb' => (int) env('COMMUNICATIONS_FILE_MAX_KB', 10240),
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
