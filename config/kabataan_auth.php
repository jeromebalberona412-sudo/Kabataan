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

    'remember' => [
        'lifetime_days' => (int) env('KABATAAN_REMEMBER_LIFETIME_DAYS', 7),
    ],
];
