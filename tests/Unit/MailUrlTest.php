<?php

uses(Tests\TestCase::class);

use App\Support\MailUrl;

it('prefers APP_PUBLIC_URL over localhost APP_URL', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://localhost:8002',
        'app.public_url' => 'https://kabataan.example.com',
    ]);

    expect(MailUrl::root())->toBe('https://kabataan.example.com');
});
