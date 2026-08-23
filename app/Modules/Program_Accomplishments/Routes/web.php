<?php

use App\Modules\Program_Accomplishments\Controllers\ProgramAccomplishmentsController;
use Illuminate\Support\Facades\Route;

Route::get('/barangay-accomplishments', [ProgramAccomplishmentsController::class, 'index'])
    ->name('program_accomplishments.barangays');

Route::get('/barangay-accomplishments/{barangay:slug}', [ProgramAccomplishmentsController::class, 'show'])
    ->name('program_accomplishments.barangays.show');

Route::get('/barangay-accomplishments/{barangay:slug}/documents/{document}', [ProgramAccomplishmentsController::class, 'downloadDocument'])
    ->whereNumber('document')
    ->name('program_accomplishments.barangays.document');

Route::get('/barangay-accomplishments/{barangay:slug}/{report}', [ProgramAccomplishmentsController::class, 'report'])
    ->whereNumber('report')
    ->name('program_accomplishments.barangays.report');

Route::permanentRedirect('/accomplishments', '/barangay-accomplishments');
Route::permanentRedirect('/accomplishments/{barangay:slug}', '/barangay-accomplishments/{barangay:slug}');
