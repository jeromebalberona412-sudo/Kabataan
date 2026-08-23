<?php

return [
    /*
  |--------------------------------------------------------------------------
  | Allowed Kabataan Portal Roles
  |--------------------------------------------------------------------------
  |
  | Only users with these roles may sign in to the Kabataan application.
  | Shared schema uses "user" for youth accounts; registration may store "kabataan".
  |
  */
    'allowed_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('KABATAAN_ALLOWED_ROLES', 'kabataan,user'))
    ))),

    /*
  |--------------------------------------------------------------------------
  | Blocked Emails
  |--------------------------------------------------------------------------
  |
  | System/bootstrap accounts that must never authenticate on the Kabataan portal.
  |
  */
    'blocked_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env(
            'KABATAAN_BLOCKED_EMAILS',
            'skoneportal@gmail.com'
        ))
    ))),

    'account_activation' => [
        'expire_minutes' => (int) env('ACCOUNT_ACTIVATION_EXPIRES_MINUTES', 60 * 24),
        'cooldown_seconds' => (int) env('ACCOUNT_ACTIVATION_COOLDOWN_SECONDS', 60),
        'rate_limit' => [
            'ip_per_minute' => (int) env('ACCOUNT_ACTIVATION_IP_PER_MINUTE', 5),
            'email_per_hour' => (int) env('ACCOUNT_ACTIVATION_EMAIL_PER_HOUR', 3),
        ],
    ],

    'remember' => [
        'lifetime_days' => (int) env('KABATAAN_REMEMBER_LIFETIME_DAYS', 7),
    ],
];
