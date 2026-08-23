<?php

use App\Models\User;
use App\Modules\Authentication\Services\AccountActivationRecoveryService;
use App\Services\KkProfilingAccountInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('shows activate account on the sign-in page', function () {
    get(route('sign-in'))
        ->assertSuccessful()
        ->assertSee('Activate Account')
        ->assertSee('/verify-account', false);
});

it('renders the activate account request page', function () {
    get(route('account.activation.request'))
        ->assertSuccessful()
        ->assertSee('Activate Account')
        ->assertSee('Send Activation Link')
        ->assertDontSee('No email on your account?');
});

it('requires a valid email address to request an activation link', function () {
    post(route('account.activation.send'), [
        'email' => '',
    ])->assertSessionHasErrors('email');

    post(route('account.activation.send'), [
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});

it('shows an invalid email error instead of the check-email page', function () {
    Notification::fake();

    post(route('account.activation.send'), [
        'email' => 'missing-youth@example.com',
    ])->assertRedirect()
        ->assertSessionHas('verify_account_error', AccountActivationRecoveryService::INVALID_MESSAGE);

    Notification::assertNothingSent();
});

it('does not send an activation email for an already active kabataan account', function () {
    Notification::fake();

    User::query()->create([
        'name' => 'Active Youth',
        'email' => 'active-youth@example.com',
        'password' => Hash::make('Password123!'),
        'role' => User::ROLE_KABATAAN,
        'status' => User::STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    post(route('account.activation.send'), [
        'email' => 'active-youth@example.com',
    ])->assertRedirect(route('account.activation.already-active'));

    Notification::assertNothingSent();
});

it('classifies expired invite messages for the recovery page', function () {
    $service = new KkProfilingAccountInviteService;

    expect($service->errorTypeFromMessage('This activation link has expired.'))->toBe('expired');
    expect($service->errorTypeFromMessage('This activation link is no longer valid.'))->toBe('invalid');
});

it('hashes newly issued invite tokens', function () {
    expect(KkProfilingAccountInviteService::hashToken('plain-token'))
        ->toBe(hash('sha256', 'plain-token'))
        ->not->toBe('plain-token');
});
