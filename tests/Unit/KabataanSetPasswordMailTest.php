<?php

uses(Tests\TestCase::class);

use App\Mail\KabataanSetPasswordMail;
use App\Support\MailUrl;

it('builds set-password mail without throwing', function () {
    config([
        'app.url' => 'https://kabataan.example.com',
        'app.public_url' => 'https://kabataan.example.com',
    ]);

    $url = MailUrl::root().'/kkprofiling/wizard/set-password/token/hash';
    $mailable = new KabataanSetPasswordMail($url);

    expect(fn () => $mailable->build())->not->toThrow(Throwable::class);

    $built = $mailable->build();
    expect($built->subject)->toBe('Set Your KK Profiling Account Password');
});
