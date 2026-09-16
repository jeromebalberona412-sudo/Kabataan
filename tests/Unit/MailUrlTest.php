<?php

uses(TestCase::class);

use App\Support\MailUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

it('prefers APP_PUBLIC_URL over localhost APP_URL', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://localhost:8002',
        'app.public_url' => 'https://kabataan.example.com',
    ]);

    expect(MailUrl::root())->toBe('https://kabataan.example.com');
});

it('uses the current request host in production when APP_URL is localhost', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://localhost:8002',
        'app.public_url' => null,
    ]);

    $this->app->instance('request', Request::create('https://kabataan.live.test/dashboard', 'GET'));

    expect(MailUrl::root())->toBe('https://kabataan.live.test');
    expect(MailUrl::uri('/api/communications/conversations'))->toBe('/api/communications/conversations');
    expect(MailUrl::sameOrigin('http://localhost:8002/api/feed'))->toBe('/api/feed');
});

it('rewrites stored localhost file urls to same-origin paths', function () {
    expect(MailUrl::media('http://localhost:8002/storage/docs/a.jpg'))->toBe('/storage/docs/a.jpg');
    expect(MailUrl::media('/storage/docs/a.jpg'))->toBe('/storage/docs/a.jpg');
    expect(MailUrl::media('https://res.cloudinary.com/demo/image/upload/x.jpg'))
        ->toBe('https://res.cloudinary.com/demo/image/upload/x.jpg');
});
