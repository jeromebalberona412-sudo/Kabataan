<?php

use App\Models\User;
use App\Services\KabataanAuthService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'kabataan_auth.allowed_roles' => ['kabataan', 'user'],
        'kabataan_auth.blocked_emails' => ['skoneportal@gmail.com'],
    ]);
});

function makeUser(string $role, string $email = 'test@example.com'): User
{
    $user = new User;
    $user->role = $role;
    $user->email = $email;

    return $user;
}

it('allows kabataan and legacy user roles', function () {
    $service = new KabataanAuthService;

    expect($service->canAccessPortal(makeUser('kabataan')))->toBeTrue();
    expect($service->canAccessPortal(makeUser('user')))->toBeTrue();
});

it('blocks sk official and sk federation roles', function () {
    $service = new KabataanAuthService;

    expect($service->canAccessPortal(makeUser('sk_official')))->toBeFalse();
    expect($service->canAccessPortal(makeUser('sk_fed')))->toBeFalse();
    expect($service->canAccessPortal(makeUser('admin')))->toBeFalse();
});

it('blocks bootstrap email regardless of role', function () {
    $service = new KabataanAuthService;

    expect($service->canAccessPortal(makeUser('kabataan', 'skoneportal@gmail.com')))->toBeFalse();
    expect($service->isBlockedEmail('SKOnePortal@gmail.com'))->toBeTrue();
});

it('treats null users as blocked', function () {
    $service = new KabataanAuthService;

    expect($service->canAccessPortal(null))->toBeFalse();
});
