<?php

use App\Modules\Dashboard\Controllers\AnnouncementFeedController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Dashboard\Http\Middleware\ResolveOpaqueFeedIds;
use App\Support\OpaqueUrlId;
use Illuminate\Support\Facades\Route;

// Dashboard routes with authentication
Route::middleware(['web', 'auth', ResolveOpaqueFeedIds::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/comments/{id}', [DashboardController::class, 'comments'])
        ->name('dashboard.comments');
    Route::get('/dashboard/{id}/comments', function (int $id) {
        return redirect('/dashboard/comments/'.OpaqueUrlId::encode($id));
    });
    Route::get('/barangay/{slug}/{post}', [DashboardController::class, 'barangayComments'])
        ->name('barangay.comments');
    Route::get('/barangay/{slug}', [DashboardController::class, 'barangay'])->name('barangay');

    Route::get('/api/feed', [AnnouncementFeedController::class, 'feed']);
    Route::get('/api/feed/{id}', [AnnouncementFeedController::class, 'show']);
    Route::get('/api/feed/{id}/likes', [AnnouncementFeedController::class, 'likes']);
    Route::post('/api/feed/{id}/react', [AnnouncementFeedController::class, 'react']);
    Route::post('/api/feed/{id}/comment', [AnnouncementFeedController::class, 'comment']);
    Route::put('/api/feed/{id}/comments/{comment}', [AnnouncementFeedController::class, 'updateComment']);
    Route::delete('/api/feed/{id}/comments/{comment}', [AnnouncementFeedController::class, 'destroyComment']);
    Route::get('/api/feed/{id}/comments/{comment}/reactions', [AnnouncementFeedController::class, 'commentReactions']);
    Route::post('/api/feed/{id}/comments/{comment}/reactions', [AnnouncementFeedController::class, 'commentReact']);
});
