<?php

use App\Modules\Authentication\Controllers\AccountActivationController;
use App\Modules\Authentication\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Sign In routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [AuthController::class, 'showSignin'])->name('sign-in');
    Route::post('/sign-in', [AuthController::class, 'signin']);

    // Backward-compat alias: any route('signin') reference redirects to /sign-in
    Route::get('/login', fn () => redirect()->route('sign-in'))->name('login');

    // Registration routes — direct users to KK Profiling signup
    Route::get('/register', function () {
        return redirect()->route('kkprofiling.signup');
    })->name('register');
    Route::post('/register', function () {
        return redirect()->route('kkprofiling.signup');
    });

    // Password Reset Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
        ->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
        ->name('password.email');
    Route::get('/forgot-password/verify-email', [AuthController::class, 'showForgotPasswordVerifyEmail'])
        ->name('password.verify-email');
    Route::post('/forgot-password/verify-email/resend', [AuthController::class, 'resendForgotPasswordEmail'])
        ->name('password.verify-email.resend');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])
        ->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.update');

    Route::get('/verify-account', [AccountActivationController::class, 'showRequestForm'])
        ->name('account.activation.request');
    Route::post('/verify-account', [AccountActivationController::class, 'sendLink'])
        ->middleware(['throttle:kabataan-account-activation-ip', 'throttle:kabataan-account-activation-email'])
        ->name('account.activation.send');
    Route::get('/verify-account/check-email', [AccountActivationController::class, 'showSent'])
        ->name('account.activation.sent');
    Route::get('/verify-account/already-active', [AccountActivationController::class, 'showAlreadyActive'])
        ->name('account.activation.already-active');

    // Email Verification Routes (Prototype)
    Route::get('/email/verify', [AuthController::class, 'showEmailVerification'])
        ->name('verification.notice');
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->name('verification.send');
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->name('verification.resend');
    Route::get('/email/verify/{token}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');
});

Route::get('/remember-me/wait', fn () => redirect()->route('sign-in'));
Route::get('/remember-me/wait-status', fn () => redirect()->route('sign-in'));
Route::post('/remember-me/resend', fn () => redirect()->route('sign-in'));
Route::get('/remember-me/confirm/{token}', fn () => redirect()->route('sign-in'));

// Email verification status check (can be accessed by guest or auth)
Route::get('/email/check-status', [AuthController::class, 'checkVerificationStatus'])
    ->name('verification.check');

// Test page for email verification (Development only)
Route::get('/test-email-verification', [AuthController::class, 'showTestEmailVerification'])
    ->name('test.verification');

// Logout route
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('web');
