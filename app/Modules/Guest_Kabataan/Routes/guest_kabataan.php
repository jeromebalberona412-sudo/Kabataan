<?php

use App\Modules\Guest_Kabataan\Controllers\GuestKabataanController;
use Illuminate\Support\Facades\Route;

Route::prefix('guest')->name('guest_kabataan.')->group(function () {
    Route::get('/', [GuestKabataanController::class, 'barangays'])->name('barangays');
    Route::post('/barangay', [GuestKabataanController::class, 'selectBarangay'])
        ->middleware('throttle:30,1')
        ->name('select');
    Route::get('/home', [GuestKabataanController::class, 'home'])->name('home');
    Route::get('/claim-lock', [GuestKabataanController::class, 'claimLock'])->name('claim-lock');
    Route::post('/claim/identity', [GuestKabataanController::class, 'confirmIdentity'])
        ->middleware('throttle:20,1')
        ->name('claim.identity');
    Route::post('/claim', [GuestKabataanController::class, 'claim'])
        ->middleware('throttle:20,1')
        ->name('claim');
    Route::get('/activate', fn () => redirect()->route('guest_kabataan.activate'))->name('activate.legacy');
    Route::get('/activate/add-email', [GuestKabataanController::class, 'activate'])->name('activate');
    Route::get('/activate/sent', [GuestKabataanController::class, 'activateSent'])->name('activate.sent');
    Route::post('/activate/add-email', [GuestKabataanController::class, 'sendActivation'])
        ->middleware('throttle:10,1')
        ->name('activate.send');
    Route::post('/activate/resend', [GuestKabataanController::class, 'resendActivation'])
        ->middleware('throttle:10,1')
        ->name('activate.resend');
    Route::post('/logout', [GuestKabataanController::class, 'logout'])->name('logout');
});
