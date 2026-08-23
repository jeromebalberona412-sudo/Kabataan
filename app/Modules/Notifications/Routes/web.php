<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/api/kabataan/notifications', [NotificationController::class, 'list'])->name('api.kabataan.notifications');
    Route::post('/api/kabataan/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.kabataan.notifications.read');
    Route::post('/api/kabataan/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.kabataan.notifications.read-all');
});
