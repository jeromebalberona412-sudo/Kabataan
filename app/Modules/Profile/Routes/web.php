<?php

use App\Modules\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/change-email/confirm/{id}/{token}', [ProfileController::class, 'confirmChangeEmail'])
            ->middleware('throttle:6,1')
            ->name('change-email.confirm');

        Route::get('/change-email/set-password/{id}/{token}', [ProfileController::class, 'showSetPasswordAfterEmailChange'])
            ->middleware('throttle:6,1')
            ->name('change-email.set-password');

        Route::post('/change-email/set-password/{id}/{token}', [ProfileController::class, 'updateSetPasswordAfterEmailChange'])
            ->middleware('throttle:6,1')
            ->name('change-email.set-password.update');

        Route::get('/change-password/confirm/{id}/{token}', [ProfileController::class, 'confirmChangePassword'])
            ->middleware('throttle:6,1')
            ->name('change-password.confirm');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::post('/upload-profile-picture', [ProfileController::class, 'uploadProfilePicture'])->name('profile.upload-picture');
        Route::post('/upload-supporting-document', [ProfileController::class, 'uploadSupportingDocument'])->name('profile.upload-supporting-document');

        Route::get('/change-email', [ProfileController::class, 'showChangeEmail'])->name('change-email');
        Route::post('/change-email', [ProfileController::class, 'requestChangeEmail'])->name('change-email.request');
        Route::get('/change-email/verify', [ProfileController::class, 'showChangeEmailVerify'])->name('change-email.verify');
        Route::get('/change-email/verify-status', [ProfileController::class, 'checkChangeEmailVerifyStatus'])->name('change-email.verify.status');
        Route::post('/change-email/resend', [ProfileController::class, 'resendChangeEmail'])->name('change-email.resend');
        Route::post('/change-email/cancel', [ProfileController::class, 'cancelChangeEmail'])->name('change-email.cancel');

        Route::get('/change-password', [ProfileController::class, 'showChangePassword'])->name('change-password');
        Route::post('/change-password', [ProfileController::class, 'requestChangePassword'])->name('change-password.post');
        Route::get('/change-password/verify', [ProfileController::class, 'showChangePasswordVerify'])->name('change-password.verify');
        Route::get('/change-password/verify-status', [ProfileController::class, 'checkChangePasswordVerifyStatus'])->name('change-password.verify.status');
        Route::post('/change-password/resend', [ProfileController::class, 'resendChangePassword'])->name('change-password.resend');
        Route::post('/change-password/cancel', [ProfileController::class, 'cancelChangePassword'])->name('change-password.cancel');
    });
});
