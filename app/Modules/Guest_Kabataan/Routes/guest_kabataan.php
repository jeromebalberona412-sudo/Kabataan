<?php

use App\Modules\Guest_Kabataan\Controllers\GuestKabataanController;
use Illuminate\Support\Facades\Route;

Route::prefix('guest')->name('guest_kabataan.')->group(function () {
    Route::get('/', [GuestKabataanController::class, 'barangays'])->name('barangays');
    Route::post('/barangay', [GuestKabataanController::class, 'selectBarangay'])
        ->middleware('throttle:30,1')
        ->name('select');
    Route::get('/home', [GuestKabataanController::class, 'home'])->name('home');
    Route::post('/logout', [GuestKabataanController::class, 'logout'])->name('logout');
});
