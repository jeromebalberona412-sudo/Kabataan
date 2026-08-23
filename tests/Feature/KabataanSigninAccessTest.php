<?php

use App\Models\User;
use App\Services\KabataanAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createPortalUser(string $role, string $email, string $password = 'password123', string $status = 'ACTIVE'): User
{
    return User::query()->create([
        'name' => 'Test User',
        'email' => $email,
        'password' => Hash::make($password),
        'role' => $role,
        'status' => $status,
        'email_verified_at' => now(),
    ]);
}

it('allows kabataan users to log in', function () {
    createPortalUser('kabataan', 'youth@example.com');

    $response = $this->post('/login', [
        'email' => 'youth@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('allows legacy user role kabataan accounts to log in', function () {
    createPortalUser('user', 'legacy-youth@example.com');

    $response = $this->post('/login', [
        'email' => 'legacy-youth@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('blocks sk official accounts with invalid credentials message', function () {
    createPortalUser('sk_official', 'official@example.com');

    $response = $this->post('/login', [
        'email' => 'official@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('login_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
    $this->assertGuest();
});

it('blocks sk federation accounts with invalid credentials message', function () {
    createPortalUser('sk_fed', 'federation@example.com');

    $response = $this->post('/login', [
        'email' => 'federation@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('login_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
    $this->assertGuest();
});

it('blocks the bootstrap skoneportal account even when credentials are correct', function () {
    createPortalUser('sk_fed', 'skoneportal@gmail.com', '@Jerome123456');

    $response = $this->post('/login', [
        'email' => 'skoneportal@gmail.com',
        'password' => '@Jerome123456',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('login_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
    $this->assertGuest();
});

it('logs out non-kabataan users who reach authenticated routes', function () {
    $official = createPortalUser('sk_official', 'official-session@example.com');

    $response = $this->actingAs($official)->get('/dashboard');

    $response->assertRedirect(route('sign-in'));
    $response->assertSessionHas('login_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
    $this->assertGuest();
});

it('hides non-kabataan accounts on forgot password', function () {
    createPortalUser('sk_official', 'official@example.com');

    $response = $this->post('/forgot-password', [
        'email' => 'official@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas(
        'forgot_password_error',
        'No account found with this email address. Please check your email and try again.'
    );
});
