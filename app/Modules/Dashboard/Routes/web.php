<?php

use App\Modules\Dashboard\Controllers\AnnouncementFeedController;
use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard routes with authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/comments/{id}', [DashboardController::class, 'comments'])
        ->whereNumber('id')
        ->name('dashboard.comments');
    Route::get('/dashboard/{id}/comments', function (int $id) {
        return redirect('/dashboard/comments/'.$id);
    })->whereNumber('id');
    Route::get('/barangay/{slug}/{post}', [DashboardController::class, 'barangayComments'])
        ->whereNumber('post')
        ->name('barangay.comments');
    Route::get('/barangay/{slug}', [DashboardController::class, 'barangay'])->name('barangay');

    Route::get('/api/feed', [AnnouncementFeedController::class, 'feed']);
    Route::get('/api/feed/{id}', [AnnouncementFeedController::class, 'show'])->whereNumber('id');
    Route::get('/api/feed/{id}/likes', [AnnouncementFeedController::class, 'likes'])->whereNumber('id');
    Route::post('/api/feed/{id}/react', [AnnouncementFeedController::class, 'react'])->whereNumber('id');
    Route::post('/api/feed/{id}/comment', [AnnouncementFeedController::class, 'comment'])->whereNumber('id');
    Route::put('/api/feed/{id}/comments/{comment}', [AnnouncementFeedController::class, 'updateComment'])->whereNumber('id');
    Route::delete('/api/feed/{id}/comments/{comment}', [AnnouncementFeedController::class, 'destroyComment'])->whereNumber('id');
    Route::get('/api/feed/{id}/comments/{comment}/reactions', [AnnouncementFeedController::class, 'commentReactions'])->whereNumber('id');
    Route::post('/api/feed/{id}/comments/{comment}/reactions', [AnnouncementFeedController::class, 'commentReact'])->whereNumber('id');
});
