<?php

use App\Modules\Baranggay_ABYIP\Controllers\Baranggay_ABYIPController;
use Illuminate\Support\Facades\Route;

Route::get('/barangay-abyip', [Baranggay_ABYIPController::class, 'index'])
    ->name('baranggay_abyip.index');

Route::get('/barangay-abyip/{barangay:slug}', [Baranggay_ABYIPController::class, 'show'])
    ->name('baranggay_abyip.show');

Route::get('/barangay-abyip/{barangay:slug}/documents', [Baranggay_ABYIPController::class, 'documents'])
    ->name('baranggay_abyip.documents');

Route::get('/barangay-abyip/{barangay:slug}/file/{document}', [Baranggay_ABYIPController::class, 'file'])
    ->whereNumber('document')
    ->name('baranggay_abyip.file');

Route::get('/barangay-abyip/{barangay:slug}/legacy-file/{legacy}', [Baranggay_ABYIPController::class, 'legacyFile'])
    ->whereNumber('legacy')
    ->name('baranggay_abyip.legacy_file');
