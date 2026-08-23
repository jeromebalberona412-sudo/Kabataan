<?php

use App\Models\User;
use App\Modules\Authentication\Services\TrustedDeviceService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function createRememberPortalUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Youth User',
        'email' => 'remember-youth@example.com',
        'password' => Hash::make('password123'),
        'role' => 'kabataan',
        'status' => User::STATUS_ACTIVE,
        'email_verified_at' => now(),
    ], $overrides));
}

function kabataanResponseHasRememberCookie($response): bool
{
    foreach ($response->headers->getCookies() as $cookie) {
        if (str_starts_with($cookie->getName(), 'remember_web')) {
            return true;
        }
    }

    return false;
}

it('shows the remember me checkbox on the kabataan sign-in page', function () {
    $this->get('/sign-in')
        ->assertOk()
        ->assertSee('Remember me')
        ->assertDontSee('Remember me on this device')
        ->assertSee('Forgot password')
        ->assertDontSee('Forgot password?')
        ->assertSee('Remember this device?');
});

it('signs in without remember me and does not set a remember cookie', function () {
    $user = createRememberPortalUser();

    $response = $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(kabataanResponseHasRememberCookie($response))->toBeFalse();
});

it('signs a verified kabataan user in with remember me and goes to the dashboard', function () {
    Notification::fake();
    $user = createRememberPortalUser();

    $response = $this->from('/sign-in')->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(kabataanResponseHasRememberCookie($response))->toBeTrue();
    Notification::assertNothingSent();
});

it('does not send kabataan users to email verification just because remember me is checked', function () {
    Notification::fake();
    $user = createRememberPortalUser([
        'email' => 'unverified-youth@example.com',
        'email_verified_at' => null,
    ]);

    $this->from('/sign-in')->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

it('invalidates kabataan remember me on logout', function () {
    $user = createRememberPortalUser();

    $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->post('/logout')->assertRedirect(route('sign-in'));
    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('sign-in'));
});

it('uses a seven day kabataan remember lifetime from configuration', function () {
    expect((int) config('kabataan_auth.remember.lifetime_days'))->toBe(7);
    expect(app(TrustedDeviceService::class)->expirationDays())->toBe(7);
});

it('rejects invalid kabataan credentials even when remember me is checked', function () {
    Notification::fake();
    $user = createRememberPortalUser();

    $this->from('/sign-in')->post('/sign-in', [
        'email' => $user->email,
        'password' => 'WrongPassword123!',
        'remember' => '1',
    ])->assertRedirect('/sign-in');

    $this->assertGuest();
    Notification::assertNothingSent();
});

it('prevents remembered kabataan access after the account is disabled', function () {
    $user = createRememberPortalUser();

    $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'));

    $user->forceFill(['status' => User::STATUS_INACTIVE])->save();

    $this->get(route('dashboard'))->assertRedirect(route('sign-in'));
    $this->assertGuest();
});

it('invalidates kabataan remember authentication after a password reset', function () {
    $user = createRememberPortalUser();

    $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->remember_token)->not->toBeNull();

    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertRedirect(route('sign-in'));

    expect($user->fresh()->remember_token)->toBeNull();
});
