<?php

use App\Models\User;
use App\Modules\Profile\Notifications\PasswordChangeVerificationNotification;
use App\Modules\Profile\Services\PasswordChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class);

if (extension_loaded('pdo_sqlite')) {
    uses(RefreshDatabase::class);
}

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for password change verification tests.');
    }
});

function createPasswordChangeYouth(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Youth User',
        'email' => 'password-change-youth@example.com',
        'password' => Hash::make('password123'),
        'role' => User::ROLE_KABATAAN,
        'status' => User::STATUS_ACTIVE,
        'email_verified_at' => now(),
    ], $overrides));
}

function latestPasswordChangeToken(User $user): string
{
    $notification = Notification::sent($user, PasswordChangeVerificationNotification::class)->last();

    expect($notification)->not->toBeNull();

    return $notification->plainToken;
}

it('confirms a password change from email while logged in and redirects to the dashboard', function () {
    Notification::fake();

    $user = createPasswordChangeYouth();
    app(PasswordChangeService::class)->requestChange($user, 'NewPass1!');

    $this->actingAs($user)
        ->get(route('change-password.confirm', [
            'id' => $user->id,
            'token' => latestPasswordChangeToken($user),
        ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Password changed successfully.');

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('NewPass1!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->pending_password)->toBeNull();
    expect($user->fresh()->password_change_token)->toBeNull();
});

it('logs a guest in and sends them to the dashboard after they confirm from email', function () {
    Notification::fake();

    $user = createPasswordChangeYouth();
    app(PasswordChangeService::class)->requestChange($user, 'NewPass1!');

    $this->get(route('change-password.confirm', [
        'id' => $user->id,
        'token' => latestPasswordChangeToken($user),
    ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Password changed successfully.');

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('NewPass1!', $user->fresh()->password))->toBeTrue();
});

it('rejects an old verification link after a newer email is resent', function () {
    Notification::fake();

    $user = createPasswordChangeYouth();
    $service = app(PasswordChangeService::class);
    $service->requestChange($user, 'NewPass1!');
    $oldToken = latestPasswordChangeToken($user);

    $user->forceFill([
        'password_change_last_sent_at' => now()->subSeconds(61),
    ])->save();

    $service->resend($user->fresh());
    $newToken = latestPasswordChangeToken($user);

    expect($newToken)->not->toBe($oldToken);

    $this->actingAs($user)
        ->get(route('change-password.confirm', [
            'id' => $user->id,
            'token' => $oldToken,
        ]))
        ->assertRedirect(route('change-password.verify'))
        ->assertSessionHas('error', 'This password change link is no longer valid. Please use the latest verification email.');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password_change_token)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('change-password.confirm', [
            'id' => $user->id,
            'token' => $newToken,
        ]))
        ->assertRedirect(route('dashboard'));

    expect(Hash::check('NewPass1!', $user->fresh()->password))->toBeTrue();
});

it('keeps the confirming browser logged in on the verify status poll', function () {
    $user = createPasswordChangeYouth();

    app(PasswordChangeService::class)->markRecentlyConfirmed($user->id);

    $this->actingAs($user)
        ->withSession([
            'password_change_confirmed_this_session' => true,
            'password_change_verify_active' => true,
        ])
        ->getJson(route('change-password.verify.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'confirmed',
            'redirect' => '/dashboard',
        ]);

    $this->assertAuthenticatedAs($user);
});

it('logs out a waiting verify session after the password is confirmed on another device', function () {
    $user = createPasswordChangeYouth();

    app(PasswordChangeService::class)->markRecentlyConfirmed($user->id);

    $this->actingAs($user)
        ->withSession([
            'password_change_verify_active' => true,
        ])
        ->getJson(route('change-password.verify.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'confirmed',
            'redirect' => '/sign-in',
        ]);

    $this->assertGuest();
});
